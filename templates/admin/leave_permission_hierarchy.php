<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php $roles = ['Admin', 'SuperAdmin', 'HR', 'HOD', 'Manager']; ?>
<?php require __DIR__ . '/settings_nav.php'; ?>

<section class="card standard-settings-card approval-hierarchy-card">
  <div class="settings-module-head">
    <div>
      <h2>Leave &amp; Permission Approval Hierarchy</h2>
      <p class="muted">Global routing for leave and short-permission requests.</p>
    </div>
    <span class="status-badge status-approved">Global Apply</span>
  </div>

  <form method="post" action="?route=settings.leave-permission-hierarchy.store" class="approval-hierarchy-grid">
    <article>
      <div class="approval-level-head"><span>1</span><div><h3>Initial HR Review</h3><small>0-24 hrs | Warning: 20 hrs</small></div></div>
      <label>Approver roles
        <select name="level1_roles[]" multiple size="2">
          <?php foreach ($roles as $role): ?><option value="<?= $role ?>" <?= in_array($role, $hierarchy['level1_roles'], true) ? 'selected' : '' ?>><?= $role ?></option><?php endforeach; ?>
        </select>
      </label>
    </article>
    <article>
      <div class="approval-level-head"><span>2</span><div><h3>Operations &amp; HOD Approval</h3><small>24-48 hrs | Warning: 44 hrs</small></div></div>
      <label>Approver roles
        <select name="level2_roles[]" multiple size="2">
          <?php foreach ($roles as $role): ?><option value="<?= $role ?>" <?= in_array($role, $hierarchy['level2_roles'], true) ? 'selected' : '' ?>><?= $role ?></option><?php endforeach; ?>
        </select>
      </label>
    </article>
    <article>
      <div class="approval-level-head"><span>3</span><div><h3>Final HR Oversight</h3><small>48+ hrs | Immediate action</small></div></div>
      <label>Approver roles
        <select name="level3_roles[]" multiple size="2">
          <?php foreach ($roles as $role): ?><option value="<?= $role ?>" <?= in_array($role, $hierarchy['level3_roles'], true) ? 'selected' : '' ?>><?= $role ?></option><?php endforeach; ?>
        </select>
      </label>
    </article>
    <button class="approval-save-button">Save Approval Hierarchy</button>
    <details class="approval-notification-rules">
      <summary>Automated notification schedule</summary>
      <div><span>Submission: Level 1 notification</span><span>24 hrs: Escalated - Level 2</span><span>48 hrs: Critical Escalation - Level 3</span></div>
    </details>
  </form>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
