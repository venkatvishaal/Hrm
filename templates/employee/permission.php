<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$permissionTimeRange = static function (array $request): string {
  $start = substr((string)($request['permission_start_time'] ?? ''), 0, 5);
  $end = substr((string)($request['permission_end_time'] ?? ''), 0, 5);
  return trim($start . ' - ' . $end, ' -') ?: '-';
};
?>

<form method="post" action="?route=employee.leave.store" class="card grid">
  <input type="hidden" name="leave_type" value="Permission">
  <input type="date" name="start_date" id="permission_date" required>
  <input type="hidden" name="end_date" id="permission_end_date">
  <input type="time" name="permission_start_time" required>
  <input type="time" name="permission_end_time" required>
  <textarea name="reason" placeholder="Permission reason"></textarea>
  <button>Apply Permission</button>
</form>
<p class="muted">Permission rule: same day, 30 minutes to 2 hours, maximum 4 requests per month.</p>
<script>
  (function () {
    const start = document.getElementById('permission_date');
    const end = document.getElementById('permission_end_date');
    if (start && end) {
      const sync = () => { end.value = start.value; };
      start.addEventListener('change', sync);
      sync();
    }
  })();
</script>

<h2>Permission Status</h2>
<table>
<tr><th>ID</th><th>Date</th><th>Time</th><th>Status</th><th>Reason</th></tr>
<?php foreach (($myPermissions ?? []) as $p): ?>
<tr>
<td><?= $p['id'] ?></td>
<td><?= htmlspecialchars(fmt_date($p['start_date'])) ?></td>
<td class="permission-time-cell"><?= htmlspecialchars($permissionTimeRange($p)) ?></td>
<td><span class="status-badge status-<?= strtolower($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span></td>
<td><?= htmlspecialchars((string)$p['reason']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
