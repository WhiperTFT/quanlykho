<?php
// File: process/generate_sales_quote_pdf.php

// 1. Nạp các file cần thiết
require_once '../vendor/autoload.php'; // Nạp thư viện mPDF
require_once '../includes/init.php';   // Nạp file khởi tạo ($pdo, $lang, session...)

// 2. Kiểm tra quyền truy cập
if (!is_logged_in()) {
    die($lang['access_denied'] ?? 'Access Denied.');
}

// 3. Lấy và xác thực ID báo giá từ URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo htmlspecialchars($lang['invalid_quote_id'] ?? 'Invalid Quote ID.');
    exit;
}
$quote_id = (int)$_GET['id']; // Đổi: quote_id -> quote_id

// 4. Truy vấn dữ liệu từ Database
try {
    // Lấy thông tin chung của báo giá và thông tin khách hàng
    // Đổi: sales_quotes -> sales_quotes, partner_id -> customer_id, quote_id -> quote_id
    // Đổi: join partners với điều kiện type='customer' (nếu bảng partners lưu cả khách hàng và NCC)
    // Hoặc join bảng customers nếu bạn có bảng riêng cho khách hàng
    $sql_quote = "SELECT sq.*,
                     p.partner_name AS customer_name, p.address AS customer_address, p.phone AS customer_phone,
                     p.email AS customer_email, p.tax_code AS customer_tax_code,
                     COALESCE(sq.customer_info_snapshot, JSON_OBJECT(
                         'partner_name', p.partner_name,
                         'address', p.address,
                         'phone', p.phone,
                         'email', p.email,
                         'tax_code', p.tax_code
                     )) AS customer_info_snapshot_data,
                     COALESCE(sq.company_info_snapshot, '{}') as company_info_snapshot_data,
                     u_creator.full_name AS created_by_name
              FROM sales_quotes sq
              LEFT JOIN partners p ON sq.customer_id = p.id AND p.type = 'customer' -- THÊM ĐIỀU KIỆN NÀY
              LEFT JOIN users u_creator ON sq.created_by = u_creator.id
              WHERE sq.id = :quote_id";
    $stmt_quote = $pdo->prepare($sql_quote);
    $stmt_quote->bindParam(':quote_id', $quote_id, PDO::PARAM_INT);
    $stmt_quote->execute();
    $quote = $stmt_quote->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        die(($lang['quote_not_found'] ?? 'Quote not found with ID:') . ' ' . $quote_id); // Đổi: quote -> quote
    }

    // Parse JSON snapshot nếu dữ liệu là chuỗi JSON
    if (is_string($quote['customer_info_snapshot_data'])) {
        $quote['customer_info_data'] = json_decode($quote['customer_info_snapshot_data'], true) ?: [];
    } else {
        $quote['customer_info_data'] = $quote['customer_info_snapshot_data'] ?: [];
    }
    if (is_string($quote['company_info_snapshot_data'])) {
        $quote['company_info_data_parsed'] = json_decode($quote['company_info_snapshot_data'], true) ?: [];
    } else {
        $quote['company_info_data_parsed'] = $quote['company_info_snapshot_data'] ?: [];
    }


    // Lấy chi tiết các sản phẩm trong báo giá
    // Đổi: sales_quote_details -> sales_quote_details, sales_quote_id -> quote_id
    $sql_details = "SELECT sqd.*,
                           COALESCE(pr.product_code, '') AS product_code,
                           sqd.product_name_snapshot, -- Ưu tiên snapshot
                           COALESCE(u.unit_name, sqd.unit_snapshot, '') AS unit_name -- Ưu tiên unit_name từ unit, sau đó snapshot
                    FROM sales_quote_details sqd
                    LEFT JOIN products pr ON sqd.product_id = pr.id
                    LEFT JOIN units u ON pr.unit_id = u.id -- Giả sử product có unit_id
                    WHERE sqd.quote_id = :quote_id";
    $stmt_details = $pdo->prepare($sql_details);
    $stmt_details->bindParam(':quote_id', $quote_id, PDO::PARAM_INT);
    $stmt_details->execute();
    $quote_details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

    // Lấy thông tin công ty (có thể dùng snapshot từ $quote['company_info_data_parsed'] nếu đầy đủ)
    // Hoặc query lại nếu cần thông tin mới nhất và snapshot không đủ
    $company_info_pdf = !empty($quote['company_info_data_parsed']) ? $quote['company_info_data_parsed'] : [];
    if (empty($company_info_pdf)) { // Query lại nếu snapshot rỗng
        $sql_company_live = "SELECT * FROM company_info WHERE id = 1 LIMIT 1";
        $stmt_company_live = $pdo->query($sql_company_live);
        $company_info_pdf = $stmt_company_live->fetch(PDO::FETCH_ASSOC);
    }


} catch (PDOException $e) {
    error_log("PDF Generation DB Error for Quote ID {$quote_id}: " . $e->getMessage()); // Đổi
    die($lang['database_error'] ?? 'Database query error.');
}

