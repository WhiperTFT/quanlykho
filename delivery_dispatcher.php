<?php
// File: delivery_dispatcher.php
require_once 'includes/init.php';

$page_title = $lang['delivery_dispatcher'] ?? 'Delivery Dispatcher';
require_once 'includes/header.php';

// Fetch all drivers for the dropdown
$drivers_stmt = $pdo->query("SELECT id, ten, bien_so_xe FROM drivers ORDER BY ten ASC");
$drivers = $drivers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Initial data for trips list (last 30 days)
$initial_trips_stmt = $pdo->prepare("
    SELECT t.*, d.ten as driver_name 
    FROM dispatcher_trips t 
    JOIN drivers d ON t.driver_id = d.id 
    WHERE t.trip_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY t.trip_date DESC, t.created_at DESC
");
$initial_trips_stmt->execute();
$initial_trips = $initial_trips_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-truck me-2 text-primary"></i><?= $page_title ?></h1>
            <button class="btn btn-primary" id="btn-new-trip">
                <i class="bi bi-plus-lg me-1"></i> <?= $lang['create_new_trip'] ?? 'Create New Trip' ?>
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1"><?= $lang['trips_this_month'] ?? 'Trips This Month' ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-trips-month">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-check fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1"><?= $lang['total_freight_this_month'] ?? 'Total Freight' ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-total-freight">0 ₫</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-currency-dollar fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1"><?= $lang['pending_orders_count'] ?? 'Pending Orders' ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-pending-orders">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-box-seam fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Trips List Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-light">
                    <h6 class="m-0 font-weight-bold text-primary"><?= $lang['manage_trips'] ?? 'Quản lý chuyến xe' ?></h6>
                    <div class="dropdown no-arrow">
                        <button class="btn btn-link btn-sm dropdown-toggle" type="button" id="tripsFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-filter"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="tripsFilterDropdown">
                            <li><a class="dropdown-item filter-trip-status active" href="#" data-status="all"><?= $lang['all'] ?? 'Tất cả' ?></a></li>
                            <li><a class="dropdown-item filter-trip-status" href="#" data-status="scheduled"><?= $lang['scheduled'] ?? 'Lên lịch' ?></a></li>
                            <li><a class="dropdown-item filter-trip-status" href="#" data-status="in_progress"><?= $lang['shipping'] ?? 'Đang giao' ?></a></li>
                            <li><a class="dropdown-item filter-trip-status" href="#" data-status="completed"><?= $lang['completed'] ?? 'Hoàn tất' ?></a></li>
                            <li><a class="dropdown-item filter-trip-status" href="#" data-status="cancelled"><?= $lang['cancelled'] ?? 'Đã hủy' ?></a></li>
                        </ul>
                    </div>
                </div>
                <div class="p-2 bg-white border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="search-trips-input" placeholder="<?= $lang['search_trips_placeholder'] ?? 'Mã chuyến, tài xế...' ?>">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush overflow-auto" style="max-height: 600px;" id="trips-list">
                        <?php foreach ($initial_trips as $trip): ?>
                            <a href="#" class="list-group-item list-group-item-action trip-item p-3" data-id="<?= $trip['id'] ?>">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <h6 class="mb-1 fw-bold text-primary"><?= htmlspecialchars($trip['trip_number']) ?></h6>
                                    <small class="badge rounded-pill bg-<?= $trip['status'] === 'completed' ? 'success' : ($trip['status'] === 'cancelled' ? 'danger' : 'info') ?>">
                                        <?= strtoupper($trip['status']) ?>
                                    </small>
                                </div>
                                <div class="small text-muted mb-1">
                                    <i class="bi bi-person me-1"></i> <?= htmlspecialchars($trip['driver_name']) ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small><i class="bi bi-calendar3 me-1"></i> <?= date('d/m/Y', strtotime($trip['trip_date'])) ?></small>
                                    <span class="fw-bold text-dark"><?= number_format($trip['base_freight_cost'] + $trip['extra_costs'], 0, ',', '.') ?> ₫</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($initial_trips)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-truck fs-1 d-block mb-2"></i>
                                <?= $lang['no_trips_found'] ?? 'No trips found for the selected period.' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trip Editor / Detail View -->
        <div class="col-lg-8">
            <div class="card shadow mb-4" id="trip-editor-container">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary" id="editor-title"><?= $lang['trip_details'] ?? 'Trip Details' ?></h6>
                    <div id="editor-actions" class="d-none">
                        <button class="btn btn-sm btn-outline-danger me-1" id="btn-delete-trip"><i class="bi bi-trash"></i></button>
                        <button class="btn btn-sm btn-outline-secondary" id="btn-print-trip"><i class="bi bi-printer"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="trip-form" class="row g-3">
                        <input type="hidden" name="trip_id" id="trip_id">
                        
                        <!-- Header Info -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold"><?= $lang['trip_number'] ?></label>
                            <input type="text" class="form-control" name="trip_number" id="trip_number" readonly placeholder="<?= $lang['auto_gen'] ?? 'TỰ ĐỘNG' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold"><?= $lang['trip_date'] ?></label>
                            <input type="date" class="form-control" name="trip_date" id="trip_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold"><?= $lang['trip_status'] ?></label>
                            <select class="form-select" name="status" id="trip_status">
                                <option value="draft"><?= $lang['draft'] ?? 'Draft' ?></option>
                                <option value="scheduled" selected><?= $lang['scheduled'] ?? 'Scheduled' ?></option>
                                <option value="in_progress"><?= $lang['shipping'] ?? 'In Progress' ?></option>
                                <option value="completed"><?= $lang['completed'] ?? 'Completed' ?></option>
                                <option value="cancelled"><?= $lang['cancelled'] ?? 'Cancelled' ?></option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold"><?= $lang['driver'] ?></label>
                            <select class="form-select" name="driver_id" id="driver_id_select" required>
                                <option value=""><?= $lang['select_driver'] ?></option>
                                <?php foreach ($drivers as $d): ?>
                                    <option value="<?= $d['id'] ?>" data-plate="<?= htmlspecialchars($d['bien_so_xe'] ?? '') ?>"><?= htmlspecialchars($d['ten']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold"><?= $lang['vehicle_plate'] ?></label>
                            <input type="text" class="form-control" name="vehicle_plate" id="vehicle_plate" placeholder="e.g. 51G-123.45">
                        </div>

                        <!-- Assigned Orders -->
                        <div class="col-12 mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-dark mb-0"><?= $lang['scheduled_orders'] ?></h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-orders">
                                    <i class="bi bi-plus-circle me-1"></i> <?= $lang['add_order_to_trip'] ?>
                                </button>
                            </div>
                            <div class="table-responsive border rounded">
                                <table class="table table-hover align-middle mb-0" id="trip-orders-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th><?= $lang['order_number'] ?></th>
                                            <th><?= $lang['supplier'] ?> (NCC)</th>
                                            <th><?= $lang['customer'] ?> (KH)</th>
                                            <th><?= $lang['items'] ?? 'Hàng hóa' ?></th>
                                            <th class="text-center"><?= $lang['status'] ?></th>
                                            <th class="text-end"><?= $lang['actions'] ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="trip-orders-body">
                                        <!-- Dynamic content -->
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted small">
                                                <?= $lang['no_orders_assigned'] ?? 'No orders assigned to this trip yet.' ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Costs & Notes -->
                        <div class="col-md-6 mt-4">
                            <label class="form-label small fw-bold"><?= $lang['notes'] ?></label>
                            <textarea class="form-control" name="notes" id="trip_notes" rows="4" placeholder="<?= $lang['trip_notes_placeholder'] ?>"></textarea>
                        </div>
                        <div class="col-md-6 mt-4">
                            <div class="bg-light p-3 rounded border">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold"><?= $lang['base_freight_cost'] ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control text-end currency-input" name="base_freight_cost" id="base_freight_cost" value="0">
                                        <span class="input-group-text">₫</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold"><?= $lang['extra_costs'] ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control text-end currency-input" name="extra_costs" id="extra_costs" value="0">
                                        <span class="input-group-text">₫</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <span class="fw-bold text-primary"><?= $lang['total_trip_cost'] ?></span>
                                    <span class="h5 mb-0 fw-bold text-primary" id="total_trip_cost_display">0 ₫</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 text-end mt-4">
                            <button type="button" class="btn btn-secondary me-2" id="btn-reset-form"><?= $lang['cancel'] ?></button>
                            <button type="submit" class="btn btn-success px-4" id="btn-save-trip">
                                <i class="bi bi-save me-1"></i> <?= $lang['save_trip'] ?? 'Save Trip' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Select Orders for Trip -->
<div class="modal fade" id="orderSelectorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $lang['select_orders_to_add'] ?? 'Select Orders to Add' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="search-available-orders" placeholder="<?= $lang['search_orders_placeholder'] ?? 'Search by number, customer, or supplier...' ?>">
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="check-all-orders"></th>
                                <th><?= $lang['order_number'] ?></th>
                                                                <th><?= $lang['trip_date'] ?></th>
                                                                <th><?= $lang['supplier'] ?> (NCC)</th>
                                                                <th><?= $lang['customer'] ?> (KH)</th>
                                                                <th><?= $lang['items'] ?? 'Hàng hóa' ?></th>
                            </tr>
                        </thead>
                        <tbody id="available-orders-body">
                            <!-- Loading spinner -->
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                    <span class="ms-2"><?= $lang['loading'] ?? 'Đang tải...' ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $lang['close'] ?></button>
                <button type="button" class="btn btn-primary" id="btn-confirm-add-orders"><?= $lang['add_selected'] ?? 'Add Selected' ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    const TRIP_LANG = {
        editTrip: "<?= $lang['edit_trip'] ?? 'Edit Trip' ?>",
        createNewTrip: "<?= $lang['create_new_trip'] ?? 'Create New Trip' ?>",
        noPendingOrders: "<?= $lang['no_pending_orders'] ?? 'No pending orders available.' ?>",
        noOrdersAssigned: "<?= $lang['no_orders_assigned'] ?? 'No orders assigned to this trip yet.' ?>",
        areYouSure: "<?= $lang['are_you_sure'] ?? 'Are you sure?' ?>",
        wontBeAbleToRevert: "<?= $lang['wont_be_able_to_revert'] ?? 'You won\'t be able to revert this!' ?>",
        yesDeleteIt: "<?= $lang['yes_delete_it'] ?? 'Yes, delete it!' ?>",
        deleted: "<?= $lang['deleted'] ?? 'Deleted!' ?>",
        error: "<?= $lang['error'] ?? 'Error' ?>",
        success: "<?= $lang['success'] ?? 'Success' ?>",
        serverError: "<?= $lang['server_error'] ?? 'Server error occurred.' ?>",
        pending: "<?= $lang['pending'] ?? 'Pending' ?>"
    };
</script>
<?php require_once 'includes/footer.php'; ?>
<script src="assets/js/delivery_dispatcher.js"></script>
