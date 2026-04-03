<?php
// invoice_packing.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/init.php';
require_login();

if ($_SESSION['role'] !== 'admin') {
    die("Bạn không có quyền truy cập chức năng này.");
}

$page_title = 'Invoice & Packing List Management';

// Fetch company info for the header
$company_info = null;
try {
    $stmt_company = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
    $company_info = $stmt_company->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error fetching company info: " . $e->getMessage());
}
?>

<div class="page-header">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>INVOICE / PACKING LIST</h1>
        <p class="text-muted mb-0 small">Quản lý hóa đơn và danh sách đóng gói hàng hóa</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary" id="btn-create-new">
            <i class="bi bi-plus-circle me-1"></i> Tạo mới Invoice
        </button>
    </div>
</div>

<!-- Invoice Form Card -->
<div class="card shadow-sm mb-4" id="invoice-form-card" style="display: none;">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0" id="form-title">Tạo mới Invoice</h5>
        <button type="button" class="btn-close btn-close-white" id="btn-close-form" aria-label="Close"></button>
    </div>
    <div class="card-body p-4">
        <form id="invoice-form">
            <input type="hidden" name="id" id="invoice_id">
            
            <!-- Document Header Mimicking PDF Layout -->
            <div class="invoice-header-preview border-bottom pb-4 mb-4">
                <div class="row">
                    <div class="col-md-12">
                        <h4 class="fw-bold mb-0"><?= htmlspecialchars($company_info['name_vi'] ?? 'Tên Công Ty') ?></h4>
                        <p class="mb-0">Add: <?= htmlspecialchars($company_info['address_vi'] ?? 'Địa chỉ') ?></p>
                        <p class="mb-0">Tel: <?= htmlspecialchars($company_info['phone'] ?? 'Số điện thoại') ?></p>
                        <p class="mb-0">Tax code: <?= htmlspecialchars($company_info['tax_id'] ?? 'Mã số thuế') ?></p>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <h2 class="display-6 fw-bold">INVOICE / PACKING LIST</h2>
                </div>
            </div>

            <!-- Date and No Section -->
            <div class="row mb-4 justify-content-center">
                <div class="col-md-4 text-center">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date:</label>
                        <input type="text" class="form-control text-center datepicker" name="invoice_date" id="invoice_date" value="<?= date('d/m/Y') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">No:</label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" name="invoice_prefix" id="invoice_prefix" placeholder="Prefix (VD: STV-AMON)" required>
                            <span class="input-group-text">/<?= date('Y') ?>-</span>
                            <input type="text" class="form-control text-center bg-light" id="invoice_seq_display" readonly placeholder="XX">
                            <input type="hidden" name="invoice_year" id="invoice_year" value="<?= date('Y') ?>">
                        </div>
                        <small class="text-muted">Nhập prefix để hệ thống tự tạo số thứ tự</small>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- BILL TO Section -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <h6 class="fw-bold border-bottom pb-2 mb-3">BILL TO:</h6>
                        <input type="hidden" name="partner_bill_id" id="partner_bill_id">
                        <div class="mb-3">
                            <input type="text" class="form-control fw-bold partner-autocomplete" id="bill_to_name" placeholder="Chọn khách hàng (autocomplete)..." required>
                        </div>
                        <div class="mb-1"><span class="text-muted small">Add:</span> <span id="bill_to_address_display">-</span></div>
                        <div class="mb-1"><span class="text-muted small">Tell:</span> <span id="bill_to_phone_display">-</span></div>
                        <div class="mb-0"><span class="text-muted small">Tax code:</span> <span id="bill_to_tax_display">-</span></div>
                    </div>
                </div>
                <!-- SHIP TO Section -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <h6 class="fw-bold border-bottom pb-2 mb-3">SHIP TO:</h6>
                        <input type="hidden" name="partner_ship_id" id="partner_ship_id">
                        <div class="mb-3">
                            <input type="text" class="form-control fw-bold partner-autocomplete" id="ship_to_name" placeholder="Chọn khách hàng (giống Bill To nếu bỏ trống)...">
                        </div>
                        <div class="mb-1"><span class="text-muted small">Add:</span> <span id="ship_to_address_display">-</span></div>
                        <div class="mb-1"><span class="text-muted small">Tell:</span> <span id="ship_to_phone_display">-</span></div>
                        <div class="mb-0"><span class="text-muted small">Tax code:</span> <span id="ship_to_tax_display">-</span></div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle" id="items-table">
                    <thead class="table-light text-center">
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Description of Goods</th>
                            <th style="width: 120px;">Quantity (KG)</th>
                            <th style="width: 150px;">Unit Price (VND)</th>
                            <th style="width: 150px;">Total (VND)</th>
                            <th>Remark</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        <!-- Dynamic Rows -->
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7">
                                <button type="button" class="btn btn-sm btn-outline-success" id="btn-add-item">
                                    <i class="bi bi-plus-lg"></i> Thêm hàng hóa
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Total and Say -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">TOTAL:</label>
                        <input type="text" class="form-control" name="total_remark" id="total_remark" placeholder="Remark for total (e.g. CPT CP...)">
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="h5 fw-bold text-primary">VND: <span id="total_amount_display">0</span></div>
                    <input type="hidden" name="total_amount" id="total_amount">
                </div>
                <div class="col-md-12">
                    <div class="alert alert-secondary py-2">
                        <strong>SAY:</strong> <span id="total_text_display">Không đồng</span>
                    </div>
                </div>
            </div>

            <!-- Packing Details -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Packing:</label>
                    <input type="text" class="form-control" name="packing" id="invoice_packing" placeholder="VD: IN DRUMS">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Net weight:</label>
                    <input type="text" class="form-control" name="net_weight" id="invoice_net_weight" placeholder="VD: 1,000.00 KG">
                </div>
            </div>

            <!-- Signature Area -->
            <div class="row mt-5">
                <div class="col-md-8"></div>
                <div class="col-md-4 text-center">
                    <p class="mb-0 fw-bold"><?= htmlspecialchars($company_info['company_name'] ?? 'Tên Công Ty') ?></p>
                    <div style="height: 100px;"></div>
                    <p class="mb-0">(Ký và đóng dấu)</p>
                </div>
            </div>

            <hr class="my-4">
            
            <div class="text-end">
                <button type="button" class="btn btn-secondary me-2" id="btn-cancel">Hủy</button>
                <button type="submit" class="btn btn-success" id="btn-save">
                    <i class="bi bi-save me-1"></i> Lưu Invoice
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Invoice List Card -->
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Danh sách Invoice / Packing List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="invoice-list-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Ngày</th>
                        <th>BILL TO</th>
                        <th>SHIP TO</th>
                        <th class="text-end">Tổng tiền</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="invoice-list-body">
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// Flatpickr CSS for usage in the page
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">';
require_once __DIR__ . '/includes/footer.php';
?>

<script src="assets/js/invoice_packing_module.js"></script>

<style>
.invoice-header-preview {
    font-family: 'Times New Roman', Times, serif;
}
.datepicker {
    background-color: #fff !important;
}
.partner-autocomplete {
    font-size: 1.1rem;
    border-color: #ced4da;
}
#items-table th {
    font-size: 0.85rem;
    text-transform: uppercase;
}
</style>
