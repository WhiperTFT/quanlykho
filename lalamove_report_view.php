<?php
require_once __DIR__ . '/includes/init.php';

$id = (int)($_GET['id'] ?? 0);

$reportStmt = $pdo->prepare("SELECT * FROM lalamove_reports WHERE id=?");
$reportStmt->execute([$id]);
$report = $reportStmt->fetch();

$itemStmt = $pdo->prepare("
    SELECT * FROM lalamove_report_items
    WHERE report_id=?
");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<h2>📄 Chi tiết báo cáo Lalamove</h2>

<p><b>Công ty:</b> <?= htmlspecialchars($report['company_name']) ?></p>
<p><b>Date Range:</b> <?= htmlspecialchars($report['date_range']) ?></p>
<p><b>Tổng phí:</b> <?= number_format($report['total_charge'],0,',','.') ?> ₫</p>

<table class="table table-bordered">
<thead>
<tr>
    <th>Thời gian</th>
    <th>Lộ trình</th>
    <th>Lấy hàng</th>
    <th>Giao hàng</th>
    <th>Km</th>
    <th>Phí</th>
</tr>
</thead>
<tbody>
<?php foreach ($items as $i): ?>
<tr>
    <td><?= $i['created_time'] ?></td>
    <td><?= $i['order_path'] ?></td>
    <td><?= $i['pickup_address'] ?></td>
    <td><?= $i['dropoff_address'] ?></td>
    <td><?= $i['distance'] ?></td>
    <td class="text-end"><?= number_format($i['final_charge'],0,',','.') ?> ₫</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<a href="lalamove_reports.php" class="btn btn-secondary">← Quay lại</a>

<?php include __DIR__ . '/includes/footer.php'; ?>
