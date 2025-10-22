<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$attachment_id = $input['attachment_id'] ?? 0;

if (empty($attachment_id)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu ID attachment.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT path, type FROM delivery_attachments WHERE id = :id");
    $stmt->execute([':id' => $attachment_id]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attachment && $attachment['type'] == 'file') {
        $file_path = '../uploads/proof/' . $attachment['path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM delivery_attachments WHERE id = :id");
    $stmt->execute([':id' => $attachment_id]);

    echo json_encode(['success' => true, 'message' => 'Attachment đã được xóa thành công!']);
} catch (Exception $e) {
    error_log("Remove Attachment Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}
?>