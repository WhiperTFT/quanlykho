<?php
// File: includes/partner_info_form.php

// 1. Đảm bảo các biến cơ bản đã được thiết lập từ file cha
if (!isset($partner_type) || !isset($partner_label) || !isset($partner_select_label) || !isset($lang)) {
    error_log("partner_info_form.php: Biến cơ bản partner_type, partner_label, partner_select_label, hoặc lang chưa được đặt.");
    return; // Hoặc hiển thị thông báo lỗi thân thiện
}

// 2. Đảm bảo $document_context được đặt bởi file cha (đã xác nhận là có)
//    Nếu muốn cẩn thận hơn, bạn vẫn có thể kiểm tra và đặt giá trị mặc định ở đây:
if (!isset($document_context)) {
    error_log("partner_info_form.php: \$document_context chưa được đặt bởi file cha. Mặc định thành 'purchase_order_title'.");
    $document_context = 'purchase_order_title';
}

// 3. Xác định tiêu đề chính của tài liệu (ví dụ: ĐƠN ĐẶT HÀNG, BÁO GIÁ BÁN HÀNG)
$documentTitleKey = 'purchase_order_title'; // Mặc định
// Lưu ý: Đoạn logic if/elseif của bạn ở đây cho $documentTitleKey cần được xem lại
// để đảm bảo nó đúng và không có điều kiện trùng lặp.
// Ví dụ đã sửa:
if ($document_context === 'purchase_quote_title') {
    $documentTitleKey = 'purchase_quote_title';
} elseif ($document_context === 'sales_quote_title') {
    $documentTitleKey = 'sales_quote_title';
}
// Thêm các elseif khác cho các loại chứng từ khác
$documentTitle = $lang[$documentTitleKey] ?? 'Tài Liệu';


// 4. Mảng cấu hình trung tâm cho các thuộc tính động
$document_configs = [
    'purchase_order_title' => [
        'number_label_lang_key' => 'order_number',       // Key trong $lang cho nhãn "Số Đơn Hàng"
        'number_input_id'       => 'order_number',       // ID và name cho input số đơn hàng
        'number_input_name'     => 'order_number',       // Thuộc tính name
        'generate_button_id'    => 'btn-generate-order-number', // ID nút tạo số ĐH
        'date_label_lang_key'   => 'order_date',         // Key trong $lang cho nhãn "Ngày Đơn Hàng"
        'date_input_id'         => 'order_date',         // ID và name cho input ngày ĐH
        'date_input_name'       => 'order_date',
    ],
    'sales_quote_title' => [ // Cấu hình cho Báo giá bán hàng
        'number_label_lang_key' => 'quote_number',       // Key trong $lang cho nhãn "Số Báo Giá"
        'number_input_id'       => 'quote_number',       // ID và name cho input số báo giá
        'number_input_name'     => 'quote_number',
        'generate_button_id'    => 'btn-generate-quote-number', // ID nút tạo số báo giá
        'date_label_lang_key'   => 'quote_date',         // Key trong $lang cho nhãn "Ngày Báo Giá"
        'date_input_id'         => 'quote_date',         // ID và name cho input ngày báo giá
        'date_input_name'       => 'quote_date',
    ],
    'purchase_quote_title' => [ // Cấu hình cho Báo giá mua hàng (nếu khác sales_quote)
        'number_label_lang_key' => 'quote_number',       // Có thể dùng chung key lang nếu nhãn giống
        'number_input_id'       => 'purchase_quote_number',// Hoặc ID riêng nếu cần
        'number_input_name'     => 'purchase_quote_number',
        'generate_button_id'    => 'btn-generate-purchase-quote-number',
        'date_label_lang_key'   => 'quote_date',
        'date_input_id'         => 'purchase_quote_date',
        'date_input_name'       => 'purchase_quote_date',
    ],
    // Thêm các loại chứng từ khác vào đây khi cần
    // 'delivery_note_title' => [ ... ]
];

// 5. Lấy cấu hình cụ thể dựa trên $document_context
$current_config = $document_configs[$document_context] ?? $document_configs['purchase_order_title']; // Mặc định về purchase_order nếu context lạ

// 6. Gán các biến PHP từ cấu hình để sử dụng trong HTML
$doc_number_label_text = $lang[$current_config['number_label_lang_key']] ?? ucfirst(str_replace('_', ' ', $current_config['number_label_lang_key']));
$doc_number_input_id   = $current_config['number_input_id'];
$doc_number_input_name = $current_config['number_input_name'];
$doc_generate_btn_id   = $current_config['generate_button_id'];

