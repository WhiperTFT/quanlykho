<?php
$page_title = $lang['manage_drivers'] ?? 'Quản lý Tài xế';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/init.php';
require_login();
?>
<style>
    .form-label.required::after {
        content: " *";
        color: red;
        font-weight: normal;
    }

    /* CARD STYLE */
    .driver-card {
        font-family: 'Segoe UI', sans-serif;
        padding: 20px;
        border-radius: 12px;
        background-color: #ffffff;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        max-width: 600px;
        margin: auto;
    }

    .driver-card h3 {
        margin-bottom: 10px;
        font-size: 1.8rem;
        font-weight: 600;
    }

    .driver-card p {
        margin-bottom: 6px;
        font-size: 1rem;
        color: #333;
    }

    .driver-card .label {
        font-weight: 600;
        color: #555;
    }
    .driver-card .btn-remove-field {
    border: none;
    background: none;
    padding: 0;
    font-size: 0.9rem;
    color: red;
    }

    .driver-card .btn-remove-field:hover i {
        color: darkred;
    }

</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= htmlspecialchars($page_title) ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#driverModal" id="addDriverBtn">
            <i class="bi bi-plus-lg"></i> <?= $lang['add_driver'] ?? 'Thêm Tài xế' ?>
        </button>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <input type="text" id="driver-search" class="form-control" placeholder="<?= $lang['filter_drivers'] ?? 'Lọc tài xế...' ?>">
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover" id="driver-table">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th><?= $lang['driver_name_label'] ?? 'Tên Tài xế' ?></th>
                <th><?= $lang['cccd_label'] ?? 'CCCD' ?></th>
                <th><?= $lang['issue_date_label'] ?? 'Ngày cấp' ?></th>
                <th><?= $lang['issue_place_label'] ?? 'Nơi cấp' ?></th>
                <th><?= $lang['phone_label'] ?? 'SĐT' ?></th>
                <th><?= $lang['license_plates_label'] ?? 'Biển số xe' ?></th>
                <th><?= $lang['notes_label'] ?? 'Ghi chú' ?></th>
                <th><?= $lang['action_label'] ?? 'Hành động' ?></th>
                <th>Danh thiếp</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="10" class="text-center" id="loading-row"><?= $lang['loading_data'] ?? 'Đang tải dữ liệu...' ?></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Modal Thêm / Sửa Tài xế -->
<div class="modal fade" id="driverModal" tabindex="-1" aria-labelledby="driverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="driverForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="driverModalLabel"><?= $lang['add_driver'] ?? 'Thêm Tài xế' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="driver_id" name="driver_id">

                    <div class="mb-3">
                        <label for="driver_name" class="form-label required"><?= $lang['driver_name_label'] ?? 'Tên Tài xế' ?></label>
                        <input type="text" class="form-control" id="driver_name" name="ten" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="driver_cccd" class="form-label required"><?= $lang['cccd_label'] ?? 'Số CCCD' ?></label>
                            <input type="text" class="form-control" id="driver_cccd" name="cccd" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="driver_sdt" class="form-label"><?= $lang['phone_label'] ?? 'Số Điện Thoại' ?></label>
                            <input type="tel" class="form-control" id="driver_sdt" name="sdt">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="driver_ngay_cap" class="form-label"><?= $lang['issue_date_label'] ?? 'Ngày cấp CCCD' ?></label>
                            <input type="date" class="form-control" id="driver_ngay_cap" name="ngay_cap">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="driver_noi_cap" class="form-label"><?= $lang['issue_place_label'] ?? 'Nơi cấp CCCD' ?></label>
                            <input type="text" class="form-control" id="driver_noi_cap" name="noi_cap">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="driver_bien_so_xe" class="form-label"><?= $lang['license_plates_label'] ?? 'Biển số xe' ?></label>
                        <input type="text" class="form-control" id="driver_bien_so_xe" name="bien_so_xe" placeholder="<?= $lang['license_plates_placeholder'] ?? 'Ví dụ: 61A-123.45, 51F-678.90' ?>">
                        <small class="form-text text-muted"><?= $lang['license_plates_instruction'] ?? 'Nhập nhiều biển số, cách nhau bằng dấu phẩy.' ?></small>
                    </div>

                    <div class="mb-3">
                        <label for="driver_ghi_chu" class="form-label"><?= $lang['notes_label'] ?? 'Ghi chú' ?></label>
                        <textarea class="form-control" id="driver_ghi_chu" name="ghi_chu" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $lang['cancel_button'] ?? 'Hủy' ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= $lang['save_button'] ?? 'Lưu' ?></button>
                </div>
            </form>
            <div id="modal-error-message" class="alert alert-danger mt-3 d-none" role="alert"></div>
        </div>
    </div>
</div>

<!-- Modal Danh Thiếp -->
<div class="modal fade" id="driverCardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Danh thiếp tài xế</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div id="driverCardContent" class="p-4 rounded-3 bg-white shadow-sm">
                    <p class="text-center text-muted">Đang tải thông tin...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/drivers.js"></script>
