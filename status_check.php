<?php
// File: status_check.php
// CHỈNH SỬA: Cập nhật để xử lý log_id và log_type, và JOIN với bảng document tương ứng

header('Content-Type: application/json');

// Bao gồm file kết nối database của bạn
// Đường dẫn này giả định file status_check.php nằm ở thư mục gốc, cùng cấp với thư mục 'config'
// Nếu file này nằm ở nơi khác (ví dụ: trong 'process/' hoặc 'api/'), bạn cần điều chỉnh đường dẫn cho đúng.
// Ví dụ: require_once __DIR__ . '/../config/database.php'; nếu file này nằm trong thư mục con.
require_once __DIR__ . '/config/database.php'; // <<< KIỂM TRA VÀ CHỈNH SỬA ĐƯỜNG DẪN NÀY NẾU CẦN
require_once __DIR__ . '/includes/init.php';
$pdo = db_connect();

// CHỈNH SỬA: Nhận thêm log_type từ GET request
$log_id = isset($_GET['log_id']) ? (int)$_GET['log_id'] : 0;
$log_type = isset($_GET['log_type']) ? trim($_GET['log_type']) : ''; // 'order' hoặc 'quote'
// GHI CHÚ CHỈNH SỬA: `$log_id` và `$log_type` được lấy từ tham số GET.

if ($log_id <= 0 || ($log_type !== 'order' && $log_type !== 'quote')) {
    // CHỈNH SỬA: Kiểm tra cả log_id và log_type hợp lệ
    error_log("[STATUS_CHECK] Invalid or missing log_id ({$log_id}) or log_type ({$log_type}).");
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing log ID or log type.']);
    http_response_code(400);
    exit();
}

// CHỈNH SỬA: Xác định bảng log và bảng tài liệu (document) dựa trên log_type
$target_log_table = '';
$document_table = '';
$document_id_column_in_log = 'order_id'; // Giả định cột này trong cả 2 bảng log lưu ID của document gốc
$document_number_column_in_document_table = ''; // Tên cột số của document (order_number hoặc quote_number)
$document_partner_info_column = ''; // Tên cột chứa snapshot thông tin đối tác
$document_date_column = ''; // Tên cột ngày của document

if ($log_type === 'quote') {
    $target_log_table = 'quote_email_logs';
    $document_table = 'sales_quotes';
    $document_number_column_in_document_table = 'quote_number'; // Giả sử tên cột là 'quote_number' trong sales_quotes
    $document_partner_info_column = 'customer_info_snapshot'; // Giả sử cột này trong sales_quotes
    $document_date_column = 'quote_date'; // Giả sử cột này trong sales_quotes
} else { // Mặc định là 'order'
    $target_log_table = 'email_logs';
    $document_table = 'sales_orders';
    $document_number_column_in_document_table = 'order_number';
    $document_partner_info_column = 'supplier_info_snapshot';
    $document_date_column = 'order_date';
}
// GHI CHÚ CHỈNH SỬA:
// - Xác định `$target_log_table` (bảng log email).
// - Xác định `$document_table` (bảng `sales_orders` hoặc `sales_quotes`).
// - Xác định `$document_id_column_in_log` (tên cột trong bảng log chứa ID của document gốc, ví dụ: `el.order_id`).
// - Xác định `$document_number_column_in_document_table` (tên cột chứa số của document, ví dụ: `doc.order_number` hoặc `doc.quote_number`).
// - Xác định các cột thông tin khác của document nếu cần.

try {
    // CHỈNH SỬA: Câu SQL JOIN động với bảng log và bảng document tương ứng
    $sql = "
        SELECT
            el.status,
            el.message,
            el.{$document_id_column_in_log} as document_id_from_log, -- Lấy ID của document từ log
            doc.{$document_number_column_in_document_table} as document_number, -- Lấy số của document
            doc.{$document_partner_info_column} as partner_info_snapshot, -- Lấy snapshot thông tin đối tác
            doc.{$document_date_column} as document_date -- Lấy ngày của document
        FROM
            {$target_log_table} el
        LEFT JOIN
            {$document_table} doc ON el.{$document_id_column_in_log} = doc.id
        WHERE
            el.id = :log_id
        LIMIT 1;
    ";
    // GHI CHÚ CHỈNH SỬA:
    // - Câu SQL được cập nhật để JOIN động dựa trên `$target_log_table`, `$document_table`, và các tên cột đã xác định.
    // - `el.{$document_id_column_in_log} = doc.id` là điều kiện JOIN chính.
    // - Lấy ra `document_number`, `partner_info_snapshot`, và `document_date` từ bảng document.

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':log_id', $log_id, PDO::PARAM_INT);
    $stmt->execute();
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        $document_info = null;
        // CHỈNH SỬA: Sử dụng document_number lấy được từ JOIN
        if ($record['document_number'] !== null) {
             $document_info = [
                'id' => $record['document_id_from_log'], // ID của sales_order hoặc sales_quote
                'number' => $record['document_number'],  // order_number hoặc quote_number
                'partner_info' => $record['partner_info_snapshot'], // supplier_info_snapshot hoặc customer_info_snapshot
                'date' => $record['document_date'] // order_date hoặc quote_date
             ];
        }
        // GHI CHÚ CHỈNH SỬA: `$document_info` được tạo với các tên trường chung (`number`, `partner_info`, `date`)
        // để JavaScript có thể xử lý một cách nhất quán.

        echo json_encode([
            'status' => $record['status'],      // Trạng thái của log email
            'message' => $record['message'],    // Thông báo lỗi/thành công từ log email
            'document_info' => $document_info,  // Thông tin về document (order/quote) liên quan
            'log_type_checked' => $log_type     // Trả về log_type để JS có thể dùng nếu cần
        ]);
        // GHI CHÚ CHỈNH SỬA:
        // - `order_info` được đổi tên thành `document_info` cho tổng quát.
        // - Thêm `log_type_checked` vào phản hồi.

    } else {
        error_log("[STATUS_CHECK] Email log record not found for log_id {$log_id} in table {$target_log_table}.");
        echo json_encode(['status' => 'not_found', 'message' => 'Email log record not found.']);
         http_response_code(404);
    }

} catch (PDOException $e) {
    error_log("[STATUS_CHECK] Database error for log_id {$log_id}, type {$log_type}: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
    http_response_code(500);
} catch (Exception $e) {
     error_log("[STATUS_CHECK] General error for log_id {$log_id}, type {$log_type}: " . $e->getMessage());
     echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
     http_response_code(500);
}
?>