<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/reports_nav.php'; ?>
<?php $canManageAudit = in_array((string)($_SESSION['user']['role'] ?? ''), ['Admin', 'SuperAdmin'], true); ?>

<section class="audit-page">
  <div class="card audit-toolbar">
    <div>
      <h2>Audit Trail</h2>
      <p>Important logs are preserved when clearing the rest.</p>
    </div>
    <?php if ($canManageAudit): ?>
      <div class="audit-clear-actions">
        <form method="post" action="?route=audit.clear" onsubmit="return confirm('Clear all non-important audit logs? Important logs will stay.');">
          <?= csrf_field() ?>
          <input type="hidden" name="mode" value="rest">
          <button class="btn-compact" type="submit">Clear Rest</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div class="table-scroll audit-table-scroll">
    <table class="audit-table">
      <tr>
        <th>Time</th>
        <th>User</th>
        <th>Action</th>
        <th>Entity</th>
        <th>ID</th>
        <th>Details</th>
        <?php if ($canManageAudit): ?><th>Important</th><?php endif; ?>
      </tr>
      <?php foreach ($items as $item): ?>
        <?php $important = (int)($item['is_important'] ?? 0) === 1; ?>
        <tr class="<?= $important ? 'audit-important-row' : '' ?>">
          <td><?= htmlspecialchars(fmt_datetime($item['created_at'])) ?></td>
          <td><?= htmlspecialchars((string)$item['user_name']) ?></td>
          <td><?= htmlspecialchars($item['action']) ?></td>
          <td><?= htmlspecialchars($item['entity_type']) ?></td>
          <td><?= htmlspecialchars((string)$item['entity_id']) ?></td>
          <td><?= htmlspecialchars((string)$item['details']) ?></td>
          <?php if ($canManageAudit): ?>
            <td>
              <form method="post" action="?route=audit.important">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="important" value="<?= $important ? 0 : 1 ?>">
                <button class="btn-compact audit-important-toggle" type="submit"><?= $important ? 'Important' : 'Mark' ?></button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
