<?php
// File: process/ajax_email_logs.php
// Phiên bản đầy đủ và đã sửa đổi để hỗ trợ hiển thị log cho cả order và quote
// CHỈNH SỬA: Cập nhật để xử lý get_for_document với log_type

// Include file kết nối database
// Đường dẫn tính từ file process/ajax_email_logs.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/init.php';
// Hàm kết nối CSDL được định nghĩa trong database.php
$pdo = db_connect();

// --- XỬ LÝ YÊU CẦU LẤY LOG CHO DOCUMENT CỤ THỂ (Order hoặc Quote) ---
// CHỈNH SỬA: Thay đổi 'action', 'order_id' thành 'document_id' và thêm 'log_type'
if (isset($_GET['action']) && $_GET['action'] === 'get_for_document' && isset($_GET['document_id']) && isset($_GET['log_type'])) {
    // GHI CHÚ CHỈNH SỬA:
    // - 'action' đổi thành 'get_for_document'.
    // - 'order_id' đổi thành 'document_id'.
    // - Thêm yêu cầu 'log_type' ('order' hoặc 'quote').

    // Luôn trả về JSON
    header('Content-Type: application/json');

    // Kiểm tra kết nối CSDL
    if (!$pdo) {
        error_log('[AJAX_EMAIL_LOGS] Lỗi CSDL nghiêm trọng (get_for_document): Không thể kết nối.');
        http_response_code(500); // Internal Server Error
        echo json_encode(["success" => false, "message" => "Lỗi máy chủ: Không thể kết nối cơ sở dữ liệu."]);
        exit;
    }

    // Lọc và kiểm tra document_id và log_type
    $document_id = filter_var($_GET['document_id'], FILTER_VALIDATE_INT);
    $log_type = trim($_GET['log_type']); // 'order' hoặc 'quote'

    $logs = [];
    $message = 'Không tìm thấy log.';
    $success = false;

    // CHỈNH SỬA: Kiểm tra $document_id và $log_type hợp lệ
    if ($document_id && ($log_type === 'order' || $log_type === 'quote')) {
        // GHI CHÚ CHỈNH SỬA: Điều kiện kiểm tra $document_id và $log_type.

        // CHỈNH SỬA: Xác định bảng log mục tiêu và cột ID liên kết
        $target_log_table = '';
        $document_id_column_in_log = 'order_id'; // Giả định cả hai bảng log dùng cột 'order_id' để lưu ID của document (order/quote)

        if ($log_type === 'quote') {
            $target_log_table = 'quote_email_logs';
        } else { // Mặc định là 'order'
            $target_log_table = 'email_logs';
        }
        // GHI CHÚ CHỈNH SỬA: `$target_log_table` được xác định. `$document_id_column_in_log` là tên cột trong bảng log
        // chứa ID của sales_order hoặc sales_quote. Giả định là 'order_id' cho cả hai.

        try {
            // Truy vấn log cho document_id cụ thể từ bảng tương ứng
            // CHỈNH SỬA: Câu SQL sử dụng $target_log_table và $document_id_column_in_log
            $sql = "SELECT id, 
                           {$document_id_column_in_log} as document_id_val, -- Alias để JS có thể dùng tên nhất quán
                           created_at, sent_at, to_email, cc_emails, subject, body, status, message, attachment_paths
                    FROM {$target_log_table}
                    WHERE {$document_id_column_in_log} = :document_id
                    ORDER BY created_at DESC";
            // GHI CHÚ CHỈNH SỬA:
            // - Truy vấn từ `$target_log_table`.
            // - Lọc theo `$document_id_column_in_log = :document_id`.
            // - Alias cột ID gốc (ví dụ: order_id) thành `document_id_val` để JS có thể truy cập một cách nhất quán nếu cần.

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
            $stmt->execute();

            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $success = true;
            $message = (count($logs) > 0) ? 'OK' : 'Không tìm thấy lịch sử gửi email nào cho mục này.';

             foreach ($logs as $key => $log) {
                $logs[$key]['created_at_formatted'] = $log['created_at'] ? date('d/m/Y H:i:s', strtotime($log['created_at'])) : 'Không rõ';
                $logs[$key]['sent_at_formatted'] = $log['sent_at'] ? date('d/m/Y H:i:s', strtotime($log['sent_at'])) : null;
             }

        } catch (PDOException $e) {
            error_log("[AJAX_EMAIL_LOGS] Lỗi truy vấn CSDL khi lấy log cho {$log_type} ID {$document_id}: " . $e->getMessage());
            $message = "Lỗi máy chủ khi tải lịch sử email.";
            $success = false;
            http_response_code(500);
        }
    } else {
        $message = "ID tài liệu hoặc loại log không hợp lệ. Document ID: {$document_id}, Log Type: {$log_type}";
        $success = false;
        http_response_code(400); // Bad Request
        error_log("[AJAX_EMAIL_LOGS] Invalid document_id or log_type. Document ID: {$document_id}, Log Type: {$log_type}");
    }

    // Trả về phản hồi JSON
    echo json_encode([
        "success" => $success,
        "logs" => $logs,
        "message" => $message,
        "log_type_processed" => $log_type // CHỈNH SỬA: Trả về log_type để JS có thể xác nhận nếu cần
        // GHI CHÚ CHỈNH SỬA: Thêm `log_type_processed` vào phản hồi.
    ]);
    exit;
}


