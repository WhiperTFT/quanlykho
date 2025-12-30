<?php
// includes/header.php
require_once __DIR__ . '/init.php'; // Cung cấp $current_lang, $lang, $page_title
header('Permissions-Policy: fullscreen=(self)');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang ?? 'vi', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? ($lang['appName'] ?? 'Inventory Management'), ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Bootstrap 5.3.3 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- DataTables + jQuery UI -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <!-- Dropzone CSS (CDN) -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css"
    />

    <!-- Upload Enhance CSS (custom) -->
        <link
        rel="stylesheet"
        href="assets/css/upload-enhance.css"
    />

    <!-- Custom CSS (kèm cache busting thông minh) -->
    <link rel="stylesheet" href="<?= PROJECT_BASE_URL; ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
if (empty($skip_login_check)) {
    require_login();
}

if (is_logged_in() || (!empty($force_navbar_always))) {
    include_once __DIR__ . '/navbar.php';
}
?>
<main class="container mt-4 mb-5 main-content">
<?php
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
        . htmlspecialchars($_SESSION['error_message']) .
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
        . htmlspecialchars($_SESSION['success_message']) .
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['success_message']);
}
?>
