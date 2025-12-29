<?php
require_once __DIR__ . '/includes/init.php';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM lalamove_reports WHERE id=?")->execute([$id]);
    header("Location: lalamove_reports.php");
    exit;
}

$reports = $pdo->query("
    SELECT id, company_name, date_range, total_charge, created_at
    FROM lalamove_reports
    ORDER BY created_at DESC
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<h2>📊 Báo cáo Lalamove đã lưu</h2>

<table class="table table-bordered">
<thead>
<tr>
    <th>ID</th>
    <th>Công ty</th>
    <th>Date Range</th>
    <th>Tổng phí</th>
    <th>Ngày tạo</th>
    <th>Hành động</th>
</tr>
</thead>
<tbody>
<?php foreach ($reports as $r): ?>
<tr>
    <td><?= $r['id'] ?></td>
    <td><?= htmlspecialchars($r['company_name']) ?></td>
    <td><?= htmlspecialchars($r['date_range']) ?></td>
    <td><?= number_format($r['total_charge'],0,',','.') ?> ₫</td>
    <td><?= $r['created_at'] ?></td>
    <td>
        <a href="lalamove_report_view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-info">Xem</a>
        <a href="?delete=<?= $r['id'] ?>" class="btn btn-sm btn-danger"
           onclick="return confirm('Xóa báo cáo này?')">Xóa</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php include __DIR__ . '/includes/footer.php'; ?>
