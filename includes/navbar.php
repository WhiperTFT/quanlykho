<?php
// Đảm bảo các biến và hàm từ init.php đã được tải
// init.php được include trong header.php, và header.php được include trước navbar trong cấu trúc hiện tại
// Tuy nhiên, nếu navbar được include trực tiếp ở đâu đó, cần đảm bảo init.php đã chạy trước đó.
// Các kiểm tra dưới đây giúp phát hiện lỗi nếu init.php chưa được include.
if (!isset($lang)) { die('Language data not loaded in navbar'); }
if (!function_exists('has_permission')) { die('Permission helper function not loaded in navbar'); }
if (!function_exists('is_logged_in')) { die('is_logged_in helper function not loaded in navbar'); }


// Các hàm tiện ích is_nav_active, is_nav_dropdown_active
// Cập nhật hàm is_nav_active để xử lý tốt hơn nếu các file nằm trong thư mục con
if (!function_exists('is_nav_active')) {
    /**
     * Kiểm tra xem trang hiện tại có phải là trang được chỉ định không.
     * So sánh basename của script hiện tại với tên file trang.
     * @param string $pageName Tên file của trang (ví dụ: 'dashboard.php')
     * @return string 'active' nếu là trang hiện tại, ngược lại ''.
     */
    function is_nav_active(string $pageName): string {
        return basename($_SERVER['PHP_SELF']) === $pageName ? 'active' : '';
    }
}
if (!function_exists('is_nav_dropdown_active')) {
     /**
     * Kiểm tra xem trang hiện tại có nằm trong danh sách các trang con của dropdown không.
     * Thêm class 'active' cho mục dropdown.
     * @param array $pageNames Mảng chứa tên các file trang con (ví dụ: ['user.php', 'roles.php'])
     * @return string 'active' nếu trang hiện tại nằm trong mảng, ngược lại ''.
     */
    function is_nav_dropdown_active(array $pageNames): string {
        return in_array(basename($_SERVER['PHP_SELF']), $pageNames) ? 'active' : '';
    }
}


$usernameDisplay = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ($lang['guest'] ?? 'Guest');

// Lấy mã ngôn ngữ hiện tại từ session hoặc request, mặc định là 'en'
// Đảm bảo $current_lang_code được định nghĩa
if (isset($_GET['lang'])) {
    $current_lang_code = $_GET['lang'];
    $_SESSION['lang'] = $current_lang_code; // Lưu vào session để duy trì trạng thái
} elseif (isset($_SESSION['lang'])) {
    $current_lang_code = $_SESSION['lang'];
} else {
    $current_lang_code = 'en'; // Mặc định là tiếng Anh
}

