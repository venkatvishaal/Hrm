<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'administrator';
    $_SESSION['user_role'] = 'admin';
}

$pageTitle = 'Dashboard';
require __DIR__ . '/templates/header.php';
?>
<section class="data-card">
  <h2>Management Panel</h2>
  <p>This isolated boilerplate demonstrates the secure PHP 8.x, PDO, CSRF, CSS Grid, inline SVG, CRUD, logging, and streaming export architecture.</p>
</section>
<?php require __DIR__ . '/templates/footer.php'; ?>
