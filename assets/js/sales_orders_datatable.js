// File: assets/js/sales_orders_datatable.js (GIỮ NGUYÊN CODE GỐC, CHỈ THÊM 3 CỘT MỚI)

// --- Hàm Khởi tạo DataTables (SERVER-SIDE) ---
function initializeSalesOrderDataTable() {
    console.log("Initializing Server-Side DataTables for Sales Orders...");
    if ($.fn.dataTable.isDataTable(orderTableElement)) {
        orderTableElement.DataTable().destroy();
        console.log("Existing DataTable instance destroyed.");
    }

    try {
        salesOrderDataTable = orderTableElement.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
  url: 'process/sales_order_serverside.php',
  type: 'POST',
  data: function (d) {
    // (ĐÃ BỎ) đọc .column-filter-input vì đã gộp thành 1 ô
    d.unified_filter   = $('#unifiedFilter').val();       // ô gộp
    d.delivery_status  = $('#deliveryStatusFilter').val(); // '', not_delivered, delivered
    d.filter_year      = $('#filterYear').val();
    d.filter_month     = $('#filterMonth').val();
    console.log("DataTables AJAX params:", d);
    return d;
  },
  error: function (jqXHR, textStatus, errorThrown) {
    console.error("DataTables Server-Side AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
    let errorMsg = LANG['server_error_loading_list'] || 'Lỗi máy chủ khi tải danh sách.';
    try {
      const response = JSON.parse(jqXHR.responseText);
      if (response && response.message) errorMsg = response.message;
    } catch (e) {}
    showUserMessage(errorMsg, 'error');
    // >>> dùng colspan động theo số cột hiện tại
    const colCount = orderTableElement.find('thead th').length || 11;
    orderTableElement.find('tbody').html(
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
                { // 1: Số Đơn Hàng
                    title: LANG['order_number'] || 'Số ĐH',
                    data: 'order_number', 
                    name: 'so.order_number', 
                    className: 'dt-body-left' 
                },
                { // 2: Ngày Đơn Hàng
                    title: LANG['order_date'] || 'Ngày ĐH',
                    data: 'order_date_formatted', 
                    name: 'so.order_date', 
                    className: 'dt-body-center col-date' 
                },
                { // 3: Nhà Cung Cấp
                    title: LANG['supplier'] || 'Nhà Cung Cấp',
                    data: 'supplier_name', 
                    name: 'p.name', 
                    className: 'dt-body-left col-supplier' 
                },
                { // 4: BG ĐANG LIÊN KẾT
                    title: LANG['linked_sales_quote'] || 'BG đang liên kết',
                    data: 'linked_quote_number', 
                    name: 'linked_quote_number', 
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
                { // 5: Tổng Tiền
                    title: LANG['grand_total'] || 'Tổng tiền',
                    data: 'grand_total_formatted', 
                    name: 'so.grand_total', 
                    className: 'dt-body-right', 
                    orderable: true, 
                    searchable: false,
                    render: function(data, type, row) {
                        return (data || '0') + ' <small>' + (row.currency || (typeof DEFAULT_CURRENCY !== 'undefined' ? DEFAULT_CURRENCY : 'VND')) + '</small>';
                    }
                },
                { // 6: Khách hàng
                    title: LANG['customer'] || 'Khách hàng',
                    data: 'customer_name',
                    name: 'customer_name', // dùng để filter trên server
                    className: 'dt-body-left'
                },

                { // 7: Cột Tài xế
                    title: 'Tài xế',
                    data: 'driver_name',
                    name: 'driver_name',
                    orderable: true,
                    searchable: false
                },
                { // Ngày giao hàng
                    title: 'Ngày giao',
                    data: 'expected_delivery_date_formatted',
                    name: 'so.expected_delivery_date',
                    className: 'dt-body-center col-expected_delivery_date'
                },
                { // 8: Cột Tiền xe
                    title: 'Tiền xe',
                    data: 'tien_xe',
                    name: 'tien_xe',
                    orderable: false,
                    searchable: false
                },
                { // 9: Cột Ghi chú
                    title: 'Ghi chú',
                    data: 'ghi_chu_order',
                    name: 'ghi_chu_order',
                    orderable: false,
                    searchable: false
                },
                // =======================================================

                { // 10: HÀNH ĐỘNG
    title: LANG['actions'] || 'Hành động',
    data: null, 
    orderable: false,
    searchable: false,
    className: 'text-end action-cell dt-nowrap col-actions', 
    render: function(data, type, row) {
        const orderId = row.id; 
        const orderNumber = escapeHtml(row.order_number || '');
        const safeOrderNumber = (typeof sanitizeFilename === 'function' 
                                 ? sanitizeFilename(row.order_number) 
                                 : String(row.order_number || '').replace(/[^a-z0-9_.-]/gi, '-'));
        const pdfPath = `${PROJECT_BASE_URL}pdf/${safeOrderNumber}.pdf`; 

        let actionsHtml = '';
        actionsHtml += `<button class="btn btn-sm btn-outline-secondary btn-view-pdf ms-1" 
                    data-id="${orderId}" 
                    data-order-number="${orderNumber}" 
                    data-pdf-path="${escapeHtml(pdfPath)}"
                    title="${LANG['view_pdf'] || 'Xem PDF'}">
                    <i class="bi bi-file-earmark-pdf"></i>
                </button>`;
        actionsHtml += `<button class="btn btn-sm btn-outline-primary btn-send-email ms-1" data-id="${orderId}" data-order-number="${orderNumber}" data-pdf-url="${escapeHtml(pdfPath)}" data-type="sales_order" title="${LANG['send_email'] || 'Gửi Email'}"><i class="bi bi-envelope"></i></button>`;

        actionsHtml += `<button class="btn btn-sm btn-outline-warning btn-edit-document ms-1" data-id="${orderId}" title="${LANG['edit'] || 'Sửa'}"><i class="bi bi-pencil-square"></i></button>`;

        actionsHtml += `<button class="btn btn-sm btn-outline-danger btn-delete-document ms-1" data-id="${orderId}" data-number="${orderNumber}" title="${LANG['delete'] || 'Xóa'}"><i class="bi bi-trash"></i></button>`;

        actionsHtml += `<button class="btn btn-sm btn-outline-info btn-view-order-logs ms-1" data-order-id="${orderId}" data-order-number="${orderNumber}" title="${LANG['view_email_logs'] || 'Xem LS Email'}"><i class="bi bi-mailbox"></i></button>`;

        actionsHtml += `<button class="btn btn-sm btn-outline-success btn-generate-letter ms-1" data-id="${orderId}" title="Tạo giấy giới thiệu"><i class="bi bi-file-earmark-arrow-down"></i></button>`;

        return `<div class="btn-group" role="group">${actionsHtml}</div>`;
    }
}
            ],
            
            order: [[1, 'desc']],
            language: {
                url: (typeof LANG !== 'undefined' && LANG.language === 'vi') ? `${PROJECT_BASE_URL}lang/vi.json` : `${PROJECT_BASE_URL}lang/en-GB.json`,
            },
            paging: true,
            lengthChange: true,
            lengthMenu: [[25, 50, -1], [25, 50, "Tất cả"]],
            searching: false, // Tắt search chung, dùng filter riêng
            info: true,
            autoWidth: false,
            responsive: false,
            // stateSave: true, stateDuration: 3600
        });
        
    } catch (e) {
        console.error("Error initializing DataTables:", e);
        showUserMessage(LANG['error_initializing_datatable'] || 'Lỗi khi khởi tạo bảng.', 'error');
    }
    
    
}
$(document).on('click', '.btn-view-pdf', function () {
    const btn = $(this);
    const orderId = btn.data('id');
    const orderNumber = btn.data('order-number');
    const pdfPath = btn.data('pdf-path');
    const showSignature = false;
    const type = 'order';

    const originalHtml = btn.html(); // Lưu nội dung nút gốc
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
            showUserMessage("Đang tạo file PDF, vui lòng chờ...", "info");

            $.ajax({
                url: `${PROJECT_BASE_URL}process/export_pdf.php?id=${orderId}&show_signature=${showSignature}&type=${type}`,
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.pdf_web_path) {
                        window.open(response.pdf_web_path, '_blank');
                        showUserMessage("Tạo PDF thành công!", "success");
                    } else {
                        showUserMessage('Không thể tạo PDF: ' + (response.message || 'Lỗi không xác định.'), 'error');
                    }
                },
                error: function (xhr) {
                    console.error("Lỗi khi tạo PDF:", xhr.responseText);
                    showUserMessage('Lỗi máy chủ khi tạo file PDF.', 'error');
                },
                complete: function () {
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
        }
    });
});


