<?php
// index.php
require_once './includes/init.php';
require_once __DIR__ . '../includes/auth_check.php'; // Đảm bảo người dùng đã đăng nhập
require_once __DIR__ . '../includes/admin_check.php'; // Kiểm tra quyền admin

if (is_logged_in()) {
    // Nếu đã đăng nhập, chuyển đến dashboard
    header("Location: dashboard.php");
    exit();
} else {
    // Nếu chưa đăng nhập, chuyển đến trang login
    header("Location: login.php");
    exit();
}
?>