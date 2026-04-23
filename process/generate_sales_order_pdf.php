<?php
// File: process/generate_sales_order_pdf.php

// 1. Nạp các file cần thiết
require_once '../vendor/autoload.php'; // Nạp thư viện mPDF
require_once '../includes/init.php';   // Nạp file khởi tạo ($pdo, $lang, session...)

// 2. Kiểm tra quyền truy cập
if (!is_logged_in()) {
    die($lang['access_denied'] ?? 'Access Denied.');
}

// 3. Lấy và xác thực ID đơn hàng từ URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo htmlspecialchars($lang['invalid_order_id'] ?? 'Invalid Order ID.');
    exit;
}
$order_id = (int)$_GET['id'];

// 4. Truy vấn dữ liệu từ Database
try {
    // Lấy thông tin chung của đơn hàng và thông tin nhà cung cấp
    $sql_order = "SELECT so.*,
                     p.partner_name AS supplier_name, p.address AS supplier_address, p.phone AS supplier_phone,
                     p.email AS supplier_email, p.tax_code AS supplier_tax_code,
                     COALESCE(so.supplier_info_snapshot, JSON_OBJECT(
                         'partner_name', p.partner_name,
                         'address', p.address,
                         'phone', p.phone,
                         'email', p.email,
                         'tax_code', p.tax_code
                     )) AS supplier_info_snapshot_data,
                     COALESCE(so.company_info_snapshot, '{}') as company_info_snapshot_data,
                     u_creator.full_name AS created_by_name
              FROM sales_orders so
              LEFT JOIN partners p ON so.supplier_id = p.id AND p.type = 'supplier'
              LEFT JOIN users u_creator ON so.created_by = u_creator.id
              WHERE so.id = :order_id";
    $stmt_order = $pdo->prepare($sql_order);
    $stmt_order->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt_order->execute();
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die(($lang['order_not_found'] ?? 'Order not found with ID:') . ' ' . $order_id);
    }

    // Parse JSON snapshot nếu dữ liệu là chuỗi JSON
    if (is_string($order['supplier_info_snapshot_data'])) {
        $order['supplier_info_data'] = json_decode($order['supplier_info_snapshot_data'], true) ?: [];
    } else {
        $order['supplier_info_data'] = $order['supplier_info_snapshot_data'] ?: [];
    }
    if (is_string($order['company_info_snapshot_data'])) {
        $order['company_info_data_parsed'] = json_decode($order['company_info_snapshot_data'], true) ?: [];
    } else {
        $order['company_info_data_parsed'] = $order['company_info_snapshot_data'] ?: [];
    }

    // Lấy chi tiết các sản phẩm trong đơn hàng
    $sql_details = "SELECT sod.*,
                           COALESCE(pr.product_code, '') AS product_code,
                           sod.product_name_snapshot,
                           COALESCE(u.unit_name, sod.unit_snapshot, '') AS unit_name
                    FROM sales_order_details sod
                    LEFT JOIN products pr ON sod.product_id = pr.id
                    LEFT JOIN units u ON pr.unit_id = u.id
                    WHERE sod.order_id = :order_id";
    $stmt_details = $pdo->prepare($sql_details);
    $stmt_details->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt_details->execute();
    $order_details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

    // Lấy thông tin công ty
    $company_info_pdf = !empty($order['company_info_data_parsed']) ? $order['company_info_data_parsed'] : [];
    if (empty($company_info_pdf)) {
        $sql_company_live = "SELECT * FROM company_info WHERE id = 1 LIMIT 1";
        $stmt_company_live = $pdo->query($sql_company_live);
        $company_info_pdf = $stmt_company_live->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("PDF Generation DB Error for Order ID {$order_id}: " . $e->getMessage());
    die($lang['database_error'] ?? 'Database query error.');
}

