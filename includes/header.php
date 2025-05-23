<?php
// quanlykho/includes/header.php
require_once __DIR__ . '/init.php';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang ?? 'vi', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? ($lang['appName'] ?? 'Quản Lý Kho'), ENT_QUOTES, 'UTF-8') ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.3/themes/smoothness/jquery-ui.css">

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/ui/trumbowyg.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/plugins/colors/ui/trumbowyg.colors.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/plugins/table/ui/trumbowyg.table.min.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">

    <?php
    $set_js_vars_path = dirname(__DIR__) . '/config/set_js_vars.php';
    if (file_exists($set_js_vars_path)) {
        require_once $set_js_vars_path;
    } else {
        error_log("CRITICAL: set_js_vars.php not found at " . $set_js_vars_path);
    }
    ?>
</head>
<body>
    <?php
    if (function_exists('is_logged_in') && is_logged_in() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
        $navbar_path = __DIR__ . '/navbar.php';
        if (file_exists($navbar_path)) {
            include $navbar_path;
        } else {
            error_log("Navbar file not found at " . $navbar_path);
        }
    }
    ?>
    <main class="container-fluid mt-3 mb-3 main-content">
        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['success_message']);
        }
        ?>
        <div id="loadingSpinnerOverlay" style="display: none; /* Khởi tạo ẩn */">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div id="loadingSpinnerMessage" class="ms-2 text-white"></div>
    </div>