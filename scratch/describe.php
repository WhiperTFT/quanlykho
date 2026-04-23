<?php
require_once __DIR__ . '/../includes/init.php';
$table = isset($argv[1]) ? $argv[1] : 'sales_orders';
echo "DESCRIBE $table:\n";
$stmt = $pdo->query("DESCRIBE $table");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
