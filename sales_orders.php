<?php
// File: sales_orders.php
require_once __DIR__ . '/includes/init.php'; // Khởi tạo session, ngôn ngữ, DB ($pdo, $lang, $user_settings)
require_once __DIR__ . '/includes/auth_check.php'; // Đảm bảo người dùng đã đăng nhập
// require_once __DIR__ . '/includes/admin_check.php'; // Kiểm tra quyền admin - Bỏ comment nếu cần

require_once __DIR__ . '/includes/header.php'; // Chứa $pdo, $lang, $user_settings

// === 1. Điều chỉnh thông tin cho Sales Order ===
$page_title = $lang['sales_orders_management'] ?? 'Sales Order Management'; // Đổi thành Sales Order
// Các biến cho partner_info_form.php
$partner_type = 'customer'; // QUAN TRỌNG: Đổi sang 'customer'
$partner_label = $lang['customer'] ?? 'Customer';
$partner_select_label = $lang['select_customer'] ?? 'Select Customer';
$document_context = 'sales_order_title'; // Ngữ cảnh cho tiêu đề tài liệu
$partner_id_field_name = 'partner_id'; // Tên trường ID đối tác trong form
$partner_name_field_name = 'partner_name'; // Tên trường tên đối tác trong form (nếu có)

// Lấy thông tin công ty
$company_info = null;
try {
    $stmt_company = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
    $company_info = $stmt_company->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error fetching company info for sales_orders.php: " . $e->getMessage());
    $_SESSION['error_message'] = $lang['database_error'] ?? 'Database error fetching company info.';
}

// === 2. Lấy danh sách báo giá (Sales Quotes) phù hợp để liên kết ===
// Biến này sẽ chứa các báo giá được load ban đầu từ PHP
$available_quotes_from_db = [];
$current_order_id_for_quote_query = 0; // ID của đơn hàng hiện tại nếu đang edit, để không loại trừ báo giá của chính nó

// Nếu là hành động 'edit', lấy ID đơn hàng để không loại trừ báo giá của chính nó
// (Giả sử ID đơn hàng được truyền qua URL là 'id' khi action=edit)
// if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
//     $current_order_id_for_quote_query = intval($_GET['id']);
// }
// Hoặc nếu bạn lấy dữ liệu đơn hàng đang sửa vào một biến $order_data:
// if (isset($order_data['id']) && !empty($order_data['id'])) {
//     $current_order_id_for_quote_query = $order_data['id'];
// }


// Câu lệnh SQL để lấy các báo giá 'accepted' và chưa được liên kết với đơn hàng nào khác đang hoạt động
// HOẶC là báo giá của chính đơn hàng này (nếu đang edit)
// Lưu ý: $current_order_id_for_quote_query cần được xử lý đúng tùy theo logic edit của bạn
try {
    $sql_available_quotes = "
        SELECT sq.id, sq.quote_number, sq.partner_id, p.name as customer_name, sq.quote_date
        FROM sales_quotes sq
        JOIN partners p ON sq.partner_id = p.id
        WHERE sq.status = 'accepted' AND (
            sq.id NOT IN (
                SELECT DISTINCT so.quote_id
                FROM sales_orders so
                WHERE so.quote_id IS NOT NULL
                  AND so.status NOT IN ('cancelled', 'rejected') -- Các trạng thái đơn hàng không còn hiệu lực
                  AND so.id != :current_order_id -- Không loại trừ chính nó nếu đang sửa
            )
            OR sq.id = (SELECT quote_id FROM sales_orders WHERE id = :current_order_id_if_linked LIMIT 1) -- Luôn bao gồm BG đã link của ĐH này
        )
        ORDER BY sq.quote_date DESC, sq.id DESC
    ";
    $stmt_available_quotes = $pdo->prepare($sql_available_quotes);
    $stmt_available_quotes->bindValue(':current_order_id', $current_order_id_for_quote_query, PDO::PARAM_INT);
    $stmt_available_quotes->bindValue(':current_order_id_if_linked', $current_order_id_for_quote_query, PDO::PARAM_INT);

    $stmt_available_quotes->execute();
    $available_quotes_from_db = $stmt_available_quotes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching available sales quotes for sales_orders.php: " . $e->getMessage());
    // Xử lý lỗi nếu cần, ví dụ thông báo cho người dùng
}


$initial_orders = []; // Dữ liệu ban đầu cho danh sách đơn hàng (nếu có)
$user_date_format = $user_settings['date_format'] ?? 'Y-m-d'; // Lấy từ user settings

