<?php
// process/inventory_serverside.php
require_once '../includes/init.php'; // Kết nối PDO: $pdo

// DataTables params
$draw   = intval($_POST['draw'] ?? 1);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');

// Optional filters (nếu bạn muốn): category_id, unit_id
$category_id = $_POST['category_id'] ?? null;

$where = [];
$params = [];

// Tìm kiếm theo tên sp / đơn vị / danh mục
if ($search !== '') {
  $where[] = "(p.name LIKE :q OR u.name LIKE :q OR c.name LIKE :q)";
  $params[':q'] = "%{$search}%";
}
if ($category_id !== null && $category_id !== '') {
  $where[] = "p.category_id = :cid";
  $params[':cid'] = $category_id;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Tổng số bản ghi (không filter)
$totalSql = "SELECT COUNT(*) FROM products p";
$totalRecords = (int)$pdo->query($totalSql)->fetchColumn();

// Tổng số sau filter
$countSql = "
  SELECT COUNT(*)
  FROM products p
  LEFT JOIN units u      ON u.id = p.unit_id
  LEFT JOIN categories c ON c.id = p.category_id
  LEFT JOIN v_stock_on_hand soh     ON soh.product_id = p.id
  LEFT JOIN v_inventory_allocated a ON a.product_id   = p.id
  $whereSql
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$recordsFiltered = (int)$stmtCount->fetchColumn();

// Order (mặc định theo tên)
$orderBy = "p.name ASC";
if (isset($_POST['order'][0])) {
  $colIndex = intval($_POST['order'][0]['column']);
  $dir = ($_POST['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
  // map cột DataTables -> SQL
  $cols = [
    0 => 'p.id',
    1 => 'p.name',
    2 => 'u.name',
    3 => 'c.name',
    4 => 'COALESCE(soh.on_hand,0)',
    5 => 'COALESCE(a.allocated_qty,0)',
    6 => '(COALESCE(soh.on_hand,0) - COALESCE(a.allocated_qty,0))',
  ];
  if (isset($cols[$colIndex])) $orderBy = $cols[$colIndex] . ' ' . $dir;
}

// Data
$dataSql = "
  SELECT
    p.id,
    p.name AS product_name,
    COALESCE(u.name,'') AS unit_name,
    COALESCE(c.name,'') AS category_name,
    COALESCE(soh.on_hand, 0) AS on_hand,
    COALESCE(a.allocated_qty, 0) AS allocated,
    (COALESCE(soh.on_hand, 0) - COALESCE(a.allocated_qty, 0)) AS atp
  FROM products p
  LEFT JOIN units u      ON u.id = p.unit_id
  LEFT JOIN categories c ON c.id = p.category_id
  LEFT JOIN v_stock_on_hand soh     ON soh.product_id = p.id
  LEFT JOIN v_inventory_allocated a ON a.product_id   = p.id
  $whereSql
  ORDER BY $orderBy
  LIMIT :start, :length
";
$stmt = $pdo->prepare($dataSql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();

$rows = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $rows[] = [
    $r['id'],
    htmlspecialchars($r['product_name']),
    htmlspecialchars($r['unit_name']),
    htmlspecialchars($r['category_name']),
    (float)$r['on_hand'],
    (float)$r['allocated'],
    (float)$r['atp'],
    // Hành động: nút xem sổ chi tiết
    '<button class="btn btn-sm btn-outline-primary btn-ledger" data-product-id="'.$r['id'].'" data-product-name="'.htmlspecialchars($r['product_name']).'">
       <i class="bi bi-journal-text"></i> Sổ chi tiết
     </button>'
  ];
}

echo json_encode([
  "draw" => $draw,
  "recordsTotal" => $totalRecords,
  "recordsFiltered" => $recordsFiltered,
  "data" => $rows
]);
