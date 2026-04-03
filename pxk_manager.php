<?php
// File: pxk_manager.php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
require_login();

$page_title = $lang['pxk_manager'] ?? 'Quản lý Phiếu Xuất Kho';
?>
<style>
  /* ERP Custom Styles */
  .erp-table-container {
    max-height: calc(100vh - 210px);
    overflow-y: auto;
  }
  .erp-table th {
    position: sticky;
    top: 0;
    z-index: 2;
    background-color: #f8f9fa;
    font-weight: 600;
    white-space: nowrap;
    border-bottom: 2px solid #dee2e6;
  }
  .erp-table td {
    vertical-align: middle;
  }
  .erp-table tbody tr {
    cursor: pointer;
    transition: background-color 0.15s ease-in-out;
  }
  .erp-table tbody tr:hover {
    background-color: #f1f5fb!important;
  }
  /* Cột action ko trigger row click */
  .col-action {
    cursor: default;
  }
  
  /* Offcanvas rộng */
  .offcanvas-erp {
    width: 950px !important;
    max-width: 100vw;
  }
  
  /* Editable Table Items */
  .table-editable td {
    padding: 0.25rem;
    vertical-align: middle;
  }
  .table-editable .form-control {
    border: 1px solid transparent;
    border-radius: 4px;
    box-shadow: none;
    padding: 0.375rem 0.5rem;
    transition: all 0.2s;
    background-color: transparent;
  }
  .table-editable .form-control:not([readonly]):hover,
  .table-editable .form-control:not([readonly]):focus {
    border-color: #0d6efd;
    background-color: #fff;
  }
  .table-editable .form-control[readonly] {
    background-color: #f8f9fa;
    color: #6c757d;
  }
  
  .search-group input {
    border-radius: 20px;
    padding-left: 36px;
  }
  .search-group i.bi-search {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    z-index: 4;
  }
</style>

<div class="container-fluid py-3">
  
  <!-- Header & Toolbar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <div>
      <h4 class="mb-0 fw-bold"><?= htmlspecialchars($page_title) ?></h4>
    </div>
    
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <!-- Search & Filter Left -->
      <div class="d-flex align-items-center gap-2 bg-white px-2 py-1 rounded shadow-sm border">
        <label for="pageSizeSelect" class="form-label mb-0 small text-muted text-nowrap">Hiển thị:</label>
        <select id="pageSizeSelect" class="form-select form-select-sm border-0 bg-light" style="width:auto; cursor:pointer;">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
          <option value="0">Tất cả</option>
        </select>
        <div class="vr mx-1"></div>
        <div class="search-group position-relative">
          <i class="bi bi-search"></i>
          <input type="text" id="filter-keyword" class="form-control form-control-sm border-0 bg-light" style="width:250px;" placeholder="Tìm số PXK, biên nhận...">
          <button type="button" class="btn btn-sm btn-link text-muted px-2 position-absolute end-0 top-50 translate-middle-y clear-search" id="btn-clear-search" style="display:none;" aria-label="Xóa">
            <i class="bi bi-x-circle-fill"></i>
          </button>
        </div>
      </div>
      
      <!-- Right Actions -->
      <button id="btn-reload" class="btn btn-light shadow-sm border" title="Tải lại danh sách">
        <i class="bi bi-arrow-repeat"></i>
      </button>

      <div class="dropdown">
        <button class="btn btn-light shadow-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Tiện ích máy in">
          <i class="bi bi-printer me-1"></i> Máy in
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li><a class="dropdown-item" href="#" id="btnSelectPrinter"><i class="bi bi-gear me-2 text-secondary"></i> Chọn máy in...</a></li>
          <li><a class="dropdown-item" href="#" id="btnPrintCleanup"><i class="bi bi-broom me-2 text-danger"></i> Dọn hàng đợi</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="#" id="btn-print-diag"><i class="bi bi-activity me-2 text-info"></i> Chẩn đoán máy in</a></li>
        </ul>
      </div>

      <button id="btn-new" class="btn btn-primary shadow-sm fw-medium">
        <i class="bi bi-plus-lg me-1"></i> Thêm PXK (F2)
      </button>
    </div>
  </div>

  <!-- Danh sách bản ghi -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white py-2 border-bottom d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-list-columns-reverse text-primary"></i>
        <h6 class="mb-0 fw-bold">Danh sách bản ghi</h6>
        <span class="badge bg-primary-subtle text-primary border rounded-pill px-2" id="totalCount">0</span>
      </div>
    </div>
    <div class="card-body p-0 erp-table-container">
      <table id="pxkTable" class="table table-hover erp-table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th style="width:60px;" class="text-center">ID</th>
            <th style="width:160px;">Số PXK</th>
            <th style="width:120px;">Ngày</th>
            <th>Tên đơn vị nhận</th>
            <th style="width:180px;">Tệp PDF / In</th>
            <th style="width:100px;" class="text-center col-action">Hành động</th>
          </tr>
        </thead>
        <tbody id="pxkTableBody">
          <!-- JS Render -->
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-light py-2">
      <div id="pxkPagination" class="d-flex justify-content-between align-items-center w-100">
        <small id="pagingInfo" class="text-muted"></small>
        <ul id="pager" class="pagination pagination-sm mb-0"></ul>
      </div>
    </div>
  </div>
</div>

<!-- ==============================================
     MODAL FORM BẢN RỘNG (THAY THẾ OFFCANVAS)
