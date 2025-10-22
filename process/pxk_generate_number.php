<?php
// File: process/pxk_generate_number.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/init.php';

try {
    if (!$pdo) throw new Exception('DB not available');

    // Tạo bảng nếu chưa có
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pxk_slips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pxk_number VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            UNIQUE KEY uk_pxk_number (pxk_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $today = new DateTime('now');
    $d = $today->format('d');
    $m = $today->format('m');
    $Y = $today->format('Y');
    $ymd = $today->format('Y-m-d');

    // Đếm trong ngày
    $st = $pdo->prepare("SELECT COUNT(*) FROM pxk_slips WHERE DATE(created_at) = :d");
    $st->execute([':d' => $ymd]);
    $count = (int)$st->fetchColumn();
    $next  = $count + 1;
    $seq   = str_pad((string)$next, 2, '0', STR_PAD_LEFT); // 2 chữ số cuối

    $pxk_number = 'PXK' . $d . $m . $Y . $seq; // Ví dụ: PXK1408202501

    // Lưu lại
    $ins = $pdo->prepare("INSERT INTO pxk_slips (pxk_number) VALUES (:n)");
    $ins->execute([':n' => $pxk_number]);

    echo json_encode(['success'=>true, 'pxk_number'=>$pxk_number]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'Error generating PXK number: '.$e->getMessage()]);
}
