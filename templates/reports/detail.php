<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/reports_nav.php'; ?>
<?php
$rangeQuery = '&from=' . urlencode($from) . '&to=' . urlencode($to);
$employeeQuery = $employeeId > 0 ? '&employee_id=' . urlencode((string)$employeeId) : '';
$downloadQuery = 'type=' . urlencode($selectedReportKey) . $rangeQuery . $employeeQuery;
?>
<section class="reports-page report-detail-page">
  <div class="report-detail-topbar">
    <a class="btn-link" href="?route=reports<?= htmlspecialchars($rangeQuery) ?>">Back To Reports</a>
    <form method="get" class="reports-toolbar">
      <input type="hidden" name="route" value="report-detail">
      <input type="hidden" name="report" value="<?= htmlspecialchars($selectedReportKey) ?>">
      <label>FROM <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
      <label>TO <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
      <?php if ($selectedReportKey === 'employee_attendance'): ?>
        <label>EMPLOYEE
          <select name="employee_id">
            <option value="0">All Employees</option>
            <?php foreach ($employees as $employee): ?>
              <option value="<?= (int)$employee['id'] ?>" <?= $employeeId === (int)$employee['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name']) . ' - ' . (string)$employee['employee_code']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <button class="reports-apply">Apply</button>
    </form>
  </div>

  <div class="card report-detail-card">
    <div class="report-hospital-header">
      <h2><?= htmlspecialchars($reportHeader['hospital_name']) ?></h2>
      <p><?= htmlspecialchars($reportHeader['hospital_address']) ?></p>
      <p><?= htmlspecialchars($reportHeader['hospital_location']) ?></p>
    </div>
    <div class="report-detail-head">
      <div>
        <h2><?= htmlspecialchars($selectedReport['title']) ?></h2>
        <p><?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?> &middot; <?= count($reportRows) ?> rows</p>
      </div>
      <div class="report-downloads">
        <a class="btn-link" href="?route=report-export&format=xlsx&<?= htmlspecialchars($downloadQuery) ?>">Download XLSX</a>
        <a class="btn-link" href="?route=report-export&format=pdf&orientation=portrait&<?= htmlspecialchars($downloadQuery) ?>">PDF A4 Portrait</a>
        <a class="btn-link" href="?route=report-export&format=pdf&orientation=landscape&<?= htmlspecialchars($downloadQuery) ?>">PDF A4 Landscape</a>
      </div>
    </div>
    <div class="table-scroll report-detail-scroll">
      <table class="report-detail-table">
        <tr>
          <?php foreach ($selectedReport['columns'] as $heading): ?>
            <th><?= htmlspecialchars($heading) ?></th>
          <?php endforeach; ?>
        </tr>
        <?php if (!$reportRows): ?>
          <tr><td colspan="<?= count($selectedReport['columns']) ?>">No records found.</td></tr>
        <?php endif; ?>
        <?php foreach ($reportRows as $row): ?>
          <tr>
            <?php foreach (array_keys($selectedReport['columns']) as $column): ?>
              <td><?= htmlspecialchars((string)($row[$column] ?? '')) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
