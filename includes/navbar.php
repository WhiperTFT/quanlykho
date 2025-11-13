<?php
// navbar.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Lỗi nghiêm trọng: Biến kết nối CSDL ($pdo) không tồn tại hoặc không hợp lệ khi nạp navbar.');
}
if (!isset($lang)) {
    die('Lỗi: Dữ liệu ngôn ngữ chưa được nạp.');
}
if (!function_exists('has_permission')) {
    die('Lỗi: Chưa có hàm has_permission');
}
if (!function_exists('is_logged_in')) {
    die('Lỗi: Chưa có hàm is_logged_in');
}

require_once __DIR__ . '/menu_functions.php';

$all_menus = get_all_menus_from_db($pdo);
$menu_html_output = build_navbar_html($all_menus);

$isLoggedIn = is_logged_in();

if ($isLoggedIn) {
    $usernameDisplay = $_SESSION['username'];
} else {
    $usernameDisplay = null; // Không dùng chữ Khách nữa
}

$current_lang_code = $_SESSION['lang_code'] ?? 'vi';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm main-navbar">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <i class="bi bi-box-seam-fill me-2 fs-4"></i>
            <span class="fw-bold"><?= htmlspecialchars($lang['appName'] ?? 'InventoryApp') ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbarContent" aria-controls="mainNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?= $menu_html_output ?>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-translate me-1"></i>
                        <span class="d-none d-lg-inline ms-1">
                            <?= $current_lang_code == 'vi' ? ($lang['vietnamese'] ?? 'Tiếng Việt') : ($lang['english'] ?? 'English') ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                        <li><a class="dropdown-item <?= $current_lang_code == 'en' ? 'active' : '' ?>" href="?lang=en">English</a></li>
                        <li><a class="dropdown-item <?= $current_lang_code == 'vi' ? 'active' : '' ?>" href="?lang=vi">Tiếng Việt</a></li>
                    </ul>
                </li>

                <?php if (is_logged_in()): ?>
                    <li class="nav-item d-none d-lg-block">
                        <span class="navbar-text mx-2 text-white-50">|</span>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1 fs-5"></i>
                            <span class="d-none d-lg-inline"><?= htmlspecialchars($usernameDisplay) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="user.php"><i class="bi bi-person-badge me-2"></i><?= htmlspecialchars($lang['profile'] ?? 'Hồ sơ') ?></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="process/logout_process.php"><i class="bi bi-box-arrow-right me-2"></i><?= htmlspecialchars($lang['logout'] ?? 'Đăng xuất') ?></a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i><?= htmlspecialchars($lang['login'] ?? 'Đăng nhập') ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
