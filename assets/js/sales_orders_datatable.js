// File: assets/js/sales_orders_datatable.js

// --- Hàm Khởi tạo DataTables (SERVER-SIDE) ---
function initializeSalesOrderDataTable() {
    devLog("Initializing Server-Side DataTables for Sales Orders...");
    if ($.fn.dataTable.isDataTable(orderTableElement)) {
        orderTableElement.DataTable().destroy();
        devLog("Existing DataTable instance destroyed.");
    }

    try {
        salesOrderDataTable = orderTableElement.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'process/sales_order_serverside.php',
                type: 'POST',
                data: function (d) {
                    d.unified_filter   = $('#unifiedFilter').val();
                    d.delivery_status  = $('#deliveryStatusFilter').val();
                    d.filter_year      = $('#filterYear').val();
                    d.filter_month     = $('#filterMonth').val();
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
                    const colCount = orderTableElement.find('thead th').length || 10;
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
                { // 1: Checkbox for selection
                    className: 'dt-body-center',
                    orderable: false,
                    data: null,
                    render: function(data, type, row) {
                        const tripInfo = row.assigned_trip_number ? `data-trip="${row.assigned_trip_number}"` : '';
                        const warningIcon = row.assigned_trip_number ? ` <i class="bi bi-exclamation-triangle-fill text-warning" title="Đã có trong chuyến: ${row.assigned_trip_number}"></i>` : '';
                        return `<div class="d-flex align-items-center justify-content-center">
                                    <input type="checkbox" class="form-check-input order-select-checkbox" value="${row.id}" ${tripInfo}>
                                    ${warningIcon}
                                </div>`;
                    },
                    width: "50px"
                },
                { // 2: Số Đơn Hàng
                    title: LANG['order_number'] || 'Số ĐH',
                    data: 'order_number', 
                    name: 'so.order_number', 
                    className: 'dt-body-left' 
                },
                { // 3: Ngày Đơn Hàng
                    title: LANG['order_date'] || 'Ngày ĐH',
                    data: 'order_date_formatted', 
                    name: 'so.order_date', 
                    className: 'dt-body-center col-date' 
                },
                { // 4: Nhà Cung Cấp
                    title: LANG['supplier'] || 'Nhà Cung Cấp',
                    data: 'supplier_name', 
                    name: 'p.name', 
                    className: 'dt-body-left col-supplier' 
                },
                { // 5: BG ĐANG LIÊN KẾT
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
                { // 6: Tổng Tiền
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
                { // 7: Khách hàng
                    title: LANG['customer'] || 'Khách hàng',
                    data: 'customer_name',
                    name: 'customer_name',
                    className: 'dt-body-left'
                },
                { // 8: Ngày giao hàng
                    title: 'Ngày giao',
                    data: 'expected_delivery_date_formatted',
                    name: 'so.expected_delivery_date',
                    className: 'dt-body-center col-expected_delivery_date'
                },
                { // 9: Ghi chú
                    title: 'Ghi chú',
                    data: 'ghi_chu_order',
                    name: 'ghi_chu_order',
                    orderable: false,
                    searchable: false
                },
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
            order: [[2, 'desc']],
            language: {
                url: (typeof LANG !== 'undefined' && LANG.language === 'vi') ? `${PROJECT_BASE_URL}lang/vi.json` : `${PROJECT_BASE_URL}lang/en-GB.json`,
            },
            paging: true,
            lengthChange: true,
            lengthMenu: [[25, 50, -1], [25, 50, "Tất cả"]],
            searching: false,
            info: true,
            autoWidth: false,
            responsive: false,
            drawCallback: function() {
                $('#select-all-orders').prop('checked', false);
            }
        });
    } catch (e) {
        console.error("Error initializing DataTables:", e);
        showUserMessage(LANG['error_initializing_datatable'] || 'Lỗi khi khởi tạo bảng.', 'error');
    }
}

// Logic for "Select All" checkbox
$(document).on('change', '#select-all-orders', function() {
    const isChecked = $(this).prop('checked');
    $('.order-select-checkbox').each(function() {
        $(this).prop('checked', isChecked).trigger('change');
    });
});

// Logic for selection warning
$(document).on('change', '.order-select-checkbox', function(e) {
    const trip = $(this).data('trip');
    if ($(this).prop('checked') && trip) {
        Swal.fire({
            icon: 'warning',
            title: 'Đơn hàng đã có chuyến',
            text: `Đơn hàng ${$(this).closest('tr').find('td:eq(2)').text()} đã được gán cho chuyến xe ${trip}. Bạn có chắc muốn tiếp tục?`,
            showCancelButton: true,
            confirmButtonText: 'Tiếp tục',
            cancelButtonText: 'Bỏ chọn'
        }).then((result) => {
            if (!result.isConfirmed) {
                $(this).prop('checked', false);
                $('#select-all-orders').prop('checked', false);
            }
        });
    }
});

// Logic for "Create Delivery Trip" button
$(document).on('click', '#btn-create-delivery-trip', function() {
    const selectedIds = [];
    $('.order-select-checkbox:checked').each(function() {
        selectedIds.push($(this).val());
    });

    if (selectedIds.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Chưa chọn đơn hàng',
            text: 'Vui lòng chọn ít nhất một đơn hàng để tạo chuyến giao hàng.'
        });
        return;
    }

    // Redirect to delivery_dispatcher.php with selected IDs
    window.location.href = `delivery_dispatcher.php?selected_order_ids=${selectedIds.join(',')}`;
});

