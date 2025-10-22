<?php
// File: includes/document_list.php
// Hiển thị bảng danh sách các tài liệu (Đơn hàng, Báo giá, Xuất kho)
// Cần biến: $list_data (mảng dữ liệu), $list_type ('sales_order', 'quote', 'dispatch'), $lang

if (!isset($list_type) || !isset($lang)) {
    // $list_data có thể không cần nếu dùng DataTables server-side hoặc load bằng JS
}
$list_data = $list_data ?? []; // Khởi tạo nếu không có

// Xác định các cột dựa trên loại danh sách
$columns = [];
$tableId = '';
if ($list_type === 'sales_order') {
    $columns = [
        'order_number' => ['label' => $lang['order_number'] ?? 'Order No.', 'filter_type' => 'text', 'placeholder' => $lang['filter_by_number'] ?? 'Filter by Number...'],
        'order_date' => ['label' => $lang['order_date'] ?? 'Order Date', 'filter_type' => 'date', 'placeholder' => $lang['filter_by_date'] ?? 'Filter by Date...'],
        'supplier_name' => ['label' => $lang['supplier'] ?? 'Supplier', 'filter_type' => 'text', 'placeholder' => $lang['filter_by_supplier'] ?? 'Filter by Supplier...'],
        'linked_quote_number' => ['label' => $lang['linked_sales_quote'] ?? 'BG đang liên kết', 'filter_type' => 'none'],
        'grand_total' => ['label' => $lang['grand_total'] ?? 'Grand Total', 'filter_type' => 'none'],
        'customer_name' => ['label' => $lang['customer'] ?? 'Khách hàng', 'filter_type' => 'text', 'placeholder' => $lang['filter_by_customer'] ?? 'Filter by Customer...'],
        'expected_delivery_date' => ['label' => 'Ngày giao hàng', 'filter_type' => 'none'],
        'driver_name' => ['label' => 'Tài xế', 'filter_type' => 'none'],
        'tien_xe' => ['label' => 'Tiền xe', 'filter_type' => 'none'],
        'ghi_chu_order' => ['label' => 'Ghi chú', 'filter_type' => 'none'],
        'actions' => ['label' => $lang['actions'] ?? 'Hành động', 'filter_type' => 'none'],
    ];
    $tableId = 'sales-orders-table';
} elseif ($list_type === 'sales_quote') {
    $columns = [
        'quote_number' => ['label' => $lang['quote_number'] ?? 'Quote No.', 'filter_type' => 'text', 'placeholder' => $lang['filter_by_number'] ?? 'Filter by Number...'],
        'quote_date' => ['label' => $lang['quote_date'] ?? 'Quote Date', 'filter_type' => 'date', 'placeholder' => $lang['filter_by_date'] ?? 'Filter by Date...'],
        'customer_name' => ['label' => $lang['customer'] ?? 'Customer', 'filter_type' => 'text', 'placeholder' => $lang['filter_by_partner'] ?? 'Filter by Partner...'],
        'linked_order_number' => ['label' => $lang['linked_sales_order'] ?? 'PO đang liên kết', 'filter_type' => 'none'],
        'grand_total' => ['label' => $lang['grand_total'] ?? 'Grand Total', 'filter_type' => 'none'],
        'status' => ['label' => $lang['status'] ?? 'Status', 'filter_type' => 'select', 'options' => [
            '' => $lang['filter_by_status'] ?? 'Filter by Status...', 'draft' => $lang['status_draft'] ?? 'Draft', 'quoteed' => $lang['status_quoteed'] ?? 'Quoteed', 'partially_received' => $lang['status_partially_received'] ?? 'Partially Received', 'fully_received' => $lang['status_fully_received'] ?? 'Fully Received', 'cancelled' => $lang['status_cancelled'] ?? 'Cancelled',
        ]],
        'ghi_chu_quote' => ['label' => 'Ghi chú', 'filter_type' => 'none'],
        'actions' => ['label' => $lang['actions'] ?? 'Hành động', 'filter_type' => 'none'],
    ];
    $tableId = 'sales-quotes-table';
} elseif ($list_type === 'dispatch') {
    $tableId = 'dispatches-table';
} else {
    echo "<p class='text-danger'>Invalid list type specified.</p>";
    return;
}

