<?php require __DIR__ . '/../layouts/header.php'; ?>

<form method="get" class="card grid">
  <input type="hidden" name="route" value="employee-availed">
  <label>Year
    <select name="year">
      <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 5; $y--): ?>
        <option value="<?= $y ?>" <?= $y === (int)$year ? 'selected' : '' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <label>Month
    <select name="month">
      <option value="0" <?= (int)$month === 0 ? 'selected' : '' ?>>All Months</option>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m === (int)$month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
      <?php endfor; ?>
    </select>
  </label>
  <button>Load History</button>
</form>

<h2>Month/Year Summary</h2>
<table>
  <tr><th>Month/Year</th><th>Requests</th><th>Total Days Availed</th></tr>
  <?php foreach (($summary ?? []) as $s): ?>
    <tr>
      <td><?= htmlspecialchars(date('m/Y', strtotime($s['ym'] . '-01'))) ?></td>
      <td><?= (int)$s['total_requests'] ?></td>
      <td><?= (int)$s['total_days'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<h2>Detailed Leave Records</h2>
<table>
  <tr><th>ID</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Reason</th></tr>
  <?php foreach (($items ?? []) as $it): ?>
    <tr>
      <td><?= (int)$it['id'] ?></td>
      <td><?= htmlspecialchars($it['leave_type']) ?></td>
      <td><?= htmlspecialchars(fmt_date($it['start_date'])) ?></td>
      <td><?= htmlspecialchars(fmt_date($it['end_date'])) ?></td>
      <td><?= (int)$it['days_count'] ?></td>
      <td><span class="status-badge status-<?= strtolower((string)$it['status']) ?>"><?= htmlspecialchars((string)$it['status']) ?></span></td>
      <td><?= htmlspecialchars((string)$it['reason']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
