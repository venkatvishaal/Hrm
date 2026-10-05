<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$employeeName = trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']);
$employeeCode = (string)($employee['employee_code'] ?: ('EMP-' . str_pad((string)$employee['id'], 4, '0', STR_PAD_LEFT)));
$certificateTitle = $type === 'salary' ? 'Salary Certificate' : 'To Whom It May Concern';
$salaryAmount = $latestPayslip ? (float)$latestPayslip['net_salary'] : (float)($employee['salary'] ?? 0);
?>
<section class="certificate-paper card">
  <div class="training-head no-print">
    <h2><?= htmlspecialchars($certificateTitle) ?></h2>
    <button class="btn-compact" onclick="window.print()">Print</button>
  </div>
  <div class="certificate-letter">
    <div class="certificate-letter-head">
      <h2>Hospital HR</h2>
      <p>Workforce System</p>
    </div>
    <h1><?= htmlspecialchars($certificateTitle) ?></h1>
    <p class="certificate-date">Date: <?= date('d M Y') ?></p>

    <?php if ($type === 'salary'): ?>
      <p>This is to certify that <strong><?= htmlspecialchars($employeeName) ?></strong>, Employee Code <strong><?= htmlspecialchars($employeeCode) ?></strong>, is employed with Hospital HR as <strong><?= htmlspecialchars((string)$employee['position']) ?></strong> in the <strong><?= htmlspecialchars((string)$employee['department']) ?></strong> department.</p>
      <p>As per the current payroll records, the employee's latest net salary is <strong><?= number_format($salaryAmount, 2) ?></strong><?php if (!empty($latestPayslip['payroll_month'])): ?> for payroll month <strong><?= htmlspecialchars((string)$latestPayslip['payroll_month']) ?></strong><?php endif; ?>.</p>
      <p>This certificate is issued upon the employee's request for official purposes.</p>
    <?php else: ?>
      <p>This is to certify that <strong><?= htmlspecialchars($employeeName) ?></strong>, Employee Code <strong><?= htmlspecialchars($employeeCode) ?></strong>, is associated with Hospital HR as <strong><?= htmlspecialchars((string)$employee['position']) ?></strong> in the <strong><?= htmlspecialchars((string)$employee['department']) ?></strong> department.</p>
      <p>The employee joined on <strong><?= htmlspecialchars(fmt_date($employee['join_date'])) ?></strong><?php if (!empty($employee['employment_type'])): ?> and is recorded as <strong><?= htmlspecialchars((string)$employee['employment_type']) ?></strong><?php endif; ?>.</p>
      <p>This letter is issued to whom it may concern for verification and official use.</p>
    <?php endif; ?>

    <div class="certificate-signature">
      <span>Authorized Signatory</span>
      <strong>Human Resources</strong>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
