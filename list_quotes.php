<?php
require_once __DIR__ . '/includes/init.php';
$stmt = $pdo->query("SELECT id, quote_number, status FROM sales_quotes LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
