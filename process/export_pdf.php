<?php
// File: process/export_pdf.php
header("Expires: 0");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false); // fix cho IE
header("Pragma: no-cache");
ob_start(); // Bắt đầu output buffering

// Includes
require_once __DIR__ . '/../vendor/autoload.php'; // Dompdf autoload
require_once __DIR__ . '/../includes/init.php';   // Cung cấp $pdo, $lang, session

use Dompdf\Dompdf;
use Dompdf\Options; 

// Cài đặt cho các tác vụ dài và tốn bộ nhớ
set_time_limit(300);
ini_set('memory_limit', '256M');

// --- 1. Lấy và xác thực tham số yêu cầu ---
$document_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
$document_type = isset($_REQUEST['type']) ? strtolower(trim($_REQUEST['type'])) : ''; // 'order' hoặc 'quote'

// Tham số hiển thị chữ ký (giữ nguyên từ file gốc của bạn)
$js_show_signature_param = isset($_REQUEST['show_signature']) ? $_REQUEST['show_signature'] : 'false';
$show_buyer_signature_flag = ($js_show_signature_param === 'true' || $js_show_signature_param === '1' || $js_show_signature_param === 1);

if ($document_id <= 0) {
    ob_clean(); http_response_code(400);
    if (!headers_sent()) { header('Content-Type: application/json'); }
    echo json_encode(['success' => false, 'message' => 'Missing or Invalid Document ID.']);
    exit();
}

if (empty($document_type) || !in_array($document_type, ['order', 'quote'])) {
    ob_clean(); http_response_code(400);
    if (!headers_sent()) { header('Content-Type: application/json'); }
    echo json_encode(['success' => false, 'message' => 'Missing or Invalid Document Type. Supported types: order, quote.']);
    exit();
}

// --- 2. Mảng Cấu Hình Trung Tâm (Đơn giản hóa cho Order và Sales Quote) ---
$pdf_configs = [
    'order' => [
        'main_table'          => 'sales_orders',
        'details_table'       => 'sales_order_details',
        'main_id_column'      => 'id',
        'details_fk_column'   => 'order_id',
        'number_column'       => 'order_number',
        'date_column'         => 'order_date',
        'partner_fk_column'   => 'supplier_id', // Đơn hàng là đặt cho Nhà cung cấp
        'partner_info_label_key' => 'supplier_info',  // lang key: "Thông tin Nhà cung cấp"
  
        'main_title_lang_key' => 'purchase_order_title', // "ĐƠN ĐẶT HÀNG"
        'short_title_lang_key'=> 'purchase_order_short', // "PO-"
        'details_label_lang_key' => 'order_details',    // "Chi tiết đơn hàng"
    ],
    'quote' => [ // Báo giá Bán hàng (cho Khách hàng)
        'main_table'          => 'sales_quotes',
        'details_table'       => 'sales_quote_details',
        'main_id_column'      => 'id',
        'details_fk_column'   => 'quote_id', // Theo cấu trúc bảng sales_quote_details bạn đã tạo
        'number_column'       => 'quote_number',
        'date_column'         => 'quote_date',
        'partner_fk_column'   => 'customer_id', // !! QUAN TRỌNG: Giả định sales_quotes dùng 'customer_id' để lưu ID Khách hàng (từ bảng 'partners')
                                                // Nếu cột này tên là 'customer_id' trong sales_quotes, hãy đổi ở đây.
        'partner_info_label_key' => 'customer_info',  // lang key: "Thông tin Khách hàng"
        'partner_role_on_pdf_key' => 'buyer',        // lang key: "Khách hàng"
        'own_company_role_on_pdf_key' => 'seller',     // lang key: "Bên Cung Cấp" (Công ty của bạn)
        'main_title_lang_key' => 'sales_quote_title',  // "BÁO GIÁ BÁN HÀNG"
        'short_title_lang_key'=> 'sales_quote_short',  // "BG_" hoặc "SQ_"
        'details_label_lang_key' => 'quote_details',    // "Chi tiết báo giá"
    ],
];

$current_config = $pdf_configs[$document_type] ?? null;

if (!$current_config) {
    // Trường hợp này không nên xảy ra vì đã kiểm tra $document_type ở trên
    ob_clean(); http_response_code(500);
    if (!headers_sent()) { header('Content-Type: application/json'); }
    echo json_encode(['success' => false, 'message' => 'Internal server error: Invalid document configuration.']);
    exit();
}

