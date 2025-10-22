<?php
// includes/admin_check.php

if (!isset($pdo)) {
    require_once __DIR__ . '/init.php';
}

require_login(); // đảm bảo đã đăng nhập

if (!is_admin()) {
    // Không phải admin → báo lỗi và quay về dashboard (hoặc login)
    $_SESSION['error_message'] = $lang['access_denied'] . ' - ' . $lang['admin_only'];
    $base = defined('PROJECT_BASE_URL') ? PROJECT_BASE_URL : '/';
    header('Location: ' . $base . 'dashboard.php');
    exit();
}
