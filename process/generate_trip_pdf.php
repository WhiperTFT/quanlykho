<?php
// File: process/generate_trip_pdf.php
require_once '../vendor/autoload.php';
require_once '../includes/init.php';

if (!is_logged_in()) {
    die($lang['access_denied'] ?? 'Access Denied.');
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die('Invalid Trip ID.');
}
$trip_id = (int)$_GET['id'];

try {
    // 1. Fetch Trip Info
    $stmt = $pdo->prepare("
        SELECT t.*, d.ten as driver_name 
        FROM dispatcher_trips t 
        JOIN drivers d ON t.driver_id = d.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$trip_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) die('Trip not found.');

    // 2. Fetch All Items from all orders in this trip
    // We group items by Product to give a consolidated view of what needs to be loaded
    $stmt = $pdo->prepare("
        SELECT 
            p.product_code, p.product_name, u.unit_name,
            SUM(sod.quantity) as total_qty,
            GROUP_CONCAT(DISTINCT so.order_number SEPARATOR ', ') as linked_orders
        FROM dispatcher_trip_orders dto
        JOIN sales_orders so ON dto.order_id = so.id
        JOIN sales_order_details sod ON so.id = sod.sales_order_id
        JOIN products p ON sod.product_id = p.id
        LEFT JOIN units u ON p.unit_id = u.id
        WHERE dto.trip_id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$trip_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Company Info
    $company_info = $pdo->query("SELECT * FROM company_info LIMIT 1")->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= $lang['delivery_dispatcher'] ?> - <?= $trip['trip_number'] ?></title>
    <style>
        body { font-family: 'dejavusans', sans-serif; font-size: 10pt; line-height: 1.4; color: #333; }
        .header-section { margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .trip-info { margin-bottom: 20px; }
        .trip-info table { width: 100%; border: none; }
        .trip-info td { border: none; padding: 5px; }
        table.items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items-table th, table.items-table td { border: 1px solid #333; padding: 8px; text-align: left; }
        table.items-table th { background-color: #f2f2f2; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .signature-section { margin-top: 50px; }
        .signature-section table { width: 100%; border: none; text-align: center; }
        .signature-section td { border: none; width: 33%; vertical-align: top; }
        h1 { margin: 0; font-size: 18pt; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header-section">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;">
                    <h3 style="margin:0;"><?= htmlspecialchars($company_info['company_name']) ?></h3>
                    <p style="margin:2px 0; font-size: 9pt;"><?= htmlspecialchars($company_info['address']) ?></p>
                    <p style="margin:2px 0; font-size: 9pt;">Tel: <?= htmlspecialchars($company_info['phone']) ?></p>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top;">
                    <h1><?= $lang['warehouse_dispatch_slip'] ?? 'BẢNG KÊ GIAO HÀNG' ?></h1>
                    <p class="text-bold"><?= $lang['trip_number'] ?>: <?= $trip['trip_number'] ?></p>
                    <p><?= $lang['trip_date'] ?>: <?= date('d/m/Y', strtotime($trip['trip_date'])) ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="trip-info">
        <table>
            <tr>
                <td style="width: 15%;" class="text-bold"><?= $lang['driver'] ?>:</td>
                <td style="width: 35%;"><?= htmlspecialchars($trip['driver_name']) ?></td>
                <td style="width: 15%;" class="text-bold"><?= $lang['vehicle_plate'] ?>:</td>
                <td style="width: 35%;"><?= htmlspecialchars($trip['vehicle_plate'] ?: '---') ?></td>
            </tr>
            <tr>
                <td class="text-bold"><?= $lang['notes'] ?>:</td>
                <td colspan="3"><?= nl2br(htmlspecialchars($trip['notes'] ?: '---')) ?></td>
            </tr>
        </table>
    </div>

    <h3><?= $lang['products_in_this_shipment:'] ?? 'Danh sách hàng hóa:' ?></h3>
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 5%;">STT</th>
                <th style="width: 15%;"><?= $lang['product_code'] ?></th>
                <th><?= $lang['product_name'] ?></th>
                <th class="text-center" style="width: 10%;"><?= $lang['unit'] ?></th>
                <th class="text-center" style="width: 10%;"><?= $lang['quantity'] ?></th>
                <th><?= $lang['order_number'] ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $stt = 1; foreach ($items as $item): ?>
            <tr>
                <td class="text-center"><?= $stt++ ?></td>
                <td><?= htmlspecialchars($item['product_code']) ?></td>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="text-center"><?= htmlspecialchars($item['unit_name']) ?></td>
                <td class="text-center text-bold"><?= number_format($item['total_qty'], 2, ',', '.') ?></td>
                <td style="font-size: 8pt;"><?= htmlspecialchars($item['linked_orders']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="signature-section">
        <table>
            <tr>
                <td>
                    <p class="text-bold"><?= $lang['sender_signature'] ?? 'Người lập phiếu' ?></p>
                    <p>(Ký, họ tên)</p>
                </td>
                <td>
                    <p class="text-bold"><?= $lang['driver'] ?></p>
                    <p>(Ký, họ tên)</p>
                </td>
                <td>
                    <p class="text-bold"><?= $lang['recipient_signature'] ?? 'Người nhận hàng' ?></p>
                    <p>(Ký, họ tên)</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'default_font' => 'dejavusans'
    ]);
    $mpdf->WriteHTML($html);
    $mpdf->Output('Trip_' . $trip['trip_number'] . '.pdf', \Mpdf\Output\Destination::INLINE);
} catch (Exception $e) {
    die('PDF Error: ' . $e->getMessage());
}
