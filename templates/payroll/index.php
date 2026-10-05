<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php $monthQuery = '&month=' . urlencode($month); ?>
<div class="payroll-page">
  <form method="get" class="card payroll-month-panel">
    <input type="hidden" name="route" value="payroll">
    <label>Payroll Month <input type="month" name="month" value="<?= htmlspecialchars($month) ?>"></label>
    <button class="btn-compact payroll-load-button">Load Month</button>
    <a class="btn-link payroll-action-button" href="?route=attendance">Upload Attendance</a>
    <a class="btn-link payroll-action-button" href="?route=allowances&month=<?= urlencode($month) ?>">Add Allowances / Deductions</a>
    <a class="btn-link payroll-action-button" href="?route=export&type=salary_monthly<?= htmlspecialchars($monthQuery) ?>">Download Monthly Salary Excel</a>
    <a class="btn-link payroll-action-button" href="?route=export&type=salary_cumulative">Download Cumulative Excel</a>
  </form>

  <div class="card payroll-workflow">
    <strong>Salary Processing Workflow</strong>
    <span>1. Upload Attendance</span>
    <span>2. Add Allowances / Deductions</span>
    <span>3. Generate Monthly Salary</span>
    <span>4. Download Salary Report</span>
  </div>

  <form method="post" action="?route=payroll.generate" class="card payroll-generate-panel">
    <label>Generate Salary Month <input type="month" name="payroll_month" value="<?= htmlspecialchars($month) ?>" required></label>
    <label>Standard Daily Hours <input type="number" step="0.25" min="1" name="standard_daily_hours" value="8" required></label>
    <button class="btn-compact">Generate Monthly Salary From Attendance</button>
  </form>

  <div class="stats dashboard-cards payroll-summary">
    <div class="card dash-card"><span class="dash-label">Employees Paid</span><strong class="dash-value"><?= (int)$payrollSummary['employees'] ?></strong></div>
    <div class="card dash-card"><span class="dash-label">Basic Salary</span><strong class="dash-value"><?= number_format((float)$payrollSummary['basic'], 0) ?></strong></div>
    <div class="card dash-card"><span class="dash-label">Allowances</span><strong class="dash-value"><?= number_format((float)$payrollSummary['allowances'], 0) ?></strong></div>
    <div class="card dash-card"><span class="dash-label">Deductions</span><strong class="dash-value"><?= number_format((float)$payrollSummary['deductions'], 0) ?></strong></div>
    <div class="card dash-card"><span class="dash-label">Net Salary</span><strong class="dash-value"><?= number_format((float)$payrollSummary['net'], 0) ?></strong></div>
  </div>

<form method="post" action="?route=payroll.store" class="card grid payroll-manual-form">
  <label>Employee
    <select name="employee_id" required>
      <option value="">Select employee</option>
      <?php foreach ($employees as $e): ?>
      <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['first_name'].' '.$e['last_name'].' - '.$e['department']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Month <input type="month" name="payroll_month" value="<?= htmlspecialchars($month) ?>" required></label>
  <label>Basic Salary / Wages <input type="number" step="0.01" name="basic_salary" required></label>
  <label>Allowances <input type="number" step="0.01" name="allowances" value="0"></label>
  <label>Deductions <input type="number" step="0.01" name="deductions" value="0"></label>
  <label>Status
    <select name="status"><option>Draft</option><option>Processed</option><option>Paid</option><option>Hold</option></select>
  </label>
  <label>Payment Date <input type="date" name="payment_date"></label>
  <label>Notes <textarea name="notes"></textarea></label>
  <button class="btn-compact">Save Manual Payroll</button>
</form>

<div class="table-scroll">
<table>
  <tr><th>Employee</th><th>Department</th><th>Hours</th><th>Days</th><th>Rate/Hr</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net</th><th>Status</th><th>Payment</th><th>Actions</th></tr>
  <?php foreach ($items as $p): ?>
  <tr>
    <td><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></td>
    <td><?= htmlspecialchars((string)$p['department']) ?></td>
    <td><?= number_format((float)($p['work_hours'] ?? 0), 2) ?></td>
    <td><?= number_format((float)($p['payable_days'] ?? 0), 2) ?></td>
    <td><?= number_format((float)($p['hourly_rate'] ?? 0), 2) ?></td>
    <td><?= number_format((float)$p['basic_salary'], 2) ?></td>
    <td><?= number_format((float)$p['allowances'], 2) ?></td>
    <td><?= number_format((float)$p['deductions'], 2) ?></td>
    <td><strong><?= number_format((float)$p['net_salary'], 2) ?></strong></td>
    <td><span class="status-badge status-<?= strtolower($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span></td>
    <td><?= htmlspecialchars((string)$p['payment_date']) ?></td>
    <td>
      <form method="post" action="?route=payroll.status" class="leave-status-form">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
        <select name="status"><option>Draft</option><option>Processed</option><option>Paid</option><option>Hold</option></select>
        <input type="date" name="payment_date" value="<?= htmlspecialchars((string)$p['payment_date']) ?>">
        <button class="btn-compact">Update</button>
      </form>
      <form method="post" action="?route=payroll.delete" onsubmit="return confirm('Delete payroll row?')">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
        <button class="btn-compact btn-danger">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
