<?php
// process/inventory_movements.php (safe, PDO, collation-aware, robust init)
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth_check.php';
header('Content-Type: application/json; charset=utf-8');
require_login();

try {
    $pkey = trim((string)($_GET['pkey'] ?? $_POST['pkey'] ?? ''));
    if ($pkey === '') {
        echo json_encode(['ok' => false, 'error' => 'Thiếu pkey'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Collation nhất quán với inventory_serverside.php
    $COLL = "utf8mb4_general_ci";

    // TÁCH pkey => "product_id|product_name_snapshot"
    $parts = explode('|', $pkey, 2);
    $pid   = (isset($parts[0]) && $parts[0] !== '' && is_numeric($parts[0])) ? (int)$parts[0] : null;
    $pname = $parts[1] ?? '';

    // ---------------------------
    // 1) LỊCH SỬ NHẬP (Mua từ SO)
    // ---------------------------
    // Điều kiện trạng thái: tính tất cả trừ 'cancelled'
    $condStatus = "so.status <> 'cancelled'";

    // Điều kiện khớp theo product_id và/hoặc theo tên snapshot (convert + collate)
    $condPurch = [];
    $paramsPurch = [];

    if ($pid !== null) {
        // Ưu tiên theo product_id
        $condPurch[] = "sod.product_id = :pid";
        $paramsPurch[':pid'] = $pid;

        // Fallback theo tên nếu detail không có product_id
        if ($pname !== '') {
            $condPurch[] = "(
                sod.product_id IS NULL AND
                (CONVERT(sod.product_name_snapshot USING utf8mb4) COLLATE {$COLL}) = :pname
            )";
            $paramsPurch[':pname'] = $pname;
        }
    } else {
        // Chỉ theo tên snapshot
        $condPurch[] = "(
            (CONVERT(sod.product_name_snapshot USING utf8mb4) COLLATE {$COLL}) = :pname
        )";
        $paramsPurch[':pname'] = $pname;
    }

    $sqlPurch = "
        SELECT
          so.order_date,
          so.order_number,
          so.supplier_info_snapshot,
          sod.quantity,
          sod.unit_price,
          (sod.quantity * sod.unit_price) AS line_total
        FROM sales_order_details sod
        JOIN sales_orders so ON so.id = sod.order_id
        WHERE {$condStatus}
          AND (" . implode(' OR ', $condPurch) . ")
        ORDER BY so.order_date DESC, so.order_number DESC
        LIMIT 500
    ";

    $rowsPurch = [];
    $stmt = pdo()->prepare($sqlPurch);
    foreach ($paramsPurch as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($stmt->execute() !== false) {
        $rowsPurch = $stmt->fetchAll() ?: [];
    }

    // ---------------------------
    // 2) LỊCH SỬ XUẤT (Bán từ Quotes accepted)
    // ---------------------------
    // Ở phần Sales bạn nói đã OK; mình vẫn giữ khớp pkey tuyệt đối cho chắc.
    $sqlSales = "
        SELECT
          sq.quote_date,
          sq.quote_number,
          sq.customer_info_snapshot,
          sqd.quantity,
          sqd.unit_price,
          (sqd.quantity * sqd.unit_price) AS line_total
        FROM sales_quote_details sqd
        JOIN sales_quotes sq ON sq.id = sqd.quote_id
        WHERE sq.status = 'accepted'
          AND (
            (
              CONCAT_WS('|',
                IFNULL(CAST(sqd.product_id AS CHAR), ''),
                CONVERT(sqd.product_name_snapshot USING utf8mb4)
              )
            ) COLLATE {$COLL} = :pkey_exact
          )
        ORDER BY sq.quote_date DESC, sq.quote_number DESC
        LIMIT 500
    ";

    $rowsSales = [];
    $stmt = pdo()->prepare($sqlSales);
    $stmt->bindValue(':pkey_exact', $pkey, PDO::PARAM_STR);
    if ($stmt->execute() !== false) {
        $rowsSales = $stmt->fetchAll() ?: [];
    }

    // ---------------------------
    // 3) Chuẩn hoá tên đối tác từ snapshot
    // ---------------------------
    $purchases = array_map(function($r){
        $r['supplier_name'] = snapshot_name($r['supplier_info_snapshot']);
        unset($r['supplier_info_snapshot']);
        return $r;
    }, $rowsPurch);

    $sales = array_map(function($r){
        $r['customer_name'] = snapshot_name($r['customer_info_snapshot']);
        unset($r['customer_info_snapshot']);
        return $r;
    }, $rowsSales);

    echo json_encode([
        'ok' => true,
        'purchases' => $purchases,
        'sales'     => $sales,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
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

// Tách tên đối tác từ snapshot (JSON hoặc block text)
function snapshot_name(?string $snap): string {
    if ($snap === null || $snap === '') return '';
    $j = json_decode($snap, true);
    if (is_array($j)) {
        foreach (['name','company','company_name','full_name','ten_cong_ty'] as $k) {
            if (!empty($j[$k])) return (string)$j[$k];
        }
    }
    $first = trim(preg_split('/\r\n|\r|\n/', $snap)[0] ?? '');
    return mb_substr($first, 0, 120);
}
