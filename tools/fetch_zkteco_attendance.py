#!/usr/bin/env python3
"""
ZKTeco Biometric Attendance Fetch Tool for Krishna Health Hospital HRM
Safely connects to registered ZKTeco biometric devices or logs status if offline.
"""
import argparse
import csv
import os
import sys
from datetime import datetime

def main():
    parser = argparse.ArgumentParser(description="Fetch attendance logs from ZKTeco biometric devices.")
    parser.add_argument("--devices", required=True, help="Path to devices configuration CSV")
    parser.add_argument("--output", required=True, help="Path to raw attendance logs output CSV")
    parser.add_argument("--calculated-output", required=True, help="Path to calculated attendance output CSV")
    args = parser.parse_args()

    if not os.path.isfile(args.devices):
        print(f"Devices file not found: {args.devices}")
        sys.exit(1)

    devices = []
    with open(args.devices, "r", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        for row in reader:
            devices.append(row)

    if not devices:
        print("No devices configured in the provided file.")
        sys.exit(0)

    # Initialize raw and calculated CSV outputs
    raw_fieldnames = ["employee_code", "timestamp", "status", "punch", "device_name"]
    calc_fieldnames = ["employee_code", "date", "check_in", "check_out", "device_name"]

    raw_records = []
    calculated_records = []
    success_count = 0
    fail_count = 0

    has_zk = False
    try:
        from zk import ZK, const
        has_zk = True
    except ImportError:
        pass

    for dev in devices:
        name = dev.get("device_name", "Unknown")
        host = dev.get("host", "127.0.0.1")
        port = int(dev.get("port", 4370))
        password = int(dev.get("password", 0))
        use_udp = dev.get("udp", "false").lower() in ("true", "1", "yes")

        if not has_zk:
            # pyzk module not installed in Python environment; report clean diagnostic message
            fail_count += 1
            print(f"Device [{name}] ({host}:{port}): pyzk package is not installed. Run 'pip install pyzk' on the server.")
            continue

        try:
            zk = ZK(host, port=port, timeout=5, password=password, force_udp=use_udp, ommit_ping=False)
            conn = zk.connect()
            conn.disable_device()
            attendances = conn.get_attendance()
            for att in attendances:
                user_id = str(att.user_id).strip()
                att_time = att.timestamp.strftime("%Y-%m-%d %H:%M:%S")
                punch_type = str(att.punch)
                raw_records.append({
                    "employee_code": user_id,
                    "timestamp": att_time,
                    "status": str(att.status),
                    "punch": punch_type,
                    "device_name": name,
                })
            conn.enable_device()
            conn.disconnect()
            success_count += 1
            print(f"Device [{name}] ({host}:{port}): Successfully fetched {len(attendances)} records.")
        except Exception as e:
            fail_count += 1
            print(f"Device [{name}] ({host}:{port}): Connection failed ({str(e)}).")

    # Group punches by employee and date to build calculated check-in and check-out
    punches_by_emp_date = {}
    for r in raw_records:
        emp = r["employee_code"]
        dt = r["timestamp"].split(" ")[0]
        tm = r["timestamp"].split(" ")[1]
        key = (emp, dt)
        if key not in punches_by_emp_date:
            punches_by_emp_date[key] = {"times": [], "device": r["device_name"]}
        punches_by_emp_date[key]["times"].append(tm)

    for (emp, dt), info in punches_by_emp_date.items():
        sorted_times = sorted(info["times"])
        check_in = sorted_times[0]
        check_out = sorted_times[-1] if len(sorted_times) > 1 else ""
        calculated_records.append({
            "employee_code": emp,
            "date": dt,
            "check_in": check_in,
            "check_out": check_out,
            "device_name": info["device"],
        })

    # Write output files
    with open(args.output, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=raw_fieldnames)
        writer.writeheader()
        writer.writerows(raw_records)

    with open(args.calculated_output, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=calc_fieldnames)
        writer.writeheader()
        writer.writerows(calculated_records)

    print(f"Fetch completed: {success_count} succeeded, {fail_count} offline/failed. Total raw: {len(raw_records)}.")
    sys.exit(0 if fail_count == 0 else (2 if success_count > 0 else 1))

if __name__ == "__main__":
    main()
