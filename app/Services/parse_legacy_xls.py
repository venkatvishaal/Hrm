import json
import sys
from datetime import datetime

import xlrd


def value(cell, sheet, datemode):
    if cell.ctype == xlrd.XL_CELL_DATE:
        return datetime(*xlrd.xldate_as_tuple(cell.value, datemode)).strftime('%Y-%m-%d')
    if cell.ctype == xlrd.XL_CELL_NUMBER and cell.value.is_integer():
        return str(int(cell.value))
    return str(cell.value).strip()


book = xlrd.open_workbook(sys.argv[1], on_demand=True)
sheet = book.sheet_by_index(0)
raw = [[value(sheet.cell(r, c), sheet, book.datemode) for c in range(sheet.ncols)] for r in range(sheet.nrows)]
raw = [row for row in raw if any(item.strip() for item in row)]
if not raw:
    print('[]')
    sys.exit(0)

header_index = 0
for index, row in enumerate(raw[:13]):
    keys = {''.join(ch for ch in item.lower() if ch.isalnum()) for item in row}
    if keys.intersection({'employeeid', 'employeecode', 'attendance_date', 'status', 'checkin'}):
        header_index = index
        break

headers = [item.strip() for item in raw[header_index]]
result = []
for row in raw[header_index + 1:]:
    padded = row + [''] * (len(headers) - len(row))
    item = {headers[i]: padded[i] for i in range(len(headers)) if headers[i]}
    if any(str(v).strip() for v in item.values()):
        result.append(item)
print(json.dumps(result))
