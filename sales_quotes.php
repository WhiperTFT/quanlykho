<?php
// File: sales_quote.php (Đã cập nhật để tích hợp PDF vào nút Lưu)
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
$page_title = $lang['quotes_management'] ?? 'Quotations Management';

$company_info = null;
try {
    $stmt_company = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
    $company_info = $stmt_company->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error fetching company info for quotes: " . $e->getMessage());
    $_SESSION['error_message'] = $lang['database_error'] ?? 'Database error fetching company info.';
}

$initial_quotes = [];
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><i class="bi bi-receipt-cutoff me-2"></i><?= $lang['sales_quotes'] ?? 'Sales Quotes' ?></h1>
        <button class="btn btn-primary" id="btn-create-new-quote">
            <i class="bi bi-plus-circle me-1"></i> <?= $lang['create_new_quote'] ?? 'Create New Quote' ?>
        </button>
    </div>

    <div class="card shadow-sm mb-4" id="quote-form-card" style="display: none;">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0" id="quote-form-title"><?= $lang['create_new_quote'] ?? 'Create New Quote' ?></h5>
        </div>
        <div class="card-body p-4">
            <form id="quote-form" novalidate>
                <input type="hidden" name="quote_id" id="quote_id">

                <div id="pdf-export-content-placeholder"> <?php
                    require __DIR__ . '/includes/document_header.php'; // Thông tin công ty, v.v cho form
                    ?>
                    <?php
                     $partner_type = 'customer';
                     $partner_label = $lang['customer'] ?? 'Customer';
                     $partner_select_label = $lang['select_customer'] ?? 'Select Customer';
                     $document_context = 'sales_quote_title';
                     require __DIR__ . '/includes/partner_info_form.php'; // Form thông tin NCC
                     ?>
                     <hr class="my-4">
                    <h5 class="mb-3"><?= $lang['item_details'] ?? 'Item Details' ?></h5>
                    <?php require __DIR__ . '/includes/item_details_table.php'; // Bảng chi tiết sản phẩm ?>
                    <?php require __DIR__ . '/includes/document_summary.php'; // Phần tóm tắt tổng cộng ?>
                    <hr class="my-4">
                </div>


                <div class="text-end mt-4">
                    <button type="button" class="btn btn-secondary me-2" id="btn-cancel-quote-form">
                        <i class="bi bi-x-circle me-1"></i><?= $lang['cancel'] ?? 'Cancel' ?>
                    </button>

                    <button type="submit" class="btn btn-success" id="btn-save-quote">
                        <i class="bi bi-save me-1"></i> <span class="save-text"><?= $lang['save_quote'] ?? 'Save Quote' ?></span>
                        <span class="spinner-bquote spinner-bquote-sm d-none" role="status" aria-hidden="true"></span>
                    </button>

                    <button id="toggle-signature" class="btn btn-sm btn-outline-secondary mt-1 me-2" type="button">
                        <?= $lang['show_signature'] ?? 'Show Signature' ?>
                    </button>
                    <div id="form-error-message" class="alert alert-danger mt-3 d-none" role="alert"></div>
                </div>
            </form>
        </div>
    </div>

    <h3 class="mt-4 mb-3" id="quote-list-title"><?= $lang['quote_list'] ?? 'Quotes List' ?></h3>
    <?php
     $list_data = $initial_quotes;
     $list_type = 'sales_quote';
     require __DIR__ . '/includes/document_list.php';
     ?>
</div>

<?php
// Truyền dữ liệu PHP sang Javascript
echo '<script>const LANG = ' . json_encode($lang) . ';</script>';
echo '<script>const COMPANY_INFO = ' . json_encode($company_info ?? null) . ';</script>';
echo '<script>
    const AJAX_URL = {
        sales_quote: "process/sales_quote_handler.php",
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
        error_log("Warning: \$company_info is not set when trying to get signature_path for JS in sales_quotes.php.");
    } elseif (empty($company_info['signature_path'])) {
        error_log("Warning: \$company_info['signature_path'] is empty in sales_quotes.php.");
    }
}

?>

<script>
    window.APP_SETTINGS = window.APP_SETTINGS || {};
    window.APP_SETTINGS.buyerSignatureUrl = '<?php echo $js_company_signature_path; ?>';
    console.log('Signature URL for JS on sales_quotes.php:', window.APP_SETTINGS.buyerSignatureUrl);
    window.APP_CONTEXT = {
        type: 'quote', // Xác định loại tài liệu là 'quote'
        documentName: '<?= $lang['sales_quote_short'] ?? 'Báo giá' ?>', // Tên ngắn gọn của tài liệu

    };
    console.log('APP_CONTEXT for sales_quotes.php:', window.APP_CONTEXT);
</script>
<?php
    require_once __DIR__ . '/config/set_js_vars.php';
?>

<script src="assets/js/sq_config.js"></script>
<script src="assets/js/sq_helpers.js"></script>
<script src="assets/js/sq_form.js"></script>
<script src="assets/js/sq_datatable.js"></script>
<script src="assets/js/sq_email.js"></script>
<script src="assets/js/sq_pdf.js"></script>
<script src="assets/js/sq_main.js"></script>
<script src="assets/js/sq_events.js"></script>
<script>
  window.APP_CONTEXT = { type: 'quote', documentName: 'Báo giá' };
</script>
