<?php
require_once __DIR__ . '/includes/init.php';
echo "DESCRIBE sales_quotes:\n";
$stmt = $pdo->query("DESCRIBE sales_quotes");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nTOP 5 IDs:\n";
$stmt = $pdo->query("SELECT id, status FROM sales_quotes LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
