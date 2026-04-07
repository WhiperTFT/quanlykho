<?php
require_once __DIR__ . '/includes/init.php';
$stmt = $pdo->query('SHOW COLUMNS FROM pxk_slips');
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
file_put_contents('db_cols.json', json_encode($cols));