?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm main-navbar">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
             <i class="bi bi-box-seam-fill me-2 fs-4"></i> <span class="fw-bold"><?= $lang['appName'] ?? 'InventoryApp' ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbarContent" aria-controls="mainNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php // Hiển thị Dashboard nếu có quyền view_dashboard ?>
                <?php if (has_permission('dashboard_view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= is_nav_active('dashboard.php') ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i><?= $lang['dashboard'] ?? 'Dashboard' ?>
                    </a>
                </li>
                <?php endif; ?>

                <?php // Hiển thị menu Thao tác nếu có quyền xem menu operations_menu_view ?>
                <?php
                // Kiểm tra nếu có bất kỳ quyền 'view' nào trong nhóm operations thì hiển thị dropdown
                $show_operations_dropdown = has_permission('catalog_view') ||
                                            has_permission('sales_orders_create') ||
                                            has_permission('quotes_view') ||
                                            has_permission('warehouse_dispatch_view') ||
                                            has_permission('orders_list_view') ||
                                            has_permission('delivery_comparison_view') || // Thêm quyền cho delivery_comparison.php
                                            has_permission('drivers_view') || // Thêm quyền cho drivers.php
                                            has_permission('warehouse_status_view');
                ?>
                 <?php if ($show_operations_dropdown): ?>
                 <li class="nav-item dropdown <?= is_nav_dropdown_active(['catalog.php', 'sales_orders.php', 'sales_quotes.php', 'warehouse_dispatch.php', 'orders_list.php', 'warehouse_status.php', 'delivery_comparison.php', 'drivers.php']) ?>">
                    <a class="nav-link dropdown-toggle" href="#" id="operationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-clipboard-data me-1"></i><?= $lang['operations'] ?? 'Operations' ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="operationsDropdown">
                        <?php // Hiển thị từng mục trong dropdown nếu có quyền tương ứng ?>
                        <?php if (has_permission('catalog_view')): ?>
                        <li>
                            <a class="dropdown-item <?= is_nav_active('catalog.php') ?>" href="catalog.php">
                                <i class="bi bi-journal-bookmark-fill me-2"></i><?= $lang['catalog'] ?? 'Catalog' ?>
                            </a>
                        </li>
                         <li><hr class="dropdown-divider"></li>
                         <?php endif; ?>

                         <?php if (has_permission('sales_orders_create')): // Ví dụ quyền tạo đơn hàng bán ?>
                         <li>
                            <a class="dropdown-item <?= is_nav_active('sales_orders.php') ?>" href="sales_orders.php">
                                <i class="bi bi-cart-plus me-2"></i><?= $lang['create_sales_order'] ?? 'Create Sales Order' ?>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (has_permission('quotes_view')): // Ví dụ quyền xem báo giá ?>
                        <li>
                            <a class="dropdown-item <?= is_nav_active('sales_quotes.php') ?>" href="sales_quotes.php">
                                <i class="bi bi-file-earmark-text me-2"></i><?= $lang['quotes'] ?? 'Quotes' ?>
                            </a>
                        </li>
                        <?php endif; ?>

                         <?php if (has_permission('warehouse_dispatch_view')): // Ví dụ quyền xem xuất kho ?>
                         <li>
                            <a class="dropdown-item <?= is_nav_active('warehouse_dispatch.php') ?>" href="warehouse_dispatch.php">
                                <i class="bi bi-truck me-2"></i><?= $lang['warehouse_dispatch'] ?? 'Warehouse Dispatch' ?>
                            </a>
                        </li>
                         <?php endif; ?>

                        <?php if (has_permission('delivery_comparison_view')): // Thêm quyền cho delivery_comparison.php ?>
                        <li>
                            <a class="dropdown-item <?= is_nav_active('delivery_comparison.php') ?>" href="delivery_comparison.php">
                                <i class="bi bi-truck-flatbed me-2"></i><?= $lang['delivery_comparison'] ?? 'Delivery Comparison' ?>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (has_permission('drivers_view')): // Thêm quyền cho drivers.php ?>
                        <li>
                            <a class="dropdown-item <?= is_nav_active('drivers.php') ?>" href="drivers.php">
                                <i class="bi bi-person-badge me-2"></i><?= $lang['drivers'] ?? 'Drivers' ?>
                            </a>
                        </li>
                        <?php endif; ?>

                         <?php
                            // Hiển thị divider chỉ khi có ít nhất 2 mục trở lên trong dropdown
                             $visible_operations_items = (int)has_permission('catalog_view') +
                                                         (int)has_permission('sales_orders_create') +
                                                         (int)has_permission('quotes_view') +
                                                         (int)has_permission('warehouse_dispatch_view') +
                                                         (int)has_permission('delivery_comparison_view') + // Thêm vào tính toán
                                                         (int)has_permission('drivers_view') + // Thêm vào tính toán
                                                         (int)has_permission('orders_list_view') +
                                                         (int)has_permission('warehouse_status_view');
                             if ($visible_operations_items > 0 && (has_permission('orders_list_view') || has_permission('warehouse_status_view'))):
                         ?>
                         <li><hr class="dropdown-divider"></li>
                         <?php endif; ?>


                         <?php if (has_permission('orders_list_view')): // Ví dụ quyền xem danh sách đơn hàng ?>
                         <li>
                            <a class="dropdown-item <?= is_nav_active('orders_list.php') ?>" href="orders_list.php">
                                <i class="bi bi-list-check me-2"></i><?= $lang['orders_list'] ?? 'Orders List' ?>
                            </a>
                         </li>
                         <?php endif; ?>

                         <?php if (has_permission('warehouse_status_view')): // Ví dụ quyền xem tình trạng kho ?>
                         <li>
                            <a class="dropdown-item <?= is_nav_active('warehouse_status.php') ?>" href="warehouse_status.php">
                                <i class="bi bi-house-door me-2"></i><?= $lang['warehouse_status'] ?? 'Warehouse Status' ?>
                            </a>
                         </li>
                         <?php endif; ?>

                    </ul>
                </li>
                <?php endif; // End if show_operations_dropdown ?>

                <?php // Hiển thị menu Quản lý nếu có quyền xem menu management_menu_view ?>
                <?php
                // Kiểm tra nếu có bất kỳ quyền 'view' nào trong nhóm management thì hiển thị dropdown
                $show_management_dropdown = has_permission('company_info_view') ||
                                            has_permission('partners_view') ||
                                            has_permission('units_view') ||
                                            has_permission('users_view');
                ?>
                <li class="nav-item dropdown <?= is_nav_dropdown_active(['company_info.php', 'partners.php', 'units.php', 'user.php']) ?>">
                <?php if ($show_management_dropdown): ?>
                     <a class="nav-link dropdown-toggle" href="#" id="managementDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear me-1"></i><?= $lang['management'] ?? 'Management' ?>
                     </a>
                     <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="managementDropdown">
                         <?php if (has_permission('company_info_view')): // Ví dụ quyền xem thông tin công ty ?>
                         <li>
                             <a class="dropdown-item <?= is_nav_active('company_info.php') ?>" href="company_info.php">
                                 <i class="bi bi-building me-2"></i><?= $lang['company_info'] ?? 'Company Info' ?>
                             </a>
                         </li>
                         <?php endif; ?>

                         <?php if (has_permission('partners_view')): // Ví dụ quyền xem đối tác ?>
                         <li>
                            <a class="dropdown-item <?= is_nav_active('partners.php') ?>" href="partners.php">
                                <i class="bi bi-people-fill me-2"></i><?= $lang['partners'] ?? 'Partners' ?>
                            </a>
                         </li>
                         <?php endif; ?>

                         <?php if (has_permission('units_view')): // Ví dụ quyền xem đơn vị tính ?>
                         <li>
                            <a class="dropdown-item <?= is_nav_active('units.php') ?>" href="units.php">
                                <i class="bi bi-rulers me-2"></i><?= $lang['units_of_measurement'] ?? 'Units' ?>
                            </a>
                         </li>
                         <?php endif; ?>

                         <?php if (has_permission('users_view')): // Ví dụ quyền xem người dùng ?>
                         <li>
                            <a class="dropdown-item <?= is_nav_active('user.php') ?>" href="user.php"> <i class="bi bi-person-gear me-2"></i><?= $lang['users'] ?? 'Users' ?>
                            </a>
                         </li>
                         <?php endif; ?>
                     </ul>
                <?php endif; // End if show_management_dropdown ?>
                </li>

            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <?php // Hiển thị mục ngôn ngữ (thường không cần quyền) ?>
                 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-translate"></i>
                        <span class="d-none d-lg-inline ms-1">
                            <?php
                                // Hiển thị tên ngôn ngữ dựa vào $current_lang_code
                                if ($current_lang_code == 'vi') {
                                    echo $lang['vietnamese'] ?? 'Tiếng Việt';
                                } else {
                                    echo $lang['english'] ?? 'English';
                                }
                            ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="languageDropdown">
                        <li>
                            <a class="dropdown-item <?= $current_lang_code == 'en' ? 'active' : '' ?>" href="?lang=en">
                                <i class="bi bi-flag me-2"></i><?= $lang['english'] ?? 'English' ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $current_lang_code == 'vi' ? 'active' : '' ?>" href="?lang=vi">
                                <i class="bi bi-flag me-2"></i><?= $lang['vietnamese'] ?? 'Tiếng Việt' ?>
                            </a>
                        </li>
                    </ul>
                </li>

                <?php // Hiển thị thông tin user và logout chỉ khi đã đăng nhập ?>
                <?php if (is_logged_in()): ?>
                <li class="nav-item d-none d-lg-block">
                     <span class="navbar-text mx-2 text-white-50">|</span>
                </li>

                 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1 fs-5"></i>
                        <span class="d-none d-lg-inline"><?= $usernameDisplay ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="userDropdown">
                        <li>
                           <a class="dropdown-item" href="user.php">
                               <i class="bi bi-person-badge me-2"></i><?= $lang['profile'] ?? 'Profile' ?>
                           </a>
                       </li>
                       <li><hr class="dropdown-divider"></li>
                       <li>
                           <a class="dropdown-item link-danger" href="process/logout_process.php">
                               <i class="bi bi-box-arrow-right me-2"></i><?= $lang['logout'] ?? 'Logout' ?>
                           </a>
                       </li>
                    </ul>
                </li>
                <?php else: // Nếu chưa đăng nhập, hiển thị link Login ?>
                     <li class="nav-item">
                        <a class="nav-link <?= is_nav_active('login.php') ?>" href="login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i><?= $lang['login'] ?? 'Login' ?>
                        </a>
                     </li>
                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>