// Generate year options
$currentYear = date('Y');
$yearOptions = ['' => $lang['all_years'] ?? 'Tất cả năm'];
for ($i = 0; $i < 6; $i++) {
    $year = $currentYear - $i;
    $yearOptions[$year] = $year;
}

// Generate month options
$monthOptions = ['' => $lang['all_months'] ?? 'Tất cả tháng'];
for ($month = 1; $month <= 12; $month++) {
    $value = str_pad($month, 2, '0', STR_PAD_LEFT);
    $text = ($lang['month'] ?? 'Tháng') . " " . $month;
    $monthOptions[$value] = $text;
}
?>

<div class="card-body">

  <!-- Hàng 1: Năm / Tháng (giữ nguyên) -->
  <div class="row gx-3 gy-2 mb-3 align-items-end">
    <div class="col-md-3 col-sm-6">
      <label for="filterYear" class="form-label mb-1"><?= $lang['filter_by_year'] ?? 'Hiển thị theo Năm' ?>:</label>
      <select class="form-select form-select-sm" id="filterYear">
        <?php foreach ($yearOptions as $value => $text): ?>
          <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($text) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3 col-sm-6">
      <label for="filterMonth" class="form-label mb-1"><?= $lang['filter_by_month'] ?? 'Hiển thị theo Tháng' ?>:</label>
      <select class="form-select form-select-sm" id="filterMonth">
        <?php foreach ($monthOptions as $value => $text): ?>
          <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($text) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Hàng 2: 1 ô bộ lọc duy nhất + “Chưa giao” + Reset -->
  <div class="row gx-2 gy-2 align-items-end flex-nowrap overflow-auto pb-1">
    <div class="col-md-6 col-sm-12">
      <input type="text"
             id="unifiedFilter"
             class="form-control form-control-sm"
             placeholder="<?= htmlspecialchars(($lang['search_placeholder'] ?? 'Tìm Số ĐH, Ngày ĐH, NCC, Khách hàng, Tên SP…')) ?>">
    </div>

    <div class="col-md-3 col-sm-6">
      <select class="form-select form-select-sm" id="deliveryStatusFilter" title="Lọc trạng thái giao">
        <option value=""><?= $lang['all'] ?? 'Tất cả' ?></option>
        <option value="not_delivered">Chưa giao</option>
        <option value="delivered">Đã giao</option>
      </select>
    </div>

    <div class="col-auto">
      <button class="btn btn-sm btn-outline-secondary" id="reset-filters-<?= $tableId ?>">
        <i class="bi bi-arrow-clockwise"></i> <?= $lang['reset_filters'] ?? 'Reset' ?>
      </button>
    </div>
  </div>

  <!-- Bảng -->
  <div id="scroll-wrapper" style="overflow-x: auto;">
    <table class="table table-striped table-hover table-bordered table-sm document-list-table" id="<?= $tableId ?>" style="width:100%;">
      <thead class="table-light">
        <tr>
          <th class="col-details-control" style="width: 20px;"></th>
          <?php foreach ($columns as $key => $col_config): ?>
            <th class="col-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($col_config['label']) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <div id="sticky-scroll" class="sticky-scroll">
    <div id="scroll-fake-track"></div>
  </div>

  <div class="mt-3 d-flex justify-content-start">
    <button id="expand-collapse-all" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrows-expand me-1"></i> <?= $lang['expand_all'] ?? 'Mở rộng tất cả' ?>
    </button>
  </div>
</div>

