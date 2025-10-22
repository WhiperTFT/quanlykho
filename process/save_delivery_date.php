<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$order_id = $input['order_id'] ?? 0;
$delivery_date = $input['delivery_date'] ?? '';

if (empty($order_id) || empty($delivery_date)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin order_id hoặc ngày giao.']);
    exit;
}

try {
    $check_stmt = $pdo->prepare("SELECT id FROM driver_adjustments WHERE order_id = :order_id");
    $check_stmt->execute([':order_id' => $order_id]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE driver_adjustments SET delivery_date = :delivery_date WHERE order_id = :order_id"
        );
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO driver_adjustments (order_id, delivery_date) VALUES (:order_id, :delivery_date)"
        );
    }

    $stmt->execute([
        ':order_id' => $order_id,
        ':delivery_date' => $delivery_date
    ]);

    echo json_encode(['success' => true, 'message' => 'Ngày giao đã được lưu thành công!']);
} catch (Exception $e) {
    error_log("Save Delivery Date Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}
?>