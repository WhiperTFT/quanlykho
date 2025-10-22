<?php
session_start();

// Đặt ngôn ngữ nếu hợp lệ
if (isset($_GET['lang']) && in_array($_GET['lang'], ['vi', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Trở về trang trước (nếu có)
if (!empty($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Nếu không có HTTP_REFERER, thì trở về đúng thư mục dự án
$base_path = dirname($_SERVER['PHP_SELF']); // /quanlykho/includes → /quanlykho
$redirect_url = $base_path . '/../index.php';
$redirect_url = str_replace('\\', '/', $redirect_url);

header('Location: ' . $redirect_url);
exit;
