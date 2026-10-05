<?php
$departmentOptions = [
  'Medical', 'Nursing', 'Laboratory', 'Imaging', 'Pharmacy', 'Paramedical', 'Administration',
  'Front Office', 'Records', 'IT', 'Maintenance', 'Security', 'Store', 'Emergency', 'Allied Services'
];
$locationOptions = ['KH', 'KCI', 'KNS', 'MANAGEMENT'];

// Full mapping of Departments to Designations
$designationMap = [
  'Medical' => ['Doctor', 'DMO', 'Physician Assistant', 'Consultant', 'Resident Doctor'],
  'Nursing' => ['Staff Nurse', 'Senior Staff Nurse', 'Nursing Assistant', 'Nurse Supervisor', 'Head Nurse'],
  'Laboratory' => ['Lab Technician', 'Senior Lab Technician', 'Pathologist Assistant', 'Microbiologist'],
  'Imaging' => ['Radiology Technician', 'CT Technician', 'MRI Technician', 'X-Ray Technician'],
  'Pharmacy' => ['Pharmacist', 'Senior Pharmacist', 'Pharmacy Assistant'],
  'Paramedical' => ['Physiotherapist', 'Dialysis Technician', 'ECG Technician', 'Anesthesia Technician'],
  'Administration' => ['Administrative Officer', 'HR Executive', 'Accountant', 'Billing Executive'],
  'Front Office' => ['Receptionist', 'Front Desk Officer', 'Patient Care Executive'],
  'Records' => ['Medical Records Officer', 'MRD Assistant'],
  'IT' => ['IT Support Engineer', 'System Administrator', 'Network Specialist'],
  'Maintenance' => ['Maintenance Technician', 'Electrician', 'Plumber', 'HVAC Engineer'],
  'Security' => ['Security Officer', 'Security Guard'],
  'Store' => ['Storekeeper', 'Inventory Executive'],
  'Emergency' => ['Emergency Medical Officer', 'Paramedic', 'Triage Nurse'],
  'Allied Services' => ['Dietitian', 'Attender', 'Sanitary Worker', 'Cook', 'Driver'],
];

