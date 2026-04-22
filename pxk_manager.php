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

<div class="page-header">
  <div>
    <h1 class="h3 fw-bold mb-1"><i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i><?= htmlspecialchars($page_title) ?></h1>
    <p class="text-muted mb-0 small"><?= $lang['pxk_description'] ?? 'Quản lý phiếu xuất kho và theo dõi in ấn' ?></p>
  </div>
  <div class="page-header-actions d-flex align-items-center gap-2">
    <!-- Search & Filter -->
    <div class="search-group position-relative">
      <i class="bi bi-search"></i>
      <input type="text" id="filter-keyword" class="form-control form-control-sm border-0 bg-light" style="width:250px; padding-left:36px;" placeholder="<?= $lang['search_pxk_placeholder'] ?? 'Tìm số PXK, biên nhận...' ?>">
      <button type="button" class="btn btn-sm btn-link text-muted px-2 position-absolute end-0 top-50 translate-middle-y clear-search" id="btn-clear-search" style="display:none;" aria-label="<?= $lang['clear'] ?? 'Xóa' ?>">
        <i class="bi bi-x-circle-fill"></i>
      </button>
    </div>
    <!-- Right Actions -->
    <button id="btn-reload" class="btn btn-outline-secondary btn-sm" title="<?= $lang['reload'] ?? 'Tải lại danh sách' ?>">
      <i class="bi bi-arrow-repeat"></i>
    </button>
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= $lang['printer_utility'] ?? 'Tiện ích máy in' ?>">
        <i class="bi bi-printer me-1"></i> <?= $lang['printer_utility'] ?? 'Máy in' ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow">
        <li><a class="dropdown-item" href="#" id="btnSelectPrinter"><i class="bi bi-gear me-2 text-secondary"></i> <?= $lang['select_printer'] ?? 'Chọn máy in...' ?></a></li>
        <li><a class="dropdown-item" href="#" id="btnPrintCleanup"><i class="bi bi-broom me-2 text-danger"></i> <?= $lang['cleanup_queue'] ?? 'Dọn hàng đợi' ?></a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" id="btn-print-diag"><i class="bi bi-activity me-2 text-info"></i> <?= $lang['print_diag'] ?? 'Chẩn đoán máy in' ?></a></li>
      </ul>
    </div>
    <button id="btn-new" class="btn btn-primary fw-medium">
      <i class="bi bi-plus-lg me-1"></i> <?= $lang['add_pxk_f2'] ?? 'Thêm PXK (F2)' ?>
    </button>
  </div>
</div>

