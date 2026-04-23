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
    // NEW LOGIC: Fetch Trips instead of single orders
    $sql = "
        SELECT t.*, d.ten as driver_name 
        FROM dispatcher_trips t 
        JOIN drivers d ON t.driver_id = d.id 
        WHERE t.driver_id = :driver_id
    ";

    $params = [':driver_id' => $driver_id];

    if ($advanced) {
        if ($adv_from && $adv_to) {
            $sql .= " AND t.trip_date BETWEEN :df AND :dt ";
            $params[':df'] = $adv_from;
            $params[':dt'] = $adv_to;
        }
    } else {
        $sql .= " AND YEAR(t.trip_date) = :year AND MONTH(t.trip_date) = :month";
        $params[':year'] = $year;
        $params[':month'] = $month;
    }

    $sql .= " ORDER BY t.trip_date ASC";
    $trip_stmt = $pdo->prepare($sql);
    $trip_stmt->execute($params);
    $trips = $trip_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch linked orders for all trips in the list
    $all_trip_ids = array_column($trips, 'id');
    $linked_orders = [];
    if (!empty($all_trip_ids)) {
        $in_query = implode(',', array_fill(0, count($all_trip_ids), '?'));
        $orders_stmt = $pdo->prepare("
            SELECT dto.trip_id, so.order_number, p_sup.name as supplier_name, p_cus.name as customer_name,
                   (SELECT GROUP_CONCAT(CONCAT(sod.product_name_snapshot, ' (', sod.quantity, ' ', IFNULL(sod.unit_snapshot, ''), ')') SEPARATOR '; ') 
                    FROM sales_order_details sod WHERE sod.order_id = so.id) as items_summary
            FROM dispatcher_trip_orders dto
            JOIN sales_orders so ON dto.order_id = so.id
            JOIN partners p_sup ON so.supplier_id = p_sup.id
            LEFT JOIN sales_quotes sq ON so.quote_id = sq.id
            LEFT JOIN partners p_cus ON sq.customer_id = p_cus.id
            WHERE dto.trip_id IN ($in_query)
        ");
        $orders_stmt->execute($all_trip_ids);
        $raw_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($raw_orders as $ro) {
            $linked_orders[$ro['trip_id']][] = $ro;
        }
    }

    foreach ($trips as $t) {
        if ($t['status'] !== 'cancelled') {
            $total_shipping_cost += ($t['base_freight_cost'] + $t['extra_costs']);
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
<div class="page-header">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-truck-flatbed me-2 text-primary"></i>Bảng kê chi tiết theo tài xế</h1>
        <p class="text-muted mb-0 small">Xết lịch, tính tiền xe và phân công giao hàng</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#advancedSearchModal">
            <i class="bi bi-search me-1"></i> Tìm kiếm nâng cao
        </button>
    </div>
</div>

<div class="content-card shadow-sm mb-4">
    <div class="content-card-header">
        <i class="bi bi-funnel-fill me-2 text-primary"></i>Chọn tài xế và kỳ
    </div>
    <div class="content-card-body">
        <form method="GET" action="driver_trips.php" id="driver-filter-form">
            <div class="row g-3 align-items-end">
                <div class="col-md-4"><label for="driver_id" class="form-label">Chọn tài xế:</label><select name="driver_id" id="driver_id" class="form-select" required><option value="">-- Vui lòng chọn --</option><?php foreach ($all_drivers as $d): ?><option value="<?= $d['id'] ?>" <?= ($driver_id == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['ten']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label for="year" class="form-label">Năm:</label><input type="number" name="year" id="year" class="form-control" value="<?= $year ?>"></div>
                <div class="col-md-3"><label for="month" class="form-label">Tháng:</label><input type="number" name="month" id="month" class="form-control" value="<?= (int)$month ?>"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Xem</button></div>
            </div>
        </form>
    </div>
</div>

<?php if ($driver): ?>
<div class="content-card shadow-sm mb-4">
    <div class="content-card-header">
        <span><i class="bi bi-person-badge me-2 text-primary"></i>Bảng kê: <strong><?= htmlspecialchars($driver['ten']) ?></strong> &mdash; Tháng <?= $month ?>/<?= $year ?></span>
        <!-- Removed toggle-zero-trips-btn as all trips are now shown by default -->
    </div>
    <div class="content-card-body">
            <div class="table-responsive">
                <!-- Wrapper cuộn thật -->
                <div id="scroll-wrapper" style="overflow-x: auto;">
                    <table class="table table-hover table-custom" id="driver-trips-table" cellspacing="0" style="width:100%;">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30px;"></th>
                                <th><?= $lang['trip_date'] ?></th>
                                <th style="width: 45%;">Lộ trình (NCC >>> KH)</th>
                                <th><?= $lang['notes'] ?></th>
                                <th class="text-end">Tiền Xe</th>
                                <th class="text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): ?>
                                <tr class="<?= $trip['status'] === 'cancelled' ? 'table-danger opacity-50' : '' ?>" data-trip-id="<?= $trip['id'] ?>">
                                    <td class="dt-control"></td>
                                    <td data-order="<?= $trip['trip_date'] ?>">
                                        <?= date('d/m/Y', strtotime($trip['trip_date'])) ?>
                                    </td>
                                    <td>
                                        <?php if (isset($linked_orders[$trip['id']])): ?>
                                            <div class="d-flex flex-column gap-2">
                                                <?php 
                                                foreach ($linked_orders[$trip['id']] as $lo):
                                                ?>
                                                <div class="border rounded p-2 bg-light small">
                                                    <div class="fw-bold mb-1">
                                                        <span class="text-dark"><?= htmlspecialchars($lo['supplier_name']) ?></span> 
                                                        <i class="bi bi-arrow-right-circle-fill text-primary mx-1"></i> 
                                                        <span class="text-dark"><?= htmlspecialchars($lo['customer_name'] ?: 'N/A') ?></span>
                                                    </div>
                                                    <div class="text-muted fst-italic">
                                                        <i class="bi bi-box-seam me-1"></i> <?= htmlspecialchars($lo['items_summary'] ?: 'Không có hàng hóa') ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small text-truncate" style="max-width: 150px;"><?= htmlspecialchars($trip['notes'] ?: '') ?></div>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?= number_format($trip['base_freight_cost'] + $trip['extra_costs'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="delivery_dispatcher.php?id=<?= $trip['id'] ?>" class="btn btn-outline-primary" title="<?= $lang['edit'] ?? 'Chỉnh sửa' ?>">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button class="btn btn-outline-secondary" onclick="window.open('process/generate_trip_pdf.php?id=<?= $trip['id'] ?>', '_blank')" title="<?= $lang['print'] ?? 'In' ?>">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        </div>
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
                <div id="salary-calculation-section" class="row g-4">
                    <div class="col-12">
                        <div class="row g-3">
                            <div class="col-lg-8">
                                <div class="content-card h-100">
                                    <div class="content-card-header">
                                        <span><i class="bi bi-sliders me-2 text-primary"></i>Các khoản điều chỉnh (Phụ cấp, Tạm ứng,...)</span>
                                    </div>
                                    <div class="content-card-body p-2">
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
                                <div class="content-card mb-3">
                                    <div class="content-card-body p-3 d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="bi bi-truck me-2 text-primary"></i>Tổng tiền xe:</h5>
                                        <h5 class="mb-0 text-primary fw-bold" id="total-shipping-cost"><?= number_format($total_shipping_cost, 0, ',', '.') ?></h5>
                                    </div>
                                </div>
                                <div class="content-card" style="background: linear-gradient(135deg,#4361ee,#3a0ca3); color:#fff;">
                                    <div class="content-card-body p-3 d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Thực lãnh:</h5>
                                        <h4 class="mb-0 fw-bold" id="final-payout">0</h4>
                                    </div>
                                </div>
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