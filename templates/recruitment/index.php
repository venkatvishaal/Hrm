<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php $canManageEmployees = in_array(($_SESSION['user']['role'] ?? ''), ['Admin', 'SuperAdmin', 'HR'], true); ?>

<div class="content-body">
  <!-- ── 1. Page Header ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px">
    <div class="dashboard-header-greeting">
      <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 4px">Employee Directory</h1>
      <p style="font-size:13.5px;color:#64748b;margin:0">Manage employee records, departmental postings, and workforce credentials.</p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <?php if ($canManageEmployees): ?>
        <a href="?route=recruitment.template" style="display:inline-flex;align-items:center;gap:6px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 16px;font-size:12.5px;font-weight:700;color:#475569;text-decoration:none">
          📄 Template
        </a>
        <a href="?route=export&type=employees" style="display:inline-flex;align-items:center;gap:6px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 16px;font-size:12.5px;font-weight:700;color:#475569;text-decoration:none">
          📥 Export CSV
        </a>
        <a href="?route=recruitment.create" class="btn-primary" style="background:#0d474c;display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:10px;font-size:12.5px;font-weight:700;text-decoration:none">
          + Add Employee
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── 2. Search & Filter Bar ── -->
  <div class="card" style="padding:14px 18px;margin-bottom:18px">
    <form method="get" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <input type="hidden" name="route" value="recruitment">
      <div style="flex:1;min-width:260px;position:relative">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="#94a3b8" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);pointer-events:none"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
        <input name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search by name, employee code, department, email, position..." style="padding-left:38px !important;border-radius:10px !important;width:100%">
      </div>
      <button type="submit" class="btn-primary" style="background:#0d474c;padding:9px 20px;font-size:12.5px">Search</button>
      <?php if (!empty($q)): ?>
        <a href="?route=recruitment" style="font-size:12.5px;color:#64748b;text-decoration:none;padding:8px">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- ── 3. Employee Directory Data Table ── -->
  <div class="card" style="padding:0;margin:0 0 24px;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
      <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Active Staff Roster</h3>
      <span style="font-size:12px;color:#64748b"><?= count($employees) ?> employee(s) listed</span>
    </div>

    <div class="table-scroll">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left">
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Employee</th>
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Code</th>
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Department</th>
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Position</th>
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Location</th>
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase">Role</th>
            <th style="padding:12px 18px;font-size:11.5px;font-weight:700;color:#475569;text-transform:uppercase;text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($employees)): ?>
            <tr>
              <td colspan="7" style="padding:32px 18px;text-align:center;color:#64748b;font-size:13px">
                No employee records found matching your search.
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($employees as $e): ?>
            <?php
              $fullName = trim((string)$e['first_name'] . ' ' . (string)$e['last_name']);
              $empCode = (string)($e['employee_code'] ?: ('KH' . str_pad((string)$e['id'], 3, '0', STR_PAD_LEFT)));
              $roleBadgeColor = match((string)$e['role']) {
                'Admin', 'SuperAdmin' => 'background:#fee2e2;color:#b91c1c',
                'HR' => 'background:#ede9fe;color:#7c3aed',
                'Manager', 'HOD' => 'background:#e0f2fe;color:#0369a1',
                default => 'background:#f1f5f9;color:#475569',
              };
            ?>
            <tr style="border-bottom:1px solid #f1f5f9;transition:background .15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
              <td style="padding:12px 18px">
                <div style="display:flex;align-items:center;gap:12px">
                  <div style="width:36px;height:36px;border-radius:50%;background:#e6f4f1;border:1.5px solid #0f766e;color:#0f766e;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px">
                    <?= htmlspecialchars(strtoupper(substr($fullName ?: 'Staff', 0, 1))) ?>
                  </div>
                  <div>
                    <strong style="display:block;font-size:13.5px;color:#0f172a"><?= htmlspecialchars($fullName) ?></strong>
                    <span style="font-size:11.5px;color:#64748b"><?= htmlspecialchars((string)$e['email'] ?: 'No email registered') ?></span>
                  </div>
                </div>
              </td>
              <td style="padding:12px 18px">
                <span style="font-size:12px;font-weight:700;color:#0f766e;background:#f0fdf4;padding:3px 8px;border-radius:6px;border:1px solid #bbf7d0">
                  <?= htmlspecialchars($empCode) ?>
                </span>
              </td>
              <td style="padding:12px 18px;font-size:13px;color:#334155;font-weight:500">
                <?= htmlspecialchars((string)$e['department']) ?>
              </td>
              <td style="padding:12px 18px;font-size:13px;color:#475569">
                <?= htmlspecialchars((string)$e['position']) ?>
              </td>
              <td style="padding:12px 18px">
                <span style="font-size:11.5px;font-weight:600;padding:2px 8px;border-radius:6px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569">
                  <?= htmlspecialchars((string)($e['location'] ?: 'KH')) ?>
                </span>
              </td>
              <td style="padding:12px 18px">
                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;<?= $roleBadgeColor ?>">
                  <?= htmlspecialchars((string)$e['role']) ?>
                </span>
              </td>
              <td style="padding:12px 18px;text-align:right">
                <?php if ($canManageEmployees): ?>
                  <div style="display:inline-flex;align-items:center;gap:6px">
                    <a href="?route=recruitment.edit&id=<?= (int)$e['id'] ?>" style="padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;background:#0d474c;color:#ffffff;text-decoration:none">
                      Edit
                    </a>
                    <?php if (($_SESSION['user']['role'] ?? '') === 'HR' && (string)$e['role'] === 'Employee'): ?>
                      <form method="post" action="?route=employee.password.reset" onsubmit="return confirm('Reset this employee password to kh1234?')" style="margin:0;display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="employee_id" value="<?= (int)$e['id'] ?>">
                        <button type="submit" style="padding:5px 10px;border-radius:8px;font-size:11.5px;font-weight:600;background:#fef3c7;color:#b45309;border:1px solid #fde68a;cursor:pointer">Reset Pwd</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="?route=recruitment.delete" onsubmit="return confirm('Delete this employee record?')" style="margin:0;display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                      <button type="submit" style="padding:5px 10px;border-radius:8px;font-size:11.5px;font-weight:600;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;cursor:pointer">Delete</button>
                    </form>
                  </div>
                <?php else: ?>
                  <span style="font-size:12px;color:#94a3b8">View only</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── 4. Bulk Upload Utility ── -->
  <?php if ($canManageEmployees): ?>
    <div class="card" style="padding:22px;border-radius:14px">
      <div style="margin-bottom:14px">
        <h4 style="font-size:15px;font-weight:700;color:#0f172a;margin:0 0 4px">Bulk Staff Onboarding</h4>
        <p style="font-size:12.5px;color:#64748b;margin:0">Upload a CSV or Excel sheet containing employee records for automated registration.</p>
      </div>
      <form method="post" action="?route=recruitment.bulk-upload" enctype="multipart/form-data" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input type="file" name="employee_sheet" accept=".csv,.xlsx" required style="font-size:13px;padding:6px;border:1px dashed #cbd5e1;border-radius:8px">
        <button type="submit" class="btn-primary" style="background:#0d474c;font-size:12.5px;padding:8px 18px">Upload Sheet</button>
        <small style="font-size:11.5px;color:#64748b">Columns: first_name, last_name, email, phone, department, location, position, role, password.</small>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
