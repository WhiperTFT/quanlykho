<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/init.php';
require_login();
// Kiểm tra đăng nhập và quyền admin
if (!is_logged_in()) {
    header("Location: " . PROJECT_BASE_URL . "login.php?message=login_required");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    die("Bạn không có quyền truy cập chức năng này.");
}
$page_title = $lang['sales_orders_management'] ?? 'Purchase Order Management';
$active_sales_quotes = [];
if (function_exists('get_active_sales_quotes_for_linking') && isset($pdo)) {
    // $pdo đã được tạo bởi init.php (do header.php gọi)
    $active_sales_quotes = get_active_sales_quotes_for_linking($pdo);
} else {
    error_log("Function get_active_sales_quotes_for_linking or PDO object not available in sales_orders.php");
    // Có thể gán mảng rỗng hoặc xử lý lỗi nếu cần
    $active_sales_quotes = [];
}
$company_info = null;
try {
    $stmt_company = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
    $company_info = $stmt_company->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error fetching company info for orders: " . $e->getMessage());
    $_SESSION['error_message'] = $lang['database_error'] ?? 'Database error fetching company info.';
}

$initial_orders = [];
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><i class="bi bi-receipt-cutoff me-2"></i><?= $lang['sales_orders'] ?? 'Purchase Orders' ?></h1>
        <button class="btn btn-primary" id="btn-create-new-order">
            <i class="bi bi-plus-circle me-1"></i> <?= $lang['create_new_order'] ?? 'Create New Order' ?>
        </button>
    </div>

    <div class="card shadow-sm mb-4" id="order-form-card" style="display: none;">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0" id="order-form-title"><?= $lang['create_new_order'] ?? 'Create New Order' ?></h5>
        </div>
        <div class="card-body p-4">
            <form id="order-form" novalidate>
                <input type="hidden" name="order_id" id="order_id">

                <div id="pdf-export-content-placeholder"> <?php
                    require __DIR__ . '/includes/document_header.php'; // Thông tin công ty, v.v cho form
                    ?>
                    <?php
                     $partner_type = 'supplier';
                     $partner_label = $lang['supplier'] ?? 'Supplier';
                     $partner_select_label = $lang['select_supplier'] ?? 'Select Supplier';
                     $document_context = 'purchase_order_title';
                     require __DIR__ . '/includes/partner_info_form.php'; // Form thông tin NCC
                     ?>
                    <div class="row mt-3"> <div class="col-md-6"> <label for="order_quote_id_form" class="form-label"><?= $lang['link_to_sales_quote'] ?? 'Link to Sales Quote' ?></label>
                                        <select class="form-select form-select-sm" id="order_quote_id_form" name="quote_id">
                                            <option value=""><?= $lang['none'] ?? '-- None --' ?></option>
                                            <?php if (!empty($active_sales_quotes)): ?>
                                                <?php 
                                                $display_date_format = $user_date_format ?? 'd/m/Y'; 
                                                foreach ($active_sales_quotes as $sq): 
                                                    $quote_display_date = '';
                                                    if (!empty($sq['quote_date'])) {
                                                        try {
                                                            $dateObj = new DateTime($sq['quote_date']);
                                                            $quote_display_date = $dateObj->format($display_date_format);
                                                        } catch (Exception $e) {
                                                            $quote_display_date = $sq['quote_date']; 
                                                        }
                                                    }
                                                ?>
                                                    <option value="<?= htmlspecialchars((string)$sq['id'], ENT_QUOTES, 'UTF-8') ?>" data-customer-id="<?= htmlspecialchars((string)($sq['customer_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($sq['quote_number'] . (!empty($sq['customer_name']) ? ' - ' . $sq['customer_name'] : '') . (!empty($quote_display_date) ? ' (' . $quote_display_date . ')' : ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <small class="form-text text-muted"><?= $lang['link_quote_note'] ?? 'Select a quote. If selected, order items might be auto-populated.' ?></small>
                                    </div>
                                </div>
                    <hr class="my-4">
                    <h5 class="mb-3"><?= $lang['item_details'] ?? 'Item Details' ?></h5>
                    <?php require __DIR__ . '/includes/item_details_table.php'; // Bảng chi tiết sản phẩm ?>
                    <?php require __DIR__ . '/includes/document_summary.php'; // Phần tóm tắt tổng cộng ?>
                    <hr class="my-4">
                </div>


                <div class="text-end mt-4">
                    <button type="button" class="btn btn-secondary me-2" id="btn-cancel-order-form">
                        <i class="bi bi-x-circle me-1"></i><?= $lang['cancel'] ?? 'Cancel' ?>
                    </button>

                    <button type="submit" class="btn btn-success" id="btn-save-order">
                        <i class="bi bi-save me-1"></i> <span class="save-text"><?= $lang['save_order'] ?? 'Save Order' ?></span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>

                    <button id="toggle-signature" class="btn btn-sm btn-outline-secondary mt-1 me-2" type="button">
                        <?= $lang['show_signature'] ?? 'Show Signature' ?>
                    </button>
                    <div id="form-error-message" class="alert alert-danger mt-3 d-none" role="alert"></div>
                </div>
            </form>
        </div>
    </div>

    <h3 class="mt-4 mb-3" id="order-list-title"><?= $lang['orders_list'] ?? 'Orders List' ?></h3>
    <?php
     $list_data = $initial_orders;
     $list_type = 'sales_order';
     require __DIR__ . '/includes/document_list.php';
     ?>
