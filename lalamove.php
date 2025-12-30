<?php
require_once 'includes/init.php';
require_once 'includes/excel.php'; // file bạn đã test OK

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

function cellText($v) {
    if ($v instanceof RichText) {
        return trim($v->getPlainText());
    }
    return trim((string)$v);
}

$headerMap = [
    'Company Name'      => 'Tên công ty',
    'Date Range'        => 'Khoảng thời gian',
    'Currency'          => 'Loại tiền',
    'Total Orders'      => 'Tổng số chuyến',
    'Completed Orders'  => 'Hoàn thành',
    'Cancelled Orders'  => 'Huỷ',
    'Total Fee'         => 'Tổng phí (Lalamove)',
    'Generated Time'    => 'Thời điểm tạo báo cáo',
];

$headerDisplay = [];
$items = [];
$totalFee = 0;
$uploaded = false;
$error = '';

if (!empty($_FILES['excel']['tmp_name'])) {
    try {
        $spreadsheet = IOFactory::load($_FILES['excel']['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();

        // ===== A1 → B8 =====
        $headerRaw = [];
        for ($i = 1; $i <= 8; $i++) {
            $k = cellText($sheet->getCell("A$i")->getValue());
            $v = cellText($sheet->getCell("B$i")->getFormattedValue());
            if ($k !== '') {
                $headerRaw[$k] = $v;
                $headerDisplay[] = [
                    'label' => $headerMap[$k] ?? $k,
                    'value' => $v
                ];
            }
        }

        // Date range
        preg_match('/(\d{4}-\d{2}-\d{2}).*(\d{4}-\d{2}-\d{2})/', $headerRaw['Date Range'], $m);
        $dateFrom = $m[1] ?? null;
        $dateTo   = $m[2] ?? null;

        // ===== Check duplicate =====
        $isDuplicate = 0;
        $chk = $pdo->prepare("
            SELECT COUNT(*) FROM lalamove_reports
            WHERE company_name = ? AND date_from = ? AND date_to = ?
        ");
        $chk->execute([$headerRaw['Company Name'], $dateFrom, $dateTo]);
        if ($chk->fetchColumn() > 0) $isDuplicate = 1;

        // ===== Insert report =====
        $stmt = $pdo->prepare("
            INSERT INTO lalamove_reports
            (company_name, date_from, date_to, total_orders, total_fee, generated_time, is_duplicate)
            VALUES (?, ?, ?, ?, 0, ?, ?)
        ");
        $stmt->execute([
            $headerRaw['Company Name'],
            $dateFrom,
            $dateTo,
            (int)($headerRaw['Total Orders'] ?? 0),
            $headerRaw['Generated Time'] ?? null,
            $isDuplicate
        ]);
        $reportId = $pdo->lastInsertId();

        // ===== Items từ dòng 10 =====
        $row = 10;
        while (true) {
            $created = cellText($sheet->getCell("J$row")->getValue());
            if ($created === '') break;

            $distance = (float)cellText($sheet->getCell("T$row")->getValue());

            $feeRaw = $sheet->getCell("D$row")->getValue();
            if ($feeRaw instanceof RichText) {
                $feeRaw = $feeRaw->getPlainText();
            }
            $fee = abs((float)$feeRaw); // FIX lỗi dư số 0
            $totalFee += $fee;

            $items[] = [
                'created_time' => $created,
                'order_path'   => cellText($sheet->getCell("Q$row")->getValue()),
                'pickup'       => cellText($sheet->getCell("R$row")->getValue()),
                'dropoff'      => cellText($sheet->getCell("S$row")->getValue()),
                'distance'     => $distance,
                'special'      => cellText($sheet->getCell("V$row")->getValue()),
                'fee'          => $fee
            ];
            $row++;
        }

        // insert items
        $stmtItem = $pdo->prepare("
            INSERT INTO lalamove_report_items
            (report_id, created_time, order_path, pickup_address, dropoff_address, distance_km, special_request, fee)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $it) {
            $stmtItem->execute([
                $reportId,
                $it['created_time'],
                $it['order_path'],
                $it['pickup'],
                $it['dropoff'],
                $it['distance'],
                $it['special'],
                $it['fee']
            ]);
        }

        $pdo->prepare("UPDATE lalamove_reports SET total_fee=? WHERE id=?")
            ->execute([$totalFee, $reportId]);

        $uploaded = true;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="container">
    <h3 class="mb-3">Upload báo cáo Lalamove</h3>

    <form method="post" enctype="multipart/form-data" class="mb-4">
        <input type="file" name="excel" required class="form-control" onchange="this.form.submit()">
    </form>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($uploaded): ?>
    <?php if ($isDuplicate): ?>
        <div class="alert alert-warning">⚠ Báo cáo này trùng khoảng thời gian với báo cáo đã có</div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header fw-bold">Thông tin chung</div>
        <table class="table table-sm mb-0">
            <?php foreach ($headerDisplay as $h): ?>
            <tr>
                <td class="text-muted" width="30%"><?= $h['label'] ?></td>
                <td><?= htmlspecialchars($h['value']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <table class="table table-bordered table-hover table-striped">
        <thead class="table-light">
            <tr>
                <th>Thời gian</th>
                <th>Mã đơn</th>
                <th>Lấy hàng</th>
                <th>Giao hàng</th>
                <th>Kilomet</th>
                <th>Yêu cầu</th>
                <th class="text-end">Phí (₫)</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= $it['created_time'] ?></td>
                <td><?= $it['order_path'] ?></td>
                <td><?= $it['pickup'] ?></td>
                <td><?= $it['dropoff'] ?></td>
                <td><?= number_format($it['distance'],2) ?></td>
                <td><?= $it['special'] ?></td>
                <td class="text-end"><?= number_format($it['fee'],0,',','.') ?> ₫</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="fw-bold">
                <td colspan="6">Tổng</td>
                <td class="text-end"><?= number_format($totalFee,0,',','.') ?> ₫</td>
            </tr>
        </tfoot>
    </table>

    <a href="lalamove_reports.php" class="btn btn-secondary">Quản lý báo cáo</a>
<?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