?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><i class="bi bi-receipt-cutoff me-2"></i><?= $lang['sales_orders'] ?? 'Sales Orders' ?></h1>
        <button class="btn btn-primary" id="btn-create-new-order">
            <i class="bi bi-plus-circle me-1"></i> <?= $lang['create_new_order'] ?? 'Create New Sales Order' ?>
        </button>
    </div>

    <div class="card shadow-sm mb-4" id="order-form-card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0" id="order-form-title"><?= $lang['create_new_order'] ?? 'Create New Sales Order' ?></h5>
        </div>
        <div class="card-body p-4">
            <form id="order-form" novalidate>
                <input type="hidden" name="order_id" id="order_id">

                <div id="pdf-export-content-placeholder">
                    <?php
                    // Truyền biến $company_info, $document_context cho document_header.php
                    // $document_title (ví dụ: "ĐƠN ĐẶT HÀNG") sẽ được đặt trong sales_orders.js hoặc partner_info_form
                    require __DIR__ . '/includes/document_header.php';
                    ?>
                    <?php
                    // Các biến $partner_type, $partner_label, $partner_select_label,
                    // $document_context, $partner_id_field_name, $partner_name_field_name
                    // đã được định nghĩa ở trên cho 'customer'
                    require __DIR__ . '/includes/partner_info_form.php'; // Form thông tin Khách hàng
                    ?>
                    <div class="row mt-3">
                        <div class="col-md-6"> <label for="order_quote_id_form" class="form-label"><?= $lang['link_to_sales_quote'] ?? 'Link to Sales Quote' ?></label>
                            <select class="form-select form-select-sm" id="order_quote_id_form" name="quote_id">
                                <option value=""><?= $lang['none'] ?? '-- None --' ?></option>
                                <?php if (!empty($available_quotes_from_db)): ?>
                                    <?php
                                    $display_date_format = $user_date_format; // Sử dụng định dạng ngày của người dùng
                                    foreach ($available_quotes_from_db as $sq):
                                        $quote_display_date = '';
                                        if (!empty($sq['quote_date'])) {
                                            try {
                                                $dateObj = new DateTime($sq['quote_date']);
                                                $quote_display_date = $dateObj->format($display_date_format);
                                            } catch (Exception $e) {
                                                $quote_display_date = $sq['quote_date']; // Fallback
                                            }
                                        }
                                    ?>
                                        <option value="<?= htmlspecialchars((string)$sq['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-customer-id="<?= htmlspecialchars((string)($sq['partner_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($sq['quote_number'] . (!empty($sq['customer_name']) ? ' - ' . $sq['customer_name'] : '') . (!empty($quote_display_date) ? ' (' . $quote_display_date . ')' : ''), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </select>
                            <small class="form-text text-muted"><?= $lang['link_quote_note_sales_order'] ?? 'Select an accepted quote. Customer and items might be auto-populated.' ?></small>
                        </div>
                        <div class="col-md-6">
                            <label for="order_date_form" class="form-label"><?= $lang['order_date'] ?? 'Order Date' ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm date-picker" id="order_date_form" name="order_date" required>
                        </div>
                    </div>
                    <hr class="my-4">
                    <h5 class="mb-3"><?= $lang['item_details'] ?? 'Item Details' ?></h5>
                    <?php require __DIR__ . '/includes/item_details_table.php'; // Bảng chi tiết sản phẩm ?>
                    <?php require __DIR__ . '/includes/document_summary.php'; // Phần tóm tắt tổng cộng ?>
                    <hr class="my-4">
                </div> <div class="text-end mt-4">
                    <button type="button" class="btn btn-secondary me-2" id="btn-cancel-order-form">
                        <i class="bi bi-x-circle me-1"></i><?= $lang['cancel'] ?? 'Cancel' ?>
                    </button>
                    <button type="submit" class="btn btn-success" id="btn-save-order">
                        <i class="bi bi-save me-1"></i> <span class="save-text"><?= $lang['save_order'] ?? 'Save Order' ?></span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                    <div id="form-error-message" class="alert alert-danger mt-3 d-none" role="alert"></div>
                </div>
            </form>
        </div>
    </div>

    <h3 class="mt-4 mb-3" id="order-list-title"><?= $lang['orders_list'] ?? 'Sales Orders List' ?></h3>
    <?php
        // Cấu hình cho document_list.php
        $list_data_source_url = 'process/sales_order_serverside.php'; // Endpoint cho DataTables
        $list_type = 'sales_order'; // Để document_list.php biết loại tài liệu
        $list_columns = [ // Định nghĩa các cột cho DataTables
            ['data' => 'order_number', 'title' => ($lang['order_number_short'] ?? 'Order No.')],
            ['data' => 'partner_name', 'title' => ($lang['customer'] ?? 'Customer')],
            ['data' => 'order_date_formatted', 'title' => ($lang['order_date'] ?? 'Order Date')],
            ['data' => 'total_amount_formatted', 'title' => ($lang['total_amount'] ?? 'Total Amount')],
            ['data' => 'status_display', 'title' => ($lang['status'] ?? 'Status')],
            ['data' => 'actions', 'title' => ($lang['actions'] ?? 'Actions'), 'orderable' => false, 'searchable' => false]
        ];
        $list_delete_handler = 'process/sales_order_handler.php'; // Endpoint để xóa
        $list_document_name = $lang['sales_order_short'] ?? 'ĐH'; // Tên ngắn cho xác nhận xóa

        require __DIR__ . '/includes/document_list.php'; // Include file list chung
    ?>
</div>

<?php
// Truyền dữ liệu PHP sang Javascript
echo '<script>const COMPANY_INFO = ' . json_encode($company_info ?? null) . ';</script>';
echo '<script>
    const AJAX_URL = {
        sales_order: "process/sales_order_handler.php",     // Đã có
        partner_search: "process/partners_handler.php",   // Để tìm khách hàng
        product_search: "process/product_handler.php",    // Để tìm sản phẩm
        get_quote_details: "process/get_quote_items_for_order.php" // Thêm URL này nếu JS cần gọi lại
    };
    // Truyền danh sách báo giá ban đầu cho sales_orders.js
    const allAvailableQuotesFromDB = ' . json_encode($available_quotes_from_db) . ';
    const initialOrderData = null; // Sẽ được set nếu là edit mode
    const currentOrderId = ' . json_encode(isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) ? intval($_GET['id']) : null) . '; // ID đơn hàng nếu đang sửa
</script>';

require_once __DIR__ . '/includes/footer.php'; // Chứa các script chung

// Nhúng Thư viện JS và CSS cần thiết cho DataTables (nếu chưa có trong footer.php)
// Ví dụ:
// echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">';
// echo '<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>';
// echo '<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>';

// Xử lý đường dẫn chữ ký (đoạn này có vẻ ổn, chỉ cần xem xét tên biến JS `buyerSignatureUrl`)
$js_company_signature_path = 'uploads/signature/default_sign.png'; // Giá trị mặc định fallback, đảm bảo file này tồn tại
if (isset($company_info) && is_array($company_info) && !empty($company_info['signature_path'])) {
    $potential_path = ltrim($company_info['signature_path'], '/');
    // Kiểm tra file tồn tại để tránh lỗi JS nếu đường dẫn sai
    if (file_exists(__DIR__ . '/' . $potential_path)) {
        $js_company_signature_path = htmlspecialchars($potential_path, ENT_QUOTES, 'UTF-8');
    } else {
        error_log("Company signature file not found at: " . __DIR__ . '/' . $potential_path . ". Using default for sales_orders.php.");
    }
} else {
    if (!isset($company_info)) {
        error_log("Warning: \$company_info is not set when trying to get signature_path for JS in sales_orders.php.");
    } elseif (empty($company_info['signature_path'])) {
        error_log("Warning: \$company_info['signature_path'] is empty in sales_orders.php. Using default.");
    }
}
?>

<script>
    window.APP_SETTINGS = window.APP_SETTINGS || {};
    // Đổi tên 'buyerSignatureUrl' thành 'companySignatureUrl' cho rõ nghĩa hơn trong Sales Order
    window.APP_SETTINGS.companySignatureUrl = '<?php echo $js_company_signature_path; ?>';
    console.log('Company Signature URL for JS on sales_orders.php:', window.APP_SETTINGS.companySignatureUrl);

    window.APP_CONTEXT = {
        type: 'sales_order', // Đổi type thành 'sales_order' cho nhất quán
        documentName: '<?= $lang['sales_order_short'] ?? 'ĐH' ?>',
        formId: '#order-form',
        tableId: '#document-table', // ID bảng từ document_list.php
        createButtonId: '#btn-create-new-order',
        cancelButtonId: '#btn-cancel-order-form',
        formCardId: '#order-form-card',
        listTitleId: '#order-list-title',
        formTitleId: '#order-form-title',
        ajaxHandlerUrl: AJAX_URL.sales_order, // URL xử lý chính
        serversideListUrl: '<?= $list_data_source_url ?>', // URL cho DataTables
        deleteConfirmationText: '<?= $lang['confirm_delete_document_generic'] ?? 'Bạn có chắc chắn muốn xóa {documentName} này không?' ?>',
        deleteSuccessText: '<?= $lang['document_deleted_successfully_generic'] ?? '{documentName} đã được xóa thành công.' ?>',
        deleteErrorText: '<?= $lang['error_deleting_document_generic'] ?? 'Có lỗi xảy ra khi xóa {documentName}.' ?>'
    };
    console.log('APP_CONTEXT for sales_orders.php:', window.APP_CONTEXT);
</script>
<?php
    // Nhúng các file JS cụ thể cho trang này
    echo '<script src="assets/js/helpers.js?v=' . time() . '"></script>';
    // Đảm bảo document_form_management.js được nhúng TRƯỚC sales_orders.js nếu sales_orders.js kế thừa hoặc dùng hàm từ nó
    // echo '<script src="assets/js/document_form_management.js?v=' . time() . '"></script>';
    echo '<script src="assets/js/sales_orders.js?v=' . time() . '"></script>';
?>
