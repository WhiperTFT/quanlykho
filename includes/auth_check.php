<?php
// includes/auth_check.php

function require_login() {
    $base = defined('PROJECT_BASE_URL') ? PROJECT_BASE_URL : '/';

    if (!is_logged_in()) {
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

        $current = $_SERVER['REQUEST_URI'] ?? ($base . 'dashboard.php');
        $redirect = $base . 'login.php?message=login_required&redirect=' . urlencode($current);
        header('Location: ' . $redirect);
        exit();
    }
}

// ----------------------------------------------------
// GLOBAL AUTH ENFORCEMENT
// ----------------------------------------------------
// Define central sharing secret here for security components to inherit
if (!defined('SHARE_SECRET')) {
    define('SHARE_SECRET', 'qlkho_s3cr3t_2026!');
}

$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Exception Whitelist
$public_routes = [
    '/login.php',
    '/share.php',
    '/assets/',
    '/driver_trips.php',
    '/process/get_attachments.php'
];

$is_cli = php_sapi_name() === 'cli';
$is_public = $is_cli;

if (!$is_public) {
    foreach ($public_routes as $route) {
        if (strpos($current_path, $route) !== false) {
            $is_public = true;
            break;
        }
    }
    // Specific condition for public read-only details
    if (!$is_public && strpos($current_path, '/process/sales_order_handler.php') !== false) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_details') {
            $is_public = true;
        }
    }
}

// If URI is an authentic application view or backend process but not public, intercept immediately.
if (!$is_public) {
    require_login();
}
?>
