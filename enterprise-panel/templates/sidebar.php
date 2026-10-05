<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$navItems = [
    'dashboard.php' => [
        'Dashboard',
        'M3 13h8V3H3v10Zm10 8h8V3h-8v18ZM3 21h8v-6H3v6Zm10 0h8v-6h-8v6Z',
        ['admin', 'editor', 'user'],
    ],
    'users.php' => [
        'Users',
        'M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3ZM8 11c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5Z',
        ['admin'],
    ],
    'reports.php' => [
        'Reports',
        'M4 19h16v2H4v-2Zm2-8h3v6H6v-6Zm5-6h3v12h-3V5Zm5 3h3v9h-3V8Z',
        ['admin', 'editor'],
    ],
];

$activeFile = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
?>
<aside class="app-sidebar">
  <a class="brand" href="dashboard.php" aria-label="Management Panel Home">
    <span class="brand-mark">EP</span>
    <span>
      <strong>Enterprise Panel</strong>
      <small>PHP Management</small>
    </span>
  </a>
  <nav class="sidebar-nav" aria-label="Primary navigation">
    <?php foreach ($navItems as $url => [$label, $path, $roles]): ?>
      <?php if (!hasRole($roles)) { continue; } ?>
      <a class="nav-link <?= $activeFile === $url ? 'active' : '' ?>" href="<?= e($url) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="<?= e($path) ?>"></path>
        </svg>
        <span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
</aside>
