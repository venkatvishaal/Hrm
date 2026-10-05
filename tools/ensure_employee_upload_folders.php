<?php
require_once __DIR__ . '/../app/Core/Database.php';

$app = require __DIR__ . '/../config/app.php';
$dbConfig = require __DIR__ . '/../config/database.php';
$db = new App\Core\Database($dbConfig);

$root = rtrim((string)$app['upload_dir'], '/\\');
$employees = $db->fetchAll('SELECT id, employee_code FROM employees ORDER BY id');
$created = 0;

foreach ($employees as $employee) {
    $code = trim((string)($employee['employee_code'] ?? ''));
    if ($code === '') {
        $code = 'employee-' . (int)$employee['id'];
    }
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $code) ?: 'employee');
    $slug = trim($slug, '-') ?: 'employee';

    foreach (['photos', 'certificates'] as $folder) {
        $directory = $root . '/' . $slug . '/' . $folder;
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
            $created++;
        }
    }
}

echo 'employees=' . count($employees) . ' folders_created=' . $created . PHP_EOL;
