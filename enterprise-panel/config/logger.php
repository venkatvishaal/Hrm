<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function app_log_error(Throwable $exception, array $context = []): void
{
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }

    $message = preg_replace('/(password|passwd|pwd|secret|token)\s*=\s*[^;\s]+/i', '$1=[redacted]', $exception->getMessage());
    $entry = [
        'timestamp' => date('c'),
        'file' => $context['file'] ?? ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown'),
        'session_user_id' => current_user_id() ?? 'guest',
        'type' => get_class($exception),
        'message' => $message,
    ];

    if (!empty($context['query'])) {
        $entry['query'] = preg_replace('/(password|passwd|pwd|secret|token)\s*=\s*[^;\s]+/i', '$1=[redacted]', (string)$context['query']);
    }

    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($logDir . '/app_errors.log', $line, FILE_APPEND | LOCK_EX);
}
