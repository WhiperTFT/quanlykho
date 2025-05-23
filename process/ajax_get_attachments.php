<?php
// File: process/ajax_get_attachments.php

header('Content-Type: application/json');

// Nạp các file cần thiết (kết nối CSDL, session, auth...)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/init.php'; // Giả định init.php có db_connect(), is_logged_in()...
require_once __DIR__ . '/../includes/auth_check.php'; // Đảm bảo người dùng đăng nhập

$pdo = db_connect();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi kết nối CSDL."]);
    exit;
}

// Kiểm tra người dùng đã đăng nhập chưa (Quan trọng!)
if (!is_logged_in()) {
    http_response_code(401); // Unauthorized
    echo json_encode(["success" => false, "message" => "Bạn cần đăng nhập để thực hiện thao tác này."]);
    exit;
}

// Lấy và kiểm tra order_id từ yêu cầu GET
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$order_id || $order_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(["success" => false, "message" => "ID đơn hàng không hợp lệ."]);
    exit;
}

try {
    // Truy vấn danh sách file đính kèm cho order_id này
    // Giả định bạn có bảng `email_attachments` như mô tả ở Bước 6
    $sql = "SELECT
                id,           -- ID của bản ghi đính kèm
                email_log_id, -- ID của log email (có thể NULL nếu đính kèm vào đơn hàng mà chưa gửi email)
                order_id,     -- ID đơn hàng mà file này đính kèm
                file_path,    -- Đường dẫn file trên server (ví dụ: uploads/attachments/...)
                original_filename, -- Tên file gốc khi upload
                file_url,     -- URL công khai để tải/xem file (ví dụ: /uploads/attachments/...)
                uploaded_at   -- Thời gian upload
            FROM
                email_attachments
            WHERE
                order_id = :order_id"; // Hoặc điều kiện khác tùy logic của bạn

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->execute();

    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Trả về danh sách file đính kèm
    echo json_encode(["success" => true, "message" => "OK", "data" => $attachments]);

} catch (PDOException $e) {
    // Ghi log lỗi chi tiết trên server
    error_log("Database Error fetching attachments for order_id $order_id: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    // Không hiển thị chi tiết lỗi CSDL cho người dùng cuối
    echo json_encode(["success" => false, "message" => "Lỗi truy vấn CSDL khi lấy danh sách file đính kèm."]);
} catch (Exception $e) {
     error_log("General Error fetching attachments for order_id $order_id: " . $e->getMessage());
     http_response_code(500);
     echo json_encode(["success" => false, "message" => "Đã xảy ra lỗi không mong muốn khi lấy danh sách file đính kèm."]);
}

?>