$(document).on('click', '.btn-generate-letter', function () {
    const orderId = $(this).data('id');
    const url = `${PROJECT_BASE_URL}introduction_letter_form.php?id=${orderId}`;
    window.open(url, '_blank');
});
// Khi click vào "Chưa giao" thì biến thành input date
$(document).on('click', '.expected-date-placeholder', function () {
    console.log('👉 Click vào Chưa giao');

    const orderId = $(this).data('id');
    const today = new Date().toISOString().split('T')[0];

    const input = $('<input>', {
        type: 'date',
        class: 'form-control form-control-sm expected-date-input border-success text-success',
        'data-id': orderId,
        value: today
    });

    $(this).replaceWith(input);
    input.trigger('focus');
});

// Khi thay đổi ngày hoặc xóa hết
$(document).on('change', '.expected-date-input', function () {
    const orderId = $(this).data('id');
    const newDate = $(this).val();

    if (!newDate) {
        // Nếu xóa trắng, đổi lại thành "Chưa giao"
        const placeholder = $(`
            <div class="expected-date-placeholder text-danger fst-italic" data-id="${orderId}" style="cursor:pointer;">Chưa giao</div>
        `);
        $(this).replaceWith(placeholder);
        return;
    }

    // Gửi AJAX nếu có giá trị ngày
    $.post('process/update_delivery_date.php', {
        order_id: orderId,
        delivery_date: newDate
    }, function (response) {
        if (response.success) {
            showUserMessage(response.message || 'Đã lưu ngày giao hàng.', 'success');
        } else {
            showUserMessage(response.message || 'Lỗi khi lưu ngày.', 'error');
        }
    }, 'json').fail(function (jqXHR) {
        showUserMessage('Lỗi máy chủ: ' + jqXHR.responseText, 'error');
    });
});
$(document).on('blur', '.expected-date-input', function () {
    const orderId = $(this).data('id');
    const newDate = $(this).val();

    if (!newDate) {
        const placeholder = $(`
            <div class="expected-date-placeholder text-danger fst-italic" data-id="${orderId}" style="cursor:pointer;">Chưa giao</div>
        `);
        $(this).replaceWith(placeholder);

        // Gửi AJAX để cập nhật thành null hoặc "Chưa giao"
        $.post('process/update_delivery_date.php', {
            order_id: orderId,
            delivery_date: '' // hoặc null
        }, function (response) {
            if (response.success) {
                showUserMessage(response.message || 'Đã cập nhật trạng thái "Chưa giao".', 'success');
            } else {
                showUserMessage(response.message || 'Lỗi khi cập nhật ngày giao.', 'error');
            }
        }, 'json').fail(function (jqXHR) {
            showUserMessage('Lỗi máy chủ: ' + jqXHR.responseText, 'error');
        });
    }
});
$(document).ready(function () {
    const $realScroll = $('#scroll-wrapper');
    const $fakeScroll = $('#sticky-scroll');
    const $fakeTrack = $('#scroll-fake-track');
    const $dataTable = $('#sales-orders-table');

    function updateFakeScrollbarWidth() {
        const tableWidth = $dataTable.outerWidth(); // chiều rộng bảng
        $fakeTrack.width(tableWidth);
    }

    // Đồng bộ theo tỉ lệ %
    function syncScrollPercent(from, to) {
        const percent = from.scrollLeft / (from.scrollWidth - from.clientWidth);
        to.scrollLeft = percent * (to.scrollWidth - to.clientWidth);
    }

    $fakeScroll.on('scroll', function () {
        syncScrollPercent(this, $realScroll[0]);
    });

    $realScroll.on('scroll', function () {
        syncScrollPercent(this, $fakeScroll[0]);
    });

    updateFakeScrollbarWidth();

    // Khi resize hoặc vẽ lại bảng
    $(window).on('resize', updateFakeScrollbarWidth);
    $dataTable.on('draw.dt', updateFakeScrollbarWidth);
    
});
// Debounce ngắn
function debounce(fn, delay) {
  let t; return function(){ clearTimeout(t); const a=arguments,c=this; t=setTimeout(()=>fn.apply(c,a), delay); };
}

// Thay cho item-details-filter và các column filters cũ
$(document).on('keyup', '#unifiedFilter', debounce(function(){
  salesOrderDataTable && salesOrderDataTable.draw();
}, 300));

$(document).on('change', '#deliveryStatusFilter, #filterYear, #filterMonth', function(){
  salesOrderDataTable && salesOrderDataTable.draw();
});

// Nút reset
$(document).on('click', '#reset-filters-sales-orders-table', function(e){
  e.preventDefault();
  $('#unifiedFilter').val('');
  $('#deliveryStatusFilter').val('');
  $('#filterYear').val('');
  $('#filterMonth').val('');
  salesOrderDataTable && salesOrderDataTable.draw();
});
