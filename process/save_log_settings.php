<?php
// process/save_log_settings.php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json');

// Bảo mật: Chỉ cho phép admin đã đăng nhập
if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Bạn không có quyền thực hiện thao tác này.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auto_days = isset($_POST['auto_days']) ? (int)$_POST['auto_days'] : 30;
    
    // Đảm bảo thư mục tồn tại
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    $settings_file = $dir . '/settings.json';
    $config = [
        'auto_cleanup_days' => $auto_days,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $_SESSION['username'] ?? 'admin'
    ];
    
    if (file_put_contents($settings_file, json_encode($config, JSON_PRETTY_PRINT))) {
        // Ghi log hành động cấu hình
        write_user_log('UPDATE', 'system', "Đã thay đổi cấu hình tự động dọn dẹp log: $auto_days ngày.", ['days' => $auto_days], 'info');
        
        echo json_encode([
            'success' => true, 
            'message' => "Đã lưu cấu hình dọn dẹp tự động: " . ($auto_days > 0 ? "$auto_days ngày" : "Tắt")
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Không thể ghi tệp cấu hình.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Phương thức yêu cầu không hợp lệ.']);
}
