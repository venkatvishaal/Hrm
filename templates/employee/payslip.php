<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$grossSalary = (float)$payslip['basic_salary'] + (float)$payslip['allowances'];
$netSalary = (float)$payslip['net_salary'];
?>
<section class="card">
  <div class="training-head no-print">
    <h2>Payslip</h2>
    <button class="btn-compact" onclick="window.print()">Print</button>
  </div>
  <div class="payslip-head">
    <div>
      <h2>Hospital HR</h2>
      <p>Salary Slip for <?= htmlspecialchars((string)$payslip['payroll_month']) ?></p>
    </div>
    <div>
      <strong><?= htmlspecialchars($payslip['status']) ?></strong><br>
      <span><?= htmlspecialchars((string)$payslip['payment_date']) ?></span>
    </div>
  </div>
  <table>
    <tr><th>Employee Code</th><td><?= htmlspecialchars((string)$payslip['employee_code']) ?></td><th>Name</th><td><?= htmlspecialchars($payslip['first_name'].' '.$payslip['last_name']) ?></td></tr>
    <tr><th>Department</th><td><?= htmlspecialchars((string)$payslip['department']) ?></td><th>Position</th><td><?= htmlspecialchars((string)$payslip['position']) ?></td></tr>
    <tr><th>Location</th><td><?= htmlspecialchars((string)$payslip['location']) ?></td><th>Email</th><td><?= htmlspecialchars((string)$payslip['email']) ?></td></tr>
    <tr><th>Work Hours</th><td><?= number_format((float)($payslip['work_hours'] ?? 0), 2) ?></td><th>Payable Days</th><td><?= number_format((float)($payslip['payable_days'] ?? 0), 2) ?></td></tr>
    <tr><th>Hourly Rate</th><td><?= number_format((float)($payslip['hourly_rate'] ?? 0), 2) ?></td><th></th><td></td></tr>
  </table>
  <table>
    <tr><th>Earnings</th><th>Amount</th><th>Deductions</th><th>Amount</th></tr>
    <tr><td>Attendance Wages</td><td><?= number_format((float)$payslip['basic_salary'], 2) ?></td><td>Deductions</td><td><?= number_format((float)$payslip['deductions'], 2) ?></td></tr>
    <tr><td>Allowances</td><td><?= number_format((float)$payslip['allowances'], 2) ?></td><td></td><td></td></tr>
    <tr><th>Gross Salary</th><th><?= number_format($grossSalary, 2) ?></th><th>Net Salary</th><th><?= number_format($netSalary, 2) ?></th></tr>
  </table>
  <?php if (!empty($payslip['notes'])): ?>
    <p><strong>Notes:</strong> <?= htmlspecialchars((string)$payslip['notes']) ?></p>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
