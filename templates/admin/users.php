<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php require __DIR__ . '/settings_nav.php'; ?>
<div class="card"><strong>RBAC only by Admin</strong><p>Update application roles without changing employee records.</p></div>
<table><tr><th>User</th><th>Email</th><th>Department</th><th>Position</th><th>Role</th><th>Action</th></tr>
<?php foreach ($items as $u): ?><tr>
  <td><?= htmlspecialchars($u['name']) ?></td><td><?= htmlspecialchars($u['email']) ?></td><td><?= htmlspecialchars((string)$u['department']) ?></td><td><?= htmlspecialchars((string)$u['position']) ?></td>
  <td><?= htmlspecialchars($u['role']) ?></td>
  <td><form method="post" action="?route=users.role" class="leave-status-form"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><select name="role"><?php foreach (['Admin','SuperAdmin','HR','HOD','Manager','Employee'] as $role): ?><option<?= $u['role']===$role?' selected':'' ?>><?= $role ?></option><?php endforeach; ?></select><button class="btn-compact">Save</button></form></td>
</tr><?php endforeach; ?></table>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
