<?php
// process/export_invoice_pdf.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/init.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!is_logged_in()) {
    die("Unauthorized");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID is required");
}

// Fetch invoice data
try {
    $stmt = $pdo->prepare("SELECT i.*, 
        pb.name as bill_to_name, pb.address as bill_to_address, pb.phone as bill_to_phone, pb.tax_id as bill_to_tax,
        ps.name as ship_to_name, ps.address as ship_to_address, ps.phone as ship_to_phone, ps.tax_id as ship_to_tax
        FROM invoices i 
        LEFT JOIN partners pb ON i.partner_bill_id = pb.id 
        LEFT JOIN partners ps ON i.partner_ship_id = ps.id 
        WHERE i.id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        die("Invoice not found");
    }
    $invoice['items'] = json_decode($invoice['items'], true);

    $stmt_company = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
    $company = $stmt_company->fetch();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// PDF Options
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Times-Roman');
$dompdf = new Dompdf($options);

// HTML Template
$html = '
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 1cm 1.5cm; }
        body { 
            font-family: "DejaVu Sans", sans-serif; 
            font-size: 9pt; 
            line-height: 1.3; 
            color: #000; 
            margin: 0; 
            padding: 0; 
        }
        .container { width: 100%; }
        
        /* Header Section */
        .header { margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .company-name { font-weight: bold; font-size: 14pt; text-transform: uppercase; margin-bottom: 4px; }
        .company-info { font-size: 9pt; margin-bottom: 2px; }
        
        /* Title Section */
        .doc-title { text-align: center; font-size: 18pt; font-weight: bold; margin: 30px 0 5px 0; }
        .doc-no-date { text-align: center; margin-bottom: 25px; font-size: 8.5pt; }
        
        /* Partners Section */
        .partners-section { width: 100%; margin-bottom: 20px; }
        .partner-box { width: 48%; float: left; }
        .partner-box.right { float: right; }
        .partner-label { font-weight: bold; text-decoration: underline; margin-bottom: 5px; font-size: 9.5pt; }
        .partner-detail { margin-bottom: 2px; font-size: 8.5pt; min-height: 1.2em; }
        
        /* Table Section */
        table.items-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
        table.items-table th, table.items-table td { border: 1px solid #000; padding: 6px 4px; font-size: 10pt; word-wrap: break-word; }
        table.items-table th { background-color: #eee; font-weight: bold; text-align: center; text-transform: uppercase; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        
        /* Footer Section */
        .summary-section { margin-top: 10px; border-top: 1px solid #000; padding-top: 5px; }
        .total-row { font-weight: bold; font-size: 12pt; }
        .say-section { margin-top: 10px; font-style: italic; font-weight: bold; }
        .packing-info { margin-top: 20px; font-size: 10pt; }
        
        /* Signature Section */
        .signature-section { width: 100%; margin-top: 40px; }
        .signature-box { float: right; width: 250px; text-align: center; }
        .signature-name { font-weight: bold; text-transform: uppercase; margin-bottom: 60px; }
        
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    <div class="container">
        <!-- 4 lines header from info_company -->
        <div class="header">
            <div class="company-name">' . htmlspecialchars($company['name_vi'] ?? '') . '</div>
            <div class="company-info">Add: ' . htmlspecialchars($company['address_vi'] ?? '') . '</div>
            <div class="company-info">Tel: ' . htmlspecialchars($company['phone'] ?? '') . '</div>
            <div class="company-info">Tax code: ' . htmlspecialchars($company['tax_id'] ?? '') . '</div>
        </div>

        <div class="doc-title">INVOICE / PACKING LIST</div>
        <div class="doc-no-date">
            Date: ' . date('d/m/Y', strtotime($invoice['invoice_date'])) . '<br>
            No: ' . htmlspecialchars($invoice['invoice_no']) . '
        </div>

        <div class="partners-section clearfix">
            <div class="partner-box">
                <div class="partner-label">BILL TO:</div>
                <div class="partner-detail"><strong>' . htmlspecialchars($invoice['bill_to_name']) . '</strong></div>
                <div class="partner-detail">Add: ' . htmlspecialchars($invoice['bill_to_address']) . '</div>
                <div class="partner-detail">Tell: ' . htmlspecialchars($invoice['bill_to_phone']) . '</div>
                <div class="partner-detail">Tax code: ' . htmlspecialchars($invoice['bill_to_tax']) . '</div>
            </div>
            <div class="partner-box right">
                <div class="partner-label">SHIP TO:</div>
                <div class="partner-detail"><strong>' . htmlspecialchars($invoice['ship_to_name'] ?: $invoice['bill_to_name']) . '</strong></div>
                <div class="partner-detail">Add: ' . htmlspecialchars($invoice['ship_to_address'] ?: $invoice['bill_to_address']) . '</div>
                <div class="partner-detail">Tell: ' . htmlspecialchars($invoice['ship_to_phone'] ?: $invoice['bill_to_phone']) . '</div>
                <div class="partner-detail">Tax code: ' . htmlspecialchars($invoice['ship_to_tax'] ?: $invoice['bill_to_tax']) . '</div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>Description of Goods</th>
                    <th style="width: 80px;">Quantity<br>(KG)</th>
                    <th style="width: 100px;">Unit Price<br>(VND)</th>
                    <th style="width: 110px;">Total<br>(VND)</th>
                    <th style="width: 80px;">Remark</th>
                </tr>
            </thead>
            <tbody>';

$count = 1;
foreach ($invoice['items'] as $item) {
    $qty = (float)$item['quantity'];
    $price = (float)str_replace(',', '', (string)$item['price']);
    $total = $qty * $price;
    
    $html .= '
                <tr>
                    <td class="text-center">' . $count++ . '</td>
                    <td>' . nl2br(htmlspecialchars($item['description'])) . '</td>
                    <td class="text-right">' . number_format($qty, 2) . '</td>
                    <td class="text-right">' . number_format($price, 0) . '</td>
                    <td class="text-right">' . number_format($total, 0) . '</td>
                    <td>' . htmlspecialchars($item['remark'] ?? '') . '</td>
                </tr>';
}

$html .= '
            </tbody>
        </table>

        <div class="summary-section clearfix">
            <div style="float: left; width: 60%; font-weight: bold;">
                TOTAL: ' . ($invoice['total_remark'] ? '<span style="font-weight: normal; font-style: italic; margin-left: 10px;">' . htmlspecialchars($invoice['total_remark']) . '</span>' : '') . '
            </div>
            <div style="float: right; width: 40%; text-align: right; font-weight: bold; font-size: 13pt;">VND ' . number_format($invoice['total_amount'], 0) . '</div>
        </div>

        <div class="say-section">
            SAY: ' . htmlspecialchars($invoice['total_text']) . '
        </div>

        <div class="packing-info">
            <div style="margin-bottom: 5px;"><strong>Packing:</strong> ' . htmlspecialchars($invoice['packing']) . '</div>
            <div><strong>Net weight:</strong> ' . htmlspecialchars($invoice['net_weight']) . '</div>
        </div>

        <div class="signature-section clearfix">
            <div class="signature-box">
                <div class="signature-name">' . htmlspecialchars($company['name_vi'] ?? '') . '</div>
                <div style="margin-top: 80px;"></div>
            </div>
        </div>
    </div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Invoice_' . str_replace(['/', '\\'], '_', $invoice['invoice_no']) . '.pdf';
$dompdf->stream($filename, ["Attachment" => false]);
?>
