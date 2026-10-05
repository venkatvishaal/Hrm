<?php require __DIR__ . '/../layouts/header.php'; ?>
<form method="get" class="card grid">
  <input type="hidden" name="route" value="allowances">
  <label>Effective Month <input type="month" name="month" value="<?= htmlspecialchars($month) ?>"></label>
  <button class="btn-compact">Load Month</button>
  <a class="btn-link" href="?route=payroll&month=<?= urlencode($month) ?>">Back To Salary Processing</a>
</form>

<form method="post" action="?route=allowances.store" class="card grid">
  <label>Employee
    <select name="employee_id" required>
      <option value="">Select employee</option>
      <?php foreach ($employees as $e): ?>
      <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['first_name'].' '.$e['last_name'].' - '.$e['department']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Type <select name="entry_type"><option>Allowance</option><option>Deduction</option></select></label>
  <label>Title <input name="title" required></label>
  <label>Amount <input type="number" step="0.01" name="amount" required></label>
  <label>Month <input type="month" name="effective_month" value="<?= htmlspecialchars($month) ?>" required></label>
  <label><input type="checkbox" name="is_recurring" value="1"> Recurring</label>
  <label>Notes <textarea name="notes"></textarea></label>
  <button>Save Entry</button>
</form>

<table>
  <tr><th>Employee</th><th>Type</th><th>Title</th><th>Amount</th><th>Month</th><th>Recurring</th><th>Actions</th></tr>
  <?php foreach ($items as $item): ?>
  <tr>
    <td><?= htmlspecialchars($item['first_name'].' '.$item['last_name']) ?></td>
    <td><?= htmlspecialchars($item['entry_type']) ?></td>
    <td><?= htmlspecialchars($item['title']) ?></td>
    <td><?= number_format((float)$item['amount'], 2) ?></td>
    <td><?= htmlspecialchars($item['effective_month']) ?></td>
    <td><?= $item['is_recurring'] ? 'Yes' : 'No' ?></td>
    <td><form method="post" action="?route=allowances.delete" onsubmit="return confirm('Delete this entry?')"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>"><button class="btn-compact btn-danger">Delete</button></form></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
