<?php
// process/inventory_ledger_serverside.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php'; // $pdo (PDO)

$draw   = (int)($_POST['draw']   ?? 1);
$start  = (int)($_POST['start']  ?? 0);
$length = (int)($_POST['length'] ?? 50);

$product_id = (int)($_POST['product_id'] ?? 0);
$as_of      = trim($_POST['as_of'] ?? ''); // 'YYYY-MM-DD' hoặc rỗng

if ($product_id <= 0) {
  echo json_encode([
    "draw"=>$draw, "recordsTotal"=>0, "recordsFiltered"=>0, "data"=>[],
    "summary" => ["begin"=>0,"in"=>0,"out"=>0,"end"=>0],
    "error" => "product_id required"
  ]);
  exit;
}

// Xác định version MySQL để dùng window function nếu có
try {
  $mysqlVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
} catch (Throwable $e) {
  $mysqlVersion = '5.7.0';
}
$hasWindow = version_compare($mysqlVersion, '8.0.0', '>=');

// WHERE chung
$where = "WHERE m.product_id = :pid";
$params = [':pid' => $product_id];

if ($as_of !== '') {
  $where .= " AND m.movement_date <= :asof";
  $params[':asof'] = $as_of;
}

// Tổng số dòng (sau filter)
$sqlCount = "SELECT COUNT(*) FROM v_inventory_movements m $where";
$stmtC = $pdo->prepare($sqlCount);
$stmtC->execute($params);
$totalRows = (int)$stmtC->fetchColumn();

// Tính tổng hợp để lên badges:
// - begin: tồn trước ngày as_of (nếu có as_of) hoặc 0 nếu không chọn ngày
// - in/out: tổng trong kỳ (<= as_of) hoặc toàn bộ nếu không chọn ngày
// - end: begin + in - out
$begin = 0.0; $sumIn = 0.0; $sumOut = 0.0; $end = 0.0;

// begin: nếu có as_of -> tính tổng tất cả phát sinh < as_of
if ($as_of !== '') {
  $sqlBegin = "
    SELECT COALESCE(SUM(m.qty_in) - SUM(m.qty_out),0) AS bal
    FROM v_inventory_movements m
    WHERE m.product_id = :pid AND m.movement_date < :asof
  ";
  $stmtB = $pdo->prepare($sqlBegin);
  $stmtB->execute([':pid'=>$product_id, ':asof'=>$as_of]);
  $begin = (float)$stmtB->fetchColumn();
}

// in/out: tới as_of (hoặc tất cả)
$sqlIO = "
  SELECT
    COALESCE(SUM(m.qty_in),0)  AS s_in,
    COALESCE(SUM(m.qty_out),0) AS s_out
  FROM v_inventory_movements m
  $where
";
$stmtIO = $pdo->prepare($sqlIO);
$stmtIO->execute($params);
$io = $stmtIO->fetch(PDO::FETCH_ASSOC) ?: ['s_in'=>0,'s_out'=>0];
$sumIn  = (float)$io['s_in'];
$sumOut = (float)$io['s_out'];
$end    = $begin + $sumIn - $sumOut;

// Sắp xếp (DataTables → mấp map cột)
$orderBy = "m.movement_date ASC, m.ref_type ASC, m.ref_id ASC";
if (isset($_POST['order'][0])) {
  $colIndex = (int)$_POST['order'][0]['column'];
  $dir      = (($_POST['order'][0]['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
  $cols = [
    0 => 'm.movement_date',
    1 => 'm.ref_type',
    2 => 'm.ref_code',
    3 => 'm.qty_in',
    4 => 'm.qty_out',
    5 => 'm.movement_date' // fallback
  ];
  if (isset($cols[$colIndex])) $orderBy = $cols[$colIndex].' '.$dir;
}

$data = [];

if ($totalRows > 0) {
  if ($hasWindow) {
    // MySQL 8: dùng window function cho running balance (đúng & nhanh)
    $sql = "
      SELECT
        DATE_FORMAT(m.movement_date,'%Y-%m-%d') AS movement_date,
        m.ref_type, m.ref_code,
        CAST(m.qty_in AS DECIMAL(18,2))  AS qty_in,
        CAST(m.qty_out AS DECIMAL(18,2)) AS qty_out,
        CAST((
          :begin +
          SUM(m.qty_in - m.qty_out) OVER (
            PARTITION BY m.product_id
            ORDER BY m.movement_date, m.ref_type, m.ref_id
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
          )
        ) AS DECIMAL(18,2)) AS running_balance
      FROM v_inventory_movements m
      $where
      ORDER BY $orderBy
      LIMIT :start, :length
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':begin', $begin);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();
  } else {
    // MySQL 5.7: dùng biến người dùng để tính lũy kế TRÊN TẬP ĐẦY ĐỦ, rồi mới LIMIT (đảm bảo chính xác)
    // Lưu ý: với dữ liệu cực lớn, có thể cân nhắc tính trên temp table + index.
    $pdo->exec("SET @runbal := ".$pdo->quote($begin).";");
    $sqlFull = "
      SELECT
        x.movement_date,
        x.ref_type, x.ref_code,
        x.qty_in, x.qty_out,
        (@runbal := @runbal + (x.qty_in - x.qty_out)) AS running_balance
      FROM (
        SELECT
          DATE_FORMAT(m.movement_date,'%Y-%m-%d') AS movement_date,
          m.ref_type, m.ref_code,
          CAST(m.qty_in AS DECIMAL(18,2))  AS qty_in,
          CAST(m.qty_out AS DECIMAL(18,2)) AS qty_out
        FROM v_inventory_movements m
        $where
        ORDER BY m.movement_date, m.ref_type, m.ref_id
      ) x
    ";
    // bọc ngoài để LIMIT trang hiện tại
    $sql = "SELECT * FROM ($sqlFull) t ORDER BY t.movement_date, t.ref_type LIMIT :start, :length";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();
  }

  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data[] = [
      $r['movement_date'],
      htmlspecialchars($r['ref_type']),
      htmlspecialchars($r['ref_code']),
      number_format((float)$r['qty_in'], 2, '.', ''),
      number_format((float)$r['qty_out'], 2, '.', ''),
      number_format((float)$r['running_balance'], 2, '.', ''),
    ];
  }
}

echo json_encode([
  "draw" => $draw,
  "recordsTotal" => $totalRows,
  "recordsFiltered" => $totalRows,
  "data" => $data,
  "summary" => [
    "begin" => $begin,
    "in"    => $sumIn,
    "out"   => $sumOut,
    "end"   => $end
  ]
], JSON_UNESCAPED_UNICODE);
