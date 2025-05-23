<?php
// includes/admin_check.php
// File này được gọi sau auth_check.php (và cả init.php), không cần tự include init.php hay auth_check.php nữa

// Đảm bảo các hàm từ init.php và auth_check.php đã có
if (!function_exists('is_logged_in') || !function_exists('is_admin')) {
    die('Error: Core functions not defined. Check your include order for init.php and auth_check.php.');
}

if (!is_logged_in()) {
    // Người dùng chưa đăng nhập, auth_check.php đã chuyển hướng rồi,
    // nhưng nếu ai đó cố gắng truy cập admin_check.php trực tiếp, vẫn đảm bảo
    header("Location: " . PROJECT_BASE_URL . "login.php");
    exit();
}

if (!is_admin()) {
    log_activity($_SESSION['user_id'] ?? null, "ACCESS DENIED: Attempted to access admin area.", $pdo);
    $_SESSION['error_message'] = $lang['access_denied'] . ' - ' . ($lang['admin_only'] ?? 'Admin access only.');
    header("Location: " . PROJECT_BASE_URL . "dashboard.php");
    exit();
}
?>