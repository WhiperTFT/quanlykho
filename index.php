<?php
// index.php
require_once __DIR__ . '/includes/init.php';
require_login();
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