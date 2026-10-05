<?php
require __DIR__ . '/../layouts/header.php';
$departmentOptions = [
  'Medical','Nursing','Laboratory','Imaging','Pharmacy','Paramedical','Administration',
  'Front Office','Records','IT','Maintenance','Security','Store','Emergency','Allied Services'
];
$locationOptions = ['KH', 'KCI', 'KNS', 'MANAGEMENT'];
$designationOptions = [
  'Medical' => ['Doctor', 'DMO', 'Physician Assistant'],
  'Nursing' => ['Staff Nurse', 'Senior Staff Nurse', 'Nursing Assistant'],
  'Front Office' => ['Receptionist'],
  'Pharmacy' => ['Pharmacist'],
  'Laboratory' => ['Lab Technician'],
  'Radiology' => ['Radiology Technician', 'CT Technician'],
  'Diagnostics' => ['ECG Technician'],
  'Clinical Support' => ['OT Technician', 'CSSD Technician', 'Cath Lab Technician', 'Endoscopy Technician', 'Physiotherapist', 'Respiratory Therapist'],
  'Administration' => ['Administrative Officer', 'HR Executive', 'Accountant'],
  'Support Services' => ['Attender', 'Driver', 'Cook', 'Dietitian', 'Sanitary Worker', 'Maintenance Technician', 'Electrician'],
  'Training' => ['Tutor'],
  'Operations' => ['Clinical Operations Head'],
];
?>

<div class="content-body">
  <!-- ── Top Header (Screen 4) ── -->
  <div class="dashboard-header-greeting">
    <h1>Add New Employee</h1>
    <p>Create a new staff profile for the hospital workforce.</p>
  </div>

  <div class="card" style="padding:28px 32px;margin:0;border-radius:16px">
    <!-- ── Stepper (Screen 4) ── -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;padding-bottom:18px;border-bottom:1px solid #e2e8f0">
      <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#0d474c">
        <div style="width:26px;height:26px;border-radius:50%;background:#0d474c;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">1</div>
        <span>Basic Info</span>
      </div>
      <div style="flex:1;height:1px;background:#e2e8f0;margin:0 12px"></div>
      <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#94a3b8">
        <div style="width:26px;height:26px;border-radius:50%;background:#f1f5f9;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">2</div>
        <span>Employment</span>
      </div>
      <div style="flex:1;height:1px;background:#e2e8f0;margin:0 12px"></div>
      <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#94a3b8">
        <div style="width:26px;height:26px;border-radius:50%;background:#f1f5f9;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">3</div>
        <span>Additional</span>
      </div>
      <div style="flex:1;height:1px;background:#e2e8f0;margin:0 12px"></div>
      <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#94a3b8">
        <div style="width:26px;height:26px;border-radius:50%;background:#f1f5f9;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">4</div>
        <span>Review</span>
      </div>
    </div>

    <h2 style="font-size:16px;font-weight:700;color:#0f172a;margin:0 0 16px">Basic Information</h2>

    <form method="post" action="?route=recruitment.store" enctype="multipart/form-data" class="create-employee-form" data-designation-form>
      <?= csrf_field() ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">First Name *</label>
          <input name="first_name" placeholder="Venkatsd" required>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Last Name *</label>
          <input name="last_name" placeholder="S" required>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Email Address</label>
          <input name="email" type="email" placeholder="venkat@example.com">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Phone Number *</label>
          <input name="phone" placeholder="+91 98765 43210" required>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Department *</label>
          <select name="department" required>
            <option value="">Select Department</option>
            <?php foreach ($departmentOptions as $department): ?>
              <option value="<?= htmlspecialchars($department) ?>"><?= htmlspecialchars($department) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Position / Designation *</label>
          <select name="position" required>
            <option value="">Select Position</option>
            <?php foreach ($designationOptions as $dept => $designations): ?>
              <optgroup label="<?= htmlspecialchars($dept) ?>">
                <?php foreach ($designations as $designation): ?>
                  <option value="<?= htmlspecialchars($designation) ?>" data-department="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($designation) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Hospital Location *</label>
          <select name="location" required>
            <option value="">Select Location</option>
            <?php foreach ($locationOptions as $loc): ?>
              <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Employment Type *</label>
          <select name="employment_type">
            <option value="Permanent" selected>Permanent</option>
            <option value="Contract">Contract</option>
            <option value="Temp">Temp / Probation</option>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Date of Joining</label>
          <input name="join_date" type="date" value="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Employee Code (Optional, auto-generated if blank)</label>
          <input name="employee_code" placeholder="Leave blank to auto-generate">
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">System Role</label>
          <select name="role">
            <option selected>Employee</option>
            <option>HR</option>
            <option>HOD</option>
            <option>Manager</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Temporary Password *</label>
          <input name="password" type="password" value="kh1234" required>
        </div>
      </div>

      <!-- Action Buttons (Screen 4) -->
      <div style="display:flex;align-items:center;justify-content:flex-end;gap:14px;margin-top:28px;padding-top:20px;border-top:1px solid #f1f5f9">
        <a href="?route=recruitment" class="btn-secondary" style="border:none;background:#f1f5f9;color:#475569">Cancel</a>
        <button type="submit" class="btn-primary" style="background:#0d474c">
          Next &rarr;
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelectorAll('[data-designation-form]').forEach((form) => {
  const department = form.querySelector('select[name="department"]');
  const position = form.querySelector('select[name="position"]');
  if (!department || !position) return;
  const sync = () => {
    position.querySelectorAll('option[data-department]').forEach((option) => {
      const visible = !department.value || option.dataset.department === department.value;
      option.hidden = !visible;
      option.disabled = !visible;
    });
    if (position.selectedOptions[0]?.disabled) position.value = '';
  };
  department.addEventListener('change', sync);
  sync();
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
