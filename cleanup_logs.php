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

$LOG_RETENTION_DAYS = 30;

try {
    $threshold_date = date('Y-m-d H:i:s', strtotime("-{$LOG_RETENTION_DAYS} days"));
    
    $stmt = $pdo->prepare("DELETE FROM user_logs WHERE created_at < :thresh");
    $stmt->execute([':thresh' => $threshold_date]);
    $deleted = $stmt->rowCount();
    
    if (!$is_cli) {
        // Record trace of manual invocation
        require_once __DIR__ . '/includes/logging.php';
        write_user_log('DELETE', 'system', "Chạy dọn dẹp Log tự động: $deleted file đã bị loại bỏ.", ['reclaimed_rows' => $deleted], 'warning');
    }
    
    echo json_encode(['success' => true, 'message' => "Extracted $deleted legacy rows exceeding $LOG_RETENTION_DAYS Days."]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
