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
$empName = trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']);
$empCode = (string)($employee['employee_code'] ?: ('KH' . str_pad((string)$employee['id'], 3, '0', STR_PAD_LEFT)));
?>

<div class="content-body" style="max-width:1080px;margin:0 auto">
  <!-- Back Link -->
  <div style="margin-bottom:12px">
    <a href="?route=recruitment" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#64748b;text-decoration:none">
      ← Back to Employee Directory
    </a>
  </div>

  <!-- Header Card -->
  <div class="card" style="padding:22px 28px;margin:0 0 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
    <div style="display:flex;align-items:center;gap:16px">
      <div style="width:52px;height:52px;border-radius:50%;background:#e6f4f1;border:2px solid #0f766e;color:#0f766e;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px">
        <?= htmlspecialchars(strtoupper(substr($empName ?: 'S', 0, 2))) ?>
      </div>
      <div>
        <h2 style="font-size:18px;font-weight:800;color:#0f172a;margin:0 0 4px"><?= htmlspecialchars($empName) ?></h2>
        <div style="font-size:12.5px;color:#64748b">
          <?= htmlspecialchars((string)$employee['position']) ?> • <?= htmlspecialchars((string)$employee['department']) ?> • <span style="font-weight:700;color:#0f766e"><?= htmlspecialchars($empCode) ?></span>
        </div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <span class="badge-status active">Active Staff</span>
    </div>
  </div>

  <!-- Main Edit Form -->
  <form method="post" action="?route=recruitment.update" enctype="multipart/form-data" class="create-employee-form" data-designation-form>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$employee['id'] ?>">

    <!-- Section 1: Basic Information -->
    <div class="card" style="padding:24px 28px;margin:0 0 20px">
      <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 18px;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
        1. Personal & Identity Details
      </h3>

      <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Employee Code</label>
          <input name="employee_code" value="<?= htmlspecialchars((string)$employee['employee_code']) ?>" style="background:#f8fafc;font-weight:600">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">First Name *</label>
          <input name="first_name" value="<?= htmlspecialchars($employee['first_name']) ?>" required>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Last Name *</label>
          <input name="last_name" value="<?= htmlspecialchars($employee['last_name']) ?>" required>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Email Address</label>
          <input name="email" type="email" value="<?= htmlspecialchars((string)$employee['email']) ?>">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Phone Number *</label>
          <input name="phone" value="<?= htmlspecialchars((string)$employee['phone']) ?>" required>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Date of Birth</label>
          <input name="date_of_birth" type="date" value="<?= htmlspecialchars((string)$employee['date_of_birth']) ?>">
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Gender</label>
          <select name="gender">
            <option value="">Select Gender</option>
            <option value="Male" <?= ($employee['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= ($employee['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
            <option value="Other" <?= ($employee['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Marital Status</label>
          <select name="marital_status">
            <option value="">Select</option>
            <option value="Single" <?= ($employee['marital_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
            <option value="Married" <?= ($employee['marital_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
            <option value="Divorced" <?= ($employee['marital_status'] ?? '') === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
            <option value="Widowed" <?= ($employee['marital_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Blood Group</label>
          <input name="blood_group" value="<?= htmlspecialchars((string)$employee['blood_group']) ?>" placeholder="e.g. B+">
        </div>
      </div>

      <!-- Address Row -->
      <div style="margin-top:16px;display:grid;grid-template-columns:repeat(4, 1fr);gap:16px">
        <div style="grid-column:span 2">
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Street / Address</label>
          <input name="street" value="<?= htmlspecialchars((string)($employee['street'] ?? '')) ?>" placeholder="Street address">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">City</label>
          <input name="city" value="<?= htmlspecialchars((string)$employee['city']) ?>">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Pincode</label>
          <input name="pincode" value="<?= htmlspecialchars((string)$employee['pincode']) ?>">
        </div>
      </div>
    </div>

    <!-- Section 2: Hospital Workforce Assignment -->
    <div class="card" style="padding:24px 28px;margin:0 0 20px">
      <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 18px;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
        2. Hospital Department & Role Assignment
      </h3>

      <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Department *</label>
          <select name="department" required>
            <option value="">Select Department</option>
            <?php foreach ($departmentOptions as $dept): ?>
              <option value="<?= htmlspecialchars($dept) ?>" <?= (($employee['department'] ?? '') === $dept) ? 'selected' : '' ?>>
                <?= htmlspecialchars($dept) ?>
              </option>
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
                  <option value="<?= htmlspecialchars($designation) ?>" data-department="<?= htmlspecialchars($dept) ?>" <?= (($employee['position'] ?? '') === $designation) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($designation) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">System Role</label>
          <select name="role">
            <?php foreach (['Employee', 'Manager', 'HR', 'Admin', 'SuperAdmin'] as $rl): ?>
              <option value="<?= $rl ?>" <?= (($employee['role'] ?? '') === $rl) ? 'selected' : '' ?>><?= $rl ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Hospital Unit / Location</label>
          <select name="location" required>
            <?php foreach ($locationOptions as $loc): ?>
              <option value="<?= htmlspecialchars($loc) ?>" <?= (($employee['location'] ?? '') === $loc) ? 'selected' : '' ?>><?= htmlspecialchars($loc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Join Date</label>
          <input name="join_date" type="date" value="<?= htmlspecialchars((string)$employee['join_date']) ?>">
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Employment Type</label>
          <select name="employment_type">
            <option value="Permanent" <?= (($employee['employment_type'] ?? '') === 'Permanent') ? 'selected' : '' ?>>Permanent</option>
            <option value="Contract" <?= (($employee['employment_type'] ?? '') === 'Contract') ? 'selected' : '' ?>>Contract</option>
            <option value="Temp" <?= (($employee['employment_type'] ?? '') === 'Temp') ? 'selected' : '' ?>>Temporary</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Section 3: Professional & Compliance -->
    <div class="card" style="padding:24px 28px;margin:0 0 20px">
      <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 18px;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
        3. Professional Qualifications & Compliance
      </h3>

      <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Highest Qualification</label>
          <input name="qualification" value="<?= htmlspecialchars((string)$employee['qualification']) ?>">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Clinical Specialization</label>
          <input name="specialization" value="<?= htmlspecialchars((string)$employee['specialization']) ?>">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Years of Experience</label>
          <input name="years_experience" type="number" step="0.5" min="0" value="<?= htmlspecialchars((string)$employee['years_experience']) ?>">
        </div>
        <div style="grid-column:span 2">
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Medical License / Council Registration No.</label>
          <input name="license_number" value="<?= htmlspecialchars((string)$employee['license_number']) ?>" placeholder="e.g. TNMC-89421">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Monthly Salary (INR)</label>
          <input name="salary" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string)$employee['salary']) ?>">
        </div>
      </div>
    </div>

    <!-- Section 4: Emergency Contact -->
    <div class="card" style="padding:24px 28px;margin:0 0 24px">
      <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 18px;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
        4. Emergency Contact
      </h3>

      <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Contact Person Name</label>
          <input name="emergency_contact_name" value="<?= htmlspecialchars((string)$employee['emergency_contact_name']) ?>">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Contact Person Phone</label>
          <input name="emergency_contact_phone" value="<?= htmlspecialchars((string)$employee['emergency_contact_phone']) ?>">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Relationship</label>
          <input name="emergency_contact_relation" value="<?= htmlspecialchars((string)$employee['emergency_contact_relation']) ?>" placeholder="e.g. Spouse / Parent">
        </div>
      </div>
    </div>

    <!-- Submit Bar -->
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px;margin-bottom:32px">
      <a href="?route=recruitment" style="font-size:13px;font-weight:600;color:#64748b;text-decoration:none;padding:10px 18px">Cancel</a>
      <button type="submit" class="btn-primary" style="background:#0d474c;padding:12px 28px;font-size:13.5px;font-weight:700;border-radius:10px">
        Save Employee Changes
      </button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
