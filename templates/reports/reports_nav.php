<?php
$reportsNavRole = $_SESSION['user']['role'] ?? '';
$reportsNavRoute = $_GET['route'] ?? 'reports';
$reportsNavActiveRoute = in_array($reportsNavRoute, ['report-detail', 'report-export'], true) ? 'reports' : $reportsNavRoute;
$reportsNavItems = [];

if (in_array($reportsNavRole, ['Admin', 'SuperAdmin'], true)) {
    $reportsNavItems = [
        ['reports', 'Reports', '?route=reports'],
        ['performance', 'HR Analytics', '?route=performance'],
        ['performance-leaderboard', 'KPI Leaderboard', '?route=performance-leaderboard'],
        ['exit', 'Exit Records', '?route=exit'],
        ['audit', 'Audit Trail', '?route=audit'],
    ];
} elseif ($reportsNavRole === 'Manager') {
    $reportsNavItems = [
        ['reports', 'Reports', '?route=reports'],
        ['performance', 'HR Analytics', '?route=performance'],
        ['performance-leaderboard', 'KPI Leaderboard', '?route=performance-leaderboard'],
        ['exit', 'Exit Records', '?route=exit'],
    ];
} elseif ($reportsNavRole === 'HR') {
    $reportsNavItems = [
        ['reports', 'Reports', '?route=reports'],
    ];
}
?>
<?php if ($reportsNavItems): ?>
<nav class="settings-submenu reports-submenu" aria-label="Reports modules">
  <?php foreach ($reportsNavItems as [$route, $label, $href]): ?>
    <a<?= $reportsNavActiveRoute === $route ? ' class="active"' : '' ?> href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
