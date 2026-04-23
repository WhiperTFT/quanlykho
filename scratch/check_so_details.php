<?php
require_once __DIR__ . '/../includes/init.php';
$stmt = $pdo->query("DESCRIBE sales_order_details");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query("SELECT * FROM sales_order_details LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
