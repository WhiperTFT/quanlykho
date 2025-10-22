<?php
// File: process/ajax_update_document.php (Đã sửa lỗi sai biến $db thành $pdo)

// Bật hiển thị lỗi để debug nếu cần (chỉ bật trên môi trường test)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once '../includes/init.php';


header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Yêu cầu không hợp lệ.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $type = $_POST['type'] ?? ''; // 'order' or 'quote'
    $field = $_POST['field'] ?? ''; // 'ghi_chu', 'driver_id', 'tien_xe'
    $value = isset($_POST['value']) ? $_POST['value'] : null;

    if ($id > 0 && in_array($type, ['order', 'quote']) && !empty($field)) {
        $table = '';
        $allowed_fields = [];

        if ($type === 'order') {
            $table = 'sales_orders';
            $allowed_fields = ['ghi_chu', 'driver_id', 'tien_xe'];
        } elseif ($type === 'quote') {
            $table = 'sales_quotes';
            $allowed_fields = ['ghi_chu'];
        }

        if (!empty($table) && in_array($field, $allowed_fields)) {
            // Xử lý giá trị rỗng/null
            if ($value === '' || $value === null) {
                $value = NULL;
            }

            // *** ĐÂY LÀ CHỖ SỬA LỖI QUAN TRỌNG: DÙNG $pdo thay vì $db ***
            $sql = "UPDATE `$table` SET `$field` = :value WHERE `id` = :id";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt) {
                // Gán tham số
                $stmt->bindValue(':value', $value);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Cập nhật thành công!';
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $response['message'] = 'Lỗi khi cập nhật CSDL: ' . ($errorInfo[2] ?? 'Unknown error');
                }
            } else {
                $response['message'] = 'Lỗi khi chuẩn bị câu lệnh CSDL.';
            }
        } else {
            $response['message'] = 'Trường dữ liệu không hợp lệ.';
        }
    }
}

echo json_encode($response);