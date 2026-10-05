<?php require __DIR__ . '/../layouts/header.php'; ?>
<h1>Annual Health Checkups</h1>

<div class="stats">
  <div class="card"><strong>Total Reports</strong><br><?= (int)($summary['total'] ?? 0) ?></div>
  <div class="card status-rejected"><strong>Overdue</strong><br><?= (int)($summary['overdue'] ?? 0) ?></div>
  <div class="card status-pending"><strong>Due 30 Days</strong><br><?= (int)($summary['due_soon'] ?? 0) ?></div>
</div>

<h2>Upload Health Report</h2>
<form method="post" action="?route=health-checkups.store" enctype="multipart/form-data" class="card grid">
  <select name="employee_id" required>
    <option value="">Select Employee</option>
    <?php foreach (($employees ?? []) as $emp): ?>
      <option value="<?= (int)$emp['id'] ?>">
        <?= htmlspecialchars(trim($emp['first_name'] . ' ' . $emp['last_name'])) ?> <?= htmlspecialchars((string)$emp['employee_code']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="checkup_date" required>
  <input name="hospital_name" placeholder="Hospital Name" required>
  <input type="date" name="next_due_date">
  <input type="file" name="report_file" accept="application/pdf,image/png,image/jpeg,image/webp,.pdf,.png,.jpg,.jpeg,.webp" data-optimize-image="document">
  <textarea name="remarks" placeholder="Remarks"></textarea>
  <button>Save Report</button>
</form>

<form method="get" class="card grid">
  <input type="hidden" name="route" value="health-checkups">
  <select name="employee_id">
    <option value="0">All Employees</option>
    <?php foreach (($employees ?? []) as $emp): ?>
      <option value="<?= (int)$emp['id'] ?>" <?= (int)$employeeId === (int)$emp['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars(trim($emp['first_name'] . ' ' . $emp['last_name'])) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button>Filter</button>
</form>

<div class="table-scroll">
<table>
  <tr><th>Employee</th><th>Department</th><th>Checkup Date</th><th>Hospital</th><th>Next Due</th><th>Remarks</th><th>Report</th></tr>
  <?php foreach (($items ?? []) as $item): ?>
    <tr>
      <td><?= htmlspecialchars(trim((string)$item['first_name'] . ' ' . (string)$item['last_name'])) ?><br><span class="muted"><?= htmlspecialchars((string)$item['employee_code']) ?></span></td>
      <td><?= htmlspecialchars((string)$item['department']) ?></td>
      <td><?= fmt_date($item['checkup_date']) ?></td>
      <td><?= htmlspecialchars((string)$item['hospital_name']) ?></td>
      <td><?= fmt_date($item['next_due_date']) ?></td>
      <td><?= htmlspecialchars((string)$item['remarks']) ?></td>
      <td>
        <?php if (!empty($item['report_file_path'])): ?>
          <a href="?route=health-checkups.download&id=<?= (int)$item['id'] ?>">Download</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