<!-- Danh sách bản ghi -->
<div class="content-card shadow-sm">
  <div class="content-card-header">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-list-columns-reverse text-primary"></i>
      <span class="fw-bold"><?= $lang['record_list'] ?? 'Danh sách bản ghi' ?></span>
      <span class="badge bg-primary-subtle text-primary border rounded-pill px-2" id="totalCount">0</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <select id="pageSizeSelect" class="form-select form-select-sm border-0 bg-light" style="width:auto; cursor:pointer;" title="<?= $lang['rows_per_page'] ?? 'Số dòng hiển thị' ?>">
        <option value="10">10 <?= $lang['rows_per_page'] ?? 'dòng' ?></option>
        <option value="25">25 <?= $lang['rows_per_page'] ?? 'dòng' ?></option>
        <option value="50">50 <?= $lang['rows_per_page'] ?? 'dòng' ?></option>
        <option value="100">100 <?= $lang['rows_per_page'] ?? 'dòng' ?></option>
        <option value="0"><?= $lang['all_rows'] ?? 'Tất cả' ?></option>
      </select>
    </div>
  </div>
  <div class="content-card-body-flush">
    <table id="pxkTable" class="table table-hover table-custom align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:60px;" class="text-center"><?= $lang['id'] ?? 'ID' ?></th>
          <th style="width:140px;"><?= $lang['slip_number'] ?? 'Số PXK' ?></th>
          <th style="width:100px;"><?= $lang['date'] ?? 'Ngày' ?></th>
          <th><?= $lang['recipient_unit'] ?? 'Tên đơn vị nhận' ?></th>
          <th style="width:150px;"><?= $lang['driver'] ?? 'Tài xế' ?></th>
          <th style="width:70px;" class="text-center"><?= $lang['printed_status'] ?? 'Đã in' ?></th>
          <th style="width:150px;"><?= $lang['pdf_print'] ?? 'Tệp PDF / In' ?></th>
          <th style="width:90px;" class="text-center col-action"><?= $lang['action'] ?? 'Hành động' ?></th>
        </tr>
      </thead>
      <tbody id="pxkTableBody">
        <!-- JS Render -->
      </tbody>
    </table>
  </div>
  <div class="p-3 border-top d-flex justify-content-between align-items-center">
    <small id="pagingInfo" class="text-muted"></small>
    <ul id="pager" class="pagination pagination-sm mb-0"></ul>
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
          <i class="bi bi-file-earmark-text me-2"></i><span id="formTitle"><?= $lang['add_pxk_modal_title'] ?? 'Thêm Phiếu Xuất Kho' ?></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $lang['close'] ?? 'Đóng' ?>"></button>
      </div>
      
      <div class="modal-body p-4 bg-white">
        <form id="pxkForm">
      <input type="hidden" id="pxk_id" name="id" value="">
      
      <!-- Thông tin chung & Khách hàng -->
      <div class="row gx-4 gy-3 mb-3 border-bottom pb-3">
        <div class="col-md-5 border-end">
          <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-info-circle me-1"></i> <?= $lang['general_info'] ?? 'Thông tin chung' ?></h6>
          
          <div class="row g-2 mb-2">
            <div class="col-sm-6">
              <label class="form-label small fw-semibold text-muted mb-1 d-flex justify-content-between align-items-center w-100">
                <span><?= $lang['slip_number'] ?? 'Số PXK' ?> <span class="text-danger">*</span></span>
                <a href="#" id="btn-generate-number" class="text-decoration-none text-primary small bg-light px-1 rounded border" style="outline:none;" tabindex="-1"><i class="bi bi-magic"></i> <?= $lang['auto_create'] ?? 'Tạo tự động' ?></a>
              </label>
              <input type="text" class="form-control form-control-sm" id="pxk_number" name="pxk_number" placeholder="<?= $lang['required'] ?? 'Bắt buộc' ?>" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold text-muted mb-1"><?= $lang['dispatch_date'] ?? 'Ngày xuất' ?> <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-sm datepicker" id="pxk_date_display" placeholder="dd/mm/yyyy" required>
              <input type="hidden" id="pxk_date" name="pxk_date" value="">
            </div>
          </div>
          
          <div class="mb-2">
            <label class="form-label small fw-semibold text-muted mb-1"><?= $lang['notes'] ?? 'Ghi chú' ?></label>
            <input type="text" class="form-control form-control-sm" id="notes" name="notes" placeholder="<?= $lang['note_placeholder'] ?? 'Nội dung ghi chú...' ?>">
          </div>
          
          <div class="mb-0 position-relative">
            <label class="form-label small fw-semibold text-muted mb-1"><?= $lang['driver'] ?? 'Tài xế' ?></label>
            <input type="text" class="form-control form-control-sm text-primary fw-medium" id="driver_name" name="driver_name" placeholder="<?= $lang['select_driver'] ?? 'Chọn tài xế (hoặc nhập)...' ?>" autocomplete="off">
            <div id="driver_ac_box" class="ac-box shadow border rounded" style="display:none; position:absolute; z-index:9999; background:#fff; max-height:240px; overflow-y:auto; width:100%;"></div>
          </div>
        </div>
        
        <div class="col-md-7">
          <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-person-badge me-1"></i> <?= $lang['partner_receive'] ?? 'Đối tác nhận' ?></h6>
          
          <div class="mb-2 position-relative">
            <label class="form-label small fw-semibold text-muted mb-1"><?= $lang['partner_name_label'] ?? 'Tên đơn vị' ?> <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm fw-medium text-primary" id="partner_name" name="partner_name" placeholder="<?= $lang['partner_name_placeholder'] ?? 'Tìm hoặc nhập tên...' ?>" autocomplete="off" required>
            <div id="partner_ac_box" class="ac-box shadow border rounded" style="display:none; position:absolute; z-index:9999; background:#fff; max-height:240px; overflow-y:auto; width:100%;"></div>
          </div>
          
          <div class="mb-2">
            <label class="form-label small fw-semibold text-muted mb-1"><?= $lang['delivery_address_label'] ?? 'Địa chỉ' ?></label>
            <input type="text" class="form-control form-control-sm" id="partner_address" name="partner_address" placeholder="<?= $lang['delivery_address_placeholder'] ?? 'Địa chỉ giao...' ?>">
          </div>
          
          <div class="row g-2 mb-0">
            <div class="col-6">
              <label class="form-label small fw-semibold text-muted mb-1"><?= $lang['contact_person_label'] ?? 'Người liên hệ' ?></label>
              <input type="text" class="form-control form-control-sm" id="partner_contact_person" name="partner_contact_person" placeholder="<?= $lang['contact_person_placeholder'] ?? 'Họ tên...' ?>">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold text-muted mb-1"><?= $lang['phone_label'] ?? 'Điện thoại' ?></label>
              <input type="text" class="form-control form-control-sm" id="partner_phone" name="partner_phone" placeholder="<?= $lang['phone_placeholder'] ?? 'Số ĐT...' ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Khối hàng hóa chi tiết -->
      <div class="card shadow-sm border-0 bg-light">
        <div class="card-header bg-primary-subtle border-0 py-2 d-flex align-items-center justify-content-between rounded-top">
          <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-box-seam me-1"></i> <?= $lang['item_details_title'] ?? 'Chi tiết Hàng hóa' ?></h6>
          <button type="button" id="btn-add-item" class="btn btn-sm btn-primary shadow-sm px-3">
            <i class="bi bi-plus-lg"></i> <?= $lang['add_item_btn'] ?? 'Thêm (Enter)' ?>
          </button>
        </div>
        
        <div class="table-responsive bg-white" style="overflow: visible;">
          <table class="table table-bordered table-editable align-middle mb-0" id="itemsTable">
            <thead class="bg-light text-muted small">
              <tr>
                <th style="width:40px;" class="text-center">#</th>
                <th style="width:140px;"><?= $lang['category'] ?? 'Danh mục' ?></th>
                <th><?= $lang['product'] ?? 'Sản phẩm' ?> <span class="text-danger">*</span></th>
                <th style="width:90px;" class="text-center"><?= $lang['unit'] ?? 'ĐVT' ?></th>
                <th style="width:100px;" class="text-end"><?= $lang['quantity'] ?? 'Số lượng' ?> <span class="text-danger">*</span></th>
                <th style="width:160px;"><?= $lang['notes'] ?? 'Ghi chú' ?></th>
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
        <span class="badge bg-secondary">Ctrl + S</span>: <?= $lang['save'] ?? 'Lưu' ?> &nbsp;|&nbsp;
        <span class="badge bg-secondary">Ctrl + Enter</span>: <?= $lang['save'] ?? 'Lưu' ?> & In
      </div>
      <div class="d-flex gap-2 align-items-center">
        <select id="pdf_lang" class="form-select form-select-sm border-secondary" style="width: auto;" title="<?= $lang['select_pdf_language'] ?? 'Chọn ngôn ngữ PDF' ?>">
          <option value="vi">VI</option>
          <option value="en">EN</option>
        </select>
        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal"><?= $lang['cancel'] ?? 'Hủy' ?></button>
        <button type="button" id="btn-save" class="btn btn-primary px-4 shadow-sm">
          <i class="bi bi-save me-1"></i> <?= $lang['save'] ?? 'Lưu Lại' ?>
        </button>
        <button type="button" id="btn-save-export" class="btn btn-success px-4 shadow-sm">
          <i class="bi bi-file-earmark-pdf me-1"></i> <?= $lang['save_and_export_pdf'] ?? 'Lưu & Xuất PDF' ?>
        </button>
      </div>
    </div>
  </div></div></div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Logic chính ở file riêng -->
