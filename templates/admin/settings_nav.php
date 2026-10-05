<?php
$settingsNavRole = $_SESSION['user']['role'] ?? '';
$settingsNavRoute = $_GET['route'] ?? 'settings';
$settingsNavItems = [];

if (in_array($settingsNavRole, ['Admin', 'SuperAdmin'], true)) {
    $settingsNavItems = [
        ['settings', 'Leave/Permission Standard Calculation', '?route=settings#leave-permission-standard'],
        ['settings.shifts', 'Shift Creation', '?route=settings.shifts'],
        ['settings.leave-permission-hierarchy', 'Leave & Permission Approval Hierarchy', '?route=settings.leave-permission-hierarchy'],
        ['departments', 'Departments', '?route=departments'],
        ['designations', 'Designations', '?route=designations'],
        ['users', 'Users & Roles', '?route=users'],
        ['menu-rbac', 'Menu RBAC', '?route=menu-rbac'],
    ];
} elseif ($settingsNavRole === 'HR') {
    $settingsNavItems = [
        ['departments', 'Departments', '?route=departments'],
        ['designations', 'Designations', '?route=designations'],
        ['menu-rbac', 'Menu RBAC', '?route=menu-rbac'],
    ];
} elseif ($settingsNavRole === 'Manager') {
    $settingsNavItems = [
        ['departments', 'Departments', '?route=departments'],
        ['designations', 'Designations', '?route=designations'],
    ];
}
?>
<?php if ($settingsNavItems): ?>
<nav class="settings-submenu" aria-label="Settings modules">
  <?php foreach ($settingsNavItems as [$route, $label, $href]): ?>
    <a<?= $settingsNavRoute === $route ? ' class="active"' : '' ?> href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
