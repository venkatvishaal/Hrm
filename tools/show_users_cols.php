<?php
require __DIR__ . '/../config/bootstrap.php';
$cols = $db->fetchAll("SHOW COLUMNS FROM users");
foreach ($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