<script>
  const JS_LANG = {
    add_pxk_modal_title: "<?= $lang['add_pxk_modal_title'] ?? 'Thêm Phiếu Xuất Kho' ?>",
    edit_pxk_modal_title: "<?= $lang['edit_pxk_modal_title'] ?? 'Sửa Phiếu Xuất Kho' ?>",
    confirm_delete_pxk: "<?= $lang['confirm_delete_pxk'] ?? 'Bạn có chắc muốn xóa PXK này?' ?>",
    delete_warning: "<?= $lang['delete_warning'] ?? 'Dữ liệu sẽ không thể phục hồi!' ?>",
    pxk_deleted_success: "<?= $lang['pxk_deleted_success'] ?? 'Đã xóa PXK!' ?>",
    pxk_saved_success: "<?= $lang['pxk_saved_success'] ?? 'Đã lưu PXK!' ?>",
    pxk_save_export_success: "<?= $lang['pxk_save_export_success'] ?? 'Đã lưu và xuất PDF!' ?>",
    auto_number_success: "<?= $lang['auto_number_success'] ?? 'Đã tạo số PXK tự động!' ?>",
    loading_export_pdf: "<?= $lang['loading_export_pdf'] ?? 'Đang lưu và xuất PDF, vui lòng chờ...' ?>",
    rows_paging_info: "<?= $lang['rows_paging_info'] ?? 'Hiển thị %s–%s trong tổng %s bản ghi' ?>",
    not_available: "<?= $lang['not_available'] ?? 'Chưa có' ?>",
    edit: "<?= $lang['edit'] ?? 'Sửa' ?>",
    delete: "<?= $lang['delete'] ?? 'Xóa' ?>",
    save: "<?= $lang['save'] ?? 'Lưu' ?>",
    cancel: "<?= $lang['cancel'] ?? 'Hủy' ?>",
    confirm: "<?= $lang['confirm'] ?? 'Xác nhận' ?>",
    error: "<?= $lang['error'] ?? 'Lỗi' ?>",
    processing: "<?= $lang['processing'] ?? 'Đang xử lý...' ?>",
    product_placeholder: "<?= $lang['product_placeholder'] ?? 'Nhập tên...' ?>",
    note_placeholder: "<?= $lang['note_placeholder'] ?? 'Ghi chú...' ?>",
    open_pdf: "<?= $lang['open_pdf'] ?? 'Mở PDF' ?>",
    enqueue_print: "<?= $lang['enqueue_print'] ?? 'Gửi lệnh in' ?>",
    timeout: "<?= $lang['timeout'] ?? 'Hết thời gian chờ' ?>",
    fetch_diag_error: "<?= $lang['fetch_diag_error'] ?? 'Không đọc được chẩn đoán' ?>",
    missing_pdf_path: "<?= $lang['missing_pdf_path'] ?? 'Thiếu đường dẫn PDF để in' ?>",
    print_enqueue_done: "<?= $lang['print_enqueue_done'] ?? 'Đã gửi lệnh in' ?>",
    print_success: "<?= $lang['print_success'] ?? 'In thành công' ?>",
    print_failed: "<?= $lang['print_failed'] ?? 'In thất bại' ?>",
    enqueue_error: "<?= $lang['enqueue_error'] ?? 'Lỗi gửi lệnh in' ?>",
    save_failed: "<?= $lang['save_failed'] ?? 'Lưu PXK thất bại' ?>",
    load_failed: "<?= $lang['load_failed'] ?? 'Không tải được PXK' ?>",
    missing_info: "<?= $lang['missing_info'] ?? 'Thiếu thông tin' ?>",
    enter_pxk_number: "<?= $lang['enter_pxk_number'] ?? 'Vui lòng nhập Số PXK' ?>",
    select_dispatch_date: "<?= $lang['select_dispatch_date'] ?? 'Vui lòng chọn Ngày xuất' ?>",
    enter_partner_name: "<?= $lang['enter_partner_name'] ?? 'Vui lòng nhập Tên đơn vị nhận' ?>",
    enter_at_least_one_item: "<?= $lang['enter_at_least_one_item'] ?? 'Vui lòng nhập ít nhất 1 dòng hàng' ?>",
    auto_gen_error: "<?= $lang['auto_gen_error'] ?? 'Không tạo được số PXK' ?>",
    cleanup_done: "<?= $lang['cleanup_done'] ?? 'Đã dọn' ?>",
    printing_failed_count: "<?= $lang['printing_failed_count'] ?? 'printing → failed' ?>",
    pending_failed_count: "<?= $lang['pending_failed_count'] ?? 'pending → failed' ?>",
  };
</script>
<script src="assets/js/pxk_manage.js?v=<?= time() ?>"></script>