// --- 3. Khai báo biến và Fetch dữ liệu ---
$document_header = null;
$document_items = [];
$company_info_data = null;
$partner_info_data = null;
$lang = $lang ?? []; // Đảm bảo $lang là mảng
$current_lang = $_SESSION['lang'] ?? 'vi';

try {
    if (!$pdo) {
        throw new Exception("Database connection is not available.");
    }

    // Fetch thông tin tiêu đề tài liệu
    $sql_header = "SELECT 
                        h.id, 
                        h.{$current_config['number_column']} AS document_specific_number, 
                        h.{$current_config['date_column']} AS document_specific_date, 
                        h.{$current_config['partner_fk_column']} AS partner_id, 
                        h.notes, h.sub_total, h.vat_rate, h.vat_total, h.grand_total, h.currency 
                   FROM {$current_config['main_table']} h 
                   WHERE h.{$current_config['main_id_column']} = :id LIMIT 1";
    $stmt_header = $pdo->prepare($sql_header);
    $stmt_header->bindParam(':id', $document_id, PDO::PARAM_INT);
    $stmt_header->execute();
    $document_header = $stmt_header->fetch(PDO::FETCH_ASSOC);

    if (!$document_header) {
        throw new Exception(ucfirst($document_type) . ' not found with ID: ' . $document_id, 404);
    }

    // Fetch chi tiết tài liệu
    // Giả định các cột 'product_name_snapshot', 'unit_snapshot', 'quantity', 'unit_price', 'category_snapshot' là giống nhau
    $sql_details = "SELECT product_name_snapshot, unit_snapshot, quantity, unit_price, category_snapshot 
                    FROM {$current_config['details_table']} 
                    WHERE {$current_config['details_fk_column']} = :document_id ORDER BY id ASC";
    $stmt_details = $pdo->prepare($sql_details);
    $stmt_details->bindParam(':document_id', $document_id, PDO::PARAM_INT);
    $stmt_details->execute();
    $document_items = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

    // Fetch thông tin đối tác (Nhà cung cấp cho Order, Khách hàng cho Quote)
    if (!empty($document_header['partner_id'])) {
        $sql_partner = "SELECT name, address, phone, email, tax_id, contact_person FROM partners WHERE id = :id LIMIT 1";
        $stmt_partner = $pdo->prepare($sql_partner);
        $stmt_partner->bindParam(':id', $document_header['partner_id'], PDO::PARAM_INT);
        $stmt_partner->execute();
        $partner_info_data = $stmt_partner->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch thông tin công ty (Công ty của bạn) - Giữ nguyên
    $stmt_company = $pdo->query("SELECT name_vi, name_en, address_vi, address_en, tax_id, phone, email, website, logo_path, signature_path FROM company_info WHERE id = 1 LIMIT 1");
    $company_info_data = $stmt_company->fetch(PDO::FETCH_ASSOC);
    if (!$company_info_data) {
        $company_info_data = [];
        error_log("Warning in export_pdf.php: Company info (id=1) not found in database.");
    }

} catch (PDOException $e) {
    ob_clean(); $error_msg = "DB Error PDF (Type: {$document_type}, ID: {$document_id}): " . $e->getMessage(); error_log($error_msg);
    http_response_code(500); if (!headers_sent()) { header('Content-Type: application/json'); } echo json_encode(['success' => false, 'message' => 'Database error.']); exit();
} catch (Exception $e) {
    ob_clean(); $error_msg = "General Error PDF (Type: {$document_type}, ID: {$document_id}): " . $e->getMessage(); error_log($error_msg);
    $http_code = ($e->getCode() && is_int($e->getCode()) && $e->getCode() >= 400) ? $e->getCode() : 500;
    http_response_code($http_code); if (!headers_sent()) { header('Content-Type: application/json'); } echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit();
}

// --- 4. Các hàm tiện ích (format_number_pdf, get_image_base64) ---
// Giữ nguyên như file gốc của bạn
function format_number_pdf($number, $decimals = 0, $dec_point = ',', $thousands_sep = '.') {
    if (!is_numeric($number)) { return '0'; }
    return number_format((float)$number, $decimals, $dec_point, $thousands_sep);
}
function get_image_base64($absolute_system_path) {
    if ($absolute_system_path && file_exists($absolute_system_path) && is_readable($absolute_system_path)) {
        $imageData = @file_get_contents($absolute_system_path);
        if ($imageData !== false) {
            $imageType = strtolower(pathinfo($absolute_system_path, PATHINFO_EXTENSION));
            if (in_array($imageType, ['png', 'jpg', 'jpeg', 'gif'])) {
                return 'data:image/' . $imageType . ';base64,' . base64_encode($imageData);
            } else { error_log("get_image_base64: Unsupported image type '{$imageType}' for path: {$absolute_system_path}"); }
        } else { error_log("get_image_base64: Failed to read image data for path: {$absolute_system_path}"); }
    } else { error_log("get_image_base64: File does not exist or is not readable at path: " . ($absolute_system_path ?: 'NULL_PATH')); }
    return '';
}


// --- 5. Xác định tên file PDF và các chuỗi văn bản từ $lang ---
$doc_specific_number_val = $document_header['document_specific_number'] ?? ('ID_' . $document_header['id']);
$safe_doc_identifier = preg_replace('/[^a-zA-Z0-9_-]/', '-', $doc_specific_number_val);
$pdf_file_name_prefix = $lang[$current_config['short_title_lang_key']] ?? '';

if (stripos($safe_doc_identifier, $pdf_file_name_prefix) === 0) { // Dùng stripos để không phân biệt hoa thường
    $pdf_file_name = $safe_doc_identifier . '.pdf';
} else {
    $pdf_file_name = $pdf_file_name_prefix . $safe_doc_identifier . '.pdf';
}

// Lấy các chuỗi văn bản động
$txt_main_doc_title = $lang[$current_config['main_title_lang_key']] ?? ucfirst($document_type) . ' Document';
$txt_doc_number_label = $lang[$document_type === 'order' ? 'order_number' : 'quote_number'] ?? (ucfirst($document_type) . ' No.');
$txt_doc_date_label = $lang[$document_type === 'order' ? 'order_date' : 'quote_date'] ?? (ucfirst($document_type) . ' Date');
$txt_partner_info_section_title = $lang[$current_config['partner_info_label_key']] ?? 'Partner Information';
$txt_doc_details_section_title = $lang[$current_config['details_label_lang_key']] ?? ucfirst($document_type) . ' Details';
$txt_own_company_role_signature = translate($current_config['own_company_role_on_pdf_key'] ?? 'buyer');
$txt_partner_role_signature = translate($current_config['partner_role_on_pdf_key'] ?? 'seller');


// Các chuỗi văn bản dùng chung (giữ nguyên từ file gốc của bạn nếu phù hợp)
$txt_address = $lang['address'] ?? 'Address';
$txt_phone = $lang['phone'] ?? 'Phone';
$txt_tax_id = $lang['tax_id'] ?? 'Tax ID';
$txt_email = $lang['email'] ?? 'Email';
$txt_website = $lang['website'] ?? 'Website';
$txt_contact_person = $lang['contact_person'] ?? 'Contact Person';
$txt_stt = $lang['stt'] ?? 'No.';
$txt_product_name = $lang['product_name'] ?? 'Product Name';
$txt_category = $lang['category'] ?? 'Category';
$txt_unit = $lang['unit'] ?? 'Unit';
$txt_quantity = $lang['quantity'] ?? 'Quantity';
$txt_unit_price = $lang['unit_price'] ?? 'Unit Price';
$txt_line_total = $lang['line_total'] ?? 'Total';
$txt_no_items = $lang['no_items_in_order'] ?? 'No items found.';
$txt_sub_total = $lang['sub_total'] ?? 'Subtotal';
$txt_vat = $lang['vat'] ?? 'VAT';
$txt_grand_total = $lang['grand_total'] ?? 'Grand Total';
$txt_notes = $lang['notes'] ?? 'Notes';
$txt_signature_load_error = $lang['signature_load_error'] ?? 'Signature image not loaded';
$txt_signature_not_configured = $lang['signature_not_configured'] ?? 'Signature not configured';
// $txt_signature_not_shown_request // Biến này đã có trong file gốc, bạn có thể dùng nếu muốn
$txt_print_date = $lang['print_date'] ?? 'Print Date';
$txt_not_specified = $lang['not_specified'] ?? 'Not specified';

// Xử lý tên công ty và địa chỉ (giữ nguyên từ file gốc)
$company_name_display = $txt_not_specified; $company_address_display = $txt_not_specified;
if ($company_info_data) {
    $company_name_key = 'name_' . $current_lang; $company_address_key = 'address_' . $current_lang;
    $company_name_display = htmlspecialchars($company_info_data[$company_name_key] ?? ($company_info_data['name_vi'] ?: ($company_info_data['name_en'] ?: 'Company Name')));
    $company_address_display = htmlspecialchars($company_info_data[$company_address_key] ?? ($company_info_data['address_vi'] ?: ($company_info_data['address_en'] ?: '')));
}

// --- 6. Tạo nội dung HTML cho PDF ---
ob_start(); // Bắt đầu một output buffer mới cho HTML của PDF
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang); ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($txt_main_doc_title . ' #' . $doc_specific_number_val) ?></title>
    <style>
        /* Giữ nguyên toàn bộ CSS từ file gốc của bạn */
        @page { margin: 10mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.3; color: #333333; }
        .pdf-container { width: 100%; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; margin-bottom: 10px; }
        th, td { border: 1px solid #cccccc; padding: 4px 5px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .header-table { border: none; margin-bottom: 8px; width: 100%; table-layout: fixed; }
        .header-table td { border: none; vertical-align: middle; padding: 0; }
        .logo-cell { width: 10%; text-align: left; padding-right: 3px; }
        .logo-cell img { max-height: 100px; max-width: 100%; height: auto; display: block; }
        .company-info-cell { width: 90%; text-align: center; padding-left: 3px; }
        .company-name { font-size: 13pt; font-weight: bold; color: #1a237e; margin-bottom: 2px; text-transform: uppercase; line-height: 1.1; max-width: 100%; white-space: nowrap; overflow: hidden; }
        .company-info-cell p { margin: 1px 0; font-size: 7.5pt; line-height: 1.1; }
        .label { font-weight: normal; color: #555555; }
        .document-title { text-align: center; font-size: 16pt; font-weight: bold; margin: 8px 0 12px 0; color: #333; }
        .info-section-table { border:none; margin-bottom:12px; width:100%; }
        .info-section-table td { border:none; vertical-align:top; padding:0; }
        .partner-details-cell { width:60%; padding-right:10px; } /* Đổi tên từ supplier-details-cell */
        .doc-header-cell { width:40%; text-align:right; } /* Đổi tên từ order-header-cell */
        .section-title { font-size: 6pt; font-weight: bold; color: #1a237e; margin: 0 0 4px 0; padding-bottom: 1px; border-bottom: 1px solid #1a237e; }
        .details-table { width: 100%; border: none; }
        .details-table td { border: none; padding: 1px 0; font-size: 8.5pt; vertical-align: top; }
        .details-table .label { width: 100px; color: #555; font-weight:bold; }
        .partner-name { font-size: 10pt; font-weight: bold; margin-bottom:2px; } /* Đổi tên từ supplier-name */
        .address { white-space: normal; }
        .items-table th { padding: 5px; font-size: 8pt; }
        .items-table td { padding: 3px 4px; font-size: 7.5pt; }
        .items-table .text-center { text-align: center; }
        .items-table .text-right { text-align: right; }
        .summary-table { border: none; width: 45%; margin-left: 55%; margin-top: 8px; }
        .summary-table td { border: none; padding: 2px 4px; font-size: 8.5pt; }
        .summary-table .summary-label { text-align: right; font-weight: normal; padding-right: 8px; }
        .summary-table .summary-value { text-align: right; font-weight: bold; }
        .summary-table .grand-total td { border-top: 1px solid #555; padding-top: 3px; font-size: 9.5pt; font-weight: bold; }
        .signature-section-container { margin-top: 10px; page-break-inside: avoid; width:100%; }
        .signature-table { border: none; width: 100%; table-layout: fixed;}
        .signature-table td { border: none; width: 50%; text-align: center; padding: 0 10px; vertical-align:top; }
        .signature-role { font-size: 9pt; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .signature-image-placeholder { min-height: 60px; margin-bottom: 3px; display: flex; align-items: center; justify-content: center; }
        .signature-image-placeholder img { max-width: 210px; height: auto; display: block; margin: 0 auto; }
        .signature-section p.signature-error { color: red; font-size: 6.5pt; font-style: italic; margin-top:3px; }
        .signature-name { font-size:8pt; margin-top:3px; font-weight:bold; }
        .footer-section { position: fixed; bottom: -8mm; left: 0mm; right: 0mm; width: 100%; text-align: center; border-top: 0.5px solid #ccc; padding-top: 2px; font-size: 6pt; color: #666666; } /* Sửa màu border footer */
        .footer-section p { margin: 0; }
    </style>
</head>
<body>
    <div class="pdf-container">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <?php
                    // Logic lấy logo giữ nguyên từ file gốc
                    $logo_base64_to_display = '';
                    if (!empty($company_info_data['logo_path'])) {
                        $logo_system_path = realpath(__DIR__ . '/../' . ltrim($company_info_data['logo_path'], '/'));
                        if ($logo_system_path && file_exists($logo_system_path)) {
                            $logo_base64_to_display = get_image_base64($logo_system_path);
                        }
                    }
                    if (empty($logo_base64_to_display)) {
                        $placeholder_logo_system_path = realpath(__DIR__ . '/../assets/img/placeholder_logo.png'); // Đường dẫn tới logo placeholder
                        if ($placeholder_logo_system_path && file_exists($placeholder_logo_system_path)) {
                             $logo_base64_to_display = get_image_base64($placeholder_logo_system_path);
                        }
                    }
                    if ($logo_base64_to_display) { echo '<img src="' . $logo_base64_to_display . '" alt="Logo">'; }
                    ?>
                </td>
                <td class="company-info-cell">
                    <?php if ($company_info_data): ?>
                        <div class="company-name"><?= $company_name_display ?></div>
                        <p><?= $company_address_display ?></p>
                        <p><span class="label"><?= htmlspecialchars($txt_phone) ?>:</span> <span class="value"><?= htmlspecialchars($company_info_data['phone'] ?? '') ?></span> | <span class="label"><?= htmlspecialchars($txt_tax_id) ?>:</span> <span class="value"><?= htmlspecialchars($company_info_data['tax_id'] ?? '') ?></span></p>
                        <p><span class="label"><?= htmlspecialchars($txt_website) ?>:</span> <span class="value"><?= htmlspecialchars($company_info_data['website'] ?? '') ?></span> | <span class="label"><?= htmlspecialchars($txt_email) ?>:</span> <span class="value"><?= htmlspecialchars($company_info_data['email'] ?? '') ?></span></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <div class="document-title"><?= htmlspecialchars($txt_main_doc_title) ?></div>

        <table class="info-section-table">
             <tr>
                <td class="partner-details-cell"> <div class="section-title"><?= htmlspecialchars($txt_partner_info_section_title) // Thông tin Đối tác (NCC hoặc KH) ?></div>
                     <?php if ($partner_info_data): ?>
                        <table class="details-table">
                            <tr><td colspan="2" class="partner-name"><?= htmlspecialchars($partner_info_data['name'] ?? $txt_not_specified) ?></td></tr>
                            <tr><td class="label"><?= htmlspecialchars($txt_address) ?>:</td><td class="address"><?= htmlspecialchars($partner_info_data['address'] ?? '') ?></td></tr>
                            <tr><td class="label"><?= htmlspecialchars($txt_phone) ?>:</td><td><?= htmlspecialchars($partner_info_data['phone'] ?? '') ?></td></tr>
                            <tr><td class="label"><?= htmlspecialchars($txt_tax_id) ?>:</td><td><?= htmlspecialchars($partner_info_data['tax_id'] ?? '') ?></td></tr>
                            <tr><td class="label"><?= htmlspecialchars($txt_contact_person) ?>:</td><td><?= htmlspecialchars($partner_info_data['contact_person'] ?? '') ?></td></tr>
                            <tr><td class="label"><?= htmlspecialchars($txt_email) ?>:</td><td><?= htmlspecialchars($partner_info_data['email'] ?? '') ?></td></tr>
                        </table>
                    <?php else: ?>
                        <p><?= htmlspecialchars($txt_not_specified) ?></p>
                    <?php endif; ?>
                </td>
                <td class="doc-header-cell"> <table class="details-table" style="margin-left:auto; width:auto;">
                        <tr><td class="label"><?= htmlspecialchars($txt_doc_number_label) ?>:</td><td><?= htmlspecialchars($document_header['document_specific_number'] ?? $document_id) ?></td></tr>
                        <tr><td class="label"><?= htmlspecialchars($txt_doc_date_label) ?>:</td><td><?= !empty($document_header['document_specific_date']) ? date("d/m/Y", strtotime($document_header['document_specific_date'])) : '' ?></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="section-title" style="margin-top:10px;"><?= htmlspecialchars($txt_doc_details_section_title) ?></div>
        <table class="items-table">
            <thead>
                 <tr>
                    <th class="col-stt"><?= htmlspecialchars($txt_stt) ?></th>
                    <th class="col-category"><?= htmlspecialchars($txt_category) ?></th>
                    <th class="col-product"><?= htmlspecialchars($txt_product_name) ?></th>
                    <th class="col-unit"><?= htmlspecialchars($txt_unit) ?></th>
                    <th class="col-qty text-center"><?= htmlspecialchars($txt_quantity) ?></th>
                    <th class="col-price text-right"><?= htmlspecialchars($txt_unit_price) ?></th>
                    <th class="col-total text-right"><?= htmlspecialchars($txt_line_total) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stt = 1;
                if (!empty($document_items)) {
                    foreach ($document_items as $item) {
                        $productName = $item['product_name_snapshot'] ?? '';
                        $categoryName = $item['category_snapshot'] ?? '';
                        $unitName = $item['unit_snapshot'] ?? '';
                        $quantity = $item['quantity'] ?? 0;
                        $unit_price = $item['unit_price'] ?? 0;
                        $line_total = $quantity * $unit_price;
                ?>
                <tr class="item-row">
                    <td class="text-center"><?= $stt++; ?></td>
                    <td><?= htmlspecialchars($categoryName) ?></td>
                    <td><?= htmlspecialchars($productName) ?></td>
                    <td class="text-center"><?= htmlspecialchars($unitName) ?></td>
                    <td class="text-right"><?= format_number_pdf($quantity, ($quantity == (int)$quantity ? 0 : 2) ) ?></td>
                    <td class="text-right"><?= format_number_pdf($unit_price, 0) ?></td>
                    <td class="text-right"><?= format_number_pdf($line_total, 0) ?></td>
                </tr>
                <?php
                    }
                } else {
                    echo '<tr><td colspan="7" class="text-center" style="padding: 15px;">' . htmlspecialchars($txt_no_items) . '</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px; page-break-inside: avoid;">
            <tr>
                <td style="width: 55%; vertical-align: top; padding-right: 15px; border: none !important;">
                    <?php
                    // Phần xử lý và hiển thị Ghi chú (sử dụng $document_header['notes'])
                    $raw_notes_content = $document_header['notes'] ?? null;
                    $notes_to_display = '';

                    if (!empty($raw_notes_content) && is_string($raw_notes_content)) {
                        // Giải mã các ký tự \x...
                        $current_notes = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function($matches) {
                            return chr(hexdec($matches[1]));
                        }, $raw_notes_content);

                        if ($current_notes === null || trim($current_notes) === '') {
                            $current_notes = '';
                        }

                        if (trim($current_notes) !== '') {
                            // Thay <p> và <div> thành <br> để DOMPDF hiểu xuống dòng
                            $clean_notes = str_ireplace(
                                ['<p>', '</p>', '<div>', '</div>'],
                                ['', '<br>', '', '<br>'],
                                $current_notes
                            );

                            // Cho phép một số thẻ cơ bản, tránh lỗi DOMPDF và mất định dạng
                            $notes_to_display = strip_tags($clean_notes, '<br><b><strong><i><u><em>');
                        }
                    }

                    if (!empty($notes_to_display)):
                    ?>
                        <div>
                            <p style="margin-bottom: 3px; margin-top: 0;">
                                <strong><?= htmlspecialchars($txt_notes ?? 'Notes') ?>:</strong>
                            </p>
                            <div style="border: 1px solid #dddddd; padding: 6px 8px; font-size: 8pt; background-color: #fdfdfd; word-wrap: break-word; min-height: 50px;">
                                <?= $notes_to_display ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>&nbsp;</p>
                    <?php endif; ?>

                </td>

                <td style="width: 45%; vertical-align: top; border: none !important;">
                    <table class="summary-table" style="width: 100%; margin-left: 0; border: none !important;">
                         <?php
                             $sub_total_val = $document_header['sub_total'] ?? 0;
                             $vat_rate_val = isset($document_header['vat_rate']) ? (float)$document_header['vat_rate'] : 0;
                             $vat_total_val = isset($document_header['vat_total']) ? (float)$document_header['vat_total'] : 0;
                             $grand_total_val = $document_header['grand_total'] ?? 0;
                        ?>
                        <tr>
                            <td class="summary-label" style="border: none !important;"><?= htmlspecialchars($txt_sub_total) ?>:</td>
                            <td class="summary-value" style="border: none !important;"><?= format_number_pdf($sub_total_val, 0); ?> <?= htmlspecialchars($document_header['currency'] ?? 'VND') ?></td>
                        </tr>
                        <?php if ($vat_rate_val > 0): ?>
                        <tr>
                            <td class="summary-label" style="border: none !important;"><?= htmlspecialchars($txt_vat) ?> (<?= format_number_pdf($vat_rate_val, 0) ?>%):</td>
                            <td class="summary-value" style="border: none !important;"><?= format_number_pdf($vat_total_val, 0); ?> <?= htmlspecialchars($document_header['currency'] ?? 'VND') ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="grand-total">
                            <td class="summary-label" style="border-top: 1px solid #555 !important;"><?= htmlspecialchars($txt_grand_total) ?>:</td>
                            <td class="summary-value" style="border-top: 1px solid #555 !important;"><?= format_number_pdf($grand_total_val, 0); ?> <?= htmlspecialchars($document_header['currency'] ?? 'VND') ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="signature-section-container">
            <table class="signature-table">
                <tr>
                    <td>
                        <div class="signature-section">
                            <p class="signature-role"><?= htmlspecialchars($txt_partner_role_signature) // Đối tác (KH hoặc NCC) ?></p>
                            <div class="signature-image-placeholder">
                                <?php /* Để trống cho Đối tác ký */ ?>
                            </div>
                            <?php if ($partner_info_data && !empty($partner_info_data['name'])): ?>
                           <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="signature-section">
                            <p class="signature-role"><?= htmlspecialchars($txt_own_company_role_signature) // Công ty của bạn ?></p>
                            <div class="signature-image-placeholder">
                            <?php
                            // Logic hiển thị chữ ký của công ty bạn (giữ nguyên từ file gốc)
                            if ($show_buyer_signature_flag === true) {
                                $buyer_signature_img_html = '';
                                if (!empty($company_info_data['signature_path'])) {
                                    $buyer_signature_system_path = realpath(__DIR__ . '/../' . ltrim($company_info_data['signature_path'], '/'));
                                    if ($buyer_signature_system_path && file_exists($buyer_signature_system_path)) {
                                        $buyer_signature_base64 = get_image_base64($buyer_signature_system_path);
                                        if ($buyer_signature_base64) {
                                            $buyer_signature_img_html = '<img src="' . $buyer_signature_base64 . '" alt="Signature">';
                                        } else { $buyer_signature_img_html = '<p class="signature-error">' . htmlspecialchars($txt_signature_load_error) . '</p>'; }
                                    } else {
                                        error_log("PDF Export: Company signature file from DB not found. DB Path: " . ($company_info_data['signature_path'] ?? 'N/A') . ". Attempted Path: " . ($buyer_signature_system_path ?: 'Resolve_Failed'));
                                        $buyer_signature_img_html = '<p class="signature-error">' . htmlspecialchars($txt_signature_not_configured) . ' (file missing)</p>';
                                    }
                                } else { $buyer_signature_img_html = '<p class="signature-error">' . htmlspecialchars($txt_signature_not_configured) . '</p>'; }
                                echo $buyer_signature_img_html;
                            }
                            ?>
                            </div>
                             <?php if ($company_info_data && !empty($company_name_display) && $company_name_display !== $txt_not_specified): ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer-section">
             <?php if ($company_info_data): ?>
                <p><span style="font-weight:bold;"><?= $company_name_display ?></span> | <?= htmlspecialchars($txt_address) ?>: <?= $company_address_display ?></p>
                <p><?= htmlspecialchars($txt_phone) ?>: <?= htmlspecialchars($company_info_data['phone'] ?? '') ?> | Email: <?= htmlspecialchars($company_info_data['email'] ?? '') ?> | <?= htmlspecialchars($txt_tax_id) ?>: <?= htmlspecialchars($company_info_data['tax_id'] ?? '') ?></p>
            <?php endif; ?>
            <p><?= htmlspecialchars($txt_print_date) ?>: <?= date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean(); // Lấy nội dung HTML từ buffer

// --- 7. Dompdf Initialization and Rendering ---
// Giữ nguyên như file gốc của bạn
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false); // Quan trọng cho bảo mật, chỉ cho phép local images/css
$options->set('defaultFont', 'DejaVu Sans'); // Font hỗ trợ Unicode/Tiếng Việt

$project_root = realpath(__DIR__ . '/../');
if (!$project_root) {
    error_log("FATAL ERROR in export_pdf.php: Cannot resolve project root path for chroot.");
    ob_clean(); http_response_code(500); if (!headers_sent()) { header('Content-Type: application/json'); }
    echo json_encode(['success' => false, 'message' => 'Server configuration error (chroot).']); exit();
}
$options->set('chroot', $project_root); // Giới hạn Dompdf truy cập file trong thư mục dự án
$options->set('isPhpEnabled', false); // Tắt thực thi PHP trong HTML của Dompdf (bảo mật)

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');

try {
    $dompdf->render();
} catch (Exception $e) {
    ob_clean();
    $error_msg = "Dompdf Render Error (Type: {$document_type}, ID: {$document_id}): " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
    error_log($error_msg);
    http_response_code(500); if (!headers_sent()) { header('Content-Type: application/json'); }
    echo json_encode(['success' => false, 'message' => 'Server error during PDF rendering. Error: ' . $e->getMessage()]); exit();
}

// --- 8. Output và Lưu file PDF ---
// Giữ nguyên phần này, chỉ điều chỉnh debug_info
$pdf_output = $dompdf->output();
$save_path_pdf_dir = $project_root . '/pdf/'; // Thư mục lưu PDF

if (!is_dir($save_path_pdf_dir)) {
    if (!@mkdir($save_path_pdf_dir, 0775, true)) { // Tạo thư mục nếu chưa có
        ob_clean(); $error_msg = 'Failed to create PDF directory: ' . $save_path_pdf_dir; error_log($error_msg);
        http_response_code(500); if (!headers_sent()) { header('Content-Type: application/json'); }
        echo json_encode(['success' => false, 'message' => 'Failed to create PDF directory.']); exit();
    }
}

$pdf_file_full_system_path = $save_path_pdf_dir . $pdf_file_name;

if (@file_put_contents($pdf_file_full_system_path, $pdf_output) !== false) {
    ob_clean();
    http_response_code(200);
    if (!headers_sent()) { header('Content-Type: application/json'); }
    $web_pdf_path = 'pdf/' . rawurlencode($pdf_file_name); // Đường dẫn web tới file PDF
    echo json_encode([
        'success' => true,
        'message' => ($lang['pdf_saved_successfully'] ?? 'PDF saved successfully.'),
        'pdf_web_path' => $web_pdf_path,
        'debug_info' => [
            'document_id' => $document_id,
            'document_type' => $document_type,
            'generated_pdf_filename' => $pdf_file_name,
            'js_show_signature_param_received' => $js_show_signature_param,
            'php_show_buyer_signature_flag_evaluated' => $show_buyer_signature_flag,
            'db_company_signature_path' => $company_info_data['signature_path'] ?? 'N/A',
            'resolved_buyer_signature_system_path' => isset($company_info_data['signature_path']) ? realpath($project_root . '/' . ltrim($company_info_data['signature_path'], '/')) : 'N/A'
        ]
    ]);
    exit();
} else {
    ob_clean();
    $error_msg = 'Failed to save PDF file to: ' . $pdf_file_full_system_path; error_log($error_msg);
    http_response_code(500); if (!headers_sent()) { header('Content-Type: application/json'); }
    echo json_encode(['success' => false, 'message' => 'Failed to save PDF file on server.']);
    exit();
}
?>