<?php
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$environment = getenv('APP_ENV') ?: getenv('HRM_ENV') ?: '';

$isLocalHost = static function (string $host): bool {
    $host = strtolower(trim($host));
    $host = trim($host, '[]');

    if (substr_count($host, ':') === 1) {
        $host = explode(':', $host)[0];
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = array_map('intval', explode('.', $host));
        return $parts[0] === 10
            || ($parts[0] === 172 && $parts[1] >= 16 && $parts[1] <= 31)
            || ($parts[0] === 192 && $parts[1] === 168)
            || ($parts[0] === 169 && $parts[1] === 254);
    }

    $endsWith = static function (string $value, string $suffix): bool {
        return substr($value, -strlen($suffix)) === $suffix;
    };

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || $endsWith($host, '.test')
        || $endsWith($host, '.local');
};

$environment = strtolower($environment);
$hostLooksLocal = PHP_SAPI === 'cli'
    || $environment === 'local'
    || $isLocalHost($httpHost)
    || $isLocalHost($serverName);
$isLocal = $hostLooksLocal || !in_array($environment, ['production', 'prod', 'hosting'], true);

$config = $isLocal
    ? [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'hrmodule',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ]
    : [
        'host' => '',
        'port' => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
    ];

$localConfigFile = __DIR__ . '/database.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (!is_array($localConfig)) {
        throw new RuntimeException('config/database.local.php must return an array.');
    }

    $config = array_merge($config, array_intersect_key($localConfig, $config));
}

$resolved = [
    'host' => getenv('DB_HOST') ?: $config['host'],
    'port' => (int)(getenv('DB_PORT') ?: $config['port']),
    'database' => getenv('DB_DATABASE') ?: $config['database'],
    'username' => getenv('DB_USERNAME') ?: $config['username'],
    'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : $config['password'],
    'charset' => getenv('DB_CHARSET') ?: $config['charset'],
];

if (!$isLocal && ($resolved['host'] === '' || $resolved['database'] === '' || $resolved['username'] === '' || $resolved['password'] === '')) {
    throw new RuntimeException('Production database settings must be provided through DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD.');
}

return $resolved;
