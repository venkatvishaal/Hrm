<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/settings_nav.php'; ?>

<?php if ($selectedDepartment !== ''): ?>
<div class="card">
  <div class="section-head">
    <div>
      <h2><?= htmlspecialchars($selectedDepartment) ?> Employees</h2>
    </div>
    <a class="btn-link" href="?route=departments">Clear</a>
  </div>
  <?php if (!$departmentEmployees): ?>
    <p>No employees found for this department.</p>
  <?php else: ?>
    <div class="employee-mini-grid">
      <?php foreach ($departmentEmployees as $emp): ?>
      <div class="employee-mini-card">
        <strong><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></strong>
        <span><?= htmlspecialchars((string)$emp['position']) ?></span>
        <small><?= htmlspecialchars((string)$emp['employee_code']) ?> <?= htmlspecialchars((string)$emp['location']) ?></small>
        <small><?= htmlspecialchars($emp['email']) ?> | <?= htmlspecialchars($emp['role']) ?></small>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" action="?route=departments.store" class="card grid">
  <label>Department Name <input name="name" required></label>
  <label>Head
    <select name="head_employee_id" data-head-select>
      <option value="">None</option>
      <?php foreach ($employees as $e): ?>
      <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Location <select name="location"><option value="">Any</option><option>KH</option><option>KCI</option><option>KNS</option><option>MANAGEMENT</option></select></label>
  <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
  <button>Save Department</button>
</form>

<?php foreach ($items as $item): ?>
  <form id="department-edit-<?= (int)$item['id'] ?>" method="post" action="?route=departments.store"></form>
<?php endforeach; ?>

<div class="card department-head-search-panel">
  <label>Search Employee for Head Selection
    <input type="search" class="department-head-search" placeholder="Search employee name" data-head-search>
  </label>
</div>

<table>
  <tr><th>Name</th><th>Employees</th><th>Head</th><th>Location</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($items as $item): ?>
  <tr>
    <td>
      <input type="hidden" name="id" value="<?= (int)$item['id'] ?>" form="department-edit-<?= (int)$item['id'] ?>">
      <input name="name" value="<?= htmlspecialchars($item['name']) ?>" required form="department-edit-<?= (int)$item['id'] ?>">
    </td>
    <td><?= (int)$item['employee_count'] ?></td>
    <td>
      <div class="department-head-control">
        <select name="head_employee_id" form="department-edit-<?= (int)$item['id'] ?>" data-head-select>
        <option value="">None</option>
        <?php foreach ($employees as $e): ?>
          <option value="<?= (int)$e['id'] ?>" <?= ((int)($item['head_employee_id'] ?? 0) === (int)$e['id']) ? 'selected' : '' ?>><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></option>
        <?php endforeach; ?>
        </select>
      </div>
    </td>
    <td>
      <select name="location" form="department-edit-<?= (int)$item['id'] ?>">
        <option value="">Any</option>
        <?php foreach (['KH','KCI','KNS','MANAGEMENT'] as $location): ?>
          <option value="<?= htmlspecialchars($location) ?>" <?= (($item['location'] ?? '') === $location) ? 'selected' : '' ?>><?= htmlspecialchars($location) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><label class="inline-check"><input type="checkbox" name="is_active" value="1" <?= $item['is_active'] ? 'checked' : '' ?> form="department-edit-<?= (int)$item['id'] ?>"> Active</label></td>
    <td>
      <div class="department-actions">
        <button type="submit" class="btn-compact" form="department-edit-<?= (int)$item['id'] ?>">Save</button>
        <form method="post" action="?route=departments.delete" onsubmit="return confirm('Delete department?')">
          <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
          <button type="submit" class="btn-compact btn-danger">Delete</button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<script>
const departmentHeadSearch = document.querySelector('[data-head-search]');
if (departmentHeadSearch) {
  const selects = Array.from(document.querySelectorAll('[data-head-select]')).map((select) => ({
    select,
    options: Array.from(select.options).map((option) => ({
      value: option.value,
      text: option.text,
      selected: option.selected
    }))
  }));
  departmentHeadSearch.addEventListener('input', () => {
    const query = departmentHeadSearch.value.trim().toLowerCase();
    selects.forEach(({select, options}) => {
      const selectedValue = select.value;
    select.innerHTML = '';
    options.forEach((item) => {
      if (item.value !== '' && query !== '' && !item.text.toLowerCase().includes(query)) {
        return;
      }
      const option = new Option(item.text, item.value, false, item.value === selectedValue);
      select.add(option);
    });
    if (![...select.options].some((option) => option.value === selectedValue)) {
      select.add(new Option(options.find((item) => item.value === selectedValue)?.text || 'Selected employee', selectedValue, false, true));
    }
    });
  });
}
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
