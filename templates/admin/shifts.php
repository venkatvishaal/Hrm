<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/settings_nav.php'; ?>

<section class="card standard-settings-card shift-settings-card">
  <div class="settings-module-head">
    <div>
      <h2>Shift Creation</h2>
      <p class="muted">Global shift names and timings used by Duty Roster assignment and automation.</p>
    </div>
    <span class="status-badge status-approved">Global Setup</span>
  </div>

  <form method="post" action="?route=settings.shifts.store" class="standard-settings-grid shift-create-form">
    <?= csrf_field() ?>
    <label>Shift Name
      <input name="shift_name" placeholder="Morning" required>
    </label>
    <label>Start Time
      <input type="time" name="start_time" required>
    </label>
    <label>End Time
      <input type="time" name="end_time" required>
    </label>
    <label>Sort Order
      <input type="number" name="sort_order" value="50" min="0" step="1">
    </label>
    <label class="settings-check-field">
      <input type="checkbox" name="is_active" value="1" checked>
      Active
    </label>
    <button class="settings-save-button">Save Shift</button>
  </form>
</section>

<section class="card settings-module-card shift-list-card">
  <div class="settings-module-head">
    <div>
      <h2>Configured Shifts</h2>
      <p class="muted">Deactivate a shift to stop future assignment while keeping existing roster history.</p>
    </div>
  </div>
  <div class="table-scroll">
    <table>
      <tr><th>Shift</th><th>Start</th><th>End</th><th>Sort</th><th>Status</th><th>Update</th></tr>
      <?php if (empty($shifts)): ?>
        <tr><td colspan="6">No shifts configured.</td></tr>
      <?php endif; ?>
      <?php foreach ($shifts as $shift): ?>
        <tr>
          <td>
            <form id="shiftForm<?= (int)$shift['id'] ?>" method="post" action="?route=settings.shifts.store" class="shift-inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$shift['id'] ?>">
              <input name="shift_name" value="<?= htmlspecialchars((string)$shift['shift_name']) ?>" required>
            </form>
          </td>
          <td><input form="shiftForm<?= (int)$shift['id'] ?>" type="time" name="start_time" value="<?= htmlspecialchars(substr((string)$shift['start_time'], 0, 5)) ?>" required></td>
          <td><input form="shiftForm<?= (int)$shift['id'] ?>" type="time" name="end_time" value="<?= htmlspecialchars(substr((string)$shift['end_time'], 0, 5)) ?>" required></td>
          <td><input form="shiftForm<?= (int)$shift['id'] ?>" type="number" name="sort_order" value="<?= (int)$shift['sort_order'] ?>" min="0" step="1"></td>
          <td>
            <label class="settings-check-field shift-active-toggle">
              <input form="shiftForm<?= (int)$shift['id'] ?>" type="checkbox" name="is_active" value="1" <?= !empty($shift['is_active']) ? 'checked' : '' ?>>
              <?= !empty($shift['is_active']) ? 'Active' : 'Inactive' ?>
            </label>
          </td>
          <td class="employees-row-actions">
            <button form="shiftForm<?= (int)$shift['id'] ?>" class="btn-compact">Save</button>
            <?php if (!empty($shift['is_active'])): ?>
              <form method="post" action="?route=settings.shifts.delete" onsubmit="return confirm('Deactivate this shift? Existing roster rows will remain unchanged.')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$shift['id'] ?>">
                <button class="btn-compact btn-danger">Deactivate</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
