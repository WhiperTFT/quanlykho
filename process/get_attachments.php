<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (empty($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu order_id.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, type, path FROM delivery_attachments WHERE order_id = :order_id");
    $stmt->execute([':order_id' => $order_id]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'attachments' => $attachments]);
} catch (Exception $e) {
    error_log("Get Attachments Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}
?>