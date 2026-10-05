<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
  $employeePhotoSrc = '';
  if (!empty($employee['photo_path'])) {
    $photoPath = ltrim(str_replace('\\', '/', (string)$employee['photo_path']), '/');
    $employeePhotoSrc = str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/public/') ? $photoPath : 'public/' . $photoPath;
  }
  $empFullName = trim((string)($employee['first_name'] ?? '') . ' ' . (string)($employee['last_name'] ?? ''));
  $words = preg_split('/\s+/', $empFullName);
  $avatarInitials = '';
  if (count($words) >= 2) {
    $avatarInitials = strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1));
  } else {
    $avatarInitials = strtoupper(substr($empFullName, 0, 2));
  }
  $empCode = (string)($employee['employee_code'] ?: ('KH' . str_pad((string)$employee['id'], 3, '0', STR_PAD_LEFT)));
  $empDept = (string)($employee['department'] ?? 'General');
  $empPosition = (string)($employee['position'] ?? 'Staff Member');

  $profileSection = $_GET['profile_section'] ?? 'personal';
  $rosterRange = $rosterRange ?? [
    'start' => date('Y-m-01'),
    'end' => date('Y-m-t'),
    'label' => fmt_date(date('Y-m-01')) . ' to ' . fmt_date(date('Y-m-t')),
  ];
  $timeLabel = static function ($value): string {
    $value = (string)$value;
    return $value !== '' ? substr($value, 0, 5) : '-';
  };
?>