=================================================== -->
<div class="modal fade" tabindex="-1" id="pxkOffcanvas" aria-labelledby="pxkOffcanvasLabel" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header bg-light border-bottom py-3">
        <h5 class="modal-title fw-bold text-primary" id="pxkOffcanvasLabel">
          <i class="bi bi-file-earmark-text me-2"></i><span id="formTitle">Thêm Phiếu Xuất Kho</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Khép"></button>
      </div>
      
      <div class="modal-body p-4 bg-white">
        <form id="pxkForm">
      <input type="hidden" id="pxk_id" name="id" value="">
      
      <!-- Thông tin chung & Khách hàng -->
      <div class="row gx-4 gy-3 mb-3 border-bottom pb-3">
        <div class="col-md-5 border-end">
          <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-info-circle me-1"></i> Thông tin chung</h6>
          
          <div class="row g-2 mb-2">
            <div class="col-sm-6">
              <label class="form-label small fw-semibold text-muted mb-1 d-flex justify-content-between align-items-center w-100">
                <span>Số PXK <span class="text-danger">*</span></span>
                <a href="#" id="btn-generate-number" class="text-decoration-none text-primary small bg-light px-1 rounded border" style="outline:none;" tabindex="-1"><i class="bi bi-magic"></i> Tạo tự động</a>
              </label>
              <input type="text" class="form-control form-control-sm" id="pxk_number" name="pxk_number" placeholder="Bắt buộc" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold text-muted mb-1">Ngày xuất <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-sm datepicker" id="pxk_date_display" placeholder="dd/mm/yyyy" required>
              <input type="hidden" id="pxk_date" name="pxk_date" value="">
            </div>
          </div>
          
          <div class="mb-0">
            <label class="form-label small fw-semibold text-muted mb-1">Ghi chú</label>
            <input type="text" class="form-control form-control-sm" id="notes" name="notes" placeholder="Nội dung ghi chú...">
          </div>
        </div>
        
        <div class="col-md-7">
          <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-person-badge me-1"></i> Đối tác nhận</h6>
          
          <div class="mb-2 position-relative">
            <label class="form-label small fw-semibold text-muted mb-1">Tên đơn vị <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm fw-medium text-primary" id="partner_name" name="partner_name" placeholder="Tìm hoặc nhập tên..." autocomplete="off" required>
            <div id="partner_ac_box" class="ac-box shadow border rounded" style="display:none; position:absolute; z-index:9999; background:#fff; max-height:240px; overflow-y:auto; width:100%;"></div>
          </div>
          
          <div class="mb-2">
            <label class="form-label small fw-semibold text-muted mb-1">Địa chỉ</label>
            <input type="text" class="form-control form-control-sm" id="partner_address" name="partner_address" placeholder="Địa chỉ giao...">
          </div>
          
          <div class="row g-2 mb-0">
            <div class="col-6">
              <label class="form-label small fw-semibold text-muted mb-1">Người liên hệ</label>
              <input type="text" class="form-control form-control-sm" id="partner_contact_person" name="partner_contact_person" placeholder="Họ tên...">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-muted mb-1">Điện thoại</label>
              <input type="text" class="form-control form-control-sm" id="partner_phone" name="partner_phone" placeholder="Số ĐT...">
            </div>
          </div>
        </div>
      </div>

      <!-- Khối hàng hóa chi tiết -->
      <div class="card shadow-sm border-0 bg-light">
        <div class="card-header bg-primary-subtle border-0 py-2 d-flex align-items-center justify-content-between rounded-top">
          <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-box-seam me-1"></i> Chi tiết Hàng hóa</h6>
          <button type="button" id="btn-add-item" class="btn btn-sm btn-primary shadow-sm px-3">
            <i class="bi bi-plus-lg"></i> Thêm (Enter)
          </button>
        </div>
        
        <div class="table-responsive bg-white" style="overflow: visible;">
          <table class="table table-bordered table-editable align-middle mb-0" id="itemsTable">
            <thead class="bg-light text-muted small">
              <tr>
                <th style="width:40px;" class="text-center">#</th>
                <th style="width:140px;">Danh mục</th>
                <th>Sản phẩm <span class="text-danger">*</span></th>
                <th style="width:90px;" class="text-center">ĐVT</th>
                <th style="width:100px;" class="text-end">Số lượng <span class="text-danger">*</span></th>
                <th style="width:160px;">Ghi chú</th>
                <th style="width:40px;" class="text-center"><i class="bi bi-x-circle text-danger"></i></th>
              </tr>
            </thead>
            <tbody id="itemsBody" class="border-top-0">
            </tbody>
          </table>
        </div>
      </div>

      </form>
    </div>
    
    <div class="modal-footer border-top bg-light justify-content-between">
      <div class="text-muted small">
        <span class="badge bg-secondary">Ctrl + S</span>: Lưu &nbsp;|&nbsp;
        <span class="badge bg-secondary">Ctrl + Enter</span>: Lưu & In
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Hủy</button>
        <button type="button" id="btn-save" class="btn btn-primary px-4 shadow-sm">
          <i class="bi bi-save me-1"></i> Lưu Lại
        </button>
        <button type="button" id="btn-save-export" class="btn btn-success px-4 shadow-sm">
          <i class="bi bi-file-earmark-pdf me-1"></i> Lưu & Xuất PDF
        </button>
      </div>
    </div>
  </div></div></div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Logic chính ở file riêng -->
<script src="assets/js/pxk_manage.js?v=<?= time() ?>"></script>
