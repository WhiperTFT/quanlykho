<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$order_id = $input['order_id'] ?? 0;
$type = $input['type'] ?? '';
$url = $input['url'] ?? '';

if (empty($order_id) || empty($type) || empty($url)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO delivery_attachments (order_id, type, path) VALUES (:order_id, :type, :path)"
    );
    $stmt->execute([
        ':order_id' => $order_id,
        ':type' => $type,
        ':path' => $url
    ]);

    echo json_encode(['success' => true, 'message' => 'Link đã được thêm thành công!']);
} catch (Exception $e) {
    error_log("Add URL Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}
?>