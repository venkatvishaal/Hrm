<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$permissionTimeRange = static function (array $request): string {
  if (strcasecmp((string)($request['leave_type'] ?? ''), 'Permission') !== 0) {
    return '';
  }
  $start = substr((string)($request['permission_start_time'] ?? ''), 0, 5);
  $end = substr((string)($request['permission_end_time'] ?? ''), 0, 5);
  $res = trim($start . ' - ' . $end, ' -');
  return $res !== '' ? ' (' . $res . ')' : '';
};
$totalRequests = array_sum($statusCounts ?? []);
?>

<div class="content-body">
  <!-- ── 1. Page Header ── -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:12px">
    <div class="dashboard-header-greeting">
      <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 4px">Leave Management & Approvals</h1>
      <p style="font-size:13.5px;color:#64748b;margin:0">Review staff leave requests, evaluate balance impact, and record multi-level decisions.</p>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <a href="?route=export&type=leave" style="display:inline-flex;align-items:center;gap:6px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:8px 16px;font-size:12.5px;font-weight:700;color:#475569;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#ffffff'">
        <span>📥</span> Export CSV
      </a>
    </div>
  </div>

  <!-- ── 2. Status Counter Tabs ── -->
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;flex-wrap:wrap">
    <a href="?route=leave" style="text-decoration:none;padding:8px 16px;border-radius:10px;font-size:12.5px;font-weight:700;<?= empty($statusFilter) ? 'background:#0d474c;color:#ffffff;' : 'background:#ffffff;border:1px solid #e2e8f0;color:#64748b;' ?>">
      All Requests (<?= (int)$totalRequests ?>)
    </a>
    <a href="?route=leave&amp;status=Pending" style="text-decoration:none;padding:8px 16px;border-radius:10px;font-size:12.5px;font-weight:700;<?= ($statusFilter ?? '') === 'Pending' ? 'background:#d97706;color:#ffffff;' : 'background:#ffffff;border:1px solid #e2e8f0;color:#b45309;' ?>">
      Pending (<?= (int)($statusCounts['Pending'] ?? 0) ?>)
    </a>
    <a href="?route=leave&amp;status=Approved" style="text-decoration:none;padding:8px 16px;border-radius:10px;font-size:12.5px;font-weight:700;<?= ($statusFilter ?? '') === 'Approved' ? 'background:#15803d;color:#ffffff;' : 'background:#ffffff;border:1px solid #e2e8f0;color:#15803d;' ?>">
      Approved (<?= (int)($statusCounts['Approved'] ?? 0) ?>)
    </a>
    <a href="?route=leave&amp;status=Rejected" style="text-decoration:none;padding:8px 16px;border-radius:10px;font-size:12.5px;font-weight:700;<?= ($statusFilter ?? '') === 'Rejected' ? 'background:#b91c1c;color:#ffffff;' : 'background:#ffffff;border:1px solid #e2e8f0;color:#b91c1c;' ?>">
      Rejected (<?= (int)($statusCounts['Rejected'] ?? 0) ?>)
    </a>
  </div>

  <!-- ── 3. Filters Toolbar Card ── -->
  <div class="card" style="padding:16px 20px;margin-bottom:18px">
    <form method="get" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <input type="hidden" name="route" value="leave">
      <div style="flex:1;min-width:220px">
        <label style="display:block;font-size:11px;font-weight:700;color:#64748b;margin-bottom:4px;text-transform:uppercase">Filter by Employee</label>
        <select name="employee_id" style="width:100%">
          <option value="0">All Staff Members</option>
          <?php foreach (($employees ?? []) as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>" <?= ((int)$selectedEmployeeId === (int)$emp['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= htmlspecialchars((string)($emp['employee_code'] ?: ('#' . $emp['id']))) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="width:200px">
        <label style="display:block;font-size:11px;font-weight:700;color:#64748b;margin-bottom:4px;text-transform:uppercase">Request Status</label>
        <select name="status" style="width:100%">
          <option value="">All Statuses</option>
          <?php foreach (['Pending', 'Approved', 'Rejected'] as $ls): ?>
            <option value="<?= $ls ?>" <?= (($statusFilter ?? '') === $ls) ? 'selected' : '' ?>><?= $ls ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex;align-items:flex-end;gap:10px;padding-top:16px">
        <button type="submit" class="btn-primary" style="background:#0d474c;padding:8px 18px">Filter</button>
        <?php if (!empty($selectedEmployeeId) || !empty($statusFilter)): ?>
          <a href="?route=leave" style="font-size:12.5px;color:#64748b;font-weight:600;text-decoration:none;padding:8px">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ── 4. Selected Employee Leave Balance Strip (If filtered) ── -->
  <?php if (!empty($employeeStats)): ?>
    <div style="display:grid;grid-template-columns:repeat(6, 1fr);gap:12px;margin-bottom:18px">
      <div class="clinical-kpi-card accent-teal" style="padding:12px;text-align:center">
        <span class="clinical-kpi-label">Available</span>
        <div class="clinical-kpi-value" style="font-size:20px"><?= (int)$employeeStats['available'] ?></div>
      </div>
      <div class="clinical-kpi-card accent-blue" style="padding:12px;text-align:center">
        <span class="clinical-kpi-label">Availed</span>
        <div class="clinical-kpi-value" style="font-size:20px"><?= (int)$employeeStats['availed'] ?></div>
      </div>
      <div class="clinical-kpi-card accent-amber" style="padding:12px;text-align:center">
        <span class="clinical-kpi-label">Pending</span>
        <div class="clinical-kpi-value" style="font-size:20px"><?= (int)$employeeStats['pending'] ?></div>
      </div>
      <div class="clinical-kpi-card accent-green" style="padding:12px;text-align:center">
        <span class="clinical-kpi-label">Approved</span>
        <div class="clinical-kpi-value" style="font-size:20px"><?= (int)$employeeStats['approved'] ?></div>
      </div>
      <div class="clinical-kpi-card accent-red" style="padding:12px;text-align:center">
        <span class="clinical-kpi-label">Rejected</span>
        <div class="clinical-kpi-value" style="font-size:20px"><?= (int)$employeeStats['rejected'] ?></div>
      </div>
      <div class="clinical-kpi-card accent-teal" style="padding:12px;text-align:center">
        <span class="clinical-kpi-label">Net Balance</span>
        <div class="clinical-kpi-value" style="font-size:20px"><?= (int)$employeeStats['balance'] ?></div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ── 5. Enterprise Leave Requests Table ── -->
  <div class="clinical-table-wrapper" style="margin:0">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#ffffff">
      <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">Staff Leave Requests</h3>
      <span style="font-size:12px;color:#64748b"><?= count($items) ?> request(s) found</span>
    </div>

    <div>
      <table class="clinical-table-dense">
        <thead>
          <tr>
            <th>ID</th>
            <th>Employee</th>
            <th>Department</th>
            <th>Leave Type</th>
            <th>Dates</th>
            <th>Reason</th>
            <th>Status</th>
            <th style="text-align:right">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr>
              <td colspan="8" style="padding:32px 18px;text-align:center;color:#64748b;font-size:13px">
                No leave requests match the selected criteria.
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($items as $it): ?>
            <?php
              $fullName = trim((string)($it['first_name'] ?? '') . ' ' . (string)($it['last_name'] ?? ''));
              $st = (string)($it['status'] ?? 'Pending');
              $isPending = strcasecmp($st, 'Pending') === 0;
              $isApproved = strcasecmp($st, 'Approved') === 0;
              $isRejected = strcasecmp($st, 'Rejected') === 0;

              $statusBg = $isPending ? '#fef3c7' : ($isApproved ? '#dcfce7' : '#fee2e2');
              $statusColor = $isPending ? '#b45309' : ($isApproved ? '#15803d' : '#b91c1c');

              $approvalLevel = max(1, min(3, (int)($it['approval_level'] ?? 1)));
              $assignedRoles = $approvalHierarchy['level' . $approvalLevel . '_roles'] ?? [];
              $isTerminal = in_array($st, ['Approved', 'Rejected'], true);
              $canTakeDecision = !empty($canApprove) && !$isTerminal && in_array($role ?? '', $assignedRoles, true);
            ?>
            <tr>
              <td style="font-weight:600;color:#64748b">
                #<?= (int)$it['id'] ?>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="clinical-avatar" style="width:34px;height:34px;font-size:12px;flex-shrink:0">
                    <?= htmlspecialchars(strtoupper(substr($fullName ?: 'Staff', 0, 1))) ?>
                    <span class="clinical-avatar-badge <?= $isApproved ? 'leave' : 'online' ?>"></span>
                  </div>
                  <div>
                    <strong style="display:block;font-size:13px;color:#0f172a"><?= htmlspecialchars($fullName) ?></strong>
                    <span style="font-size:11px;color:#64748b"><?= htmlspecialchars((string)($it['position'] ?? 'Staff')) ?></span>
                  </div>
                </div>
              </td>
              <td style="color:#475569">
                <?= htmlspecialchars((string)$it['department']) ?>
              </td>
              <td>
                <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:11.5px;font-weight:600;background:#f1f5f9;color:#334155">
                  <?= htmlspecialchars((string)$it['leave_type']) ?><?= htmlspecialchars($permissionTimeRange($it)) ?>
                </span>
              </td>
              <td style="white-space:nowrap">
                <?= htmlspecialchars(fmt_date($it['start_date'])) ?> – <?= htmlspecialchars(fmt_date($it['end_date'])) ?>
              </td>
              <td style="color:#64748b;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= htmlspecialchars((string)$it['reason']) ?>
              </td>
              <td>
                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;background:<?= $statusBg ?>;color:<?= $statusColor ?>">
                  <?= htmlspecialchars($st) ?>
                </span>
              </td>
              <td style="text-align:right">
                <a href="?route=leave.decision&id=<?= (int)$it['id'] ?>" style="display:inline-block;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;<?= $canTakeDecision ? 'background:#0d474c;color:#ffffff;' : 'background:#f1f5f9;color:#475569;' ?>">
                  <?= $canTakeDecision ? 'Review Decision' : 'View' ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
