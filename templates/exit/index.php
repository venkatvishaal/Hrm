<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/../reports/reports_nav.php'; ?>
<h1>Exit Records</h1>
<a href="?route=export&type=exit">Export CSV</a>

<form method="post" action="?route=exit.store" class="card grid">
  <?= csrf_field() ?>
  <input type="number" name="employee_id" placeholder="Employee ID" required>
  <input type="date" name="exit_date" required>
  <input name="reason" placeholder="Exit Reason" required>
  <textarea name="remarks" placeholder="Remarks"></textarea>
  <button>Save Exit Record</button>
</form>

<div class="table-scroll">
  <table>
    <tr><th>ID</th><th>Date</th><th>Employee</th><th>Reason</th><th>Remarks</th><th>Clearance Checklist</th><th>Actions</th></tr>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><?= (int)$it['id'] ?></td>
        <td><?= htmlspecialchars(fmt_date($it['exit_date'])) ?></td>
        <td><?= htmlspecialchars($it['first_name'].' '.$it['last_name']) ?></td>
        <td><?= htmlspecialchars($it['reason']) ?></td>
        <td><?= htmlspecialchars((string)$it['remarks']) ?></td>
        <td>
          <div class="exit-clearance-list">
            <?php foreach (($clearanceByExit[(int)$it['id']] ?? []) as $clearance): ?>
              <form method="post" action="?route=exit.clearance.update" class="exit-clearance-item">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$clearance['id'] ?>">
                <strong><?= htmlspecialchars((string)$clearance['clearance_area']) ?></strong>
                <select name="status">
                  <?php foreach (['Pending','Cleared','Hold'] as $statusOption): ?>
                    <option value="<?= $statusOption ?>" <?= $clearance['status'] === $statusOption ? 'selected' : '' ?>><?= $statusOption ?></option>
                  <?php endforeach; ?>
                </select>
                <input name="remarks" value="<?= htmlspecialchars((string)($clearance['remarks'] ?? '')) ?>" placeholder="Remarks">
                <button class="btn-compact">Save</button>
              </form>
            <?php endforeach; ?>
          </div>
        </td>
        <td>
          <?php if (($_SESSION['user']['role'] ?? '') === 'Admin'): ?>
            <form method="post" action="?route=exit.update" class="grid">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <input type="number" name="employee_id" value="<?= (int)$it['employee_id'] ?>" required>
              <input type="date" name="exit_date" value="<?= htmlspecialchars($it['exit_date']) ?>" required>
              <input name="reason" value="<?= htmlspecialchars($it['reason']) ?>" required>
              <input name="remarks" value="<?= htmlspecialchars((string)$it['remarks']) ?>">
              <button>Update</button>
            </form>
            <form method="post" action="?route=exit.delete" onsubmit="return confirm('Delete exit record?')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button class="btn-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<canvas id="exitChart"></canvas>
<script>
const ex = <?= json_encode($items) ?>;
const reasonMap = {};
ex.forEach(function(row){
  reasonMap[row.reason] = (reasonMap[row.reason] || 0) + 1;
});
new Chart(document.getElementById('exitChart'), {
  type: 'pie',
  data: {
    labels: Object.keys(reasonMap),
    datasets: [{data: Object.values(reasonMap), backgroundColor: ['#3182ce','#e53e3e','#38a169','#d69e2e','#805ad5']}]
  }
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
