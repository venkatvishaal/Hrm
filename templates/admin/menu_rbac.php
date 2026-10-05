<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/settings_nav.php'; ?>

<div class="card">
  <strong>Sidebar RBAC</strong>
  <p>Control which sidebar menus are visible for each role. Route-level permissions are still enforced by the backend.</p>
</div>

<form method="post" action="?route=menu-rbac.save" class="card">
  <table class="rbac-table">
    <tr>
      <th>Section</th>
      <th>Menu</th>
      <th>Route</th>
      <?php foreach ($roles as $role): ?>
      <th><?= htmlspecialchars($role) ?></th>
      <?php endforeach; ?>
    </tr>
    <?php foreach ($menus as $menu): ?>
    <tr>
      <td><?= htmlspecialchars($menu['section_name']) ?></td>
      <td><strong><?= htmlspecialchars($menu['label']) ?></strong></td>
      <td><code><?= htmlspecialchars($menu['route']) ?></code></td>
      <?php foreach ($roles as $role): ?>
      <td class="rbac-check">
        <input type="checkbox" name="visible[<?= htmlspecialchars($menu['menu_key']) ?>][]" value="<?= htmlspecialchars($role) ?>"<?= !empty($menu['roles'][$role]) ? ' checked' : '' ?>>
      </td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="form-actions">
    <button>Save Menu RBAC</button>
  </div>
</form>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
