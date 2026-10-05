<?php
require __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/Services/NotificationService.php';
require_once __DIR__ . '/../app/Controllers/MainController.php';

$_POST = [
    'first_name' => 'Venkat',
    'last_name' => 'T S',
    'email' => 'vishaal@gmail.com',
    'phone' => '8608870926',
    'department' => 'IT',
    'location' => 'KNS',
    'position' => 'IT Support Engineer',
    'join_date' => '2026-10-04',
    'employment_type' => 'Contract',
];
$controller = new App\Controllers\MainController($db, $auth, new App\Services\NotificationService($db), $app);
try {
    $controller->employeeRegister();
    echo "REGISTER_SUCCESS!\n";
} catch (\Throwable $e) {
    echo "RESULT_ERROR: " . $e->getMessage() . "\n";
}
