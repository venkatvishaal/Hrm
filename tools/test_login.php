<?php
require __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/Core/Auth.php';

$authObj = new App\Core\Auth($db);

// 1. Test Admin Login
$adminOk = $authObj->attempt('admin', 'Admin@123');
echo "ADMIN_LOGIN_STATUS: " . ($adminOk ? 'SUCCESS' : 'FAILED') . "\n";

// 2. Test Newly Registered Employee Login
$lastEmp = $db->fetch("SELECT employee_code FROM employees ORDER BY id DESC LIMIT 1");
if ($lastEmp) {
    $code = $lastEmp['employee_code'];
    $empOk = $authObj->attempt($code, 'kh1234');
    echo "EMPLOYEE_LOGIN_STATUS ($code): " . ($empOk ? 'SUCCESS' : 'FAILED') . "\n";
}
