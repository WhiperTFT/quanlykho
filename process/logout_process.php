<?php
// process/logout_process.php
require_once __DIR__ . '/../includes/init.php'; // Cần init để log và session

// Ghi log đăng xuất (nếu user đã đăng nhập)
if (is_logged_in()) {
    log_activity($_SESSION['user_id'] ?? null, $lang['log_logout'] ?? 'User logged out.', $pdo);
}

// Xóa tất cả các biến session
$_SESSION = array();

// Hủy session cookie nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy session hoàn toàn
session_destroy();

// Chuyển hướng về trang đăng nhập
header("Location: ../login.php");
exit();
?>