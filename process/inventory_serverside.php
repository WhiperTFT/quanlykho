<?php
// process/inventory_serverside.php (PDO + collation-safe for utf8mb4_general_ci)
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth_check.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

// Debug an toàn khi cần test (có thể tắt)
// ini_set('display_errors', '0');
error_reporting(E_ALL);

/*
Tổng quan:
- Lấy Purchases (SO) và Sales commitments (Quotes accepted) -> UNION ALL -> GROUP BY pkey.
- Ép tất cả chuỗi về cùng collation 'utf8mb4_general_ci' để tránh lỗi 1271 khi UNION.
- Tìm kiếm áp dụng bằng HAVING (vì các cột là tổng hợp).
*/

try {
    // ---- DataTables params ----
    $draw    = (int)($_POST['draw'] ?? 0);
    $start   = (int)($_POST['start'] ?? 0);
    $length  = (int)($_POST['length'] ?? 10);
    $search  = trim((string)($_POST['search']['value'] ?? ''));
    $so_status = trim((string)($_POST['so_status'] ?? ''));

    // ---- Order whitelist ----
    $orderColIndex = (int)($_POST['order'][0]['column'] ?? 1);
    $orderDir = (($_POST['order'][0]['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
    $orderMap = [
        0 => 'product_id',
        1 => 'product_name',
        2 => 'category',
        3 => 'unit',
        4 => 'qty_purchased',
        5 => 'qty_committed',
        6 => 'qty_available',
        7 => 'cost_purchased',
        8 => 'revenue_committed',
        9 => 'product_name', // fallback
    ];
    $orderByCol = $orderMap[$orderColIndex] ?? 'product_name';

    // ---- Collation chung ----
    $COLL = "utf8mb4_general_ci";

    // ---- Điều kiện trạng thái SO ----
    $soWhereSql = "so.status <> 'cancelled'";
    $paramsPurch = [];
    if ($so_status !== '') {
        $soWhereSql = "so.status = :so_status";
        $paramsPurch[':so_status'] = $so_status;
    }

    // ---- Subquery Purchases (SO) - Ép collation ----
    $purchasesSub = "
        SELECT
          (
            CONCAT_WS('|',
              IFNULL(CAST(sod.product_id AS CHAR), ''),
              CONVERT(sod.product_name_snapshot USING utf8mb4)
            )
          ) COLLATE {$COLL} AS pkey,

          MAX(sod.product_id) AS product_id,

          MAX( (CONVERT(sod.product_name_snapshot USING utf8mb4)) COLLATE {$COLL} ) AS product_name,
          MAX( (CONVERT(sod.category_snapshot      USING utf8mb4)) COLLATE {$COLL} ) AS category,
          MAX( (CONVERT(sod.unit_snapshot          USING utf8mb4)) COLLATE {$COLL} ) AS unit,

          SUM(sod.quantity) AS qty_purchased,
          0 AS qty_committed,
          SUM(sod.quantity * sod.unit_price) AS cost_purchased,
          0 AS revenue_committed
        FROM sales_order_details sod
        JOIN sales_orders so ON so.id = sod.order_id
        WHERE {$soWhereSql}
        GROUP BY pkey
    ";

    // ---- Subquery Sales (Quotes accepted) - Ép collation ----
    $salesSub = "
        SELECT
          (
            CONCAT_WS('|',
              IFNULL(CAST(sqd.product_id AS CHAR), ''),
              CONVERT(sqd.product_name_snapshot USING utf8mb4)
            )
          ) COLLATE {$COLL} AS pkey,

          MAX(sqd.product_id) AS product_id,

          MAX( (CONVERT(sqd.product_name_snapshot USING utf8mb4)) COLLATE {$COLL} ) AS product_name,
          MAX( (CONVERT(sqd.category_snapshot      USING utf8mb4)) COLLATE {$COLL} ) AS category,
          MAX( (CONVERT(sqd.unit_snapshot          USING utf8mb4)) COLLATE {$COLL} ) AS unit,

          0 AS qty_purchased,
          SUM(sqd.quantity) AS qty_committed,
          0 AS cost_purchased,
          SUM(sqd.quantity * sqd.unit_price) AS revenue_committed
        FROM sales_quote_details sqd
        JOIN sales_quotes sq ON sq.id = sqd.quote_id
        WHERE sq.status = 'accepted'
        GROUP BY pkey
    ";

    // ---- Gộp & tổng hợp ----
    $base = "
      SELECT
        t.pkey,
        MAX(t.product_id)    AS product_id,
        MAX(t.product_name)  AS product_name,
        MAX(t.category)      AS category,
        MAX(t.unit)          AS unit,
        SUM(t.qty_purchased) AS qty_purchased,
        SUM(t.qty_committed) AS qty_committed,
        (SUM(t.qty_purchased) - SUM(t.qty_committed)) AS qty_available,
        SUM(t.cost_purchased)    AS cost_purchased,
        SUM(t.revenue_committed) AS revenue_committed
      FROM (
        {$purchasesSub}
        UNION ALL
        {$salesSub}
      ) t
      GROUP BY t.pkey
    ";

    // ---- Health check nhanh (tùy chọn) ----
    if (isset($_GET['__health'])) {
        $ok = (pdo() instanceof PDO);
        echo json_encode(['ok' => $ok, 'driver' => $ok ? pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) : null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---- Đếm total (không search) ----
    $countTotalSql = "SELECT COUNT(*) AS c FROM ({$base}) inv";
    $stmt = pdo()->prepare($countTotalSql);
    foreach ($paramsPurch as $k=>$v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $recordsTotal = (int)($stmt->fetch()['c'] ?? 0);

   // ---- Search (WHERE trên derived table inv) ----
    // ---- Search (WHERE trên derived table inv) ----
    $where = '';
    $paramsSearch = [];
    if ($search !== '') {
        $where = " WHERE (inv.product_name LIKE :s1 OR inv.category LIKE :s2 OR inv.unit LIKE :s3) ";
        $paramsSearch[':s1'] = '%' . $search . '%';
        $paramsSearch[':s2'] = '%' . $search . '%';
        $paramsSearch[':s3'] = '%' . $search . '%';
    }


    // ---- Đếm filtered ----
    $countFilteredSql = "SELECT COUNT(*) AS c FROM ({$base}) inv {$where}";
    $stmt = pdo()->prepare($countFilteredSql);
    foreach ($paramsPurch as $k=>$v) { $stmt->bindValue($k, $v); }
    foreach ($paramsSearch as $k=>$v) { 
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $recordsFiltered = (int)($stmt->fetch()['c'] ?? 0);

    // ---- Lấy data trang ----
    $start  = max(0, (int)$start);
    $length = max(1, (int)$length);

    $dataSql = "
      SELECT * FROM ({$base}) inv
      {$where}
      ORDER BY {$orderByCol} {$orderDir}
      LIMIT {$start}, {$length}
    ";
    $stmt = pdo()->prepare($dataSql);
    foreach ($paramsPurch as $k=>$v) { $stmt->bindValue($k, $v); }
    foreach ($paramsSearch as $k=>$v) { 
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $r) {
        $data[] = [
            'product_id'        => $r['product_id'],
            'product_name'      => $r['product_name'],
            'category'          => $r['category'],
            'unit'              => $r['unit'],
            'qty_purchased'     => (float)$r['qty_purchased'],
            'qty_committed'     => (float)$r['qty_committed'],
            'qty_available'     => (float)$r['qty_available'],
            'cost_purchased'    => (float)$r['cost_purchased'],
            'revenue_committed' => (float)$r['revenue_committed'],
            'pkey'              => $r['pkey'],
        ];
    }

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'draw' => (int)($_POST['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Server error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    // error_log($e);
    exit;
}

/** ---------- Helpers (PDO) ---------- */
function pdo(): PDO {
    global $pdo;
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException('PDO connection not initialized from init.php');
    }
    return $pdo;
}
