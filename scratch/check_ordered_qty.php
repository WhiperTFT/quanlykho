<?php
require_once __DIR__ . '/../includes/init.php';
$stmt = $pdo->query("SELECT id, quote_id, product_name_snapshot, quantity, ordered_quantity FROM sales_quote_details WHERE ordered_quantity > 0");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
