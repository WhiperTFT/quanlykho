<?php
require_once __DIR__ . '/../includes/init.php';
$stmt = $pdo->query("DESCRIBE units");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
