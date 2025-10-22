<?php
require_once '../includes/init.php';
$pdo = db_connect();
$pid = intval($_GET['product_id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.id, p.name, COALESCE(a.atp,0) as atp
                       FROM products p
                       LEFT JOIN v_inventory_atp a ON a.product_id=p.id
                       WHERE p.id=?");
$stmt->execute([$pid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode(['success'=>true,'data'=>$row]);
