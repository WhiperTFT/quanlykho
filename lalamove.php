<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/libs/psr-simple-cache/CacheInterface.php';

// PhpSpreadsheet portable autoload
spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $baseDir = __DIR__ . '/libs/phpspreadsheet/src/PhpSpreadsheet/';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require_once $file;
});

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

// ===================== Helpers =====================
function parseMoney($value)
{
    if ($value instanceof RichText) {
        $value = $value->getPlainText();
    }
    $value = preg_replace('/[^0-9\-]/', '', $value);
    return abs((int)$value);
}

$headerData = [];
$rows = [];
$totalCharge = 0;

if (!empty($_FILES['excel']['tmp_name'])) {

    $spreadsheet = IOFactory::load($_FILES['excel']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();

    // -------- HEADER A1:B8
    for ($i = 1; $i <= 8; $i++) {
        $headerData[] = [
            'label' => trim($sheet->getCell("A$i")->getFormattedValue()),
            'value' => trim($sheet->getCell("B$i")->getFormattedValue()),
        ];
    }

    // map header
    $map = [];
    foreach ($headerData as $h) {
        $map[$h['label']] = $h['value'];
    }

    // -------- DETAIL FROM ROW 10
    $highestRow = $sheet->getHighestRow();
    for ($row = 10; $row <= $highestRow; $row++) {

        $charge = parseMoney($sheet->getCell("D$row")->getValue());
        $totalCharge += $charge;

        $rows[] = [
            'created_time' => $sheet->getCell("J$row")->getFormattedValue(),
            'order_path'   => $sheet->getCell("Q$row")->getFormattedValue(),
            'pickup'       => $sheet->getCell("R$row")->getFormattedValue(),
            'dropoff'      => $sheet->getCell("S$row")->getFormattedValue(),
            'distance'     => $sheet->getCell("T$row")->getFormattedValue(),
            'special'      => $sheet->getCell("V$row")->getFormattedValue(),
            'charge'       => $charge,
        ];
    }

    // -------- SAVE REPORT
    $stmt = $pdo->prepare("
        INSERT INTO lalamove_reports
        (company_name, company_address, company_id, date_range, generated_at, total_charge)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $map['Company Name'] ?? '',
        $map['Company Address'] ?? '',
        $map['Company ID'] ?? '',
        $map['Date Range'] ?? '',
        $map['Date/Time of Generation'] ?? '',
        $totalCharge
    ]);

    $reportId = $pdo->lastInsertId();

    // -------- SAVE ITEMS
    $stmtItem = $pdo->prepare("
        INSERT INTO lalamove_report_items
        (report_id, created_time, order_path, pickup_address, dropoff_address, distance, special_request, final_charge)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($rows as $r) {
        $stmtItem->execute([
            $reportId,
            $r['created_time'],
            $r['order_path'],
            $r['pickup'],
            $r['dropoff'],
            $r['distance'],
            $r['special'],
            $r['charge']
        ]);
    }
}

include __DIR__ . '/includes/header.php';
?>

<h2>🚚 Báo cáo Lalamove</h2>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="excel" accept=".xls,.xlsx" required>
    <button type="submit" class="btn btn-primary">Xem báo cáo</button>
    <a href="lalamove_reports.php" class="btn btn-secondary">
        📊 Xem thống kê các chuyến đã lưu
    </a>
</form>

<?php if ($headerData): ?>
<hr>

<h3>Thông tin chung</h3>
<table class="table table-bordered">
<?php foreach ($headerData as $h): ?>
<tr>
    <td><b><?= htmlspecialchars($h['label']) ?></b></td>
    <td><?= htmlspecialchars($h['value']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3>Chi tiết chuyến xe</h3>
<table class="table table-bordered">
<thead>
<tr>
    <th>Thời gian</th>
    <th>Lộ trình</th>
    <th>Lấy hàng</th>
    <th>Giao hàng</th>
    <th>Km</th>
    <th>Yêu cầu</th>
    <th>Phí (VND)</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
    <td><?= $r['created_time'] ?></td>
    <td><?= $r['order_path'] ?></td>
    <td><?= $r['pickup'] ?></td>
    <td><?= $r['dropoff'] ?></td>
    <td><?= $r['distance'] ?></td>
    <td><?= $r['special'] ?></td>
    <td class="text-end"><?= number_format($r['charge'],0,',','.') ?> ₫</td>
</tr>
<?php endforeach; ?>
<tr>
    <td colspan="6" class="text-end"><b>TỔNG CỘNG</b></td>
    <td class="text-end"><b><?= number_format($totalCharge,0,',','.') ?> ₫</b></td>
</tr>
</tbody>
</table>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
