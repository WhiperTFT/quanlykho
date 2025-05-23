<?php
// dashboard.php
require_once __DIR__ . '/includes/init.php'; // Khởi tạo session, ngôn ngữ, DB
require_once __DIR__ . '/includes/auth_check.php'; // Đảm bảo người dùng đã đăng nhập
require_once __DIR__ . '/includes/admin_check.php'; // Kiểm tra quyền admin

$page_title = $lang['dashboard'] ?? 'Dashboard'; // Đặt tiêu đề trang

// Include phần header (HTML head, và navbar)
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= $lang['dashboard_title'] ?? 'System Overview' ?></h1>
    </div>

<p><?= $lang['welcome'] ?? 'Welcome' ?>, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong>!</p>
<p>Vai trò của bạn: <strong><?= htmlspecialchars($_SESSION['role'] ?? 'Unknown') ?></strong></p>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card text-white bg-primary mb-3">
            <div class="card-header"><?= $lang['total_orders'] ?? 'Total Orders' ?></div>
            <div class="card-body">
                <h5 class="card-title display-4" id="total-orders-count">...</h5>
                <p class="card-text">Xem chi tiết đơn hàng.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-warning mb-3">
             <div class="card-header"><?= $lang['pending_quotes'] ?? 'Pending Quotes' ?></div>
             <div class="card-body">
                <h5 class="card-title display-4" id="pending-quotes-count">...</h5>
                <p class="card-text">Các báo giá đang chờ duyệt.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
         <div class="card text-white bg-danger mb-3">
             <div class="card-header"><?= $lang['stock_level'] ?? 'Low Stock Items' ?></div>
            <div class="card-body">
                <h5 class="card-title display-4" id="low-stock-count">...</h5>
                <p class="card-text">Sản phẩm sắp hết hàng.</p>
            </div>
        </div>
    </div>
</div>

<h2><?= $lang['recent_activity'] ?? 'Recent Activity' ?> (Tạm thời)</h2>
<div class="table-responsive">
    <p><em>Phần nhật ký hoạt động gần đây sẽ được hiển thị ở đây (lấy từ bảng logs).</em></p>
     </div>


<?php
// Include phần footer (đóng thẻ, JS)
require_once __DIR__ . '/includes/footer.php';
?>