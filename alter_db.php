<?php
require_once __DIR__ . '/includes/init.php';
try {
    $pdo->exec("ALTER TABLE pxk_slips ADD COLUMN driver_name VARCHAR(255) NULL");
    echo "Added driver_name\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $pdo->exec("ALTER TABLE pxk_slips ADD COLUMN is_printed TINYINT(1) DEFAULT 0");
    echo "Added is_printed\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