<div class="content-body">
  <!-- ── Back Link (Screen 3) ── -->
  <div style="margin-bottom:8px">
    <a href="?route=dashboard" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#64748b;text-decoration:none">
      ← Back to Directory
    </a>
  </div>

  <!-- ── Hero Profile Card (Screen 3) ── -->
  <div class="card" style="padding:24px;margin:0;display:flex;align-items:center;justify-content:space-between;background:#ffffff;position:relative;overflow:hidden">
    <div style="display:flex;align-items:center;gap:20px;z-index:1">
      <!-- Circular Avatar with VS or Photo -->
      <div style="width:72px;height:72px;border-radius:50%;background:#e6f4f1;border:2.5px solid #0f766e;color:#0f766e;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;overflow:hidden;flex-shrink:0">
        <?php if ($employeePhotoSrc !== ''): ?>
          <img src="<?= htmlspecialchars($employeePhotoSrc) ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <span><?= htmlspecialchars($avatarInitials) ?></span>
        <?php endif; ?>
      </div>

      <!-- Identity Meta & Badges -->
      <div>
        <h2 style="font-size:20px;font-weight:800;color:#0f172a;margin:0 0 4px"><?= htmlspecialchars($empFullName) ?></h2>
        <div style="font-size:13px;color:#64748b;margin-bottom:10px"><?= htmlspecialchars($empPosition) ?></div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span style="background:#f1f5f9;color:#475569;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:100px"><?= htmlspecialchars($empCode) ?></span>
          <span style="background:#f1f5f9;color:#475569;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:100px"><?= htmlspecialchars($empDept) ?></span>
          <span class="badge-status active">Active</span>
        </div>
      </div>
    </div>

    <!-- Decorative Hospital Architecture Silhouette (Right Side) -->
    <div style="opacity:0.25;position:absolute;right:20px;top:10px;bottom:10px;pointer-events:none;display:flex;align-items:center">
      <svg viewBox="0 0 160 100" width="160" height="100" fill="#0f766e">
        <rect x="20" y="20" width="120" height="75" rx="4" fill="#cbd5e1"/>
        <rect x="50" y="10" width="60" height="85" rx="4" fill="#94a3b8"/>
        <!-- Cross symbol -->
        <rect x="74" y="22" width="12" height="24" rx="2" fill="#0f766e"/>
        <rect x="68" y="28" width="24" height="12" rx="2" fill="#0f766e"/>
        <!-- Window grid -->
        <rect x="30" y="35" width="10" height="12" fill="#ffffff"/>
        <rect x="30" y="55" width="10" height="12" fill="#ffffff"/>
        <rect x="120" y="35" width="10" height="12" fill="#ffffff"/>
        <rect x="120" y="55" width="10" height="12" fill="#ffffff"/>
        <rect x="60" y="48" width="12" height="14" fill="#ffffff"/>
        <rect x="88" y="48" width="12" height="14" fill="#ffffff"/>
        <rect x="70" y="70" width="20" height="25" fill="#0f172a"/>
      </svg>
    </div>
  </div>

  <!-- ── Profile Tabs (Screen 3) ── -->
  <div class="card" style="padding:24px;margin:0">
    <nav class="tab-nav" id="profileTabNav" style="margin-bottom:24px">
      <button type="button" class="active" data-profile-tab="personal">Personal</button>
      <button type="button" data-profile-tab="employment">Employment</button>
      <button type="button" data-profile-tab="educational">Education</button>
      <button type="button" data-profile-tab="bank">Bank Details</button>
      <button type="button" data-profile-tab="communication">Communication</button>
      <button type="button" data-profile-tab="documents">Documents</button>
    </nav>

    <!-- Main Profile Form -->
    <form method="post" action="?route=employee.onboarding-profile.update" id="profileUpdateForm">
      <?= csrf_field() ?>
      <input type="hidden" name="return_route" value="employee-profile">
      <input type="hidden" name="profile_section" id="activeProfileSection" value="personal">

      <!-- ── Tab 1: Personal (Screen 3) ── -->
      <div id="tabSectionPersonal" class="profile-tab-content">
        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:18px">
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Date of Birth</label>
            <input type="date" name="date_of_birth" value="<?= htmlspecialchars((string)($employee['date_of_birth'] ?? '1998-03-10')) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Gender</label>
            <select name="gender">
              <option value="Male" <?= (string)($employee['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= (string)($employee['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= (string)($employee['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Marital Status</label>
            <select name="marital_status">
              <option value="Single" <?= (string)($employee['marital_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
              <option value="Married" <?= (string)($employee['marital_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
              <option value="Divorced" <?= (string)($employee['marital_status'] ?? '') === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
              <option value="Widowed" <?= (string)($employee['marital_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
            </select>
          </div>

          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Phone Number</label>
            <input type="text" name="phone" value="<?= htmlspecialchars((string)($employee['phone'] ?? '+91 98765 43210')) ?>">
          </div>
          <div style="grid-column: span 2">
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars((string)($employee['email'] ?? 'venkatsd@gmail.com')) ?>">
          </div>

          <div style="grid-column: span 3">
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Address</label>
            <input type="text" name="street" placeholder="Street / Door No" value="<?= htmlspecialchars((string)($employee['street'] ?? '123, Anna Nagar')) ?>">
          </div>

          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">City</label>
            <input type="text" name="city" value="<?= htmlspecialchars((string)($employee['city'] ?? 'Chennai')) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">State</label>
            <input type="text" name="state" value="<?= htmlspecialchars((string)($employee['state'] ?? 'Tamil Nadu')) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Pincode</label>
            <input type="text" name="pincode" value="<?= htmlspecialchars((string)($employee['pincode'] ?? '600040')) ?>">
          </div>

          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Blood Group</label>
            <select name="blood_group">
              <?php foreach (['B+', 'A+', 'O+', 'AB+', 'A-', 'B-', 'O-', 'AB-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= (string)($employee['blood_group'] ?? 'B+') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Emergency Contact Name</label>
            <input type="text" name="emergency_contact_name" value="<?= htmlspecialchars((string)($employee['emergency_contact_name'] ?? 'Ravi Kumar')) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Emergency Contact Phone</label>
            <input type="text" name="emergency_contact_phone" value="<?= htmlspecialchars((string)($employee['emergency_contact_phone'] ?? '+91 98765 43219')) ?>">
          </div>

          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Employment Type</label>
            <select name="employment_type">
              <option value="Permanent" <?= (string)($employee['employment_type'] ?? '') === 'Permanent' ? 'selected' : '' ?>>Permanent</option>
              <option value="Contract" <?= (string)($employee['employment_type'] ?? '') === 'Contract' ? 'selected' : '' ?>>Contract</option>
              <option value="Temporary" <?= (string)($employee['employment_type'] ?? '') === 'Temporary' ? 'selected' : '' ?>>Temporary</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Join Date</label>
            <input type="date" name="join_date" value="<?= htmlspecialchars((string)($employee['join_date'] ?? date('Y-m-d'))) ?>">
          </div>
        </div>
      </div>

      <!-- ── Tab 2: Employment ── -->
      <div id="tabSectionEmployment" class="profile-tab-content" style="display:none">
        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:18px">
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Department</label>
            <input type="text" readonly value="<?= htmlspecialchars($empDept) ?>" style="background:#f8fafc">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Position</label>
            <input type="text" readonly value="<?= htmlspecialchars($empPosition) ?>" style="background:#f8fafc">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Employee Code</label>
            <input type="text" readonly value="<?= htmlspecialchars($empCode) ?>" style="background:#f8fafc">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Years of Experience</label>
            <input type="number" step="0.5" name="years_experience" value="<?= htmlspecialchars((string)($employee['years_experience'] ?? '3')) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">PF Number</label>
            <input type="text" name="pf_number" value="<?= htmlspecialchars((string)($employee['pf_number'] ?? '')) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">ESI Number</label>
            <input type="text" name="esi_number" value="<?= htmlspecialchars((string)($employee['esi_number'] ?? '')) ?>">
          </div>
        </div>
      </div>

      <!-- ── Tab 3: Educational ── -->
      <div id="tabSectionEducational" class="profile-tab-content" style="display:none">
        <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:18px">
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Highest Qualification</label>
            <input type="text" name="qualification" value="<?= htmlspecialchars((string)($employee['qualification'] ?? 'Graduate')) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Specialization</label>
            <input type="text" name="specialization" value="<?= htmlspecialchars((string)($employee['specialization'] ?? 'Healthcare Security')) ?>">
          </div>
          <div style="grid-column:span 2">
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">License / Certifications</label>
            <input type="text" name="license_number" value="<?= htmlspecialchars((string)($employee['license_number'] ?? '')) ?>">
          </div>
        </div>
      </div>

      <!-- ── Tab 4: Bank Details ── -->
      <div id="tabSectionBank" class="profile-tab-content" style="display:none">
        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:18px">
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Bank Name</label>
            <input type="text" name="bank_name" value="<?= htmlspecialchars((string)($employee['bank_name'] ?? 'State Bank of India')) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Account Number</label>
            <input type="text" name="bank_account_no" value="<?= htmlspecialchars((string)($employee['bank_account_no'] ?? '38492049102')) ?>">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">IFSC Code</label>
            <input type="text" name="ifsc_code" value="<?= htmlspecialchars((string)($employee['ifsc_code'] ?? 'SBIN0001234')) ?>">
          </div>
        </div>
      </div>

      <!-- ── Tab 5: Communication ── -->
      <div id="tabSectionCommunication" class="profile-tab-content" style="display:none">
        <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:18px">
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Primary Phone</label>
            <input type="text" readonly value="<?= htmlspecialchars((string)($employee['phone'] ?? '+91 98765 43210')) ?>" style="background:#f8fafc">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:6px">Official Email</label>
            <input type="email" readonly value="<?= htmlspecialchars((string)($employee['email'] ?? 'venkatsd@hospital.com')) ?>" style="background:#f8fafc">
          </div>
        </div>
      </div>

      <!-- ── Tab 6: Documents ── -->
      <div id="tabSectionDocuments" class="profile-tab-content" style="display:none">
        <div style="margin-bottom:18px;display:flex;align-items:center;justify-content:space-between">
          <div>
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Official Employee Documents</h3>
            <p style="font-size:12.5px;color:#64748b;margin:2px 0 0">Verified identity, academic, and statutory compliance documents.</p>
          </div>
          <a href="?route=employee.documents" class="btn-primary" style="background:#0d474c;font-size:12.5px;padding:8px 16px;text-decoration:none">
            Manage & Upload Documents →
          </a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px">
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;display:flex;align-items:flex-start;gap:12px">
            <div style="width:38px;height:38px;border-radius:10px;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;font-size:18px">📄</div>
            <div style="flex:1">
              <strong style="display:block;font-size:13.5px;color:#0f172a">Aadhaar / National ID</strong>
              <span style="display:block;font-size:11.5px;color:#64748b;margin-bottom:6px">Identity Verification</span>
              <span class="badge-status active">Verified</span>
            </div>
          </div>

          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;display:flex;align-items:flex-start;gap:12px">
            <div style="width:38px;height:38px;border-radius:10px;background:#ede9fe;color:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:18px">🎓</div>
            <div style="flex:1">
              <strong style="display:block;font-size:13.5px;color:#0f172a">Degree / Certificate</strong>
              <span style="display:block;font-size:11.5px;color:#64748b;margin-bottom:6px">Highest Qualification</span>
              <span class="badge-status active">Verified</span>
            </div>
          </div>

          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;display:flex;align-items:flex-start;gap:12px">
            <div style="width:38px;height:38px;border-radius:10px;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:18px">🏥</div>
            <div style="flex:1">
              <strong style="display:block;font-size:13.5px;color:#0f172a">Medical Fitness Report</strong>
              <span style="display:block;font-size:11.5px;color:#64748b;margin-bottom:6px">Annual Staff Screening</span>
              <span class="badge-status active">Current (2026)</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Submit button -->
      <div style="display:flex;justify-content:flex-end;margin-top:24px">
        <button type="submit" class="btn-primary" style="background:#0d474c">Save Profile Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    var tabBtns = document.querySelectorAll('#profileTabNav button');
    var tabContents = document.querySelectorAll('.profile-tab-content');
    var sectionInput = document.getElementById('activeProfileSection');

    tabBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        tabBtns.forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var tabKey = btn.getAttribute('data-profile-tab');
        if (sectionInput) sectionInput.value = tabKey;

        tabContents.forEach(function(content) {
          content.style.display = 'none';
        });
        var targetSection = document.getElementById('tabSection' + tabKey.charAt(0).toUpperCase() + tabKey.slice(1));
        if (targetSection) targetSection.style.display = 'block';
      });
    });
  })();
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
