<?php
// inventory.php — Trang quản lý tồn kho khả dụng
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth_check.php';
require_login();

$page_title = 'Quản lý tồn kho';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="h3 fw-bold mb-1"><i class="bi bi-boxes me-2 text-primary"></i>Quản lý Tồn kho</h1>
    <p class="text-muted mb-0 small">Theo dõi số lượng hàng hóa khả dụng và lịch sử xuất nhập</p>
  </div>
  <div class="page-header-actions">
    <input id="inv-search" type="text" class="form-control form-control-sm" style="width:220px;" placeholder="Tìm sản phẩm...">
    <select id="status-filter" class="form-select form-select-sm" style="width:200px;">
      <option value="">Trạng thái đơn mua (SO)</option>
      <option value="ordered">ordered</option>
      <option value="partially_received">partially_received</option>
      <option value="fully_received">fully_received</option>
    </select>
    <button id="btn-export" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel
    </button>
  </div>
</div>

<div class="content-card shadow-sm">
  <div class="content-card-header">
    <span><i class="bi bi-table me-2 text-primary"></i>Bảng tồn kho khả dụng</span>
  </div>
  <div class="content-card-body-flush">
    <div class="table-responsive">
      <table id="inv-table" class="table table-hover table-custom w-100 mb-0">
  <thead class="table-light">
    <tr>
      <th>Danh mục</th>
      <th>Tên sản phẩm</th>
      <th>ĐVT</th>
      <th class="text-end">SL mua (SO)</th>
      <th class="text-end">SL đã chốt bán</th>
      <th class="text-end">Tồn khả dụng</th>
      <th class="text-end">Tổng tiền mua</th>
      <th class="text-end">Tổng tiền bán (accepted)</th>
      <th>Chi tiết</th>
    </tr>
  </thead>
  <tbody></tbody>
  <tfoot>
    <tr>
      <th colspan="3" class="text-end">Tổng:</th>
      <th class="text-end"></th>
      <th class="text-end"></th>
      <th class="text-end"></th>
      <th class="text-end"></th>
      <th class="text-end"></th>
      <th></th>
    </tr>
  </tfoot>
</table>
    </div>
  </div>
</div>

<!-- Modal chi tiết xuất/nhập -->
<div class="modal fade" id="movementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Lịch sử xuất/nhập: <span id="mv-product-title"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <div class="card border-success-subtle">
              <div class="card-header bg-success-subtle">Nhập (Mua vào từ SO)</div>
              <div class="card-body p-0">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>Ngày mua</th>
                      <th>Nhà cung cấp</th>
                      <th class="text-end">SL</th>
                      <th class="text-end">Đơn giá</th>
                      <th class="text-end">Thành tiền</th>
                      <th>SO #</th>
                    </tr>
                  </thead>
                  <tbody id="mv-purchases-body"></tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="card border-primary-subtle">
              <div class="card-header bg-primary-subtle">Xuất (Bán ra từ Quote accepted)</div>
              <div class="card-body p-0">
                <table class="table table-sm mb-0">
                  <thead>
                    <tr>
                      <th>Ngày bán</th>
                      <th>Khách hàng</th>
                      <th class="text-end">SL</th>
                      <th class="text-end">Đơn giá</th>
                      <th class="text-end">Thành tiền</th>
                      <th>Quote #</th>
                    </tr>
                  </thead>
                  <tbody id="mv-sales-body"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <small class="text-muted">Ghi chú: “Tồn khả dụng” = Tổng SL mua (SO, trừ draft/cancel) − Tổng SL đã chốt bán (Quote accepted).</small>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/inventory.js?v=<?php echo time(); ?>"></script>
