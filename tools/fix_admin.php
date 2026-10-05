<?php
require __DIR__ . '/../config/bootstrap.php';
$hash = password_hash('Admin@123', PASSWORD_DEFAULT);
$db->execute("UPDATE users SET username='admin', password_hash=:h WHERE id=1", ['h' => $hash]);
echo "ADMIN_USER_UPDATED_SUCCESS\n";
