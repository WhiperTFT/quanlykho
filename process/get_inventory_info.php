<?php
// process/get_inventory_info.php
require_once '../includes/init.php';

$product_id = intval($_GET['product_id'] ?? 0);
if ($product_id <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid product_id']); exit; }

$sql = "
  SELECT
    p.id,
    p.name,
    COALESCE(soh.on_hand,0) AS on_hand,
    COALESCE(a.allocated_qty,0) AS allocated,
    (COALESCE(soh.on_hand,0) - COALESCE(a.allocated_qty,0)) AS atp
  FROM products p
  LEFT JOIN v_stock_on_hand soh     ON soh.product_id = p.id
  LEFT JOIN v_inventory_allocated a ON a.product_id   = p.id
  WHERE p.id = :pid
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':pid'=>$product_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($row ?: ['id'=>$product_id,'name'=>'','on_hand'=>0,'allocated'=>0,'atp'=>0]);
