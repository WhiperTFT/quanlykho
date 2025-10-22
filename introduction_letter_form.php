<?php
// File: introduction_letter_form.php
require_once __DIR__ . '/includes/init.php';
require_login();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    die('Thiếu ID đơn hàng.');
}

$today = new DateTime();
$pickup_date = $today->format('Y-m-d');
$valid_from = $today->format('d/m');
$valid_to = $today->format('d/m/Y');

include 'includes/header.php';
?>
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Tạo Giấy Giới Thiệu Nhận Hàng</h5>
        </div>
        <form action="process/generate_introduction_letter.php" method="POST" target="_blank">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <div class="card-body">
                <div class="mb-3">
                    <label for="pickup_date" class="form-label">Ngày lấy hàng:</label>
                    <input type="date" class="form-control" id="pickup_date" name="pickup_date" value="<?= $pickup_date ?>" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="valid_from" class="form-label">Hiệu lực từ ngày (dd/mm):</label>
                        <input type="text" class="form-control" id="valid_from" name="valid_from" value="<?= $valid_from ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="valid_to" class="form-label">Đến ngày (dd/mm/yyyy):</label>
                        <input type="text" class="form-control" id="valid_to" name="valid_to" value="<?= $valid_to ?>" required>
                    </div>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="show_signature" name="show_signature" value="1" checked>
                    <label class="form-check-label" for="show_signature">
                        Hiển thị chữ ký trên giấy giới thiệu
                    </label>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="sales_orders.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Hủy
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-file-earmark-plus"></i> Tạo Giấy Giới Thiệu (PDF)
                </button>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
