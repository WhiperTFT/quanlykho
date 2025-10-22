<?php
// dashboard.php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
require_login();

$page_title = $lang['dashboard'] ?? 'Dashboard';

try {
    // Tổng số đơn hàng
    $stmt = $pdo->query("SELECT COUNT(*) FROM sales_orders");
    $stmt = $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE driver_id IS NULL OR driver_id NOT IN (SELECT id FROM drivers)");
    $total_unshipped_orders = $stmt->fetchColumn();


    // Báo giá chờ duyệt
    $stmt = $pdo->query("SELECT COUNT(*) FROM sales_quotes WHERE status = 'pending'");
    $pending_quotes = $stmt->fetchColumn();

    // Sản phẩm sắp hết hàng - cần có cột quantity, ở đây tạm hardcode là 0
    $low_stock = 0;

    // Nhật ký hoạt động
    $stmt = $pdo->query("SELECT l.*, u.username FROM logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.timestamp DESC LIMIT 10");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $total_orders = $pending_quotes = $low_stock = 0;
    $logs = [];
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= $lang['dashboard_title'] ?? 'System Overview' ?></h1>
</div>

<p><?= $lang['welcome'] ?? 'Welcome' ?>, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong>!</p>
<p>Vai trò của bạn: <strong><?= htmlspecialchars($_SESSION['role'] ?? 'Unknown') ?></strong></p>

<div class="row g-4 mb-4">
    <!-- CARD 1: Tổng đơn hàng chưa giao -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Tổng đơn hàng chưa giao</h5>
                <h1 class="display-4"><?= $total_unshipped_orders ?></h1>
                <p class="card-text">--------------------------------------------------</p>
            </div>
        </div>
    </div>

    <!-- CARD 2: Báo giá chờ duyệt -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title"><?= $lang['pending_quotes'] ?? 'Pending Quotes' ?></h5>
                <h1 class="display-4"><?= $pending_quotes ?></h1>
                <p class="card-text">--------------------------------------------------</p>
            </div>
        </div>
    </div>

    <!-- CARD 3: Sản phẩm sắp hết hàng -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 text-white bg-danger">
            <div class="card-body">
                <h5 class="card-title"><?= $lang['stock_level'] ?? 'Low Stock Items' ?></h5>
                <h1 class="display-4"><?= $low_stock ?></h1>
                <p class="card-text">--------------------------------------------------</p>
            </div>
        </div>
    </div>
</div>

<h2 class="mt-5"><?= $lang['recent_activity'] ?? 'Recent Activity' ?></h2>
<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Người dùng</th>
                <th>Hành động</th>
                <th>IP</th>
                <th>Thời gian</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5"><em>Không có hoạt động gần đây.</em></td></tr>
            <?php else: ?>
                <?php foreach ($logs as $index => $log): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($log['username'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td><?= date('d/m/Y H:i:s', strtotime($log['timestamp'])) ?></td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
