// File: assets/js/sq_datatable.js (GIỮ NGUYÊN CODE GỐC, CHỈ THÊM CỘT "GHI CHÚ")

function initializeSalesQuoteDataTable() {
    console.log("Initializing Server-Side DataTables for Sales Quotes...");
    if ($.fn.dataTable.isDataTable(quoteTableElement)) { // Đảm bảo quoteTableElement đã được gán
        quoteTableElement.DataTable().destroy();
    }
 
    try {
        salesQuoteDataTable = quoteTableElement.DataTable({ // Gán vào salesQuoteDataTable
            processing: true,
            serverSide: true,
            ajax: {
  url: PROJECT_BASE_URL + 'process/sales_quote_serverside.php',
  type: 'POST',
  data: function (d) {
    // Ô gộp 5 bộ lọc: Số BG, Ngày BG, KH, NCC (nếu có), Tên SP…
    d.unified_filter      = $('#unifiedFilter').val() || '';

    // Trạng thái báo giá (select riêng cho quotes)
    d.quote_status        = $('#quoteStatusFilter').val() || '';

    // Lọc theo tên SP (nếu bạn vẫn giữ input này cho quotes)
    d.item_details_filter = $('#item-details-filter-input').val() || '';

    // Hiển thị theo Năm/Tháng (giữ nguyên)
    d.filter_year         = $('#filterYear').val() || '';
    d.filter_month        = $('#filterMonth').val() || '';

    return d;
  },
  error: function (jqXHR, textStatus, errorThrown) {
    console.error("DataTables Server-Side AJAX Error for quotes:", textStatus, errorThrown, jqXHR.responseText);
    let errorMsg = LANG['server_error_loading_list'] || 'Lỗi máy chủ khi tải danh sách báo giá.';
    try {
      const res = JSON.parse(jqXHR.responseText);
      if (res && res.message) errorMsg = res.message;
    } catch (e) { /* noop */ }

    showUserMessage(errorMsg, 'error');

    // colspan động theo số cột thực tế
    const colCount = quoteTableElement.find('thead th').length || 9;
    quoteTableElement.find('tbody').html(
      `<tr><td colspan="${colCount}" class="text-center text-danger">${escapeHtml(errorMsg)}</td></tr>`
    );
    $('.dataTables_processing').hide();
  }
},
            columns: [
                { // 0: Details control
                    className: 'details-control dt-body-center', 
                    orderable: false, 
                    data: null, 
                    defaultContent: '<i class="bi bi-plus-square text-success"></i>', 
                    width: "20px" 
                },
                { // 1: Số Báo Giá
                    data: 'quote_number', 
                    name: 'sq.quote_number', // sq là alias cho sales_quotes
                    className: 'dt-body-left' 
                },
                { // 2: Ngày Báo Giá
                    data: 'quote_date_formatted', 
                    name: 'sq.quote_date', 
                    className: 'dt-body-center' 
                },
                { // 3: Khách Hàng
                    data: 'customer_name', 
                    name: 'p.name', // p là alias cho partners
                    className: 'dt-body-left' 
                },
                { // 4: PO ĐANG LIÊN KẾT (Cột mới)
                    title: LANG['linked_sales_order'] || 'PO đang liên kết',
                    data: 'linked_order_number', 
                    name: 'linked_order_number', 
                    className: 'dt-body-left',
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        if (type === 'display') {
                            return data ? escapeHtml(data) : `<span class="text-muted fst-italic">${LANG['not_linked'] || 'Chưa liên kết'}</span>`;
                        }
                        return data;
                    }
                },
                { // 5: Tổng Cộng (Grand Total)
                    title: LANG['grand_total'] || 'Tổng Cộng',
                    data: 'grand_total_formatted', 
                    name: 'sq.grand_total', 
                    className: 'dt-body-right', 
                    orderable: true, 
                    searchable: false,
                    render: function (data, type, row) { 
                        return (data || '0') + ' <small>' + (row.currency || (typeof DEFAULT_CURRENCY !== 'undefined' ? DEFAULT_CURRENCY : 'VND')) + '</small>'; 
                    }
                },
                { // 6: Trạng Thái
                    title: LANG['status'] || 'Trạng thái',
                    data: 'status', 
                    name: 'sq.status', 
                    className: 'dt-body-center',
                    render: function(data, type, row) {
                        let statusText = LANG['status_' + String(data).toLowerCase()] || (data ? String(data).charAt(0).toUpperCase() + String(data).slice(1) : 'N/A');
                        let badgeClass = 'bg-secondary'; 
                        switch (String(data).toLowerCase()) { 
                            case 'draft': badgeClass = 'bg-light text-dark border'; break;
                            case 'sent': badgeClass = 'bg-info text-dark'; break;
                            case 'accepted': badgeClass = 'bg-success'; break;
                            case 'rejected': badgeClass = 'bg-danger'; break;
                            case 'expired': badgeClass = 'bg-warning text-dark'; break;
                            case 'invoiced': badgeClass = 'bg-primary'; break;
                        }
                        return `<span class="badge ${badgeClass} p-1">${escapeHtml(statusText)}</span>`;
                    }
                },

                // =======================================================
                // TÔI CHỈ THÊM 1 CỘT "GHI CHÚ" VÀO ĐÂY
                // =======================================================
                { // 7: Cột Ghi chú mới
                    title: 'Ghi chú', // Lấy tiêu đề từ PHP
                    data: 'ghi_chu_quote',    // Lấy nội dung HTML từ server
                    name: 'ghi_chu_quote',
                    orderable: false,         // Không cho sắp xếp
                    searchable: false       // Không cho tìm kiếm
                },
                // =======================================================

                            { // 8: Các nút hành động
                                title: LANG['actions'] || 'Hành động',
                                data: null, 
                                orderable: false,
                                searchable: false,
                                className: 'text-end action-cell dt-nowrap',
                                render: function(data, type, row) {
                const quoteId = row.id;
                const quoteNumber = escapeHtml(row.quote_number || '');
                const currentStatus = String(row.status).toLowerCase();
                const orderIdLinked = row.order_id;
                const safeQuoteNumber = (typeof sanitizeFilename === 'function' ? sanitizeFilename(row.quote_number) : String(row.quote_number || '').replace(/[^a-z0-9_.-]/gi, '-'));
                const pdfPath = `${PROJECT_BASE_URL}pdf/${safeQuoteNumber}.pdf`;

                let actionsHtml = '<div class="btn-group" role="group">';

                // --- CÁC NÚT CƠ BẢN LUÔN HIỂN THỊ ---
               actionsHtml += `<button class="btn btn-sm btn-outline-secondary btn-view-pdf" 
                    data-id="${quoteId}" 
                    data-quote-number="${escapeHtml(quoteNumber)}" 
                    data-pdf-path="${escapeHtml(pdfPath)}" 
                    title="${LANG['view_pdf'] || 'Xem PDF'}">
                    <i class="bi bi-file-earmark-pdf"></i>
                </button>`;
                actionsHtml += `<button class="btn btn-sm btn-outline-primary btn-send-email" data-id="${quoteId}" data-quote-number="${quoteNumber}" data-pdf-url="${escapeHtml(pdfPath)}" title="${LANG['send_email'] || 'Gửi Email'}"><i class="bi bi-envelope-fill"></i></button>`;
                actionsHtml += `<button class="btn btn-sm btn-outline-info btn-view-quote-logs" data-quote-id="${quoteId}" data-quote-number="${quoteNumber}" title="${LANG['view_email_logs'] || 'Xem LS Email'}"><i class="bi bi-mailbox"></i></button>`;

                // --- NÚT SỬA VÀ XÓA (Vẫn giữ logic cũ để đảm bảo an toàn) ---
                // Nút Sửa sẽ hiển thị khi ở trạng thái 'draft' hoặc khi có quyền sửa
                if (currentStatus === 'draft' || row.can_edit) {
                    actionsHtml += `<button class="btn btn-sm btn-outline-warning btn-edit-document" data-id="${quoteId}" title="${LANG['edit']||'Sửa'}"><i class="bi bi-pencil-square"></i></button>`;
                }
                // Nút Xóa chỉ hiển thị khi là bản nháp
                if (currentStatus === 'draft') {
                    actionsHtml += `<button class="btn btn-sm btn-outline-danger btn-delete-document" data-id="${quoteId}" data-number="${quoteNumber}" title="${LANG['delete']||'Xóa'}"><i class="bi bi-trash"></i></button>`;
                }
                
                // --- DROPDOWN THAY ĐỔI TRẠNG THÁI ---
                actionsHtml += `<div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Thay đổi trạng thái">
                                        <i class="bi bi-arrow-down-up"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item btn-update-status" href="#" data-id="${quoteId}" data-new-status="draft">${LANG['status_draft'] || 'Chuyển về Nháp'}</a></li>
                                        <li><a class="dropdown-item btn-update-status" href="#" data-id="${quoteId}" data-new-status="sent">${LANG['status_sent'] || 'Đánh dấu Đã gửi'}</a></li>
                                        <li><a class="dropdown-item btn-update-status" href="#" data-id="${quoteId}" data-new-status="accepted">${LANG['status_accepted'] || 'Đánh dấu Chấp nhận'}</a></li>
                                        <li><a class="dropdown-item btn-update-status" href="#" data-id="${quoteId}" data-new-status="rejected">${LANG['status_rejected'] || 'Đánh dấu Từ chối'}</a></li>
                                        <li><a class="dropdown-item btn-update-status" href="#" data-id="${quoteId}" data-new-status="expired">${LANG['status_expired'] || 'Đánh dấu Hết hạn'}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item btn-update-status text-danger" href="#" data-id="${quoteId}" data-new-status="cancelled">${LANG['status_cancelled'] || 'Hủy Báo giá'}</a></li>
                                    </ul>
                                </div>`;

                // --- CÁC NÚT HÀNH ĐỘNG LIÊN QUAN ---
                // Nút tạo đơn hàng từ báo giá
                if (currentStatus === 'accepted' && !orderIdLinked) {
                    actionsHtml += `<button type="button" class="btn btn-sm btn-info btn-create-order-from-quote ms-1" data-quote-id="${quoteId}" data-quote-number="${quoteNumber}" title="${LANG['create_order_from_quote'] || 'Tạo Đơn Hàng'}"><i class="bi bi-cart-plus-fill"></i></button>`;
                }
                // Nút xem đơn hàng liên quan
                if (orderIdLinked) {
                    actionsHtml += `<a href="${PROJECT_BASE_URL}sales_orders.php?action=view&id=${orderIdLinked}" class="btn btn-sm btn-outline-success ms-1" title="${LANG['view_related_order'] || 'Xem Đơn Hàng Liên Quan'}"><i class="bi bi-eye-fill"></i> ĐH</a>`;
                }

                actionsHtml += '</div>'; // Đóng btn-group lớn
                return actionsHtml;
            }
                }
            ],
            
            order: [[1, 'desc']], // Sắp xếp theo cột số báo giá giảm dần
            language: { url: (typeof LANG !== 'undefined' && LANG.language === 'vi') ? `${PROJECT_BASE_URL}lang/vi.json` : `${PROJECT_BASE_URL}lang/en-GB.json` },
            paging: true, lengthChange: true, lengthMenu: [[25, 50, -1], [25, 50, "Tất cả"]],
            searching: false, info: true, autoWidth: false, responsive: false,
        });
    } catch (e) {
        console.error("Error initializing DataTables for quotes:", e);
        showUserMessage(LANG['error_initializing_datatable'] || 'Lỗi khi khởi tạo bảng báo giá.', 'error');
    }
}
$(document).on('click', '.btn-view-pdf', function () {
    const btn = $(this);
    const quoteId = btn.data('id');
    const quoteNumber = btn.data('quote-number');
    const pdfPath = btn.data('pdf-path');
    const type = 'quote';
    const showSignature = false;

    const originalHtml = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

    $.ajax({
        url: pdfPath,
        type: 'HEAD',
        success: function () {
            window.open(pdfPath, '_blank');
            btn.prop('disabled', false).html(originalHtml);
        },
        error: function () {
            console.warn(`PDF chưa tồn tại: ${pdfPath}. Gọi export_pdf.php để tạo.`);
            showUserMessage("Đang tạo báo giá PDF, vui lòng chờ...", "info");

            $.ajax({
                url: `${PROJECT_BASE_URL}process/export_pdf.php?id=${quoteId}&show_signature=${showSignature}&type=${type}`,
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.pdf_web_path) {
                        window.open(response.pdf_web_path, '_blank');
                        showUserMessage("Tạo PDF báo giá thành công!", "success");
                    } else {
                        showUserMessage('Không thể tạo PDF: ' + (response.message || 'Lỗi không xác định.'), 'error');
                    }
                },
                error: function (xhr) {
                    console.error("Lỗi khi tạo PDF:", xhr.responseText);
                    showUserMessage('Lỗi máy chủ khi tạo PDF báo giá.', 'error');
                },
                complete: function () {
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
        }
    });
});
// Debounce ngắn
function debounce(fn, delay) {
  let t;
  return function() {
    clearTimeout(t);
    const a = arguments, c = this;
    t = setTimeout(() => fn.apply(c, a), delay);
  };
}

// Ô gộp
$(document).on('keyup', '#unifiedFilter', debounce(function(){
  salesQuoteDataTable && salesQuoteDataTable.draw();
}, 300));

// Trạng thái + Năm/Tháng
$(document).on('change', '#quoteStatusFilter, #filterYear, #filterMonth', function(){
  salesQuoteDataTable && salesQuoteDataTable.draw();
});

// Nút reset (quotes)
$(document).on('click', '#reset-filters-sales-quotes-table', function(e){
  e.preventDefault();
  $('#unifiedFilter').val('');
  $('#quoteStatusFilter').val('');
  $('#filterYear').val('');
  $('#filterMonth').val('');
  // nếu giữ input lọc tên SP thì reset luôn
  $('#item-details-filter-input').val('');
  salesQuoteDataTable && salesQuoteDataTable.draw();
});