// 5. Xây dựng nội dung HTML cho file PDF
ob_start();
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'vi' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $lang['sales_order_title'] ?? 'Purchase Order' ?> - <?= htmlspecialchars($order['order_number'] ?? $order_id) ?></title>
    <style>
        <?php
        $cssPath = __DIR__ . '/../assets/css/style.css';
        if (file_exists($cssPath)) {
            echo file_get_contents($cssPath);
        }
        ?>
        body { font-family: 'dejavusans', sans-serif; font-size: 10pt; line-height: 1.4; color: #333; }
        .pdf-container { width: 100%; padding: 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 9pt; page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; background-color: #f2f2f2; }
        th, td { border: 0.5pt solid #ccc; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { font-weight: bold; white-space: nowrap; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        h1 { font-size: 18pt; text-align: center; color: #000; margin-bottom: 15px; font-weight: bold; }
        h2 { font-size: 13pt; color: #222; margin-bottom: 8px; margin-top: 15px; border-bottom: 1px solid #eee; padding-bottom: 3px; font-weight: bold;}
        .header-section { margin-bottom: 15px; }
        .header-section table { border: none; }
        .header-section td { border: none; vertical-align: top; padding: 0 5px; }
        .company-logo img { max-width: 150px; max-height: 70px; margin-bottom: 5px;}
        .partner-section { margin-bottom: 15px; }
        .items-section table tbody td { height: auto; }
        .summary-section { margin-top: 15px; }
        .summary-section table { width: 50%; margin-left: 50%; border: none; }
        .summary-section td { border: none; padding: 3px 6px; }
        .summary-section .total-label { text-align: right; font-weight: bold; padding-right: 10px; white-space: nowrap; }
        .summary-section .total-value { text-align: right; }
        .summary-section .grand-total td { border-top: 1pt solid #555; padding-top: 5px; font-weight: bold; font-size: 11pt; }
        .notes-section { margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 10px; font-size: 9pt; }
        .signature-section { margin-top: 40px; page-break-inside: avoid; }
        .signature-section table { width: 100%; border: none; text-align: center; }
        .signature-section td { border: none; width: 48%; padding: 0 1%; vertical-align: bottom;}
        .signature-section p { margin-bottom: 60px; }
        .signature-section .signature-line { border-bottom: 1px dotted #555; margin: 5px auto; width: 80%; }
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
                        <h1><?= $lang['sales_order_title_pdf'] ?? 'PURCHASE ORDER' ?></h1>
                        <p><?= $lang['order_number_short'] ?? 'Order No.' ?>: <span class="text-bold"><?= htmlspecialchars($order['order_number'] ?? '') ?></span></p>
                        <p><?= $lang['order_date'] ?? 'Order Date' ?>: <?= !empty($order['order_date']) ? date("d/m/Y", strtotime($order['order_date'])) : '' ?></p>
                        <p><?= $lang['status'] ?? 'Status' ?>: <?= htmlspecialchars($lang['status_' . $order['status']] ?? ucfirst($order['status'] ?? '')) ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="partner-section">
            <h2><?= $lang['supplier_info'] ?? 'Supplier Information'; ?></h2>
            <?php $supplier_data = $order['supplier_info_data']; ?>
            <p><?= $lang['supplier_name'] ?? 'Supplier Name'; ?>: <span class="text-bold"><?= htmlspecialchars($supplier_data['partner_name'] ?? ($supplier_data['supplier_name'] ?? '')) ?></span></p>
            <p><?= $lang['address'] ?? 'Address'; ?>: <?= htmlspecialchars($supplier_data['address'] ?? '') ?></p>
            <p><?= $lang['phone'] ?? 'Phone'; ?>: <?= htmlspecialchars($supplier_data['phone'] ?? '') ?> | <?= $lang['email'] ?? 'Email'; ?>: <?= htmlspecialchars($supplier_data['email'] ?? '') ?></p>
            <p><?= $lang['tax_code'] ?? 'Tax Code'; ?>: <?= htmlspecialchars($supplier_data['tax_code'] ?? '') ?></p>
        </div>

        <div class="items-section">
             <h2><?= $lang['order_details'] ?? 'Order Details'; ?></h2>
            <table>
                <thead>
                    <tr>
                        <th class="text-center" style="width: 5%;"><?= $lang['stt'] ?? 'No.'; ?></th>
                        <th style="width: 15%;"><?= $lang['product_code'] ?? 'Code'; ?></th>
                        <th><?= $lang['product_name'] ?? 'Product Name'; ?></th>
                        <th class="text-center" style="width: 8%;"><?= $lang['quantity'] ?? 'Qty'; ?></th>
                        <th class="text-center" style="width: 8%;"><?= $lang['unit'] ?? 'Unit'; ?></th>
                        <th class="text-right" style="width: 12%;"><?= $lang['unit_price'] ?? 'Unit Price'; ?></th>
                        <th class="text-right" style="width: 15%;"><?= $lang['line_total'] ?? 'Total'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stt = 1;
                    if (!empty($order_details)):
                        foreach ($order_details as $item):
                            $quantity = $item['quantity'] ?? 0;
                            $unit_price = $item['unit_price'] ?? 0;
                            $line_total = $quantity * $unit_price;
                    ?>
                    <tr>
                        <td class="text-center"><?= $stt++; ?></td>
                        <td><?= htmlspecialchars($item['product_code'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['product_name_snapshot']) ?></td>
                        <td class="text-center"><?= number_format($quantity, 2, ',', '.') ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['unit_name'] ?? ($item['unit_snapshot'] ?? '')) ?></td>
                        <td class="text-right"><?= number_format($unit_price, ($order['currency'] === 'VND' ? 0 : 2), ',', '.') ?></td>
                        <td class="text-right"><?= number_format($line_total, ($order['currency'] === 'VND' ? 0 : 2), ',', '.') ?></td>
                    </tr>
                    <?php
                        endforeach;
                    else: ?>
                        <tr>
                            <td colspan="7" class="text-center"><?= $lang['no_items_in_order'] ?? 'No items found in this order.' ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="summary-section">
             <?php
                 $sub_total = $order['sub_total'] ?? 0;
                 $vat_rate = $order['vat_rate'] ?? 0;
                 $vat_total = $order['vat_total'] ?? 0;
                 $grand_total = $order['grand_total'] ?? 0;
             ?>
             <table>
                 <tr>
                     <td class="total-label"><?= $lang['subtotal'] ?? 'Subtotal'; ?>:</td>
                     <td class="total-value"><?= number_format($sub_total, ($order['currency'] === 'VND' ? 0 : 2), ',', '.'); ?> <?= htmlspecialchars($order['currency']) ?></td>
                 </tr>
                 <tr>
                     <td class="total-label"><?= $lang['vat'] ?? 'VAT'; ?> (<?= number_format($vat_rate, 2) ?>%):</td>
                     <td class="total-value"><?= number_format($vat_total, ($order['currency'] === 'VND' ? 0 : 2), ',', '.'); ?> <?= htmlspecialchars($order['currency']) ?></td>
                 </tr>
                 <tr class="grand-total">
                     <td class="total-label"><?= $lang['grand_total'] ?? 'Grand Total'; ?>:</td>
                     <td class="total-value"><?= number_format($grand_total, ($order['currency'] === 'VND' ? 0 : 2), ',', '.'); ?> <?= htmlspecialchars($order['currency']) ?></td>
                 </tr>
             </table>
        </div>

        <?php if (!empty($order['notes'])): ?>
        <div class="notes-section">
            <p><span class="text-bold"><?= $lang['notes'] ?? 'Notes'; ?>:</span> <?= nl2br(htmlspecialchars($order['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="signature-section">
             <table>
                 <tr>
                     <td>
                         <p class="text-bold"><?= $lang['supplier_signature'] ?? 'Supplier Representative'; ?></p>
                         <p>(<?= $lang['sign_and_name'] ?? 'Sign and write full name'; ?>)</p>
                         <div class="signature-line"></div>
                     </td>
                     <td>
                         <p class="text-bold"><?= $lang['buyer_signature'] ?? 'Buyer Representative'; ?></p>
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
             <?php if (!empty($order['created_by_name'])): ?>
                 <?= $lang['created_by'] ?? 'Created By' ?>: <?= htmlspecialchars($order['created_by_name']) ?> (<?= !empty($order['created_at']) ? date("d/m/Y H:i", strtotime($order['created_at'])) : '' ?>)
             <?php endif; ?>
         </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 'format' => 'A4',
        'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 25,
        'margin_bottom' => 20, 'margin_header' => 8, 'margin_footer' => 8,
        'default_font_size' => 10, 'default_font' => 'dejavusans'
    ]);

    $companyNameForPdf = $company_info_pdf['company_name'] ?? ($lang['appName'] ?? 'App');
    $orderNumberForPdf = $order['order_number'] ?? $order_id;
    $headerText = $companyNameForPdf . ' | ' . ($lang['sales_order_title'] ?? 'Purchase Order') . ' ' . $orderNumberForPdf . ' | Page {PAGENO}/{nbpg}';
    $footerText = ($lang['print_date'] ?? 'Print Date:') . ' {DATE d/m/Y H:i}';

    $mpdf->SetHeader($headerText);
    $mpdf->SetFooter($footerText);
    $mpdf->SetTitle(($lang['sales_order_title'] ?? 'Purchase Order') . ' - ' . $orderNumberForPdf);
    $mpdf->WriteHTML($html);

    $pdfFileName = ($lang['sales_order_short'] ?? 'PO') . '-' . preg_replace('/[^A-Za-z0-9_\-]/', '', $orderNumberForPdf) . '.pdf';
    $mpdf->Output($pdfFileName, \Mpdf\Output\Destination::INLINE);
    exit;

} catch (Exception $e) {
    error_log("PDF Error for Order ID {$order_id}: " . $e->getMessage());
    echo "PDF Generation Error: " . $e->getMessage();
}