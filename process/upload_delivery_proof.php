<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

$order_id = $_POST['order_id'] ?? 0;
$type = $_POST['type'] ?? '';
$file = $_FILES['delivery_proof'] ?? null;

if (empty($order_id) || empty($type) || !$file) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin.']);
    exit;
}

try {
    $upload_dir = '../uploads/proof/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($file['name']);
    $file_path = $upload_dir . $file_name;

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $stmt = $pdo->prepare(
            "INSERT INTO delivery_attachments (order_id, type, path) VALUES (:order_id, :type, :path)"
        );
        $stmt->execute([
            ':order_id' => $order_id,
            ':type' => $type,
            ':path' => $file_name
        ]);

        echo json_encode(['success' => true, 'message' => 'File đã được tải lên thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể tải lên file.']);
    }
} catch (Exception $e) {
    error_log("Upload Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}
?>