<div class="modal fade" id="sendEmailModal" tabindex="-1" aria-labelledby="sendEmailModalLabel" aria-hidden="true">
 <div class="modal-dialog modal-lg">
     <div class="modal-content">
         <form id="sendEmailForm">
             <div class="modal-header">
                 <h5 class="modal-title" id="sendEmailModalLabel">Soạn và Gửi Email Đơn Hàng: <span id="modal-send-email-order-number-display"></span></h5>
                 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
             </div>
             <div class="modal-body">
                 <input type="hidden" id="emailOrderId" name="order_id">
                 <input type="hidden" id="emailPdfUrl" name="pdf_url_for_js">

                 <div class="mb-3">
                     <label for="emailTo" class="form-label">Đến (To): <span class="text-danger">*</span></label>
                     <input type="email" class="form-control" id="emailTo" name="to_email" required>
                 </div>
                 <div class="mb-3">
                     <label for="emailCc" class="form-label">CC:</label>
                     <input type="text" class="form-control" id="emailCc" name="cc_emails" placeholder="Các email cách nhau bởi dấu phẩy (,)">
                     <small class="form-text text-muted">Ví dụ: email1@example.com, email2@example.com</small>
                 </div>
                 <div class="mb-3">
                     <label for="emailSubject" class="form-label">Tiêu đề (Subject): <span class="text-danger">*</span></label>
                     <input type="text" class="form-control" id="emailSubject" name="subject" required>
                 </div>
                 <div class="mb-3">
                     <label for="emailBody" class="form-label">Nội dung (Body): <span class="text-danger">*</span></label>
                     <textarea class="form-control" id="emailBody" name="body" rows="10" ></textarea>
                 </div>

                 <div class="mb-3">
                     <label class="form-label d-block">File đính kèm:</label>

                     <div id="emailAttachmentDisplay" class="d-flex align-items-center p-2 border rounded mb-2 d-none">
                         <i class="bi bi-file-earmark-pdf-fill text-danger me-2 fs-5"></i>
                         <span class="flex-grow-1 text-truncate me-2">
                             <a href="#" id="emailAttachmentLink" target="_blank" class="text-decoration-none">Tên_File_PDF_Mac_Dinh.pdf</a>
                             <small class="text-muted">(Đính kèm mặc định)</small>
                         </span>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-default-attachment" title="Xóa file đính kèm mặc định">
                            <i class="bi bi-x"></i>
                        </button>
                     </div>

                     <label for="emailExtraAttachments" class="form-label mt-2">Thêm file đính kèm khác:</label>
                     <input type="file" class="form-control" id="emailExtraAttachments" name="extra_attachments[]" multiple>
                     <div id="emailExtraAttachmentsList" class="form-text text-muted mt-1">
                         <span class="text-muted">Chưa có file đính kèm thêm nào được chọn.</span>
                     </div>
                 </div>
             </div>
             <div class="modal-footer">
                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                 <button type="submit" class="btn btn-primary" id="btnSubmitSendEmail">
                     <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                     Gửi Email
                 </button>
             </div>
         </form>
     </div>
 </div>
</div>
<div class="modal fade" id="viewOrderEmailLogsModal" tabindex="-1" aria-labelledby="viewOrderEmailLogsModalLabel" aria-hidden="true">
 <div class="modal-dialog modal-lg">
     <div class="modal-content">
         <div class="modal-header">
             <h5 class="modal-title" id="viewOrderEmailLogsModalLabel">Lịch sử Email Đơn hàng: <span id="modal-order-log-number"></span></h5>
             <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
         </div>
         <div class="modal-body" id="order-email-logs-content">
             </div>
         <div class="modal-footer">
             <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
         </div>
     </div>
 </div>
</div>
<div class="modal fade" id="viewQuoteEmailLogsModal" tabindex="-1" aria-labelledby="viewQuoteEmailLogsModalLabel" aria-hidden="true">
 <div class="modal-dialog modal-lg">
     <div class="modal-content">
         <div class="modal-header">
             <h5 class="modal-title" id="viewQuoteEmailLogsModalLabel">Lịch sử Email Báo giá: <span id="modal-quote-log-number"></span></h5>
             <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
         </div>
         <div class="modal-body" id="quote-email-logs-content">
             </div>
         <div class="modal-footer">
             <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
         </div>
     </div>
 </div>
</div>