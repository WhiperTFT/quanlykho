<?php
// File: process/sales_quote_serverside.php 
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

$response = [
    'draw' => intval($_POST['draw'] ?? 0),
    'recordsTotal' => 0,
    'recordsFiltered' => 0,
    'data' => [],
    'error' => null,
];

try {
    // --- 1) CẤU HÌNH ---
    $start  = intval($_POST['start']  ?? 0);
    $length = intval($_POST['length'] ?? 10);

    // Tham số lọc mới (từ UI mới)
    $unified     = isset($_POST['unified_filter']) ? trim((string)$_POST['unified_filter']) : '';
    $quoteStatus = $_POST['quote_status'] ?? ''; // '', 'draft'... tuỳ bạn định nghĩa

    // Map cột để ORDER BY (tham chiếu vị trí cột trên DataTables)
    // Điều chỉnh nếu cấu trúc cột client khác
    $column_map = [
        1 => 'sq.quote_number',
        2 => 'sq.quote_date',
        3 => 'p.name',
        4 => 's_order.order_number',   // linked order
        5 => 'sq.grand_total',
        6 => 'sq.status',
    ];
    $order_column_index = $_POST['order'][0]['column'] ?? 1;
    $order_dir          = $_POST['order'][0]['dir']    ?? 'desc';
    $order_column_name  = $column_map[$order_column_index] ?? 'sq.quote_number';

    // --- 2) CƠ SỞ TRUY VẤN ---
    $sql_select = "
        SELECT
            sq.id, sq.quote_number, sq.quote_date, sq.currency, sq.grand_total, sq.status,
            sq.ghi_chu,
            p.name AS customer_name,
            s_order.order_number AS linked_order_number
    ";
    $sql_from = "
        FROM sales_quotes sq
        LEFT JOIN partners p ON sq.customer_id = p.id AND p.type = 'customer'
        LEFT JOIN sales_orders s_order ON sq.id = s_order.quote_id
        LEFT JOIN sales_quote_details sqd ON sq.id = sqd.quote_id
    ";

    // --- 3) BỘ LỌC (WHERE) ---
    $where = [];
    $params = [];

    // (Giữ nguyên các filter cũ — để tương thích trang hiện có)
    if (!empty($_POST['columns'][1]['search']['value'])) {
        $where[] = "sq.quote_number LIKE :filter_quote_number";
        $params[':filter_quote_number'] = '%' . $_POST['columns'][1]['search']['value'] . '%';
    }
    if (!empty($_POST['columns'][2]['search']['value'])) {
        $dateObj = DateTime::createFromFormat('d/m/Y', $_POST['columns'][2]['search']['value']);
        if ($dateObj) {
            $where[] = "DATE(sq.quote_date) = :filter_exact_date";
            $params[':filter_exact_date'] = $dateObj->format('Y-m-d');
        }
    }
    if (!empty($_POST['columns'][3]['search']['value'])) {
        $where[] = "p.name LIKE :filter_customer";
        $params[':filter_customer'] = '%' . $_POST['columns'][3]['search']['value'] . '%';
    }
    if (!empty($_POST['columns'][5]['search']['value'])) {
        $where[] = "sq.status = :filter_status_col";
        $params[':filter_status_col'] = $_POST['columns'][5]['search']['value'];
    }
    if (!empty($_POST['item_details_filter'])) {
        $where[] = "sqd.product_name_snapshot LIKE :filter_item";
        $params[':filter_item'] = '%' . $_POST['item_details_filter'] . '%';
    }
    if (!empty($_POST['filter_year'])) {
        $where[] = "YEAR(sq.quote_date) = :filter_year";
        $params[':filter_year'] = intval($_POST['filter_year']);
    }
    if (!empty($_POST['filter_month'])) {
        $where[] = "MONTH(sq.quote_date) = :filter_month";
        $params[':filter_month'] = intval($_POST['filter_month']);
    }

    // (Mới) Lọc theo trạng thái quote từ select #quoteStatusFilter
    if ($quoteStatus !== '') {
        $where[] = "sq.status = :filter_status_new";
        $params[':filter_status_new'] = $quoteStatus;
    }

    // (Mới) Ô lọc gộp unified_filter: Số BG, Ngày BG (dd/mm/YYYY), KH, SP, (tùy chọn) số PO liên kết
    if ($unified !== '') {
        $kw = '%' . $unified . '%';

        $ors = [];
        // 1) Số BG
        $ors[] = "sq.quote_number LIKE :uf_kw1";
        // 2) Tên KH
        $ors[] = "p.name LIKE :uf_kw2";
        // 3) Tên SP (đã join sqd)
        $ors[] = "sqd.product_name_snapshot LIKE :uf_kw3";
        // 4) Ngày BG dạng dd/mm/YYYY
        $ors[] = "DATE_FORMAT(sq.quote_date, '%d/%m/%Y') LIKE :uf_kw4";
        // 5) (Tuỳ chọn) Số PO liên kết
        $ors[] = "s_order.order_number LIKE :uf_kw5";

        $params[':uf_kw1'] = $kw;
        $params[':uf_kw2'] = $kw;
        $params[':uf_kw3'] = $kw;
        $params[':uf_kw4'] = $kw;
        $params[':uf_kw5'] = $kw;

        // Nếu người dùng gõ đúng dd/mm/YYYY → match chính xác theo DATE
        $ufDate = DateTime::createFromFormat('d/m/Y', $unified);
        if ($ufDate) {
            $ors[] = "DATE(sq.quote_date) = :uf_exact_date";
            $params[':uf_exact_date'] = $ufDate->format('Y-m-d');
        }

        $where[] = '(' . implode(' OR ', $ors) . ')';
    }

    $where_clause = '';
    if (!empty($where)) {
        $where_clause = ' WHERE ' . implode(' AND ', $where);
    }

    // --- 4) ĐẾM ---
    $totalRecords = (int)$pdo->query("SELECT COUNT(id) FROM sales_quotes")->fetchColumn();
    $response['recordsTotal'] = $totalRecords;

    $sql_filtered_count = "SELECT COUNT(DISTINCT sq.id) " . $sql_from . $where_clause;
    $stmt_filtered = $pdo->prepare($sql_filtered_count);
    $stmt_filtered->execute($params);
    $filteredRecords = (int)$stmt_filtered->fetchColumn();
    $response['recordsFiltered'] = $filteredRecords;

    // --- 5) DATA ---
    $sql_data = $sql_select . $sql_from . $where_clause . " GROUP BY sq.id ORDER BY {$order_column_name} {$order_dir} LIMIT :limit OFFSET :offset";
    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->bindValue(':limit',  $length, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', $start,  PDO::PARAM_INT);

    // bindValue cho toàn bộ tham số
    foreach ($params as $key => $val) {
        $stmt_data->bindValue($key, $val);
    }

    $stmt_data->execute();
    $rows = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    // --- 6) FORMAT TRẢ VỀ ---
    $formatted = [];
    foreach ($rows as $row) {
        // Ngày
        try {
            $row['quote_date_formatted'] = !empty($row['quote_date'])
                ? (new DateTime($row['quote_date']))->format($user_settings['date_format_display'] ?? 'd/m/Y')
                : '-';
        } catch (Exception $e) {
            $row['quote_date_formatted'] = $row['quote_date'];
        }

        // Tiền
        $currency = $row['currency'] ?? 'VND';
        if (isset($row['grand_total'])) {
            $total = (float)$row['grand_total'];
            $row['grand_total_formatted'] = ($currency === 'VND')
                ? number_format($total, 0, ',', '.')
                : number_format($total, 2, '.', ',');
        } else {
            $row['grand_total_formatted'] = '0';
        }

        // Ghi chú
        $ghi = htmlspecialchars($row['ghi_chu'] ?? '');
        $row['ghi_chu_quote'] =
            '<div class="note-container position-relative" data-bs-toggle="tooltip" title="' . $ghi . '">' .
            '<textarea rows="2" class="form-control form-control-sm note-input" data-id="' . (int)$row['id'] . '" data-type="quote">' . $ghi . '</textarea>' .
            '<button class="btn btn-sm btn-success btn-save-note d-none" data-id="' . (int)$row['id'] . '" title="Lưu ghi chú">' .
            '<i class="bi bi-check-lg"></i></button></div>';

        // KH
        $row['customer_name'] = htmlspecialchars($row['customer_name'] ?? '-');

        $formatted[] = $row;
    }

    $response['data'] = $formatted;

} catch (PDOException $e) {
    error_log("SalesQuote ServerSide PDOError: " . $e->getMessage());
    $response['error'] = "Database error: " . $e->getMessage();
    http_response_code(500);
} catch (Exception $e) {
    error_log("SalesQuote ServerSide General Error: " . $e->getMessage());
    $response['error'] = "General error: " . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
