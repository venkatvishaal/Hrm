<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
$pageTitle = $pageTitle ?? 'Management Panel';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="public/css/variables.css">
  <link rel="stylesheet" href="public/css/base.css">
  <link rel="stylesheet" href="public/css/layout.css">
  <link rel="stylesheet" href="public/css/components.css">
</head>
<body>
<div class="app-shell">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <header class="app-header">
    <div>
      <h1><?= e($pageTitle) ?></h1>
      <p>Enterprise application workspace</p>
    </div>
    <div class="header-user">
      <span><?= e($_SESSION['username'] ?? 'Guest') ?></span>
      <span class="badge badge-success"><?= e($_SESSION['user_role'] ?? 'guest') ?></span>
    </div>
  </header>
  <main class="app-main">
