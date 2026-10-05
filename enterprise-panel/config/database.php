<?php
declare(strict_types=1);

require_once __DIR__ . '/logger.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3307';
    $database = getenv('DB_NAME') ?: 'enterprise_panel';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        ]);
        return $pdo;
    } catch (Throwable $exception) {
        app_log_error($exception, ['file' => __FILE__]);
        http_response_code(500);
        exit('Database connection failed.');
    }
}

function db_execute(string $sql, array $params = []): PDOStatement
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (Throwable $exception) {
        app_log_error($exception, ['file' => $_SERVER['SCRIPT_FILENAME'] ?? __FILE__, 'query' => $sql]);
        throw $exception;
    }
}