$doc_date_label_text   = $lang[$current_config['date_label_lang_key']] ?? ucfirst(str_replace('_', ' ', $current_config['date_label_lang_key']));
$doc_date_input_id     = $current_config['date_input_id'];
$doc_date_input_name   = $current_config['date_input_name'];

?>
<div class="text-center mb-4 mt-2">
    <h2 class="document-title display-5 text-primary fw-bold" id="document-main-title">
        <?= htmlspecialchars($documentTitle) ?>
    </h2>
</div>

<div class="partner-info-section mb-4">
    <div class="row g-3">
        <div class="col-md-7">
            <fieldset class="border p-1 rounded mb-3 h-100">
                <legend class="float-none w-auto px-2 fs-6 fw-bold"><?= htmlspecialchars($partner_label) ?></legend>
                <input type="hidden" id="partner_id" name="partner_id">
                <div class="mb-2 position-relative">
                    <label for="partner_autocomplete" class="form-label required visually-hidden"><?= $lang['partner_name'] ?? 'Partner Name' ?></label>
                    <input type="text" class="form-control required fw-bold fs-6" id="partner_autocomplete" placeholder="<?= htmlspecialchars($partner_select_label) ?>..." required>
                    <div class="invalid-feedback"></div>
                    <div id="partner-loading" class="spinner-border spinner-border-sm text-secondary position-absolute end-0 top-50 translate-middle-y me-2 d-none" role="status" style="right: 0.5rem !important;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div class="mb-2">
                    <small class="text-muted"><?= $lang['address'] ?? 'Address' ?>:</small>
                    <p id="partner_address_display" class="form-control-plaintext pt-0 border-bottom mb-1">-</p>
                </div>
                <div class="row gx-2">
                    <div class="col-sm-6 mb-2">
                        <small class="text-muted"><?= $lang['tax_id'] ?? 'Tax ID' ?>:</small>
                        <p id="partner_tax_id_display" class="form-control-plaintext pt-0 border-bottom mb-1">-</p>
                    </div>
                    <div class="col-sm-6 mb-2">
                        <small class="text-muted"><?= $lang['phone'] ?? 'Phone' ?>:</small>
                        <p id="partner_phone_display" class="form-control-plaintext pt-0 border-bottom mb-1">-</p>
                    </div>
                </div>
                <div class="mb-2">
                    <small class="text-muted"><?= $lang['contact_person'] ?? 'Contact Person' ?>:</small>
                    <p id="partner_contact_person_display" class="form-control-plaintext pt-0 border-bottom mb-1">-</p>
                </div>
                <div class="mb-0">
                    <small class="text-muted"><?= $lang['email'] ?? 'Email' ?>:</small>
                    <p id="partner_email_display" class="form-control-plaintext pt-0 border-bottom mb-0">-</p>
                </div>
            </fieldset>
        </div>

        <div class="col-md-5 text-end">
            <div class="row g-3"> <div class="mb-3 fw-bold">
                    <label for="<?= htmlspecialchars($doc_date_input_id) ?>" class="form-label required"><?= htmlspecialchars($doc_date_label_text) ?></label>
                    <input type="text" class="form-control datepicker text-end" id="<?= htmlspecialchars($doc_date_input_id) ?>" name="<?= htmlspecialchars($doc_date_input_name) ?>" required placeholder="dd/mm/yyyy" autocomplete="off">
                    <div class="invalid-feedback"></div>
                </div>
            </div>
            <div class="mb-3 fw-bold">
                <label for="<?= htmlspecialchars($doc_number_input_id) ?>" class="form-label required"><?= htmlspecialchars($doc_number_label_text) ?></label>
                <div class="input-group">
                    <button class="btn btn-outline-secondary" type="button" id="<?= htmlspecialchars($doc_generate_btn_id) ?>" title="<?= $lang['generate_number'] ?? 'Generate Number' ?>">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <input type="text" class="form-control text-end" id="<?= htmlspecialchars($doc_number_input_id) ?>" name="<?= htmlspecialchars($doc_number_input_name) ?>" required readonly>
                </div>
                <div class="invalid-feedback" id="<?= htmlspecialchars($doc_number_input_id) ?>_feedback"></div> </div>
            <div class="mb-3 currency-align">
                <label for="currency_select" class="form-label"><?= $lang['currency'] ?? 'Currency' ?></label>
                <select class="form-select text-end" id="currency_select" name="currency">
                    <option value="VND" selected><?= $lang['currency_vnd'] ?? 'VND (đ)' ?></option>
                    <option value="USD"><?= $lang['currency_usd'] ?? 'USD ($)' ?></option>
                </select>
            </div>
            </div>
    </div>
</div>
<input type="hidden" id="partner_type_filter" value="<?= htmlspecialchars($partner_type) ?>">
