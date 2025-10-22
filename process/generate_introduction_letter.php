<?php
// File: process/generate_introduction_letter.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/init.php';
require_login();

use Dompdf\Dompdf;
use Dompdf\Options;

if (!function_exists('get_image_base64')) {
    function get_image_base64(string $file_path): string {
        $image_info = pathinfo($file_path);
        $extension = strtolower($image_info['extension'] ?? 'png');
        $image_data = @file_get_contents($file_path);
        if ($image_data === false) return '';
        return 'data:image/' . $extension . ';base64,' . base64_encode($image_data);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Phương thức không hợp lệ.');
}

$document_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($document_id <= 0) {
    die('Thiếu ID đơn hàng.');
}

$pickup_date = $_POST['pickup_date'] ?? date('d/m/Y');
$valid_from = $_POST['valid_from'] ?? date('d/m');
$valid_to = $_POST['valid_to'] ?? date('d/m/Y');
$show_signature = isset($_POST['show_signature']) && $_POST['show_signature'] === '1';

// Lấy thông tin công ty
$company_stmt = $pdo->prepare("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
$company_stmt->execute();
$company_info_data = $company_stmt->fetch(PDO::FETCH_ASSOC);

$company_name_display = $company_info_data['name_vi'] ?? 'CÔNG TY TNHH ABC';
$company_address_display = $company_info_data['address_vi'] ?? '';
$company_phone = $company_info_data['phone'] ?? '';
$company_email = $company_info_data['email'] ?? '';
$company_website = $company_info_data['website'] ?? '';
$company_tax_id = $company_info_data['tax_id'] ?? '';
$logo_base64_to_display = '';

$logo_path = !empty($company_info_data['logo_path']) ? realpath(__DIR__ . '/../' . ltrim($company_info_data['logo_path'], '/')) : '';
if ($logo_path && file_exists($logo_path)) {
    $logo_base64_to_display = get_image_base64($logo_path);
} else {
    $placeholder_path = realpath(__DIR__ . '/../assets/img/placeholder_logo.png');
    if ($placeholder_path && file_exists($placeholder_path)) {
        $logo_base64_to_display = get_image_base64($placeholder_path);
    }
}

// Lấy thông tin đơn hàng
$sql = "
    SELECT so.id, so.order_number, so.order_date,
           d.ten AS driver_name, d.sdt AS driver_phone, d.bien_so_xe AS license_plate,
           d.cccd, d.ngay_cap, d.noi_cap,
           p.name AS supplier_name, p.address AS supplier_address
    FROM sales_orders so
    LEFT JOIN drivers d ON so.driver_id = d.id
    LEFT JOIN partners p ON so.supplier_id = p.id
    WHERE so.id = :id
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $document_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die('Không tìm thấy đơn hàng.');
}

// Lấy chi tiết sản phẩm
$sql_items = "
    SELECT product_name_snapshot, quantity, unit_snapshot
    FROM sales_order_details
    WHERE order_id = :order_id
";
$stmt = $pdo->prepare($sql_items);
$stmt->execute([':order_id' => $document_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime();
$day = $today->format('d');
$month = $today->format('m');
$year = $today->format('Y');

ob_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giấy giới thiệu #<?= htmlspecialchars($order['order_number']) ?></title>
    <style>
        @page { margin: 10mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.4; color: #333333; }
        .header-table { width: 100%; margin-bottom: 8px; table-layout: fixed; }
        .header-table td { vertical-align: middle; padding: 0; border: none; }
        .logo-cell { width: 10%; text-align: left; padding-right: 3px; }
        .logo-cell img { max-height: 100px; max-width: 100%; display: block; }
        .company-info-cell { width: 90%; text-align: center; padding-left: 3px; }
        .company-name { font-size: 13pt; font-weight: bold; color: #1a237e; margin-bottom: 2px; text-transform: uppercase; }
        .company-info-cell p { margin: 1px 0; font-size: 7.5pt; line-height: 1.1; }
        .section { margin: 15px 0; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border: 1px solid #ccc; padding: 5px; text-align: left; font-size: 8.5pt; }
        .signature-block { margin-top: 50px; text-align: right; }
        .signature-label { display: block; font-weight: bold; margin-top: 5px; text-align: center; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <?php if ($logo_base64_to_display): ?>
                    <img src="<?= $logo_base64_to_display ?>" alt="Logo">
                <?php endif; ?>
            </td>
            <td class="company-info-cell">
                <div class="company-name"><?= htmlspecialchars($company_name_display) ?></div>
                <p><?= htmlspecialchars($company_address_display) ?></p>
                <p>ĐT: <?= htmlspecialchars($company_phone) ?> | MST: <?= htmlspecialchars($company_tax_id) ?></p>
                <p>Website: <?= htmlspecialchars($company_website) ?> | Email: <?= htmlspecialchars($company_email) ?></p>
            </td>
        </tr>
    </table>

    <div class="document-title" style="text-align:center; font-size:16pt; font-weight:bold; margin-bottom:10px;">
        GIẤY GIỚI THIỆU NHẬN HÀNG
    </div>

    <div class="section">Kính gửi: <strong><?= htmlspecialchars($order['supplier_name']) ?></strong><br>
    <em>(<?= htmlspecialchars($order['supplier_address']) ?>)</em></div>

    <div class="section">Chúng tôi xin giới thiệu tài xế:</div>
    <ul>
        <li>Họ tên: <strong><?= htmlspecialchars($order['driver_name']) ?></strong></li>
        <li>Số CCCD: <?= htmlspecialchars($order['cccd']) ?></li>
        <li>Ngày cấp: <?= (!empty($order['ngay_cap']) ? date('d/m/Y', strtotime($order['ngay_cap'])) : '-') ?></li>
        <li>Nơi cấp: <?= htmlspecialchars($order['noi_cap']) ?></li>
        <li>Số điện thoại: <?= htmlspecialchars($order['driver_phone']) ?></li>
        <li>Biển số xe: <?= htmlspecialchars($order['license_plate']) ?></li>
    </ul>

    <div class="section">Sẽ đến nhận hàng vào ngày: <strong><?= date('d/m/Y', strtotime($pickup_date)) ?></strong></div>
    <div class="section">Giấy giới thiệu có hiệu lực từ <strong><?= htmlspecialchars($valid_from) ?></strong> đến <strong><?= htmlspecialchars($valid_to) ?></strong>.</div>

    <div class="section">Danh sách sản phẩm cần nhận:</div>
    <table class="items">
        <thead><tr><th>STT</th><th>Tên sản phẩm</th><th>Số lượng</th><th>Đơn vị</th></tr></thead>
        <tbody>
        <?php $stt = 1; foreach ($items as $item): ?>
            <tr>
                <td><?= $stt++ ?></td>
                <td><?= htmlspecialchars($item['product_name_snapshot']) ?></td>
                <td><?= htmlspecialchars($item['quantity']) ?></td>
                <td><?= htmlspecialchars($item['unit_snapshot']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="signature-block">
        <div>HCM, ngày <?= $day ?> tháng <?= $month ?> năm <?= $year ?></div>
        <div class="signature-label" style="text-align: right; margin-right: 1cm; font-weight: bold;">Người lập phiếu</div>

        <?php if ($show_signature && !empty($company_info_data['signature_path'])): 
            $signature_full = realpath(__DIR__ . '/../' . ltrim($company_info_data['signature_path'], '/'));
            if (file_exists($signature_full)) {
                $sig64 = get_image_base64($signature_full);
                echo '<img src="' . $sig64 . '" alt="Chữ ký" style="width:160px; display:block; margin: 10px auto 0 auto;"><br>';
            }
        endif; ?>
        
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('GiayGioiThieu_' . $order['order_number'] . '.pdf', ['Attachment' => false]);
exit;
