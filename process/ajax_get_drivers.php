<?php
/**
 * File: process/ajax_get_drivers.php
 * Version: 3.0 (Final - Fixed HY093 error)
 * Description: Fetches drivers for autocomplete.
 */

// --- Khởi tạo và kiểm tra xác thực ---
require_once '../includes/init.php';

// Thiết lập header là JSON
header('Content-Type: application/json; charset=utf-8');

// --- Xử lý yêu cầu ---
try {
    // 1. Kiểm tra kết nối PDO
    if (!isset($pdo) || !$pdo instanceof PDO) {
        echo json_encode(['error' => true, 'message' => 'Database connection failed']);
        exit;
    }

    // 2. Lấy và kiểm tra từ khóa tìm kiếm
    $searchTerm = trim($_GET['term'] ?? '');
    if (empty($searchTerm)) {
        echo json_encode([]);
        exit;
    }

    // 3. Chuẩn bị câu lệnh SQL
    // SỬA LỖI HY093: Dùng dấu chấm hỏi (?) làm tham số vị trí thay vì :term
    // Điều này đảm bảo mỗi điều kiện LIKE nhận được một giá trị riêng, tránh lỗi parameter number.
    $sql = "SELECT id, ten, sdt, bien_so_xe 
            FROM drivers 
            WHERE LOWER(ten) LIKE ? 
               OR LOWER(sdt) LIKE ? 
               OR LOWER(bien_so_xe) LIKE ?
            LIMIT 15";
            
    $stmt = $pdo->prepare($sql);

    // 4. Thực thi truy vấn với mảng tham số
    $likeTerm = '%' . mb_strtolower($searchTerm, 'UTF-8') . '%';
    
    // Cung cấp giá trị cho cả 3 dấu chấm hỏi (?)
    $stmt->execute([$likeTerm, $likeTerm, $likeTerm]);
    
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Định dạng kết quả
    $results = [];
    foreach ($drivers as $driver) {
        $details = "SĐT: " . htmlspecialchars($driver['sdt'] ?? '') . " | Biển số: " . htmlspecialchars($driver['bien_so_xe'] ?? '');
        $results[] = [
            'id'      => (int)$driver['id'],
            'value'   => htmlspecialchars($driver['ten']),
            'label'   => htmlspecialchars($driver['ten']),
            'details' => $details
        ];
    }

    // 6. Trả về kết quả
    echo json_encode($results);

} catch (PDOException $e) {
    // Nếu vẫn có lỗi, in ra để chẩn đoán
    echo json_encode([
        'error' => true, 
        'message' => 'SQL EXCEPTION: ' . $e->getMessage()
    ]);
    exit;
}