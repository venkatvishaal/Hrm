<?php require __DIR__ . '/../layouts/header.php'; ?>
<h1>Update Leave Request</h1>

<form method="post" action="?route=leave.update" class="card grid">
  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
  <input type="number" name="employee_id" value="<?= (int)$item['employee_id'] ?>" required>
  <select name="leave_type" required>
    <option <?= $item['leave_type']==='Sick Leave'?'selected':'' ?>>Sick Leave</option>
    <option <?= $item['leave_type']==='Casual Leave'?'selected':'' ?>>Casual Leave</option>
    <option <?= $item['leave_type']==='Annual Leave'?'selected':'' ?>>Annual Leave</option>
    <option <?= $item['leave_type']==='Maternity Leave'?'selected':'' ?>>Maternity Leave</option>
    <option <?= $item['leave_type']==='Paternity Leave'?'selected':'' ?>>Paternity Leave</option>
    <option <?= $item['leave_type']==='Emergency Leave'?'selected':'' ?>>Emergency Leave</option>
    <option <?= $item['leave_type']==='Unpaid Leave'?'selected':'' ?>>Unpaid Leave</option>
    <option <?= $item['leave_type']==='Compensatory Off'?'selected':'' ?>>Compensatory Off</option>
  </select>
  <input type="date" name="start_date" value="<?= htmlspecialchars((string)$item['start_date']) ?>" required>
  <input type="date" name="end_date" value="<?= htmlspecialchars((string)$item['end_date']) ?>" required>
  <input name="reason" value="<?= htmlspecialchars((string)$item['reason']) ?>" placeholder="Reason">
  <select name="status">
    <option <?= $item['status']==='Approved'?'selected':'' ?>>Approved</option>
    <option <?= $item['status']==='Rejected'?'selected':'' ?>>Rejected</option>
    <option <?= $item['status']==='Pending'?'selected':'' ?>>Pending</option>
  </select>
  <button>Save Changes</button>
</form>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
