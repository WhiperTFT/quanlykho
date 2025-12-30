<?php
require_once __DIR__ . '/includes/init.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID báo cáo không hợp lệ');
}

$report = $pdo->query("SELECT * FROM lalamove_reports WHERE id = $id")->fetch();
if (!$report) {
    die('Không tìm thấy báo cáo');
}

$items = $pdo->query("
    SELECT * FROM lalamove_report_items
    WHERE report_id = $id
    ORDER BY created_time
")->fetchAll();

function vnd($n) {
    return number_format($n, 0, ',', '.') . ' ₫';
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container-fluid">
    <h4 class="mb-1"><?= htmlspecialchars($report['company_name']) ?></h4>
    <div class="text-muted mb-3">
        <?= $report['date_from'] ?> → <?= $report['date_to'] ?>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                <tr>
                    <th>Thời gian</th>
                    <th>Mã đơn</th>
                    <th>Lấy hàng</th>
                    <th>Trả hàng</th>
                    <th class="text-end">Kilomet</th>
                    <th>Yêu cầu</th>
                    <th class="text-end">Phí</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $total = 0;
                foreach ($items as $i):
                    $total += (int)$i['fee'];
                ?>
                <tr>
                    <td><?= $i['created_time'] ?></td>
                    <td><?= htmlspecialchars($i['order_path']) ?></td>
                    <td><?= htmlspecialchars($i['pickup_address']) ?></td>
                    <td><?= htmlspecialchars($i['dropoff_address']) ?></td>
                    <td class="text-end"><?= number_format($i['distance_km'], 2) ?></td>
                    <td><?= htmlspecialchars($i['special_request']) ?></td>
                    <td class="text-end fw-bold text-primary"><?= vnd($i['fee']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th colspan="6" class="text-end">Tổng</th>
                    <th class="text-end text-success fs-6"><?= vnd($total) ?></th>
                </tr>
                    </tfoot>
                </table>
        </div>
    </div>

    <a href="lalamove_reports.php" class="btn btn-link mt-3">
        ← Quay lại danh sách
    </a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