$(document).on('click', '.btn-view-pdf', function () {
    const btn = $(this);
    const orderId = btn.data('id');
    const orderNumber = btn.data('order-number');
    const pdfPath = btn.data('pdf-path');
    const showSignature = false;
    const type = 'order';

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
            devLog(`PDF chưa tồn tại: ${pdfPath}. Gọi export_pdf.php để tạo.`);
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

$(document).on('click', '.expected-date-placeholder', function () {
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

$(document).on('change', '.expected-date-input', function () {
    const orderId = $(this).data('id');
    const newDate = $(this).val();
    if (!newDate) {
        const placeholder = $(`<div class="expected-date-placeholder text-danger fst-italic" data-id="${orderId}" style="cursor:pointer;">Chưa giao</div>`);
        $(this).replaceWith(placeholder);
        return;
    }
    $.post('process/update_delivery_date.php', { order_id: orderId, delivery_date: newDate }, function (response) {
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
        const placeholder = $(`<div class="expected-date-placeholder text-danger fst-italic" data-id="${orderId}" style="cursor:pointer;">Chưa giao</div>`);
        $(this).replaceWith(placeholder);
        $.post('process/update_delivery_date.php', { order_id: orderId, delivery_date: '' }, function (response) {
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
        if ($dataTable.length) {
            const tableWidth = $dataTable.outerWidth();
            $fakeTrack.width(tableWidth);
        }
    }

    function syncScrollPercent(from, to) {
        if (from.scrollWidth > from.clientWidth) {
            const percent = from.scrollLeft / (from.scrollWidth - from.clientWidth);
            to.scrollLeft = percent * (to.scrollWidth - to.clientWidth);
        }
    }

    $fakeScroll.on('scroll', function () { syncScrollPercent(this, $realScroll[0]); });
    $realScroll.on('scroll', function () { syncScrollPercent(this, $fakeScroll[0]); });

    updateFakeScrollbarWidth();
    $(window).on('resize', updateFakeScrollbarWidth);
    $dataTable.on('draw.dt', updateFakeScrollbarWidth);
});

function debounce(fn, delay) {
    let t; return function(){ clearTimeout(t); const a=arguments,c=this; t=setTimeout(()=>fn.apply(c,a), delay); };
}

$(document).on('keyup', '#unifiedFilter', debounce(function(){
    salesOrderDataTable && salesOrderDataTable.draw();
}, 300));

$(document).on('change', '#deliveryStatusFilter, #filterYear, #filterMonth', function(){
    salesOrderDataTable && salesOrderDataTable.draw();
});

$(document).on('click', '#reset-filters-sales-orders-table', function(e){
    e.preventDefault();
    $('#unifiedFilter').val('');
    $('#deliveryStatusFilter').val('');
    $('#filterYear').val('');
    $('#filterMonth').val('');
    salesOrderDataTable && salesOrderDataTable.draw();
});
