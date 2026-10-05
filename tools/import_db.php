<?php
$host = '127.0.0.1';
$port = 3307;
$user = 'root';
$pass = '';
$dbname = 'hrmodule';

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connecting to MySQL on port $port... OK!\n";

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database `$dbname` created / verified... OK!\n";

    $pdo->exec("USE `$dbname`");

    $sqlFile = __DIR__ . '/../hrmodule (5).sql';
    if (!file_exists($sqlFile)) {
        $sqlFile = 'c:/TSVV/Dilip/hrm26092026/hrm26092026/hrmodule (5).sql';
    }

    if (file_exists($sqlFile)) {
        echo "Importing SQL file: $sqlFile...\n";
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
        echo "SQL Import Completed Successfully!\n";
    } else {
        echo "SQL file not found at $sqlFile\n";
    }

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