$allDesignations = [];
foreach ($designationMap as $desgs) {
  foreach ($desgs as $d) {
    if (!in_array($d, $allDesignations, true)) {
      $allDesignations[] = $d;
    }
  }
}
sort($allDesignations);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Add New Employee — Hospital HRM</title>
  <?php $assetBase = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/public/') ? 'assets' : 'public/assets'; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <?php $themeV = @filemtime(__DIR__ . '/../../public/assets/hospital-theme.css') ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase) ?>/hospital-theme.css?v=<?= $themeV ?>">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; background: #f8fafc; font-family: 'Inter', sans-serif; color: #0f172a; }
    .reg-wrapper { max-width: 860px; margin: 40px auto; padding: 0 20px; }
    .card-stepper { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
    .step-item { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #94a3b8; }
    .step-item.active { color: #0d474c; font-weight: 700; }
    .step-circle { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; background: #f1f5f9; color: #64748b; font-weight: 700; }
    .step-item.active .step-circle { background: #0d474c; color: #ffffff; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  </style>
</head>
<body>
<div class="reg-wrapper">
  <!-- ── Top Header matching Screen 4 ── -->
  <div style="margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 4px">Add New Employee</h1>
    <p style="font-size:13.5px;color:#64748b;margin:0">Create a new staff profile for the hospital workforce.</p>
  </div>

  <div class="card" style="padding:32px 36px;border-radius:16px;background:#ffffff;box-shadow:0 4px 20px rgba(15,23,42,0.04)">
    <!-- ── Stepper (Screen 4) ── -->
    <div class="card-stepper">
      <div class="step-item active">
        <div class="step-circle">1</div>
        <span>Basic Info</span>
      </div>
      <div style="flex:1;height:1px;background:#e2e8f0;margin:0 12px"></div>
      <div class="step-item">
        <div class="step-circle">2</div>
        <span>Employment</span>
      </div>
      <div style="flex:1;height:1px;background:#e2e8f0;margin:0 12px"></div>
      <div class="step-item">
        <div class="step-circle">3</div>
        <span>Additional</span>
      </div>
      <div style="flex:1;height:1px;background:#e2e8f0;margin:0 12px"></div>
      <div class="step-item">
        <div class="step-circle">4</div>
        <span>Review</span>
      </div>
    </div>

    <h2 style="font-size:16px;font-weight:700;color:#0f172a;margin:0 0 20px">Basic Information</h2>

    <?php if ($err = flash('error')): ?>
      <div style="background:#fff1f2;border-left:4px solid #ef4444;padding:12px 16px;border-radius:8px;color:#9f1239;font-size:13px;margin-bottom:20px">
        <?= htmlspecialchars($err) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="?route=employee-register.submit" autocomplete="off" id="reg-form">
      <?= function_exists('csrf_field') ? csrf_field() : '' ?>

      <div class="form-grid">
        <div>
          <label style="display:block;font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:6px">First Name *</label>
          <input type="text" id="first_name" name="first_name" placeholder="e.g. Venkatsd" required>
        </div>

        <div>
          <label style="display:block;font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:6px">Last Name *</label>
          <input type="text" id="last_name" name="last_name" placeholder="e.g. S" required>
        </div>

        <div>
          <label style="display:block;font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:6px">Email Address *</label>
          <input type="email" id="email" name="email" placeholder="venkat@example.com" required>
        </div>

        <div>
          <label style="display:block;font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:6px">Phone Number *</label>
          <input type="tel" id="phone" name="phone" placeholder="+91 98765 43210" required>
        </div>

        <div>
          <label style="display:block;font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:6px">Department *</label>
          <select id="department" name="department" required>
            <option value="">Select Department</option>
            <?php foreach ($departmentOptions as $dept): ?>
              <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:6px">Position / Designation *</label>
          <select id="position" name="position" required>
            <option value="">Select Position</option>
            <?php foreach ($allDesignations as $desg): ?>
              <option value="<?= htmlspecialchars($desg) ?>"><?= htmlspecialchars($desg) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:6px">Hospital Location *</label>
          <select id="location" name="location" required>
            <option value="">Select Location</option>
            <?php foreach ($locationOptions as $loc): ?>
              <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:6px">Employment Type *</label>
          <select id="employment_type" name="employment_type" required>
            <option value="Permanent" selected>Permanent</option>
            <option value="Contract">Contract</option>
            <option value="Temp">Temporary / Probation</option>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12.5px;font-weight:700;color:#0f172a;margin-bottom:6px">Date of Joining *</label>
          <input type="date" id="join_date" name="join_date" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>

      <!-- Action Buttons (Screen 4) -->
      <div style="display:flex;align-items:center;justify-content:flex-end;gap:14px;margin-top:32px;padding-top:20px;border-top:1px solid #f1f5f9">
        <a href="?route=login" class="btn-secondary" style="border:none;background:#f1f5f9;color:#475569">Cancel</a>
        <button type="submit" class="btn-primary" style="background:#0d474c">
          Next &rarr;
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const designationMap = <?= json_encode($designationMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const deptSelect = document.getElementById('department');
const posSelect = document.getElementById('position');

deptSelect.addEventListener('change', function() {
  const selectedDept = this.value;
  const currentPos = posSelect.value;
  posSelect.innerHTML = '<option value="">Select Position</option>';
  let options = [];
  if (selectedDept && designationMap[selectedDept]) {
    options = designationMap[selectedDept];
  } else {
    options = <?= json_encode($allDesignations) ?>;
  }
  options.forEach(desg => {
    const opt = document.createElement('option');
    opt.value = desg;
    opt.textContent = desg;
    if (desg === currentPos) opt.selected = true;
    posSelect.appendChild(opt);
  });
});
</script>
</body>
</html>