</div>


<?php
// Truyền dữ liệu PHP sang Javascript
echo '<script>const LANG = ' . json_encode($lang) . ';</script>';
echo '<script>const COMPANY_INFO = ' . json_encode($company_info ?? null) . ';</script>';
echo '<script>
    const AJAX_URL = {
        sales_order: "process/sales_order_handler.php",
        partner_search: "process/partners_handler.php",
        product_search: "process/product_handler.php"
        // Thêm export_pdf_url nếu bạn muốn định nghĩa sẵn, hoặc tạo động trong JS
        // export_pdf: "process/export_pdf.php" 
    };
</script>';

require_once __DIR__ . '/includes/footer.php';

// Nhúng Thư viện JS bên ngoài
echo '<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">';
echo '<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>';

echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';
echo '<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>';
echo '<script src="https://npmcdn.com/flatpickr/dist/l10n/vn.js"></script>';

echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">';
echo '<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>';
echo '<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>';


$js_company_signature_path = 'uploads/sign.png'; // Giá trị mặc định fallback
if (isset($company_info) && is_array($company_info) && !empty($company_info['signature_path'])) {
    $js_company_signature_path = htmlspecialchars(ltrim($company_info['signature_path'], '/'));
} else {

    if (!isset($company_info)) {
        error_log("Warning: \$company_info is not set when trying to get signature_path for JS in sales_orders.php.");
    } elseif (empty($company_info['signature_path'])) {
        error_log("Warning: \$company_info['signature_path'] is empty in sales_orders.php.");
    }
}
// --- KẾT THÚC SỬA ĐOẠN NÀY ---
?>

<script>
    window.APP_SETTINGS = window.APP_SETTINGS || {};
    window.APP_SETTINGS.buyerSignatureUrl = '<?php echo $js_company_signature_path; ?>';
    console.log('Signature URL for JS on sales_orders.php:', window.APP_SETTINGS.buyerSignatureUrl);
    window.APP_CONTEXT = {
        type: 'order', // Xác định loại tài liệu là 'order'
        documentName: '<?= $lang['sales_order_short'] ?? 'Đơn hàng' ?>', // Tên ngắn gọn của tài liệu, dùng cho thông báo

    };
    console.log('APP_CONTEXT for sales_orders.php:', window.APP_CONTEXT);
</script>
<?php
   require_once __DIR__ . '/config/set_js_vars.php';
  
?>

<script src="assets/js/sales_orders_config.js"></script>
<script src="assets/js/sales_orders_helpers.js"></script>
<script src="assets/js/sales_orders_form.js"></script>
<script src="assets/js/sales_orders_datatable.js"></script> 
<script src="assets/js/sales_orders_email.js"></script>
<script src="assets/js/sales_orders_pdf.js"></script>
<script src="assets/js/sales_orders_events.js"></script>
<script src="assets/js/sales_orders_main.js"></script>