<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
requireRole('admin');

$tracking = $_GET['tracking'] ?? '';
$table = $_GET['table'] ?? 'users';
$allowedTracking = hash_hmac('sha256', 'users-export', $_SESSION['csrf_token'] ?? '');

if (!is_string($tracking) || !hash_equals($allowedTracking, $tracking)) {
    http_response_code(403);
    exit('Forbidden export request.');
}

$tables = [
    'users' => ['id', 'username', 'email', 'role', 'status', 'created_at'],
];

if (!is_string($table) || !array_key_exists($table, $tables)) {
    http_response_code(400);
    exit('Invalid export table.');
}

$filename = $table . '-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
if ($out === false) {
    http_response_code(500);
    exit;
}

$columns = $tables[$table];
fputcsv($out, $columns);

try {
    $columnSql = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $columns));
    $stmt = db()->query("SELECT {$columnSql} FROM `users` ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, array_map(static fn(string $column): string => (string)($row[$column] ?? ''), $columns));
        flush();
    }
} catch (Throwable $exception) {
    app_log_error($exception, ['file' => __FILE__, 'query' => 'stream users export']);
}

fclose($out);
exit;
