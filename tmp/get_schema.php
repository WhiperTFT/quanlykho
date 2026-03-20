<?php
require __DIR__ . '/../includes/init.php';
$stmt = $pdo->query("SHOW COLUMNS FROM products");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
