<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/settings_nav.php'; ?>

<?php if ($selectedDesignation !== ''): ?>
<div class="card">
  <div class="section-head">
    <div>
      <h2><?= htmlspecialchars($selectedDesignation) ?> Employees</h2>
    </div>
    <a class="btn-link" href="?route=designations">Clear</a>
  </div>
  <?php if (!$designationEmployees): ?>
    <p>No employees found for this designation.</p>
  <?php else: ?>
    <div class="employee-mini-grid">
      <?php foreach ($designationEmployees as $emp): ?>
      <div class="employee-mini-card">
        <strong><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></strong>
        <span><?= htmlspecialchars((string)$emp['department']) ?></span>
        <small><?= htmlspecialchars((string)$emp['employee_code']) ?> <?= htmlspecialchars((string)$emp['location']) ?></small>
        <small><?= htmlspecialchars($emp['email']) ?> | <?= htmlspecialchars($emp['role']) ?></small>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" action="?route=designations.store" class="card grid">
  <label>Designation <input name="title" placeholder="Doctor, DMO, Staff Nurse, Receptionist" required></label>
  <label>Department
    <select name="department_name">
      <option value="">Any</option>
      <?php foreach ($departments as $d): ?>
        <option><?= htmlspecialchars($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Grade <input name="grade"></label>
  <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
  <button>Save Designation</button>
</form>

<?php foreach ($items as $item): ?>
  <form id="designation-edit-<?= (int)$item['id'] ?>" method="post" action="?route=designations.store"></form>
<?php endforeach; ?>

<table>
  <tr><th>Title</th><th>Employees</th><th>Department</th><th>Grade</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($items as $item): ?>
  <tr>
    <td>
      <input type="hidden" name="id" value="<?= (int)$item['id'] ?>" form="designation-edit-<?= (int)$item['id'] ?>">
      <input name="title" value="<?= htmlspecialchars($item['title']) ?>" required form="designation-edit-<?= (int)$item['id'] ?>">
    </td>
    <td><a href="?route=designations&designation=<?= urlencode($item['title']) ?>"><?= (int)$item['employee_count'] ?></a></td>
    <td>
      <select name="department_name" form="designation-edit-<?= (int)$item['id'] ?>">
        <option value="">Any</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= htmlspecialchars($d['name']) ?>" <?= (($item['department_name'] ?? '') === $d['name']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input name="grade" value="<?= htmlspecialchars((string)$item['grade']) ?>" form="designation-edit-<?= (int)$item['id'] ?>"></td>
    <td><label class="inline-check"><input type="checkbox" name="is_active" value="1" <?= $item['is_active'] ? 'checked' : '' ?> form="designation-edit-<?= (int)$item['id'] ?>"> Active</label></td>
    <td>
      <div class="department-actions">
        <button type="submit" class="btn-compact" form="designation-edit-<?= (int)$item['id'] ?>">Save</button>
        <form method="post" action="?route=designations.delete" onsubmit="return confirm('Delete designation?')">
          <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
          <button type="submit" class="btn-compact btn-danger">Delete</button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
