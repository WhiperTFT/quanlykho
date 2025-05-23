<?php
// includes/auth_check.php
// File này được gọi sau init.php, không cần tự include init.php nữa

// Đảm bảo session đã được bắt đầu và các biến ngôn ngữ/hàm từ init.php đã có
// (Kiểm tra này có thể không cần thiết nếu bạn chắc chắn init.php luôn được include trước đó)
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Chỉ để đảm bảo, nhưng init.php đã làm rồi
}
// Nếu các hàm như is_logged_in() không tự động được định nghĩa bởi init.php
// thì bạn cần đảm bảo chúng đã được include hoặc định nghĩa ở đâu đó
// Tuy nhiên, init.php đã định nghĩa is_logged_in() và has_permission()
// nên không cần kiểm tra isset($lang) hoặc function_exists ở đây nữa.

if (!function_exists('is_logged_in')) {
    // Fallback: Nếu vì lý do nào đó init.php không chạy hàm này, hãy thông báo lỗi
    die('Error: is_logged_in function not defined. init.php might not be included correctly or contains errors.');
}

if (!is_logged_in()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . PROJECT_BASE_URL . "login.php"); // Sử dụng PROJECT_BASE_URL để URL đúng
    exit();
}
?>