<?php
require_once __DIR__ . '/../includes/init.php';
$stmt = $pdo->query("SELECT id, name, url, parent_id, sort_order FROM menus ORDER BY parent_id, sort_order");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