// --- PHẦN CODE XỬ LÝ DATATABLES SERVER-SIDE CHO TRANG LOG CHUNG (Nếu có) ---
// GHI CHÚ: Phần này nếu được sử dụng, cũng cần được tham số hóa tương tự
// để có thể lấy log từ `email_logs` hoặc `quote_email_logs` tùy theo ngữ cảnh của trang log chung đó.
// Hiện tại, các thay đổi chỉ tập trung vào action 'get_for_document' dùng cho modal.
// Nếu bạn có một trang quản lý log email chung và muốn nó hiển thị log từ cả hai bảng,
// bạn sẽ cần thêm một tham số (ví dụ: qua AJAX POST từ DataTables) để script này biết
// phải query bảng nào, hoặc query cả hai bảng bằng UNION ALL (nếu cấu trúc cột hoàn toàn giống nhau).

// Bao gồm file cấu hình DataTables (nếu có file riêng)
// require_once __DIR__ . '/../libs/DataTables.php'; // Giả định có file này

// Namespace cho DataTables (nếu sử dụng thư viện DataTables PHP)
// use DataTables\Database;
// use DataTables\SSP;


// --- Cấu hình DataTables Server-Side Processing ---
/*
$sql_details = array(
    'user' => DB_USER,
    'pass' => DB_PASS,
    'db'   => DB_NAME,
    'host' => DB_HOST
);
*/
$dbColumns = [
    ['db' => 'id',             'dt' => 'id'],
    // CHỈNH SỬA: Cần làm cho cột này linh động nếu dùng cho cả hai loại log
    // Ví dụ, nếu bảng log có cột 'order_id' (lưu document_id) và bạn muốn hiển thị nó
    ['db' => 'order_id',       'dt' => 'document_id_val'], // Hoặc 'order_id' tùy theo JS của DataTables
    // ['db' => 'created_at',     'dt' => 'created_at', 'formatter' => function($d, $row) { return $d ? date('d/m/Y H:i:s', strtotime($d)) : ''; }],
    ['db' => 'sent_at',        'dt' => 'sent_at', 'formatter' => function($d, $row) { return $d ? date('d/m/Y H:i:s', strtotime($d)) : ''; }],
    ['db' => 'to_email',       'dt' => 'to_email'],
    ['db' => 'cc_emails',      'dt' => 'cc_emails'],
    ['db' => 'subject',        'dt' => 'subject'],
    ['db' => 'status',         'dt' => 'status', 'formatter' => function($d, $row) {
        $status_badge = ($d === 'sent') ? 'bg-success' : (($d === 'failed') ? 'bg-danger' : 'bg-secondary');
        $status_text = ($d === 'sent') ? 'Thành công' : (($d === 'failed') ? 'Thất bại' : (($d === 'pending') ? 'Đang chờ' : (($d === 'sending') ? 'Đang gửi...' : 'Không rõ')));
        return "<span class=\"badge {$status_badge}\">{$status_text}</span>";
    }],
    ['db' => 'message',        'dt' => 'message', 'formatter' => function($d, $row) {
        return $d ? htmlspecialchars(mb_substr($d, 0, 100, 'UTF-8')) . (mb_strlen($d, 'UTF-8') > 100 ? '...' : '') : '';
    }],
];

// CHỈNH SỬA: $tableName cần được xác định động nếu DataTables này dùng cho cả hai loại log
// $tableName = 'email_logs'; // Hoặc 'quote_email_logs' hoặc logic để UNION
$primaryKey = 'id';

// require( '../ssp.class.php' );
// echo json_encode(
//    SSP::simple( $_POST, $sql_details, $tableName, $primaryKey, $dbColumns )
// );

/*
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['draw']) || isset($_POST['columns']))) {
    header('Content-Type: application/json');
    // ... (Logic DataTables tự viết cũng cần tham số hóa $tableName) ...
    exit;
}
*/

// Nếu không có action hợp lệ nào được gọi (ví dụ: action không phải 'get_for_document')
// Bạn có thể thêm một thông báo mặc định hoặc lỗi ở đây.
// Ví dụ:
if (!(isset($_GET['action']) && $_GET['action'] === 'get_for_document')) {
    // Nếu không có action get_for_document, và không phải là request DataTables (nếu bạn bật phần đó)
    // thì có thể là một yêu cầu không hợp lệ đến file này.
    // http_response_code(400);
    // echo json_encode(["success" => false, "message" => "Yêu cầu không hợp lệ hoặc action không được chỉ định."]);
}

?>