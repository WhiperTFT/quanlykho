<?php
require_once __DIR__ . '/../includes/init.php';
$stmt = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME = 'delivery_trips' AND TABLE_SCHEMA = 'db_quanlykho'");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
