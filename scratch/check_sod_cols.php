<?php
require_once __DIR__ . '/../includes/init.php';
$stmt = $pdo->query("DESCRIBE sales_order_details");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
