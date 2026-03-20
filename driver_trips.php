<?php
// File: driver_trips.php
$skip_login_check = true;
$force_navbar_always = true;
require_once 'includes/init.php';

// =======================
// PARAMS GỐC
// =======================
$driver_id = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? str_pad((int)$_GET['month'], 2, '0', STR_PAD_LEFT) : date('m');

// ===== ADVANCED SEARCH (THÊM MỚI) =====
$advanced = isset($_GET['advanced']);
$adv_supplier  = trim($_GET['adv_supplier'] ?? '');
$adv_customer  = trim($_GET['adv_customer'] ?? '');
$adv_from      = $_GET['adv_date_from'] ?? '';
$adv_to        = $_GET['adv_date_to'] ?? '';
// ======================================

$driver = null; 
$trips = []; 
$adjustments = []; 
$total_shipping_cost = 0; 
$all_drivers = [];

$driver_result = $pdo->query("SELECT id, ten FROM drivers ORDER BY ten ASC");
$all_drivers = $driver_result->fetchAll(PDO::FETCH_ASSOC);

if ($driver_id > 0) {

    $stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = :driver_id");
    $stmt->execute([':driver_id' => $driver_id]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    // =======================
    // QUERY GỐC + MỞ RỘNG
    // =======================
    $sql = "
    SELECT
        so.id,
        so.order_number,
        so.order_date,
        so.expected_delivery_date,
        so.tien_xe,
        so.ghi_chu,
        s.name AS supplier_name,
        c.name AS customer_name,
        da.delivery_date
     FROM sales_orders so
     JOIN partners s ON so.supplier_id = s.id
     LEFT JOIN sales_quotes sq ON so.quote_id = sq.id
     LEFT JOIN partners c ON sq.customer_id = c.id
     LEFT JOIN driver_adjustments da ON da.order_id = so.id
     WHERE
        so.driver_id = :driver_id
        AND so.expected_delivery_date IS NOT NULL
    ";

    $params = [':driver_id'=>$driver_id];

    // ===== nếu dùng tìm kiếm nâng cao =====
    if ($advanced) {

        if ($adv_from && $adv_to) {
            $sql .= " AND DATE(so.expected_delivery_date) BETWEEN :df AND :dt ";
            $params[':df']=$adv_from;
            $params[':dt']=$adv_to;
        }

        if ($adv_supplier !== '') {
            $sql .= " AND s.name LIKE :sup ";
            $params[':sup']="%$adv_supplier%";
        }

        if ($adv_customer !== '') {
            $sql .= " AND c.name LIKE :cus ";
            $params[':cus']="%$adv_customer%";
        }

    } else {
        // GIỮ NGUYÊN FILTER CŨ
        $sql .= "
        AND YEAR(so.expected_delivery_date)=:year
        AND MONTH(so.expected_delivery_date)=:month";
        $params[':year']=$year;
        $params[':month']=$month;
    }

    $sql .= " ORDER BY so.expected_delivery_date ASC";

    $trip_stmt = $pdo->prepare($sql);
    $trip_stmt->execute($params);
    $trips = $trip_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($trips as $trip) {
        if ($trip['tien_xe'] > 0) {
            $total_shipping_cost += $trip['tien_xe'];
        }
    }

    // GIỮ NGUYÊN LOGIC CŨ
    $adj_stmt = $pdo->prepare("
        SELECT * FROM driver_adjustments
        WHERE driver_id=:driver_id
        AND year=:year
        AND month=:month
    ");
    $adj_stmt->execute([
        ':driver_id'=>$driver_id,
        ':year'=>$year,
        ':month'=>$month
    ]);
    $adjustments=$adj_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title="Bảng kê tài xế";
include 'includes/header.php';
?>
<!-- =========================
     NÚT TÌM KIẾM NÂNG CAO (THÊM)
========================= -->
<div class="text-end mb-3">
<button class="btn btn-outline-secondary"
        data-bs-toggle="modal"
        data-bs-target="#advancedSearchModal">
    <i class="bi bi-search"></i> Tìm kiếm nâng cao
</button>
</div>
<!-- Thêm Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Bảng kê chi tiết theo tài xế</h1>

    <form method="GET" action="driver_trips.php" id="driver-filter-form" class="card shadow mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4"><label for="driver_id" class="form-label">Chọn tài xế:</label><select name="driver_id" id="driver_id" class="form-select" required><option value="">-- Vui lòng chọn --</option><?php foreach ($all_drivers as $d): ?><option value="<?= $d['id'] ?>" <?= ($driver_id == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['ten']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label for="year" class="form-label">Năm:</label><input type="number" name="year" id="year" class="form-control" value="<?= $year ?>"></div>
                <div class="col-md-3"><label for="month" class="form-label">Tháng:</label><input type="number" name="month" id="month" class="form-control" value="<?= (int)$month ?>"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Xem</button></div>
            </div>
        </div>
    </form>

    <?php if ($driver): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Bảng kê cho tài xế: <?= htmlspecialchars($driver['ten']) ?> | Tháng <?= $month ?>/<?= $year ?></h6>
            <button class="btn btn-sm btn-outline-secondary" id="toggle-zero-trips-btn">Hiện các chuyến đi giá trị 0</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <!-- Wrapper cuộn thật -->
                <div id="scroll-wrapper" style="overflow-x: auto;">
                    <table class="table table-bordered" id="driver-trips-table" cellspacing="0" style="width:100%;">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Ngày đặt</th>
                                <th>Số đơn</th>
                                <th>Nhà cung cấp</th>
                                <th>Khách hàng</th>
                                <th>Ngày giao</th>
                                <th>Ghi chú</th>
                                <th class="text-end">Tiền xe</th>
                                <th>Chứng từ/Bản đồ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): ?>
                                <tr data-order-id="<?= $trip['id'] ?>">
                                    <td></td>
                                    <td data-order="<?= $trip['expected_delivery_date'] ?>">
                                    <?= $trip['expected_delivery_date'] ? date('d/m/Y', strtotime($trip['expected_delivery_date'])) : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars($trip['order_number']) ?></td>
                                    <td><?= htmlspecialchars($trip['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars($trip['customer_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (is_logged_in()): ?>
                                            <input type="text" class="form-control form-control-sm delivery-date-input" 
                                                   value="<?= $trip['delivery_date'] ? date('d/m/Y', strtotime($trip['delivery_date'])) : '' ?>" 
                                                   data-order-id="<?= $trip['id'] ?>">
                                        <?php else: ?>
                                            <?= $trip['delivery_date'] ? date('d/m/Y', strtotime($trip['delivery_date'])) : '<span class="text-muted">Chưa giao</span>' ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($trip['ghi_chu']) ?></td>
                                    <td class="text-end trip-cost"><?= number_format($trip['tien_xe'], 0, ',', '.') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary manage-attachments-btn" data-order-id="<?= $trip['id'] ?>">
                                            <i class="bi bi-folder"></i> Quản lý
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Scrollbar giả cố định đáy -->
                <div id="sticky-scroll" class="sticky-scroll">
                    <div id="scroll-fake-track"></div>
                </div>
            </div>

            <!-- Popup Quản lý Chứng từ/Bản đồ -->
            <div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="attachmentsModalLabel">Quản lý Chứng từ/Bản đồ</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="attachments-list"></div>
                            <?php if (is_logged_in()): ?>
                            <hr>
                            <h6>Tải lên file chứng từ</h6>
                            <input type="file" class="form-control form-control-sm upload-proof-input mb-2" id="modal-upload-proof">
                            <h6>Thêm link bản đồ</h6>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" id="modal-url-input" placeholder="Nhập link bản đồ (VD: https://maps.app.goo.gl/...)">
                                <button class="btn btn-primary add-url-btn" id="modal-add-url">Thêm</button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">
            <div id="salary-calculation-section" class="row">
                <div class="col-12">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Các khoản điều chỉnh (Phụ cấp, Tạm ứng,...)</h6>
                                </div>
                                <div class="card-body p-2">
                                    <div id="adjustments-list" class="mb-2">
                                        <?php foreach ($adjustments as $adj): ?>
                                        <div class="adjustment-row row g-2 align-items-center p-2">
                                            <div class="col-md-5"><input type="text" class="form-control adjustment-description" placeholder="Diễn giải (VD: Phụ cấp xăng xe)" value="<?= htmlspecialchars($adj['description']) ?>" <?= !is_logged_in() ? 'disabled' : '' ?>></div>
                                            <div class="col-md-3"><select class="form-select adjustment-type" <?= !is_logged_in() ? 'disabled' : '' ?>><option value="add" <?= ($adj['type'] == 'add') ? 'selected' : '' ?>>Cộng (+)</option><option value="subtract" <?= ($adj['type'] == 'subtract') ? 'selected' : '' ?>>Trừ (-)</option></select></div>
                                            <div class="col-md-4"><div class="input-group"><input type="text" class="form-control adjustment-amount text-end" placeholder="0" value="<?= number_format($adj['amount'], 0, ',', '.') ?>" <?= !is_logged_in() ? 'disabled' : '' ?>><?php if (is_logged_in()): ?><button class="btn btn-outline-danger remove-adjustment-btn" type="button"><i class="bi bi-trash"></i></button><?php endif; ?></div></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (is_logged_in()): ?>
                                    <button class="btn btn-link text-success text-decoration-none" id="add-adjustment-btn"><i class="bi bi-plus-circle"></i> Thêm điều chỉnh</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded mb-3">
                                <h5 class="mb-0">Tổng tiền xe:</h5>
                                <h5 class="mb-0 text-primary" id="total-shipping-cost"><?= number_format($total_shipping_cost, 0, ',', '.') ?></h5>
                            </div>
                            <div class="bg-dark text-white rounded p-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Thực lãnh:</h5>
                                <h4 class="mb-0" id="final-payout">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <script>const IS_LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;</script>
            <?php if (is_logged_in()): ?>
            <div class="text-end mt-4">
                <button class="btn btn-primary btn-lg" id="save-adjustments-btn"
                        data-driver-id="<?= $driver_id ?? 0 ?>"
                        data-year="<?= $year ?? 0 ?>"
                        data-month="<?= (int)($month ?? 0) ?>">
                    <i class="bi bi-save"></i> Lưu Bảng Lương
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<style>

</style>

<?php include 'includes/footer.php'; ?>

<!-- =========================
     MODAL ADVANCED SEARCH
========================= -->
<div class="modal fade" id="advancedSearchModal">
<div class="modal-dialog">
<div class="modal-content">

<form method="GET" action="driver_trips.php">

<div class="modal-header">
<h5 class="modal-title">Tìm kiếm nâng cao</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<input type="hidden" name="driver_id" value="<?= $driver_id ?>">
<input type="hidden" name="advanced" value="1">

<div class="mb-3">
<label>Nhà cung cấp</label>
<input name="adv_supplier" class="form-control">
</div>

<div class="mb-3">
<label>Khách hàng</label>
<input name="adv_customer" class="form-control">
</div>

<div class="mb-3">
<label>Từ ngày</label>
<input type="date" name="adv_date_from" class="form-control">
</div>

<div class="mb-3">
<label>Đến ngày</label>
<input type="date" name="adv_date_to" class="form-control">
</div>

</div>

<div class="modal-footer">
<button class="btn btn-primary">Tìm kiếm</button>
</div>

</form>

</div>
</div>
</div>

<script src="assets/js/driver_trips.js"></script>