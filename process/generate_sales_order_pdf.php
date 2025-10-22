<?php
// File: process/generate_sales_order_pdf.php

// 1. Nạp các file cần thiết
require_once '../vendor/autoload.php'; // Nạp thư viện mPDF
require_once '../includes/init.php';   // Nạp file khởi tạo ($pdo, $lang, session...)

// 2. Kiểm tra quyền truy cập (Ví dụ: người dùng phải đăng nhập)
if (!is_logged_in()) { // Sử dụng hàm is_logged_in() từ init.php
    die($lang['access_denied'] ?? 'Access Denied.');
}

// 3. Lấy và xác thực ID đơn hàng từ URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die($lang['invalid_order_id'] ?? 'Invalid Order ID.');
}
$order_id = (int)$_GET['id'];

// 4. Truy vấn dữ liệu từ Database
try {
    // Lấy thông tin chung của đơn hàng và thông tin khách hàng (partner)
    $sql_order = "SELECT so.*,
                         p.partner_name, p.address AS partner_address, p.phone AS partner_phone,
                         p.email AS partner_email, p.tax_code AS partner_tax_code,
                         u_creator.full_name AS created_by_name,
                         u_updater.full_name AS updated_by_name
                  FROM sales_orders so
                  LEFT JOIN partners p ON so.partner_id = p.id
                  LEFT JOIN users u_creator ON so.created_by = u_creator.id
                  LEFT JOIN users u_updater ON so.updated_by = u_updater.id /* Giả sử có cột updated_by */
                  WHERE so.id = :order_id";
    $stmt_order = $pdo->prepare($sql_order); // Sử dụng $pdo
    $stmt_order->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt_order->execute();
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die(($lang['order_not_found'] ?? 'Order not found with ID:') . ' ' . $order_id);
    }

    // Lấy chi tiết các sản phẩm trong đơn hàng
    $sql_details = "SELECT sod.*, pr.product_code, pr.product_name, u.unit_name
                    FROM sales_order_details sod
                    LEFT JOIN products pr ON sod.product_id = pr.id
                    LEFT JOIN units u ON pr.unit_id = u.id /* Join Unit từ Product */
                    WHERE sod.sales_order_id = :order_id";
    $stmt_details = $pdo->prepare($sql_details); // Sử dụng $pdo
    $stmt_details->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt_details->execute();
    $order_details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

    // Lấy thông tin công ty
    $sql_company = "SELECT * FROM company_info WHERE id = 1 LIMIT 1";
    $stmt_company = $pdo->query($sql_company); // Sử dụng $pdo
    $company_info = $stmt_company->fetch(PDO::FETCH_ASSOC);

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
    <title><?= $lang['sales_order_title'] ?? 'Sales Order' ?> - <?= htmlspecialchars($order['order_code'] ?? $order_id) ?></title>
    <style>
        /* Nhúng CSS từ file chính */
        <?php
        $cssPath = __DIR__ . '/../assets/css/style.css'; // Đường dẫn tới file CSS chính
        if (file_exists($cssPath)) {
            echo file_get_contents($cssPath);
        }
        ?>

        /* CSS cơ bản và các điều chỉnh riêng cho PDF */
        body {
            font-family: 'dejavusans', sans-serif; /* Font hỗ trợ tiếng Việt */
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        .pdf-container {
            width: 100%;
            padding: 0; /* Không cần padding lớn nếu dùng margin trang */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9pt;
            page-break-inside: auto; /* Cho phép ngắt bảng giữa các trang */
        }
         tr {
             page-break-inside: avoid; /* Cố gắng không ngắt dòng giữa chừng */
             page-break-after: auto;
         }
         thead {
             display: table-header-group; /* Lặp lại a a aheader trên mỗi trang */
             background-color: #f2f2f2;
         }
        th, td {
            border: 0.5pt solid #ccc; /* Border mảnh hơn */
            padding: 5px 6px; /* Điều chỉnh padding */
            text-align: left;
            vertical-align: top;
        }
        th {
            font-weight: bold;
            white-space: nowrap; /* Không xuống dòng tiêu đề cột */
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        h1 { font-size: 18pt; text-align: center; color: #000; margin-bottom: 15px; font-weight: bold; }
        h2 { font-size: 13pt; color: #222; margin-bottom: 8px; margin-top: 15px; border-bottom: 1px solid #eee; padding-bottom: 3px; font-weight: bold;}
        h3 { font-size: 11pt; font-weight: bold; margin-bottom: 5px; }
        p { margin: 2px 0; }
        hr { display: none; } /* Ẩn thẻ hr vì có thể gây ngắt trang không mong muốn */

        .header-section { margin-bottom: 15px; }
        .header-section table { border: none; } /* Bỏ border cho table header */
        .header-section td { border: none; vertical-align: top; padding: 0 5px; }
        .company-logo img { max-width: 150px; max-height: 70px; margin-bottom: 5px;}

        .customer-section { margin-bottom: 15px; }
        .items-section table tbody td { height: auto; }

        .summary-section { margin-top: 15px; }
        .summary-section table { width: 50%; margin-left: 50%; border: none; }
        .summary-section td { border: none; padding: 3px 6px; }
        .summary-section .total-label { text-align: right; font-weight: bold; padding-right: 10px; white-space: nowrap; }
        .summary-section .total-value { text-align: right; }
        .summary-section .grand-total td { border-top: 1pt solid #555; padding-top: 5px; font-weight: bold; font-size: 11pt; }

        .notes-section { margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 10px; font-size: 9pt; }
        .signature-section { margin-top: 40px; page-break-inside: avoid; } /* Tránh ngắt trang ở phần chữ ký */
        .signature-section table { width: 100%; border: none; text-align: center; }
        .signature-section td { border: none; width: 48%; padding: 0 1%; vertical-align: bottom;}
        .signature-section p { margin-bottom: 60px; /* Khoảng trống để ký */}
        .signature-section .signature-line { border-bottom: 1px dotted #555; margin: 5px auto; width: 80%; }

        /* Ẩn các phần tử không cần thiết */
        .no-print, .btn, .action-buttons, .breadcrumb, #mainNavbar, #sidebar, .modal, form button { display: none !important; }
        /* Có thể thêm class 'pdf-hide' vào các phần tử muốn ẩn trên PDF */
        .pdf-hide { display: none !important; }

    </style>
</head>
<body>
    <div class="pdf-container">
        <div class="header-section">
            <table>
                <tr>
                    <td style="width: 60%;">
                        <?php if ($company_info): ?>
                            <?php if (!empty($company_info['logo']) && file_exists('../' . $company_info['logo'])): ?>
                                <div class="company-logo">
                                    <img src="<?= '../' . $company_info['logo'] ?>" alt="Logo">
                                </div>
                            <?php endif; ?>
                            <h3 class="text-bold"><?= htmlspecialchars($company_info['company_name'] ?? '') ?></h3>
                            <p><?= $lang['address'] ?? 'Address' ?>: <?= htmlspecialchars($company_info['address'] ?? '') ?></p>
                            <p><?= $lang['phone'] ?? 'Phone' ?>: <?= htmlspecialchars($company_info['phone'] ?? '') ?> | <?= $lang['email'] ?? 'Email' ?>: <?= htmlspecialchars($company_info['email'] ?? '') ?></p>
                            <p><?= $lang['tax_code'] ?? 'Tax Code' ?>: <?= htmlspecialchars($company_info['tax_code'] ?? '') ?></p>
                         <?php endif; ?>
                    </td>
                    <td style="width: 40%; text-align: right;">
                        <h1><?= $lang['sales_order_title_pdf'] ?? 'SALES ORDER' ?></h1>
                        <p><?= $lang['order_code'] ?? 'Order Code' ?>: <span class="text-bold"><?= htmlspecialchars($order['order_code'] ?? '') ?></span></p>
                        <p><?= $lang['order_date'] ?? 'Order Date' ?>: <?= !empty($order['order_date']) ? date("d/m/Y", strtotime($order['order_date'])) : '' ?></p>
                         <p><?= $lang['status'] ?? 'Status' ?>: <?= htmlspecialchars($lang['status_' . $order['status']] ?? ucfirst($order['status'] ?? '')) ?></p>
                    </td>
                </tr>
            </table>
        </div>


        <div class="customer-section">
            <h2><?= $lang['customer_info'] ?? 'Customer Information'; ?></h2>
            <p><?= $lang['customer_name'] ?? 'Customer Name'; ?>: <span class="text-bold"><?= htmlspecialchars($order['partner_name'] ?? '') ?></span></p>
            <p><?= $lang['address'] ?? 'Address'; ?>: <?= htmlspecialchars($order['partner_address'] ?? '') ?></p>
            <p><?= $lang['phone'] ?? 'Phone'; ?>: <?= htmlspecialchars($order['partner_phone'] ?? '') ?> | <?= $lang['email'] ?? 'Email'; ?>: <?= htmlspecialchars($order['partner_email'] ?? '') ?></p>
            <p><?= $lang['tax_code'] ?? 'Tax Code'; ?>: <?= htmlspecialchars($order['partner_tax_code'] ?? '') ?></p>
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
                    $subtotal = 0; // Tính lại subtotal từ chi tiết
                    if (!empty($order_details)):
                        foreach ($order_details as $item):
                            $quantity = $item['quantity'] ?? 0;
                            $unit_price = $item['unit_price'] ?? 0;
                            $line_total = $quantity * $unit_price;
                            $subtotal += $line_total;
                    ?>
                    <tr>
                        <td class="text-center"><?= $stt++; ?></td>
                        <td><?= htmlspecialchars($item['product_code'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['product_name'] ?? '') ?>
                            <?php if(!empty($item['description'])): ?>
                                <br><small><i><?= nl2br(htmlspecialchars($item['description'])) ?></i></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= number_format($quantity, 2, ',', '.') // Hiển thị 2 số lẻ cho số lượng ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['unit_name'] ?? '') ?></td>
                        <td class="text-right"><?= number_format($unit_price, 0, ',', '.') ?></td>
                        <td class="text-right"><?= number_format($line_total, 0, ',', '.') ?></td>
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
                 // Lấy giá trị từ $order, tính toán nếu cần
                 $discount_amount = $order['discount_amount'] ?? 0;
                 // Tính subtotal sau CK dựa trên subtotal tính từ chi tiết và discount_amount từ order
                 $subtotal_after_discount = $subtotal - $discount_amount;
                 $vat_amount = $order['vat_amount'] ?? 0;
                 $grand_total = $order['total_amount'] ?? ($subtotal_after_discount + $vat_amount); // Nên dùng total_amount từ $order
             ?>
             <table>
                 <tr>
                     <td class="total-label"><?= $lang['subtotal'] ?? 'Subtotal'; ?>:</td>
                     <td class="total-value"><?= number_format($subtotal, 0, ',', '.'); ?></td>
                 </tr>
                 <?php if (isset($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                 <tr>
                    <td class="total-label"><?= $lang['discount'] ?? 'Discount'; ?> (<?= number_format($order['discount_percentage'] ?? 0, 2) ?>%):</td>
                    <td class="total-value"><?= number_format($discount_amount, 0, ',', '.'); ?></td>
                </tr>
                 <tr>
                    <td class="total-label"><?= $lang['subtotal_after_discount'] ?? 'Subtotal After Discount'; ?>:</td>
                    <td class="total-value"><?= number_format($subtotal_after_discount, 0, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
                 <?php if (isset($order['vat_percentage'])): // Luôn hiển thị VAT, kể cả 0% ?>
                 <tr>
                     <td class="total-label"><?= $lang['vat'] ?? 'VAT'; ?> (<?= number_format($order['vat_percentage'] ?? 0, 2) ?>%):</td>
                     <td class="total-value"><?= number_format($vat_amount, 0, ',', '.'); ?></td>
                 </tr>
                 <?php endif; ?>
                 <tr class="grand-total">
                     <td class="total-label"><?= $lang['grand_total'] ?? 'Grand Total'; ?>:</td>
                     <td class="total-value"><?= number_format($grand_total, 0, ',', '.'); ?></td>
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
                         <p class="text-bold"><?= $lang['customer'] ?? 'Customer'; ?></p>
                         <p>(<?= $lang['sign_and_name'] ?? 'Sign and write full name'; ?>)</p>
                         <div class="signature-line"></div>
                     </td>
                     <td>
                         <p class="text-bold"><?= $lang['seller'] ?? 'Seller'; ?></p> <?php // Hoặc Đại diện công ty ?>
                         <p>(<?= $lang['sign_and_name'] ?? 'Sign and write full name'; ?>)</p>
                          <div class="signature-line"></div>
                     </td>
                 </tr>
             </table>
         </div>

         <div style="text-align: center; font-size: 8pt; color: #777; margin-top: 20px;">
             <?php if (!empty($order['created_by_name'])): ?>
                 <?= $lang['created_by'] ?? 'Created By' ?>: <?= htmlspecialchars($order['created_by_name']) ?> (<?= !empty($order['created_at']) ? date("d/m/Y H:i", strtotime($order['created_at'])) : '' ?>)
             <?php endif; ?>
             <?php if (!empty($order['updated_by_name'])): ?>
                 | <?= $lang['last_updated_by'] ?? 'Updated By' ?>: <?= htmlspecialchars($order['updated_by_name']) ?> (<?= !empty($order['updated_at']) ? date("d/m/Y H:i", strtotime($order['updated_at'])) : '' ?>)
             <?php endif; ?>
         </div>

    </div></body>
</html>
<?php
$html = ob_get_clean();

// 6. Khởi tạo và cấu hình mPDF
try {
    $defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];
    $defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $tempDir = __DIR__ . '/../tmp/mpdf';
    if (!is_dir($tempDir)) {
        if (!mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }
    }
    if (!is_writable($tempDir)) {
         throw new \RuntimeException(sprintf('Directory "%s" is not writable', $tempDir));
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 25,  // Tăng margin top cho header
        'margin_bottom' => 20, // Margin bottom cho footer
        'margin_header' => 8,
        'margin_footer' => 8,
        'default_font_size' => 10,
        'default_font' => 'dejavusans',
        'tempDir' => $tempDir,
        'fontDir' => array_merge($fontDirs, [ /* Thư mục font tùy chỉnh nếu có */ ]),
        'fontdata' => $fontData + [ /* Font tùy chỉnh nếu có */ ],
        'autoScriptToLang' => true,
        'autoLangToFont' => true,
        'useSubstitutions' => false, // Tắt thay thế nếu font đã đủ ký tự
        'enableImports' => true, // Cho phép import CSS từ file
    ]);

    // Kích hoạt chế độ log lỗi chi tiết của mPDF (chỉ dùng khi debug)
    // $mpdf->showImageErrors = true;
    // $mpdf->debug = true;

    // (Tùy chọn) Thêm Header/Footer
    $companyName = $company_info['company_name'] ?? ($lang['appName'] ?? 'App');
    $orderCode = $order['order_code'] ?? $order_id;
    $headerText = $companyName . ' | ' . ($lang['sales_order_title'] ?? 'Sales Order') . ' ' . $orderCode . ' | Trang {PAGENO}/{nbpg}';
    $footerText = ($lang['print_date'] ?? 'Print Date:') . ' {DATE d/m/Y H:i}';

    $mpdf->SetHeader($headerText);
    $mpdf->SetFooter($footerText);
    $mpdf->SetTitle($lang['sales_order_title'] ?? 'Sales Order' . ' - ' . $orderCode);
    $mpdf->SetAuthor($companyName);

    // Ghi HTML vào PDF
    $mpdf->WriteHTML($html);

    // 7. Xuất file PDF
    $pdfFileName = ($lang['sales_order_short'] ?? 'SO') . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $orderCode) . '.pdf';
    $mpdf->Output($pdfFileName, \Mpdf\Output\Destination::INLINE); // 'I' = INLINE
    exit;

} catch (\Mpdf\MpdfException $e) {
    error_log("mPDF Error for Order ID {$order_id}: " . $e->getMessage());
    echo ($lang['pdf_generation_error'] ?? 'PDF Generation Error:') . ' ' . $e->getMessage(); // Hiển thị lỗi cho người dùng (có thể chỉ khi đang debug)
} catch (Exception $e) {
     error_log("General Error in PDF Generation for Order ID {$order_id}: " . $e->getMessage());
     echo ($lang['general_error'] ?? 'An unexpected error occurred:') . ' ' . $e->getMessage(); // Hiển thị lỗi chung
}
?>