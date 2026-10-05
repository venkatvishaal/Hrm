<?php require __DIR__ . '/../layouts/header.php'; ?>

<form method="post" action="?route=employee.leave.store" class="card grid" enctype="multipart/form-data">
  <select name="leave_type" required>
    <option value="">Select Leave Type</option>
    <option>Sick Leave</option><option>Casual Leave</option><option>Annual Leave</option><option>Maternity Leave</option>
    <option>Paternity Leave</option><option>Emergency Leave</option><option>Unpaid Leave</option>
  </select>
  <input type="date" name="start_date" required>
  <input type="date" name="end_date" required>
  <label>Medical Certificate
    <input type="file" name="medical_certificate" accept="application/pdf,image/png,image/jpeg,image/webp,.pdf,.png,.jpg,.jpeg,.webp" data-optimize-image="document">
  </label>
  <textarea name="reason" placeholder="Reason"></textarea>
  <button>Apply Leave</button>
  <small class="muted">Medical/Sick leave longer than <?= (int)($leaveStandards['medical_certificate_after_days'] ?? 3) ?> days requires certificate upload.</small>
</form>

<h2>Leave Status</h2>
<table>
<tr><th>ID</th><th>Type</th><th>From</th><th>To</th><th>Status</th><th>Reason</th></tr>
<?php foreach (($myLeaves ?? []) as $lv): ?>
<tr>
<td><?= $lv['id'] ?></td>
<td><?= htmlspecialchars($lv['leave_type']) ?></td>
<td><?= htmlspecialchars(fmt_date($lv['start_date'])) ?></td>
<td><?= htmlspecialchars(fmt_date($lv['end_date'])) ?></td>
<td><span class="status-badge status-<?= strtolower($lv['status']) ?>"><?= htmlspecialchars($lv['status']) ?></span></td>
<td><?= htmlspecialchars((string)$lv['reason']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
