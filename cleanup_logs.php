<?php
// cleanup_logs.php
// Script designated for Web invocation or Cron Jobs execution to preserve table speeds

$is_cli = php_sapi_name() === 'cli';
if (!$is_cli) {
    require_once __DIR__ . '/includes/init.php';
    if (!is_admin()) {
        http_response_code(403);
        exit("Admin clearance required to trigger manual global cleanup via GET");
    }
} else {
    // If running via pure CLI/Cron, instantiate DB silently
    require_once __DIR__ . '/config/database.php';
    try {
        $dsn_init = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn_init, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch(Exception $e) { exit("DB Connection Fail"); }
}

$LOG_RETENTION_DAYS = isset($_GET['days']) ? $_GET['days'] : 30;

try {
    if ($LOG_RETENTION_DAYS === 'all') {
        $stmt = $pdo->prepare("DELETE FROM user_logs");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        $message = "Đã xóa TOÀN BỘ nhật ký hệ thống ($deleted bản ghi).";
    } else {
        $days = (int)$LOG_RETENTION_DAYS;
        if ($days <= 0) $days = 30;
        
        $threshold_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stmt = $pdo->prepare("DELETE FROM user_logs WHERE created_at < :thresh");
        $stmt->execute([':thresh' => $threshold_date]);
        $deleted = $stmt->rowCount();
        $message = "Đã loại bỏ $deleted bản ghi cũ hơn $days ngày.";
    }
    
    if (!$is_cli) {
        // Record trace of manual invocation
        require_once __DIR__ . '/includes/logging.php';
        write_user_log('DELETE', 'system', "Dọn dẹp nhật ký: " . $message, ['reclaimed_rows' => $deleted, 'mode' => $LOG_RETENTION_DAYS], 'warning');
    }
    
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
