<?php
require_once 'includes/init.php';

$ids = $_GET['ids'] ?? [];
if (count($ids) !== 2) die('Thiếu báo cáo');

$stmt = $pdo->prepare("
    SELECT *,
    total_fee / NULLIF(total_orders,0) AS avg_fee
    FROM lalamove_reports
    WHERE id IN (?,?)
");
$stmt->execute([$ids[0], $ids[1]]);
$r = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
<h3 class="mb-3">So sánh báo cáo</h3>

<table class="table table-bordered table-striped">
<thead class="table-light">
<tr>
    <th>Chỉ tiêu</th>
    <th>Báo cáo A</th>
    <th>Báo cáo B</th>
    <th>Chênh lệch</th>
</tr>
</thead>
<tbody>
<tr>
    <td>Tổng chuyến</td>
    <td><?= $r[0]['total_orders'] ?></td>
    <td><?= $r[1]['total_orders'] ?></td>
    <td><?= $r[1]['total_orders'] - $r[0]['total_orders'] ?></td>
</tr>
<tr>
    <td>Tổng phí</td>
    <td><?= number_format($r[0]['total_fee'],0,',','.') ?></td>
    <td><?= number_format($r[1]['total_fee'],0,',','.') ?></td>
    <td><?= number_format($r[1]['total_fee'] - $r[0]['total_fee'],0,',','.') ?></td>
</tr>
<tr>
    <td>Phí TB / chuyến</td>
    <td><?= number_format($r[0]['avg_fee'],0,',','.') ?></td>
    <td><?= number_format($r[1]['avg_fee'],0,',','.') ?></td>
    <td><?= number_format($r[1]['avg_fee'] - $r[0]['avg_fee'],0,',','.') ?></td>
</tr>
</tbody>
</table>

<a href="lalamove_reports.php" class="btn btn-secondary">← Quay lại</a>
</div>

<?php include 'includes/footer.php'; ?>
