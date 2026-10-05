<?php
require __DIR__ . '/../config/bootstrap.php';
$rows = $db->fetchAll("SELECT id, name, username, email, role, password_hash FROM users WHERE role IN ('Admin', 'SuperAdmin', 'HR')");
foreach ($rows as $r) {
    echo "ID: {$r['id']} | Name: {$r['name']} | User: {$r['username']} | Email: {$r['email']} | Role: {$r['role']}\n";
}
