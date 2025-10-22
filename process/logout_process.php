<?php
require_once __DIR__ . '/../includes/init.php';

if (isset($_SESSION['user_id'])) {
    // Ghi log đúng thứ tự tham số
    write_user_log($pdo, (int)$_SESSION['user_id'], 'logout', 'Đăng xuất khỏi hệ thống');

    // Xóa remember_token trong DB
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Xóa session
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Xóa cookie
setcookie('remember_token', '', time() - 3600, "/");

// Hủy session
session_destroy();

// Chuyển hướng
header("Location: ../login.php?message=logged_out");
exit();
