<?php
// File: process/sales_order_serverside.php (Đã bổ sung unified_filter + delivery_status)
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json');

$response = [
    'draw' => intval($_POST['draw'] ?? 0),
    'recordsTotal' => 0,
    'recordsFiltered' => 0,
    'data' => [],
    'error' => null,
];

try {
    // --- 1. CẤU HÌNH ---
    $start  = intval($_POST['start']  ?? 0);
    $length = intval($_POST['length'] ?? 10);

    // Tham số lọc mới từ client
    $unified        = isset($_POST['unified_filter']) ? trim((string)$_POST['unified_filter']) : '';
    $deliveryStatus = $_POST['delivery_status'] ?? ''; // '', 'not_delivered', 'delivered'

    $column_map = [
        1 => 'so.order_number',
        2 => 'so.order_date',
        3 => 'p.name',
        4 => 'sq.quote_number',
        5 => 'so.grand_total',
        6 => 'c.name',
        7 => 'd.ten',
        8 => 'so.expected_delivery_date',
    ];
    $order_column_index = $_POST['order'][0]['column'] ?? 1;
    $order_dir          = $_POST['order'][0]['dir'] ?? 'desc';
    $order_column_name  = $column_map[$order_column_index] ?? 'so.order_number';

    // --- 2. CƠ SỞ TRUY VẤN ---
    $sql_select = "
        SELECT
            so.id, so.order_number, so.order_date, so.currency, so.grand_total, so.status,
            so.ghi_chu, so.tien_xe, so.expected_delivery_date,
            p.name AS supplier_name,
            sq.quote_number AS linked_quote_number,
            c.name AS customer_name,
            d.ten AS driver_name,
            CONCAT('SĐT: ', d.sdt, ' | Biển số: ', d.bien_so_xe) as driver_details
    ";
    $sql_from = "
        FROM sales_orders so
        LEFT JOIN partners p ON so.supplier_id = p.id
        LEFT JOIN sales_quotes sq ON so.quote_id = sq.id
        LEFT JOIN partners c ON sq.customer_id = c.id
        LEFT JOIN drivers d ON so.driver_id = d.id
        LEFT JOIN sales_order_details sod ON so.id = sod.order_id
    ";

    // --- 3. BỘ LỌC (WHERE) ---
    $where_conditions = [];
    $params = [];

    // (Giữ nguyên các filter cũ theo cột — không xóa, để tương thích)
    if (!empty($_POST['columns'][1]['search']['value'])) {
        $where_conditions[] = "so.order_number LIKE :filter_order_number";
        $params[':filter_order_number'] = '%' . $_POST['columns'][1]['search']['value'] . '%';
    }
    if (!empty($_POST['columns'][2]['search']['value'])) {
        $dateObj = DateTime::createFromFormat('d/m/Y', $_POST['columns'][2]['search']['value']);
        if ($dateObj) {
            $where_conditions[] = "DATE(so.order_date) = :filter_exact_date";
            $params[':filter_exact_date'] = $dateObj->format('Y-m-d');
        }
    }
    if (!empty($_POST['columns'][3]['search']['value'])) {
        $where_conditions[] = "p.name LIKE :filter_supplier";
        $params[':filter_supplier'] = '%' . $_POST['columns'][3]['search']['value'] . '%';
    }
    if (!empty($_POST['columns'][5]['search']['value'])) {
        $where_conditions[] = "so.status = :filter_status";
        $params[':filter_status'] = $_POST['columns'][5]['search']['value'];
    }
    if (!empty($_POST['columns'][6]['search']['value'])) {
        $where_conditions[] = "c.name LIKE :filter_customer";
        $params[':filter_customer'] = '%' . $_POST['columns'][6]['search']['value'] . '%';
    }
    if (!empty($_POST['item_details_filter'])) {
        $where_conditions[] = "sod.product_name_snapshot LIKE :filter_item";
        $params[':filter_item'] = '%' . $_POST['item_details_filter'] . '%';
    }
    if (!empty($_POST['filter_year'])) {
        $where_conditions[] = "YEAR(so.order_date) = :filter_year";
        $params[':filter_year'] = intval($_POST['filter_year']);
    }
    if (!empty($_POST['filter_month'])) {
        $where_conditions[] = "MONTH(so.order_date) = :filter_month";
        $params[':filter_month'] = intval($_POST['filter_month']);
    }

    // >>> Lọc mới: trạng thái giao hàng
    if ($deliveryStatus === 'not_delivered') {
        $where_conditions[] = "(so.expected_delivery_date IS NULL OR so.expected_delivery_date = '0000-00-00')";
    } elseif ($deliveryStatus === 'delivered') {
        $where_conditions[] = "(so.expected_delivery_date IS NOT NULL AND so.expected_delivery_date <> '0000-00-00')";
    }

    // --- Ô lọc gộp (Số ĐH, Ngày ĐH, NCC, Khách hàng, Tên SP) ---
if ($unified !== '') {
    $kw = '%' . $unified . '%';

    // Tạo 5 placeholder KHÁC NHAU cho 5 điều kiện LIKE
    $where_or_parts = [];
    $where_or_parts[] = "so.order_number LIKE :uf_kw1";
    $where_or_parts[] = "p.name LIKE :uf_kw2";
    $where_or_parts[] = "c.name LIKE :uf_kw3";
    $where_or_parts[] = "sod.product_name_snapshot LIKE :uf_kw4";
    $where_or_parts[] = "DATE_FORMAT(so.order_date, '%d/%m/%Y') LIKE :uf_kw5";

    // Gán giá trị cho từng placeholder
    $params[':uf_kw1'] = $kw;
    $params[':uf_kw2'] = $kw;
    $params[':uf_kw3'] = $kw;
    $params[':uf_kw4'] = $kw;
    $params[':uf_kw5'] = $kw;

    // Nếu người dùng gõ đúng định dạng dd/mm/YYYY → so khớp chính xác theo DATE
    $ufDate = DateTime::createFromFormat('d/m/Y', $unified);
    if ($ufDate) {
        $where_or_parts[] = "DATE(so.order_date) = :uf_exact_date";
        $params[':uf_exact_date'] = $ufDate->format('Y-m-d');
    }

    $where_conditions[] = '(' . implode(' OR ', $where_or_parts) . ')';
}


    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = " WHERE " . implode(" AND ", $where_conditions);
    }

    // --- 4. ĐẾM BẢN GHI ---
    $totalRecords = $pdo->query("SELECT COUNT(id) FROM sales_orders")->fetchColumn();
    $response['recordsTotal'] = (int)$totalRecords;

    $sql_filtered_count = "SELECT COUNT(DISTINCT so.id) " . $sql_from . $where_clause;
    $stmt_filtered = $pdo->prepare($sql_filtered_count);
    $stmt_filtered->execute($params);
    $filteredRecords = $stmt_filtered->fetchColumn();
    $response['recordsFiltered'] = (int)$filteredRecords;

    // --- 5. LẤY DỮ LIỆU ---
    $sql_data = $sql_select . $sql_from . $where_clause . " GROUP BY so.id ORDER BY {$order_column_name} {$order_dir} LIMIT :limit OFFSET :offset";
    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->bindValue(':limit',  $length, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', $start,  PDO::PARAM_INT);
    foreach ($params as $key => $val) {
    $stmt_data->bindValue($key, $val);
}
    $stmt_data->execute();
    $data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    // --- 6. FORMAT DỮ LIỆU TRẢ VỀ ---
    $formatted_data = [];
    foreach ($data as $row) {
        // Ngày ĐH
        try {
            $row['order_date_formatted'] = !empty($row['order_date'])
                ? (new DateTime($row['order_date']))->format($user_settings['date_format_display'] ?? 'd/m/Y')
                : '-';
        } catch (Exception $e) {
            $row['order_date_formatted'] = $row['order_date'];
        }

        // Ngày giao
        $delivery_date_raw = $row['expected_delivery_date'] ?? null;
        if (!empty($delivery_date_raw) && $delivery_date_raw !== '0000-00-00') {
            $delivery_date_value = (new DateTime($delivery_date_raw))->format('Y-m-d');
            $row['expected_delivery_date_formatted'] =
                '<input type="date" class="form-control form-control-sm expected-date-input border-success text-success" ' .
                'data-id="' . (int)$row['id'] . '" value="' . htmlspecialchars($delivery_date_value, ENT_QUOTES, 'UTF-8') . '">';
        } else {
            $row['expected_delivery_date_formatted'] =
                '<div class="expected-date-placeholder text-danger fst-italic" data-id="' . (int)$row['id'] . '" style="cursor:pointer;">Chưa giao</div>';
        }

        // NCC (tooltip)
        if (!empty($row['supplier_name'])) {
            $fullSupplierName = htmlspecialchars($row['supplier_name']);
            $row['supplier_name'] = "<span title='{$fullSupplierName}'>{$fullSupplierName}</span>";
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

        $row['customer_name'] = htmlspecialchars($row['customer_name'] ?? '-');

        // Ghi chú
        $ghi_chu_content = htmlspecialchars($row['ghi_chu'] ?? '');
        $row['ghi_chu_order'] =
            '<div class="note-container position-relative" data-bs-toggle="tooltip" title="' . $ghi_chu_content . '">' .
            '<textarea rows="2" class="form-control form-control-sm note-input" data-id="' . (int)$row['id'] . '" data-type="order">' . $ghi_chu_content . '</textarea>' .
            '<button class="btn btn-sm btn-success btn-save-note d-none" data-id="' . (int)$row['id'] . '" title="Lưu ghi chú"><i class="bi bi-check-lg"></i></button>' .
            '</div>';

        // Tiền xe
        $tien_xe_val = isset($row['tien_xe']) ? number_format((float)$row['tien_xe'], 0, ',', '.') : '';
        $row['tien_xe'] =
            '<div class="shipping-cost-container"><input type="text" class="form-control form-control-sm shipping-cost-input" data-id="' . (int)$row['id'] . '" value="' . $tien_xe_val . '"></div>';

        // Tài xế
        $driver_details = htmlspecialchars($row['driver_details'] ?? 'Chưa có thông tin chi tiết');
        $driver_name    = htmlspecialchars($row['driver_name'] ?? '');
        $row['driver_name'] =
            '<div class="driver-container" data-bs-toggle="tooltip" title="' . $driver_details . '">' .
            '<input type="text" class="form-control form-control-sm driver-autocomplete" data-id="' . (int)$row['id'] . '" value="' . $driver_name . '" placeholder="Nhập tên tài xế...">' .
            '</div>';

        $formatted_data[] = $row;
    }

    $response['data'] = $formatted_data;

} catch (PDOException $e) {
    error_log("SalesOrder ServerSide PDOError: " . $e->getMessage());
    $response['error'] = "Database error: " . $e->getMessage();
    http_response_code(500);
} catch (Exception $e) {
    error_log("SalesOrder ServerSide General Error: " . $e->getMessage());
    $response['error'] = "General error: " . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
