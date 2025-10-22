<?php
// File: process/save_driver_adjustments.php
require_once '../includes/init.php';

header('Content-Type: application/json');

// Lấy dữ liệu từ yêu cầu POST
$input = json_decode(file_get_contents('php://input'), true);

$driver_id = $input['driver_id'] ?? 0;
$year = $input['year'] ?? 0;
$month = $input['month'] ?? 0;
$adjustments = $input['adjustments'] ?? [];

if (empty($driver_id) || empty($year) || empty($month)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin tài xế, năm hoặc tháng.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Xóa tất cả các điều chỉnh cũ của tài xế trong kỳ này để tránh trùng lặp
    $delete_stmt = $pdo->prepare(
        "DELETE FROM driver_adjustments WHERE driver_id = :driver_id AND year = :year AND month = :month"
    );
    $delete_stmt->execute([
        ':driver_id' => $driver_id,
        ':year' => $year,
        ':month' => $month
    ]);

    // 2. Thêm lại các điều chỉnh mới từ giao diện
    $insert_stmt = $pdo->prepare(
        "INSERT INTO driver_adjustments (driver_id, year, month, description, type, amount) 
         VALUES (:driver_id, :year, :month, :description, :type, :amount)"
    );

    foreach ($adjustments as $adj) {
        // Chỉ xử lý các dòng có mô tả
        if (!empty(trim($adj['description']))) {
            $insert_stmt->execute([
                ':driver_id'   => $driver_id,
                ':year'        => $year,
                ':month'       => $month,
                ':description' => $adj['description'],
                ':type'        => $adj['type'],
                // Chuyển đổi số tiền từ chuỗi có dấu chấm về số
                ':amount'      => (float)str_replace('.', '', $adj['amount'])
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Đã lưu các điều chỉnh thành công!']);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Save Driver Adjustments Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}
