<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/settings_nav.php'; ?>

<section id="leave-permission-standard" class="card standard-settings-card">
  <div class="settings-module-head">
    <div>
      <h2>Leave/Permission Standard Calculation</h2>
      <p class="muted">Global rules used by leave balance, permission, attendance, and payroll calculations.</p>
    </div>
    <span class="status-badge status-approved">Global Apply</span>
  </div>
  <form method="post" action="?route=settings.leave-permission.store" class="standard-settings-grid">
    <label>Allowed Casual Leaves
      <input type="number" min="0" max="365" step="1" name="leave_casual_annual_entitlement" value="<?= htmlspecialchars((string)$leaveStandards['leave_casual_annual_entitlement']) ?>">
    </label>
    <label>Medical/Sick Leave
      <input type="number" min="0" max="365" step="1" name="leave_medical_annual_entitlement" value="<?= htmlspecialchars((string)$leaveStandards['leave_medical_annual_entitlement']) ?>">
    </label>
    <label>Certificate Required After Days
      <input type="number" min="1" max="30" step="1" name="medical_certificate_after_days" value="<?= htmlspecialchars((string)$leaveStandards['medical_certificate_after_days']) ?>">
    </label>
    <label>Grace Limit Minutes
      <input type="number" min="0" max="180" step="1" name="grace_limit_minutes" value="<?= htmlspecialchars((string)$leaveStandards['grace_limit_minutes']) ?>">
    </label>
    <label>Allowed Late Days
      <input type="number" min="0" max="31" step="1" name="allowed_late_days" value="<?= htmlspecialchars((string)$leaveStandards['allowed_late_days']) ?>">
    </label>
    <label>Late-to-Leave Ratio
      <input type="number" min="1" max="31" step="1" name="late_to_leave_ratio" value="<?= htmlspecialchars((string)$leaveStandards['late_to_leave_ratio']) ?>">
    </label>
    <label>Penalty Days Per Ratio
      <input type="number" min="0" max="2" step="0.5" name="late_penalty_days" value="<?= htmlspecialchars((string)$leaveStandards['late_penalty_days']) ?>">
    </label>
    <label>Monthly Permission Quota Hours
      <input type="number" min="0" max="744" step="0.5" name="monthly_permission_quota_hours" value="<?= htmlspecialchars((string)$leaveStandards['monthly_permission_quota_hours']) ?>">
    </label>
    <label>Standard Shift Hours
      <input type="number" min="1" max="24" step="0.5" name="standard_shift_hours" value="<?= htmlspecialchars((string)$leaveStandards['standard_shift_hours']) ?>">
    </label>
    <label>Half-Day Minimum Percent
      <input type="number" min="1" max="100" step="1" name="half_day_min_percent" value="<?= htmlspecialchars((string)$leaveStandards['half_day_min_percent']) ?>">
    </label>
    <label>Payroll Fixed Days
      <input type="number" min="1" max="31" step="1" name="payroll_fixed_days" value="<?= htmlspecialchars((string)$leaveStandards['payroll_fixed_days']) ?>">
    </label>
    <button class="settings-save-button">Save Standards</button>
    <details class="standard-formula-panel settings-formulas">
      <summary>Calculation formulas</summary>
      <div>
        <span>Available Balance = Opening + Accrued - Availed - Encashed</span>
        <span>Monthly Accrual = Annual Entitlement / 12</span>
        <span>Late Penalty = floor((Late Days - Allowed Days) / Ratio)</span>
        <span>LOP Deduction = LOP Days x Per Day Rate</span>
      </div>
    </details>
  </form>
</section>

<details class="settings-advanced">
  <summary>Advanced configuration</summary>
  <form method="post" action="?route=settings.store" class="card grid">
    <label>New Key <input name="setting_key" required></label>
    <label>Value <textarea name="setting_value"></textarea></label>
    <button class="btn-compact">Add Setting</button>
  </form>

  <div class="settings-list">
    <?php foreach ($items as $item): ?>
    <form method="post" action="?route=settings.store" class="card settings-row">
      <label>Key
        <input name="setting_key" value="<?= htmlspecialchars($item['setting_key']) ?>" readonly>
      </label>
      <label>Value
        <textarea name="setting_value"><?= htmlspecialchars((string)$item['setting_value']) ?></textarea>
      </label>
      <div class="settings-meta">
        <span>Updated <?= htmlspecialchars(fmt_datetime($item['updated_at'])) ?></span>
        <button class="btn-compact">Update</button>
      </div>
    </form>
    <?php endforeach; ?>
  </div>
</details>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