// 5. Xây dựng nội dung HTML cho file PDF
ob_start();
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'vi' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $lang['sales_quote_title'] ?? 'Sales Quote' ?> - <?= htmlspecialchars($quote['quote_number'] ?? $quote_id) ?></title>
    <style>
        /* CSS giữ nguyên hoặc điều chỉnh nếu cần cho báo giá */
        <?php
        $cssPath = __DIR__ . '/../assets/css/style.css';
        if (file_exists($cssPath)) {
            echo file_get_contents($cssPath);
        }
        ?>
        body { font-family: 'dejavusans', sans-serif; font-size: 10pt; line-height: 1.4; color: #333; }
        .pdf-container { width: 100%; padding: 0; }
        table { width: 100%; bquote-collapse: collapse; margin-bottom: 10px; font-size: 9pt; page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; background-color: #f2f2f2; }
        th, td { bquote: 0.5pt solid #ccc; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { font-weight: bold; white-space: nowrap; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        h1 { font-size: 18pt; text-align: center; color: #000; margin-bottom: 15px; font-weight: bold; }
        h2 { font-size: 13pt; color: #222; margin-bottom: 8px; margin-top: 15px; bquote-bottom: 1px solid #eee; padding-bottom: 3px; font-weight: bold;}
        .header-section { margin-bottom: 15px; }
        .header-section table { bquote: none; }
        .header-section td { bquote: none; vertical-align: top; padding: 0 5px; }
        .company-logo img { max-width: 150px; max-height: 70px; margin-bottom: 5px;}
        .partner-section { margin-bottom: 15px; } /* Đổi customer-section -> partner-section cho tổng quát */
        .items-section table tbody td { height: auto; }
        .summary-section { margin-top: 15px; }
        .summary-section table { width: 50%; margin-left: 50%; bquote: none; }
        .summary-section td { bquote: none; padding: 3px 6px; }
        .summary-section .total-label { text-align: right; font-weight: bold; padding-right: 10px; white-space: nowrap; }
        .summary-section .total-value { text-align: right; }
        .summary-section .grand-total td { bquote-top: 1pt solid #555; padding-top: 5px; font-weight: bold; font-size: 11pt; }
        .notes-section { margin-top: 15px; bquote-top: 1px dashed #ccc; padding-top: 10px; font-size: 9pt; }
        .signature-section { margin-top: 40px; page-break-inside: avoid; }
        .signature-section table { width: 100%; bquote: none; text-align: center; }
        .signature-section td { bquote: none; width: 48%; padding: 0 1%; vertical-align: bottom;}
        .signature-section p { margin-bottom: 60px; }
        .signature-section .signature-line { bquote-bottom: 1px dotted #555; margin: 5px auto; width: 80%; }
        .valid-until-section { margin-top: 10px; font-size: 9pt; }
        .no-print, .pdf-hide { display: none !important; }
    </style>
</head>
<body>
    <div class="pdf-container">
        <div class="header-section">
            <table>
                <tr>
                    <td style="width: 60%;">
                        <?php if ($company_info_pdf): ?>
                            <?php if (!empty($company_info_pdf['logo']) && file_exists('../' . ltrim($company_info_pdf['logo'],'/'))): ?>
                                <div class="company-logo">
                                    <img src="<?= '../' . ltrim($company_info_pdf['logo'],'/') ?>" alt="Logo">
                                </div>
                            <?php endif; ?>
                            <h3 class="text-bold"><?= htmlspecialchars($company_info_pdf['company_name'] ?? '') ?></h3>
                            <p><?= $lang['address'] ?? 'Address' ?>: <?= htmlspecialchars($company_info_pdf['address'] ?? '') ?></p>
                            <p><?= $lang['phone'] ?? 'Phone' ?>: <?= htmlspecialchars($company_info_pdf['phone'] ?? '') ?> | <?= $lang['email'] ?? 'Email' ?>: <?= htmlspecialchars($company_info_pdf['email'] ?? '') ?></p>
                            <p><?= $lang['tax_code'] ?? 'Tax Code' ?>: <?= htmlspecialchars($company_info_pdf['tax_code'] ?? '') ?></p>
                         <?php endif; ?>
                    </td>
                    <td style="width: 40%; text-align: right;">
                        <h1><?= $lang['sales_quote_title_pdf'] ?? 'SALES QUOTE' ?></h1>
                        <p><?= $lang['quote_number_short'] ?? 'Quote No.' ?>: <span class="text-bold"><?= htmlspecialchars($quote['quote_number'] ?? '') ?></span></p>
                        <p><?= $lang['quote_date'] ?? 'Quote Date' ?>: <?= !empty($quote['quote_date']) ? date("d/m/Y", strtotime($quote['quote_date'])) : '' ?></p>
                        <p><?= $lang['status'] ?? 'Status' ?>: <?= htmlspecialchars($lang['quote_status_' . $quote['status']] ?? ucfirst($quote['status'] ?? '')) ?></p>

                    </td>
                </tr>
            </table>
        </div>

        <div class="partner-section"> <h2><?= $lang['customer_info'] ?? 'Customer Information'; ?></h2>
            <?php $customer_data = $quote['customer_info_data']; ?>
            <p><?= $lang['customer_name'] ?? 'Customer Name'; ?>: <span class="text-bold"><?= htmlspecialchars($customer_data['partner_name'] ?? ($customer_data['customer_name'] ?? '')) ?></span></p>
            <p><?= $lang['address'] ?? 'Address'; ?>: <?= htmlspecialchars($customer_data['address'] ?? '') ?></p>
            <p><?= $lang['phone'] ?? 'Phone'; ?>: <?= htmlspecialchars($customer_data['phone'] ?? '') ?> | <?= $lang['email'] ?? 'Email'; ?>: <?= htmlspecialchars($customer_data['email'] ?? '') ?></p>
            <p><?= $lang['tax_code'] ?? 'Tax Code'; ?>: <?= htmlspecialchars($customer_data['tax_code'] ?? '') ?></p>
        </div>

        <div class="items-section">
             <h2><?= $lang['quote_details'] ?? 'Quote Details'; ?></h2> <table>
                <thead>
                    <tr>
                        <th class="text-center" style="width: 5%;"><?= $lang['stt'] ?? 'No.'; ?></th>
                        <th style="width: 15%;"><?= $lang['product_code'] ?? 'Code'; ?></th>
                        <th><?= $lang['product_name'] ?? 'Product/Service Name'; ?></th> <th class="text-center" style="width: 8%;"><?= $lang['quantity'] ?? 'Qty'; ?></th>
                        <th class="text-center" style="width: 8%;"><?= $lang['unit'] ?? 'Unit'; ?></th>
                        <th class="text-right" style="width: 12%;"><?= $lang['unit_price'] ?? 'Unit Price'; ?></th>
                        <th class="text-right" style="width: 15%;"><?= $lang['line_total'] ?? 'Total'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stt = 1;
                    // Tính subtotal từ chi tiết (giống như sales_quotes, nhưng không có discount ở đây)
                    // Các trường sub_total, vat_total, grand_total trong sales_quotes đã được tính sẵn
                    if (!empty($quote_details)):
                        foreach ($quote_details as $item):
                            $quantity = $item['quantity'] ?? 0;
                            $unit_price = $item['unit_price'] ?? 0;
                            $line_total = $quantity * $unit_price;
                    ?>
                    <tr>
                        <td class="text-center"><?= $stt++; ?></td>
                        <td><?= htmlspecialchars($item['product_code'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['product_name_snapshot']) ?>
                            <?php /* if(!empty($item['description_snapshot'])): // Nếu có trường description snapshot
                                <br><small><i><?= nl2br(htmlspecialchars($item['description_snapshot'])) ?></i></small>
                            <?php endif; */ ?>
                        </td>
                        <td class="text-center"><?= number_format($quantity, 2, ',', '.') ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['unit_name'] ?? ($item['unit_snapshot'] ?? '')) ?></td>
                        <td class="text-right"><?= number_format($unit_price, ($quote['currency'] === 'VND' ? 0 : 2), ',', '.') ?></td>
                        <td class="text-right"><?= number_format($line_total, ($quote['currency'] === 'VND' ? 0 : 2), ',', '.') ?></td>
                    </tr>
                    <?php
                        endforeach;
                    else: ?>
                        <tr>
                            <td colspan="7" class="text-center"><?= $lang['no_items_in_quote'] ?? 'No items found in this quote.' ?></td> </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="summary-section">
             <?php
                 // Lấy giá trị từ $quote
                 $sub_total = $quote['sub_total'] ?? 0;
                 $vat_rate = $quote['vat_rate'] ?? 0; // Tỷ lệ VAT từ bảng sales_quotes
                 $vat_total = $quote['vat_total'] ?? 0;
                 $grand_total = $quote['grand_total'] ?? 0;
             ?>
             <table>
                 <tr>
                     <td class="total-label"><?= $lang['subtotal'] ?? 'Subtotal'; ?>:</td>
                     <td class="total-value"><?= number_format($sub_total, ($quote['currency'] === 'VND' ? 0 : 2), ',', '.'); ?> <?= htmlspecialchars($quote['currency']) ?></td>
                 </tr>
                 <?php // Không có discount trong cấu trúc sales_quotes ban đầu, nếu cần thì thêm ?>
                 <tr>
                     <td class="total-label"><?= $lang['vat'] ?? 'VAT'; ?> (<?= number_format($vat_rate, 2) ?>%):</td>
                     <td class="total-value"><?= number_format($vat_total, ($quote['currency'] === 'VND' ? 0 : 2), ',', '.'); ?> <?= htmlspecialchars($quote['currency']) ?></td>
                 </tr>
                 <tr class="grand-total">
                     <td class="total-label"><?= $lang['grand_total'] ?? 'Grand Total'; ?>:</td>
                     <td class="total-value"><?= number_format($grand_total, ($quote['currency'] === 'VND' ? 0 : 2), ',', '.'); ?> <?= htmlspecialchars($quote['currency']) ?></td>
                 </tr>
             </table>
        </div>

        <?php if (!empty($quote['notes'])): ?>
        <div class="notes-section">
            <p><span class="text-bold"><?= $lang['notes'] ?? 'Notes'; ?>:</span> <?= nl2br(htmlspecialchars($quote['notes'])) ?></p>
        </div>
        <?php endif; ?>


        <div class="signature-section">
             <table>
                 <tr>
                     <td>
                         <p class="text-bold"><?= $lang['customer_signature'] ?? 'Customer'; ?></p>
                         <p>(<?= $lang['sign_and_name'] ?? 'Sign and write full name'; ?>)</p>
                         <div class="signature-line"></div>
                     </td>
                     <td>
                         <p class="text-bold"><?= $lang['company_representative_signature'] ?? 'Company Representative'; ?></p>
                         <p>(<?= $lang['sign_and_name'] ?? 'Sign and write full name'; ?>)</p>
                          <div class="signature-line"></div>
                          <?php if ($company_info_pdf && !empty($company_info_pdf['signature_path']) && file_exists('../' . ltrim($company_info_pdf['signature_path'], '/'))): ?>
                            <img src="<?= '../' . ltrim($company_info_pdf['signature_path'],'/') ?>" alt="Company Signature" style="max-height: 60px; display: block; margin: -50px auto 0 auto; position: relative; z-index:1;">
                          <?php endif; ?>
                     </td>
                 </tr>
             </table>
         </div>

         <div style="text-align: center; font-size: 8pt; color: #777; margin-top: 20px;">
             <?php if (!empty($quote['created_by_name'])): ?>
                 <?= $lang['created_by'] ?? 'Created By' ?>: <?= htmlspecialchars($quote['created_by_name']) ?> (<?= !empty($quote['created_at']) ? date("d/m/Y H:i", strtotime($quote['created_at'])) : '' ?>)
             <?php endif; ?>
             <?php /* Bỏ updated_by nếu không có trong sales_quotes
             <?php if (!empty($quote['updated_by_name'])): ?>
                 | <?= $lang['last_updated_by'] ?? 'Updated By' ?>: <?= htmlspecialchars($quote['updated_by_name']) ?> (<?= !empty($quote['updated_at']) ? date("d/m/Y H:i", strtotime($quote['updated_at'])) : '' ?>)
             <?php endif; ?> */ ?>
         </div>

    </div></body>
</html>
<?php
$html = ob_get_clean();

// 6. Khởi tạo và cấu hình mPDF (giữ nguyên cấu hình mPDF)
try {
    $defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];
    $defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $tempDir = __DIR__ . '/../tmp/mpdf'; // Đảm bảo thư mục này tồn tại và có quyền ghi
    if (!is_dir($tempDir)) { if (!mkdir($tempDir, 0775, true) && !is_dir($tempDir)) { throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));}}
    if (!is_writable($tempDir)) { throw new \RuntimeException(sprintf('Directory "%s" is not writable', $tempDir));}


    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 'format' => 'A4',
        'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 25,
        'margin_bottom' => 20, 'margin_header' => 8, 'margin_footer' => 8,
        'default_font_size' => 10, 'default_font' => 'dejavusans',
        'tempDir' => $tempDir,
        'fontDir' => array_merge($fontDirs, [ /* Thêm thư mục font tùy chỉnh nếu có */ ]),
        'fontdata' => $fontData + [ /* Thêm font tùy chỉnh nếu có */ ],
        'autoScriptToLang' => true, 'autoLangToFont' => true,
        'useSubstitutions' => false, 'enableImports' => true,
    ]);

    // Header/Footer cho báo giá
    $companyNameForPdf = $company_info_pdf['company_name'] ?? ($lang['appName'] ?? 'App');
    $quoteNumberForPdf = $quote['quote_number'] ?? $quote_id;
    // Đổi tiêu đề Header và Footer
    $headerText = $companyNameForPdf . ' | ' . ($lang['sales_quote_title'] ?? 'Sales Quote') . ' ' . $quoteNumberForPdf . ' | Trang {PAGENO}/{nbpg}';
    $footerText = ($lang['print_date'] ?? 'Print Date:') . ' {DATE d/m/Y H:i}';

    $mpdf->SetHeader($headerText);
    $mpdf->SetFooter($footerText);
    // Đổi tiêu đề file PDF
    $mpdf->SetTitle(($lang['sales_quote_title'] ?? 'Sales Quote') . ' - ' . $quoteNumberForPdf);
    $mpdf->SetAuthor($companyNameForPdf);

    $mpdf->WriteHTML($html);

    // 7. Xuất file PDF
    // Đổi tên file PDF
    $pdfFileName = ($lang['sales_quote_short'] ?? '') . '-' . preg_replace('/[^A-Za-z0-9_\-]/', '', $quoteNumberForPdf) . '.pdf';
    $mpdf->Output($pdfFileName, \Mpdf\Output\Destination::INLINE);
    exit;

} catch (\Mpdf\MpdfException $e) {
    error_log("mPDF Error for Quote ID {$quote_id}: " . $e->getMessage()); // Đổi
    echo ($lang['pdf_generation_error'] ?? 'PDF Generation Error:') . ' ' . $e->getMessage();
} catch (Exception $e) {
     error_log("General Error in PDF Generation for Quote ID {$quote_id}: " . $e->getMessage()); // Đổi
     echo ($lang['general_error'] ?? 'An unexpected error occurred:') . ' ' . $e->getMessage();
}
?>