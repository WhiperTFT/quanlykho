<?php
// includes/auth_check.php
function require_login() {
    // Dùng hằng PROJECT_BASE_URL do init.php đã define trước khi require file này
    $base = defined('PROJECT_BASE_URL') ? PROJECT_BASE_URL : '/';

    if (!is_logged_in()) {
        // Nếu là AJAX / JSON → trả 401 để JS tự redirect
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                  || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($isAjax) {
            if (!headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'message' => 'login_required']);
            exit();
        }

        // Trang thường → redirect tuyệt đối + kèm redirect back
        $current = $_SERVER['REQUEST_URI'] ?? ($base . 'dashboard.php');
        $redirect = $base . 'login.php?message=login_required&redirect=' . urlencode($current);
        header('Location: ' . $redirect);
        exit();
    }
}
