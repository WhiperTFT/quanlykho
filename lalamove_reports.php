<?php
require_once 'includes/init.php';

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM lalamove_reports WHERE id=?")
        ->execute([(int)$_GET['delete']]);
    header("Location: lalamove_reports.php");
    exit;
}

$reports = $pdo->query("
    SELECT * FROM lalamove_reports
    ORDER BY date_from DESC
")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
<h3 class="mb-3">Báo cáo Lalamove</h3>

<form method="get" action="lalamove_compare.php">
<table class="table table-striped table-hover table-bordered">
<thead class="table-light">
<tr>
    <th></th>
    <th>Công ty</th>
    <th>Date Range</th>
    <th class="text-end">Tổng phí</th>
    <th>Ngày tạo</th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($reports as $r): ?>
<tr>
    <td><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>"></td>
    <td>
        <?= htmlspecialchars($r['company_name']) ?>
        <?php if ($r['is_duplicate']): ?>
            <span class="badge bg-warning text-dark">TRÙNG</span>
        <?php endif; ?>
    </td>
    <td><?= $r['date_from'] ?> → <?= $r['date_to'] ?></td>
    <td class="text-end"><?= number_format($r['total_fee'],0,',','.') ?> ₫</td>
    <td><?= $r['generated_time'] ?></td>
    <td>
        <a href="lalamove_report_view.php?id=<?= $r['id'] ?>">Xem</a> |
        <a href="?delete=<?= $r['id'] ?>" onclick="return confirm('Xóa báo cáo này?')" class="text-danger">Xóa</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<button class="btn btn-primary">So sánh 2 báo cáo</button>
</form>
</div>

<script>
document.querySelector('form').onsubmit = function(){
    if (document.querySelectorAll('input[name="ids[]"]:checked').length !== 2) {
        alert('Vui lòng chọn đúng 2 báo cáo');
        return false;
    }
};
</script>

<?php include 'includes/footer.php'; ?>
