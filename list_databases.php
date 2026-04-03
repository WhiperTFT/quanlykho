<?php
$pdo = new PDO('mysql:host=localhost', 'root', '');
$stmt = $pdo->query('SHOW DATABASES');
$databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($databases, JSON_PRETTY_PRINT);
