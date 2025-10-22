// File: assets/js/sq_events.js

function setupEventListeners() {
    console.log("setupEventListeners function started for " + APP_CONTEXT.type); // APP_CONTEXT từ sq_config.js

    // --- Listener cho nút Tạo Mới Báo Giá ---
    $('#btn-create-new-quote').on('click', function () { // ID cho nút tạo báo giá
        resetQuoteForm(); // resetQuoteForm từ sq_form.js
        if (quoteFormCard) quoteFormCard.slideDown(); // Đảm bảo quoteFormCard đã được gán
        if (quoteListTitle) quoteListTitle.hide();
        if (quoteFormCard) $('html, body').animate({ scrollTop: quoteFormCard.offset().top - 20 }, 300);
        $('#partner_autocomplete').focus();
    });

    // --- Listener cho nút Hủy Form Báo Giá ---
    $('#btn-cancel-quote-form').on('click', function () { // ID cho nút hủy báo giá
        if (quoteFormCard) quoteFormCard.slideUp(() => resetQuoteForm());
        if (quoteListTitle) quoteListTitle.show();
    });

    // --- Listener cho nút Tạo Số Báo Giá Tự Động ---
    $('#btn-generate-quote-number').on('click', function () {
    console.log(">>> Listener #btn-generate-quote-number clicked!");
    const button = $(this);
    button.prop('disabled', true);
    $.ajax({
        url: AJAX_URL.sales_quote,
        type: 'GET',
        data: { action: 'generate_quote_number' },
        dataType: 'json',
        success: function (response) {
            console.log(">>> Generate Quote # AJAX success response:", response);
            if (response.success && response.quote_number) {
                $('#quote_number')
                    .val(response.quote_number)
                    .removeClass('is-invalid')
                    .removeAttr('readonly') // Bỏ thuộc tính readonly để cho phép chỉnh sửa
                    .prop('disabled', false) // Bỏ thuộc tính disabled (phòng ngừa)
                    .closest('.input-group')
                    .find('.invalid-feedback').text('');
                console.log(">>> After setting: readonly =", $('#quote_number').prop('readonly')); // Debug
                showUserMessage(response.message || (LANG['number_generated'] || 'Đã tạo số báo giá.'), 'success');
            } else {
                console.error("Error generating quote number:", response.message);
                showUserMessage(response.message || (LANG['error_generating_number'] || 'Lỗi tạo số báo giá.'), 'error');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error(">>> AJAX Generate Quote # Error:", textStatus, errorThrown);
            showUserMessage(LANG['server_error'] || 'Lỗi máy chủ.', 'error');
        },
        complete: function () {
            button.prop('disabled', false);
        }
    });
});

    // --- Listener khi thay đổi giá trị VAT Rate ---
    $('#summary-vat-rate').on('input change', calculateSummaryTotals); // calculateSummaryTotals từ sq_helpers.js

    // --- Listener cho nút Thêm Dòng Item ---
    $('#add-item-row').on('click', function () {
        addItemRow(); // addItemRow từ sq_form.js
        if (itemTableBody) itemTableBody.find('tr:last .product-autocomplete').focus(); // Đảm bảo itemTableBody đã được gán
    });

    // --- Listener cho nút Xóa Dòng Item (Delegated) ---
    if (itemTableBody && itemTableBody.length) {
        itemTableBody.on('click', '.remove-item-row', function () {
            const row = $(this).closest('tr');
            if (itemTableBody.find('tr').length > 1) {
                row.fadeOut(300, function () { $(this).remove(); updateSTT(); calculateSummaryTotals(); });
            } else { // Reset dòng cuối cùng
                row.find('input[type=text], input[type=number], input[type=hidden]').val('');
                row.find('.product-autocomplete, .product-id, .category-display, input[name$="[category_snapshot]"], .unit-display, input[name$="[unit_snapshot]"]').val('');
                row.find('.quantity').val(1); row.find('.unit-price').val('0');
                row.find('.is-invalid').removeClass('is-invalid'); row.find('.invalid-feedback').text('');
                calculateLineTotal(row); // calculateLineTotal từ sq_helpers.js
            }
        });
    }


    // --- Listener khi thay đổi Số Lượng hoặc Đơn Giá (Delegated) ---
    if (itemTableBody && itemTableBody.length) {
        itemTableBody.on('input change', '.quantity, .unit-price', function () {
            calculateLineTotal($(this).closest('tr'));
        });
    }

    // --- Listener khi thay đổi Tiền Tệ ---
    if (currencySelect && currencySelect.length) { // Đảm bảo currencySelect đã được gán
        currencySelect.on('change', function () {
            const newCurrencyCode = $(this).val(), newCurrencySymbol = (newCurrencyCode === 'VND') ? 'đ' : '$';
            if (itemTableBody) itemTableBody.find('.currency-symbol-unit').text(newCurrencySymbol);
            $('.item-row-template .currency-symbol-unit').text(newCurrencySymbol);
            calculateSummaryTotals();
        });
    }

    // --- Listener cho nút Xóa File PDF Mặc Định (Email Modal) ---
    $(document).on('click', '.btn-remove-default-attachment', function() {
        $('#emailAttachmentDisplay').addClass('d-none');
    });


    // --- Listener cho Submit Form Báo Giá Chính ---
    if (quoteForm && quoteForm.length) { // Đảm bảo quoteForm đã được gán
        quoteForm.on('submit', function (e) {
            e.preventDefault();
            let isValid = true;
            if (formErrorMessageDiv) formErrorMessageDiv.addClass('d-none').text('');
            quoteForm.find('.is-invalid').removeClass('is-invalid');
            quoteForm.find('.invalid-feedback').text('');

            // Client-side Validation (Header)
            if (!$('#partner_id').val()) { $('#partner_autocomplete').addClass('is-invalid').closest('.mb-2').find('.invalid-feedback').text(LANG['customer_required'] || 'Vui lòng chọn khách hàng.').show(); isValid = false; }
            if (!$('#quote_date').val()) { $('#quote_date').addClass('is-invalid').closest('.col-sm-7,.col-md-6').find('.invalid-feedback').text(LANG['quote_date_required'] || 'Vui lòng chọn ngày báo giá.').show(); isValid = false; }
            if (!$('#quote_number').val()) { $('#quote_number').addClass('is-invalid').closest('.input-group').find('.invalid-feedback').text(LANG['quote_number_required'] || 'Vui lòng nhập số báo giá.').show(); isValid = false; }
            const vatRate = parseFloat($('#summary-vat-rate').val());
            if (isNaN(vatRate) || vatRate < 0 || vatRate > 100) { $('#summary-vat-rate').addClass('is-invalid').closest('.input-group').find('.invalid-feedback').text(LANG['invalid_vat_rate'] || 'Thuế VAT không hợp lệ.').show(); isValid = false; }

            // Client-side Validation (Items)
            let hasValidItems = false, hasAnyItemRow = itemTableBody.find('tr').length > 0;
            if (!hasAnyItemRow) { isValid = false; if (formErrorMessageDiv) formErrorMessageDiv.text(LANG['quote_must_have_items'] || 'Báo giá phải có ít nhất một sản phẩm.').removeClass('d-none'); }
            else {
                itemTableBody.find('tr').each(function() {
                    const row = $(this), prodName = row.find('.product-autocomplete'), qty = row.find('.quantity'), price = row.find('.unit-price');
                    let rowIsValid = true, itemHasData = prodName.val() || parseFloat(qty.val()) > 0 || parseFloat(price.val()) > 0;
                    if (itemHasData || itemTableBody.find('tr').length === 1) {
                        if (!prodName.val()) { prodName.addClass('is-invalid').closest('td').find('.invalid-feedback').text(LANG['product_name_required']||'Bắt buộc').show(); isValid = false; rowIsValid = false; }
                        if (isNaN(parseFloat(qty.val())) || parseFloat(qty.val()) <= 0) { qty.addClass('is-invalid').closest('td').find('.invalid-feedback').text(LANG['invalid_quantity']||'SL không hợp lệ').show(); isValid = false; rowIsValid = false; }
                        if (isNaN(parseFloat(price.val())) || parseFloat(price.val()) < 0) { price.addClass('is-invalid').closest('td').find('.invalid-feedback').text(LANG['invalid_unit_price']||'Giá không hợp lệ').show(); isValid = false; rowIsValid = false; }
                        if (rowIsValid && itemHasData) hasValidItems = true;
                    }
                });
                if (hasAnyItemRow && !hasValidItems) { isValid = false; if (formErrorMessageDiv && formErrorMessageDiv.hasClass('d-none')) formErrorMessageDiv.text(LANG['quote_must_have_valid_items']||'Báo giá phải có sản phẩm hợp lệ.').removeClass('d-none');}
            }

            
            const quoteDataPayload = { // Đổi tên biến để tránh nhầm lẫn với biến salesQuoteDataTable
                quote_id: $('#quote_id').val() || null,
                partner_id: $('#partner_id').val(), // customer_id ở backend
                quote_date: $('#quote_date').val(),
                quote_number: $('#quote_number').val(),
                currency: currencySelect.val(),
                notes: $('#notes').val(),
                vat_rate: $('#summary-vat-rate').val(),
                status: $('#quote_status_select').val() || 'draft', // Thêm trường status
                items: []
            };
            itemTableBody.find('tr').each(function(){
                const row=$(this), qtyVal=parseFloat(row.find('.quantity').val()), priceVal=parseFloat(row.find('.unit-price').val()), prodNameVal=row.find('.product-autocomplete').val();
                if(prodNameVal && !isNaN(qtyVal) && qtyVal > 0 && !isNaN(priceVal) && priceVal >= 0){
                    quoteDataPayload.items.push({
                        detail_id: row.find('input[name$="[detail_id]"]').val() || null,
                        product_id: row.find('.product-id').val() || null,
                        product_name_snapshot: prodNameVal,
                        category_snapshot: row.find('input[name$="[category_snapshot]"]').val(),
                        unit_snapshot: row.find('input[name$="[unit_snapshot]"]').val(),
                        quantity: qtyVal, unit_price: priceVal
                    });
                }
            });

            if (saveButton) saveButton.prop('disabled',true); if (saveButtonText) saveButtonText.hide(); if (saveButtonSpinner) saveButtonSpinner.removeClass('d-none');
            const action = quoteDataPayload.quote_id ? 'edit' : 'add';

            $.ajax({
                url: AJAX_URL.sales_quote + '?action=' + action, // Endpoint cho báo giá
                type: 'POST', contentType: 'application/json', data: JSON.stringify(quoteDataPayload), dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        let savedQuoteId = response.quote_id || (response.data && response.data.id) || quoteDataPayload.quote_id;
                        showUserMessage(response.message || LANG['save_success'] || 'Lưu báo giá thành công!', 'success');
                        if (quoteFormCard) quoteFormCard.slideUp(() => { resetQuoteForm(); window.scrollTo({top:0, behavior:'smooth'}); });
                        if (quoteListTitle) quoteListTitle.show();
                        if (salesQuoteDataTable) salesQuoteDataTable.draw(false);

                        if (savedQuoteId) { // Mở PDF sau khi lưu
                            const sigVisible = $('#buyer-signature').is(':visible');
                            $.ajax({
                                url: `${PROJECT_BASE_URL}process/export_pdf.php?id=${savedQuoteId}&show_signature=${sigVisible}&type=quote`,
                                type: 'GET', dataType: 'json',
                                success: (pdfRes) => {
                                    if (pdfRes.success && pdfRes.pdf_web_path) { window.open(pdfRes.pdf_web_path, '_blank')?.focus(); }
                                    else { showUserMessage('Lỗi xuất PDF báo giá: ' + escapeHtml(pdfRes.message || 'Không rõ'), 'error'); }
                                },
                                error: () => showUserMessage('Lỗi server khi tạo PDF báo giá.', 'error')
                            });
                        }
                    } else { // response.success === false
                        showUserMessage(response.message || LANG['save_error'] || 'Lỗi lưu báo giá.', 'error');
                        if(response.errors) handleFormValidationErrors(response.errors);
                        if(response.suggestion && $('#quote_number').length) {
                            $('#quote_number').val(response.suggestion).removeClass('is-invalid').closest('.input-group').find('.invalid-feedback').text('');
                            showUserMessage((LANG['suggestion_applied'] || "Đã áp dụng gợi ý số báo giá."), 'info');
                        }
                    }
                },
                error: (xhr) => { /* Xử lý lỗi AJAX tương tự sales_orders */ handleFormValidationErrors( (JSON.parse(xhr.responseText || "{}")).errors || {} ); },
                complete: () => { if (saveButton) saveButton.prop('disabled',false); if (saveButtonText) saveButtonText.show(); if (saveButtonSpinner) saveButtonSpinner.addClass('d-none'); }
            });
        });
    }


    // --- Listener cho Input Chọn File Đính Kèm Thêm (Modal Email) ---
    $('#emailExtraAttachments').on('change', function () {
        const files = this.files; selectedExtraAttachments = [];
        const listElement = $('#emailExtraAttachmentsList'); listElement.empty();
        if (files.length > 0) {
            listElement.html('<strong>File đính kèm thêm:</strong>');
            Array.from(files).forEach(file => {
                selectedExtraAttachments.push(file);
                listElement.append(`<div class="d-flex justify-content-between align-items-center border-bottom py-1"><span class="file-name small me-2">${escapeHtml(file.name)}</span><button type="button" class="btn btn-sm btn-outline-danger btn-remove-attachment" data-index="${selectedExtraAttachments.length - 1}"><i class="bi bi-x"></i></button></div>`);
            });
        } else { listElement.html('<span class="text-muted small">Chưa chọn file.</span>'); }
    });

    // --- Listener cho Nút Xóa File Đính Kèm Thêm (Modal Email) ---
    $('#emailExtraAttachmentsList').on('click', '.btn-remove-attachment', function () {
        const fileIndex = $(this).data('index');
        if (fileIndex > -1 && fileIndex < selectedExtraAttachments.length) {
            selectedExtraAttachments.splice(fileIndex, 1);
            const listElement = $('#emailExtraAttachmentsList'); listElement.empty(); // Re-render list
            if (selectedExtraAttachments.length > 0) {
                listElement.html('<strong>File đính kèm thêm:</strong>');
                selectedExtraAttachments.forEach((file, idx) => {
                    listElement.append(`<div class="d-flex justify-content-between align-items-center border-bottom py-1"><span class="file-name small me-2">${escapeHtml(file.name)}</span><button type="button" class="btn btn-sm btn-outline-danger btn-remove-attachment" data-index="${idx}"><i class="bi bi-x"></i></button></div>`);
                });
            } else { listElement.html('<span class="text-muted small">Chưa chọn file.</span>'); }
        }
    });

    // --- Listener cho Submit Form Gửi Email (Modal Email) ---
    $('#sendEmailForm').on('submit', function (e) { // ID form gửi email
        e.preventDefault();
        const $form = $(this), $btn = $form.find('#btnSubmitSendEmail'), $spinner = $btn.find('.spinner-border'); // Chú ý class spinner có thể khác
        const $btnTextNode = $btn.contents().filter(function() { return this.nodeType === 3; });
        const originalBtnText = $btnTextNode.text();

        let emailBodyContent = ''; // Đổi tên biến để tránh nhầm lẫn với #emailBody element
if (ckEditorInstances['emailBody']) {
    emailBodyContent = ckEditorInstances['emailBody'].getData();
} else {
    // Fallback: nếu CKEditor không được khởi tạo, lấy từ textarea gốc
    emailBodyContent = $('#emailBody').val() || ''; // Lấy giá trị từ textarea
    console.warn('CKEditor instance for "emailBody" not found. Got content from textarea directly.');
}
// Bây giờ emailBodyContent chứa nội dung từ CKEditor hoặc textarea
        const docId = $('#sendEmailModal').data('current-document-id');
        const docType = $('#sendEmailModal').data('current-document-type'); // Nên là 'quote'
        const to = trim($('#emailTo').val()), subject = trim($('#emailSubject').val());
        const pdfUrl = $('#emailPdfUrl').val();

        if (!to || !subject || !emailBody || !docId || !docType) { showUserMessage('Vui lòng điền đủ thông tin email.', 'warning'); return; }

        const formData = new FormData();
        formData.append('document_id', docId); formData.append('log_type', docType);
        formData.append('to_email', to); formData.append('subject', subject); formData.append('body', emailBody);
        if ($('#emailAttachmentDisplay').is(':visible') && pdfUrl) formData.append('default_pdf_url', pdfUrl);
        selectedExtraAttachments.forEach(file => formData.append('extra_attachments[]', file, file.name));

        $btn.prop('disabled', true); $btnTextNode.text(' Đang gửi...'); $spinner.removeClass('d-none');
        const emailModalInstance = bootstrap.Modal.getInstance(document.getElementById('sendEmailModal'));
        if (emailModalInstance) emailModalInstance.hide();

        $.ajax({
            url: PROJECT_BASE_URL + 'includes/send_email_custom.php', type: 'POST', data: formData,
            processData: false, contentType: false, dataType: 'json',
            success: function (response) {
                if (response.success && response.log_id && response.log_type) {
                    showUserMessage(response.message || `Yêu cầu gửi email ${APP_CONTEXT.documentName} đã được tiếp nhận.`, 'info');
                    selectedExtraAttachments = []; $('#emailExtraAttachmentsList').html('<span class="text-muted small">Chưa chọn file.</span>'); $('#emailExtraAttachments').val('');
                    startEmailStatusPolling(response.log_id, response.log_type); // startEmailStatusPolling từ sq_email.js
                    if (salesQuoteDataTable) salesQuoteDataTable.draw(false);
                } else { showUserMessage('Lỗi: ' + (response.message || 'Không thể tạo yêu cầu gửi email.'), 'error'); }
            },
            error: (xhr) => showUserMessage('Lỗi máy chủ khi gửi email: ' + xhr.status, 'error'),
            complete: () => { $btn.prop('disabled', false); $btnTextNode.text(originalBtnText); $spinner.addClass('d-none');}
        });
    });


    // --- Listener cho Child Rows (DataTables) ---
    if (typeof quoteTableElement !== 'undefined' && quoteTableElement.length && typeof salesQuoteDataTable !== 'undefined') {
    quoteTableElement.find('tbody').on('click', 'td.details-control', function (event) {
        event.stopPropagation();
        const tr = $(this).closest('tr');
        
        // Kiểm tra xem tr có phải là một phần của DataTable không
        if (!salesQuoteDataTable.row(tr).node()) {
            console.warn("SQ Child Row: Clicked on an invalid row for details-control.");
            return;
        }
        const row = salesQuoteDataTable.row(tr);
        const icon = $(this).find('i');

        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
            icon.removeClass('bi-dash-square text-danger').addClass('bi-plus-square text-success');
        } else {
            const quoteData = row.data(); // Dữ liệu của dòng chính trong DataTable
            if (!quoteData || typeof quoteData.id === 'undefined') { // Kiểm tra quoteData.id
                console.error("SQ Child Row: Missing quoteData or quoteData.id");
                row.child('<div class="p-2 text-danger">' + (LANG['error_missing_quote_id'] || 'Lỗi: Thiếu ID báo giá.') + '</div>').show();
                return;
            }

            row.child('<div class="text-center p-2"><div class="spinner-border spinner-border-sm" role="status"></div> ' + (LANG['loading_details'] || 'Đang tải chi tiết...') + '</div>').show();
            tr.addClass('shown');
            icon.removeClass('bi-plus-square text-success').addClass('bi-dash-square text-danger');

            console.log("SQ Child Row: Fetching details for quote ID:", quoteData.id);
            $.ajax({
                url: AJAX_URL.sales_quote, // Đảm bảo AJAX_URL.sales_quote trỏ đến process/sales_quote_handler.php
                type: 'GET', // Hoặc 'POST' nếu handler của bạn cho action 'get_details' là POST
                data: {
                    action: 'get_details',
                    id: quoteData.id
                },
                dataType: 'json',
                success: function(response) { // Sử dụng function thường để dễ đọc `this` nếu cần (không cần ở đây)
                    console.log("SQ Child Row: AJAX success response:", response);

                    // **QUAN TRỌNG: Kiểm tra response.data.items thay vì response.data.details**
                    // Dựa trên sales_quote_handler.php, action 'get_details' trả về:
                    // $response = ['success' => true, 'data' => ['quote' => $quoteHeader, 'items' => $quoteItems]];
                    if (response && response.success && response.data && Array.isArray(response.data.items)) {
                        // Đảm bảo hàm formatChildRowDetailsSQ (hoặc formatChildRowDetails) được định nghĩa
                        // và có thể xử lý mảng response.data.items cùng với quoteData.currency
                        let formattedContent;
                        if (typeof formatChildRowDetailsSQ === 'function') { // Ưu tiên hàm riêng cho Sales Quote
                            formattedContent = formatChildRowDetailsSQ(response.data.items, quoteData.currency);
                        } else if (typeof formatChildRowDetails === 'function') { // Dùng hàm chung nếu có
                            formattedContent = formatChildRowDetails(response.data.items, quoteData.currency);
                        } else {
                            console.error("SQ Child Row: Formatting function (formatChildRowDetailsSQ or formatChildRowDetails) is not defined.");
                            formattedContent = '<div class="p-2 text-danger">' + (LANG['error_formatting_details'] || 'Lỗi: Hàm định dạng chi tiết không tồn tại.') + '</div>';
                        }
                        row.child(formattedContent).show();
                    } else {
                        let errorMsg = LANG['error_loading_details'] || 'Lỗi tải chi tiết.';
                        if (!response.data || !Array.isArray(response.data.items)) {
                             errorMsg += " " + (LANG['no_item_details_returned_sq'] || 'Không có chi tiết sản phẩm nào được trả về cho báo giá.');
                             console.warn("SQ Child Row: AJAX success but no valid items data in response.data.items.", response);
                        } else if (response && !response.success) {
                            errorMsg = response.message || errorMsg;
                            console.warn("SQ Child Row: AJAX request was not successful.", response);
                        }
                        row.child('<div class="p-2 text-danger">' + escapeHtml(errorMsg) + '</div>').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("SQ Child Row: AJAX Error:", status, error, xhr.responseText);
                    row.child('<div class="p-2 text-danger">' + (LANG['server_error_loading_details'] || 'Lỗi máy chủ khi tải chi tiết.') + '</div>').show();
                }
            });
        }
    });
} else {
    // Thêm log để biết nếu các điều kiện ban đầu không được đáp ứng
    if (typeof quoteTableElement === 'undefined' || !quoteTableElement.length) {
        console.error("SQ Child Row Listener: quoteTableElement is not defined or not found.");
    }
    if (typeof salesQuoteDataTable === 'undefined') {
        console.error("SQ Child Row Listener: salesQuoteDataTable instance is not defined.");
    }
}

    // --- Listener cho Nút Gửi Email (DataTables) ---
    if (quoteTableElement && quoteTableElement.length) {
        quoteTableElement.find('tbody').on('click', '.btn-send-email', function () {
            const $btn = $(this), docId = $btn.data('id'), docNum = String($btn.data('quote-number') || ''), pdf = $btn.data('pdf-url');
            if (!docId || !docNum) { /* alert error */ return; }

            $('#sendEmailModal').data({ 'current-document-id': docId, 'current-document-type': APP_CONTEXT.type, 'current-document-number': docNum });
            $('#sendEmailModal #modal-send-email-quote-number-display').text(`${APP_CONTEXT.documentName} ${docNum}`); // ID title modal cho báo giá

            $.post(PROJECT_BASE_URL + 'includes/get_partner_email.php', { id: docId, type: APP_CONTEXT.type }, (res) => {
                if (res.success) {
                    $('#emailTo').val(res.email || ''); $('#emailCc').val(res.cc_emails || '');
                    $('#emailSubject').val(`${APP_CONTEXT.documentName} STV - ${docNum}`);
                    const bodyDefault = `Kính gửi Quý công ty,\n\nCông ty STV xin gửi ${APP_CONTEXT.documentName} số: ${docNum}.\nVui lòng xem file đính kèm.\n\nTrân trọng!`;
                    $('#emailBody').val(bodyDefault);
                    $('#emailPdfUrl').val(pdf || '');
                    if (pdf) { $('#emailAttachmentLink').text(pdf.substring(pdf.lastIndexOf('/') + 1)).attr('href', pdf); $('#emailAttachmentDisplay').removeClass('d-none').addClass('d-flex'); }
                    else { $('#emailAttachmentDisplay').addClass('d-none'); $('#emailAttachmentLink').text('').attr('href', '#'); }
                    selectedExtraAttachments = []; $('#emailExtraAttachments').val(''); $('#emailExtraAttachmentsList').html('<span class="text-muted small">Chưa chọn file.</span>');
                    const bodyDefaultContent = bodyDefault || ''; // Đảm bảo bodyDefault có giá trị, nếu không thì là chuỗi rỗng
                if (ckEditorInstances['emailBody']) {
                    ckEditorInstances['emailBody'].setData(bodyDefaultContent);
                } else {
                    // Fallback: nếu CKEditor không được khởi tạo, đặt giá trị cho textarea gốc
                    $('#emailBody').val(bodyDefaultContent);
                    console.warn('CKEditor instance for "emailBody" not found. Set default content for textarea directly.');
                }
                bootstrap.Modal.getOrCreateInstance(document.getElementById('sendEmailModal')).show();
                                } else { /* alert error */ }
                            }, 'json').fail(() => { /* alert server error */});
                        });
                    }


    // --- Listener cho Nút Xem Logs Email (DataTables) ---
    if (quoteTableElement && quoteTableElement.length) {
        quoteTableElement.find('tbody').on('click', '.btn-view-quote-logs', function () { // class cho nút log báo giá
            const docId = $(this).data('quote-id'), docNum = $(this).data('quote-number'); // data attributes cho báo giá
            if (!docId) { /* alert error */ return; }
            const modalEl = document.getElementById('viewQuoteEmailLogsModal'); // ID modal log báo giá
            if (!modalEl) { /* alert error */ return; }
            $('#modal-quote-log-number').text(`${APP_CONTEXT.documentName} ${docNum || `ID ${docId}`}`); // ID title modal log báo giá
            $('#quote-email-logs-content').html('<div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div></div>'); // ID div content log báo giá
            $(modalEl).data({ 'current-document-id': docId, 'current-document-type': APP_CONTEXT.type });
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            $.ajax({
                url: PROJECT_BASE_URL + 'process/ajax_email_logs.php', type: 'GET',
                data: { action: 'get_for_document', document_id: docId, log_type: APP_CONTEXT.type },
                dataType: 'json',
                success: (res) => { if (res.success) updateLogModalContent(res.logs); else $('#quote-email-logs-content').html(`<p class="text-danger p-3">Lỗi: ${escapeHtml(res.message)}</p>`); },
                error: () => $('#quote-email-logs-content').html('<p class="text-danger p-3">Lỗi máy chủ.</p>')
            });
        });
    }

    // --- Listener cho Nút Sửa Báo Giá (DataTables) ---
    if (typeof quoteTableElement !== 'undefined' && quoteTableElement.length) {
    quoteTableElement.find('tbody').on('click', '.btn-edit-document', function () {
        const $button = $(this);
        let quoteIdFromData = $button.data('id');

        console.log("SQ Edit Click: Raw data-id from button:", quoteIdFromData, "| Type:", typeof quoteIdFromData);

        let quoteIdToLoad;

        if (quoteIdFromData !== undefined && quoteIdFromData !== null && String(quoteIdFromData).trim() !== "") {
            // Cố gắng chuyển đổi sang số nguyên
            // Nếu quoteIdFromData là boolean false, String(false) là "false", parseInt("false") là NaN.
            quoteIdToLoad = parseInt(String(quoteIdFromData), 10);
        }

        // Kiểm tra cuối cùng: ID phải là một số và lớn hơn 0 (ID thường là số nguyên dương)
        if (quoteIdToLoad !== undefined && !isNaN(quoteIdToLoad) && quoteIdToLoad > 0) {
            console.log("SQ Edit Click: Valid quoteId to load:", quoteIdToLoad);
            if (typeof loadQuoteForEdit === 'function') {
                loadQuoteForEdit(quoteIdToLoad);
            } else {
                console.error("SQ Main: loadQuoteForEdit function is not defined!");
            }
        } else {
            console.error("SQ Edit Click: Invalid or missing quoteId after processing. Original data-id was:", quoteIdFromData, "Parsed as:", quoteIdToLoad);
            if(typeof showUserMessage === 'function') {
                showUserMessage(LANG['error_invalid_quote_id_for_edit'] || 'Không thể sửa: Mã báo giá không hợp lệ hoặc không tìm thấy.', 'error');
            } else {
                alert(LANG['error_invalid_quote_id_for_edit'] || 'Không thể sửa: Mã báo giá không hợp lệ hoặc không tìm thấy.');
            }
        }
    });
}

    // --- Listener cho Nút Xóa Báo Giá (DataTables) ---
    if (quoteTableElement && quoteTableElement.length) {
        quoteTableElement.find('tbody').on('click', '.btn-delete-document', function () {
            const quoteId = $(this).data('id'), quoteNum = $(this).data('number');
            if (!quoteId || !quoteNum) { /* alert error */ return; }
            if (confirm(`Bạn có chắc muốn xóa ${APP_CONTEXT.documentName} ${quoteNum}?`)) {
                $.ajax({
                    url: AJAX_URL.sales_quote, type: 'POST', // Endpoint cho báo giá
                    data: { action: 'delete', id: quoteId }, dataType: 'json',
                    success: (res) => { if (res.success) { showUserMessage(res.message || 'Xóa thành công!', 'success'); if (salesQuoteDataTable) salesQuoteDataTable.draw(false); } else { showUserMessage(res.message || 'Lỗi xóa!', 'error');}},
                    error: () => showUserMessage('Lỗi server khi xóa!', 'error')
                });
            }
        });
    }

    // --- Listener cho Nút Cập Nhật Trạng Thái Báo Giá (DataTables) ---
    if (quoteTableElement && quoteTableElement.length) {
        quoteTableElement.find('tbody').on('click', '.btn-update-status', function() {
            const $button = $(this), quoteId = $button.data('id'), newStatus = $button.data('new-status');
            const quoteNumber = $button.closest('tr').find('td:eq(1)').text(); // Lấy số BG từ cột thứ 2

            if (!quoteId || !newStatus) { /* alert error */ return; }
            const statusText = LANG['status_' + newStatus.toLowerCase()] || newStatus;
            const confirmMsg = (LANG['confirm_update_quote_status_to'] || 'Cập nhật trạng thái của BG %s thành "%s"?').replace('%s', quoteNumber).replace('%s', statusText);

            if (confirm(confirmMsg)) {
                $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
                $.ajax({
                    url: AJAX_URL.sales_quote, type: 'POST',
                    data: { action: 'update_status', id: quoteId, new_status: newStatus }, // Gửi form-data
                    dataType: 'json',
                    success: (res) => { if (res.success) { showUserMessage(res.message || 'Cập nhật thành công!', 'success'); if (salesQuoteDataTable) salesQuoteDataTable.draw(false); } else { showUserMessage(res.message || 'Cập nhật thất bại.', 'error'); $button.prop('disabled', false).html($button.data('original-html') || 'Thử lại'); /* Khôi phục html gốc nếu có */ }}, // Cần lưu html gốc của nút
                    error: () => { showUserMessage('Lỗi server khi cập nhật.', 'error'); $button.prop('disabled', false).html($button.data('original-html') || 'Thử lại'); },
                });
            }
        });
    }

    


    // --- Listener cho Bộ lọc Cột & Chi tiết (DataTables) ---
    $('.column-filter-input, #item-details-filter-input').on('keyup', function () {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => { if (salesQuoteDataTable) salesQuoteDataTable.draw(); }, 500);
    });

    // --- Listener cho nút Reset Filters (DataTables) ---
$('#reset-filters-sales-quotes-table').on('click', function () {
    if (salesQuoteDataTable) {
        $('.column-filter-input, #item-details-filter-input').val('');
        $('#filterYear').val(''); 
        $('#filterMonth').val('');
        salesQuoteDataTable.draw();

        // --- BẮT ĐẦU ĐOẠN CODE MỚI ---
        try {
            console.log('Đang xóa bộ lọc Báo giá đã lưu...');
            localStorage.removeItem('salesQuoteFilterYear');
            localStorage.removeItem('salesQuoteFilterMonth');
            console.log('Đã xóa bộ lọc Báo giá.');
        } catch (e) {
            console.error('Không thể sử dụng localStorage.', e);
        }
        // --- KẾT THÚC ĐOẠN CODE MỚI ---
    }
});

// --- Listener cho bộ lọc Năm và Tháng (DataTables) ---
$('#filterYear, #filterMonth').on('change', function () {
    if (salesQuoteDataTable) {
        salesQuoteDataTable.draw();

        // --- BẮT ĐẦU ĐOẠN CODE MỚI ---
        try {
            const yearValue = $('#filterYear').val();
            const monthValue = $('#filterMonth').val();

            localStorage.setItem('salesQuoteFilterYear', yearValue);
            localStorage.setItem('salesQuoteFilterMonth', monthValue);
            
            console.log('Đã lưu bộ lọc Báo giá: Năm=' + yearValue + ', Tháng=' + monthValue);
        } catch (e) {
            console.error('Không thể sử dụng localStorage.', e);
        }
        // --- KẾT THÚC ĐOẠN CODE MỚI ---
    }
});

    if (toggleSignatureButton && toggleSignatureButton.length) { // Đảm bảo toggleSignatureButton đã được gán
        toggleSignatureButton.on('click', function () {
            if (buyerSignatureImg) buyerSignatureImg.toggle(); // Đảm bảo buyerSignatureImg đã được gán
            const isVisible = buyerSignatureImg && buyerSignatureImg.is(':visible');
            $(this).text(isVisible ? (LANG.hide_signature ?? 'Ẩn chữ ký') : (LANG.show_signature ?? 'Hiện chữ ký'));
            if (webSignatureSrc) localStorage.setItem(signatureLocalStorageKey, isVisible.toString());
        });
    }

    // --- Listener cho Expand/Collapse All Child Rows (DataTables) ---
    $('#expand-collapse-all').on('click', function () { // ID chung
        const $btn = $(this), icon = $btn.find('i'), isExpanding = icon.hasClass('bi-arrows-expand');
        if (salesQuoteDataTable) {
            salesQuoteDataTable.rows().nodes().each(function (rowNode) {
                const row = salesQuoteDataTable.row(rowNode), controlCell = $(rowNode).find('td.details-control');
                if (isExpanding && !row.child.isShown()) controlCell.trigger('click');
                else if (!isExpanding && row.child.isShown()) controlCell.trigger('click');
            });
            if (isExpanding) { icon.removeClass('bi-arrows-expand').addClass('bi-arrows-collapse'); $btn.contents().last().replaceWith(LANG['collapse_all'] ?? 'Thu gọn Tất cả'); }
            else { icon.removeClass('bi-arrows-collapse').addClass('bi-arrows-expand'); $btn.contents().last().replaceWith(LANG['expand_all'] ?? 'Mở rộng Tất cả'); }
        }
    });

    console.log("All quote event listeners set up.");

}
// Thêm vào cuối file assets/js/sq_events.js

// === LOGIC XỬ LÝ CHO NÚT LƯU GHI CHÚ TRONG BẢNG BÁO GIÁ ===
$(document).ready(function() {
    const tableId = '#sales-quotes-table';

    // 1. Sự kiện HIỆN nút Lưu khi người dùng nhập vào ô Ghi chú
    // Sử dụng event delegation '.on()' cho các phần tử được tạo động bởi DataTables
    $(tableId).on('input', '.note-input', function() {
        // Hiện nút Lưu nằm trong cùng div.note-container
        $(this).siblings('.btn-save-note').removeClass('d-none');
    });

    // 2. Sự kiện CLICK nút Lưu để gửi dữ liệu bằng AJAX
    $(tableId).on('click', '.btn-save-note', function(e) {
        e.preventDefault(); // Ngăn hành vi mặc định của button
        
        const saveButton = $(this);
        const noteContainer = saveButton.closest('.note-container');
        const textarea = noteContainer.find('.note-input');
        
        const quoteId = textarea.data('id');
        const noteContent = textarea.val();

        // Vô hiệu hóa nút và hiển thị spinner để người dùng biết đang xử lý
        saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

        $.ajax({
            url: 'process/ajax_update_document.php', // Endpoint chung để cập nhật
            type: 'POST',
            data: {
                id: quoteId,
                type: 'quote', // Quan trọng: xác định loại tài liệu là 'quote'
                field: 'ghi_chu',
                value: noteContent
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Cập nhật lại tooltip để lần sau di chuột vào sẽ thấy nội dung mới
                    noteContainer.attr('data-bs-original-title', noteContent).tooltip('dispose').tooltip();
                    
                    // Thông báo thành công (sử dụng hàm showUserMessage nếu có)
                    if (typeof showUserMessage === 'function') {
                        showUserMessage('Đã lưu ghi chú!', 'success');
                    }

                    // Ẩn nút Lưu đi sau khi thành công
                    saveButton.addClass('d-none');
                } else {
                    // Thông báo lỗi
                    if (typeof showUserMessage === 'function') {
                        showUserMessage('Lỗi: ' + response.message, 'error');
                    } else {
                        alert('Lỗi: ' + response.message);
                    }
                }
            },
            error: function() {
                // Thông báo lỗi kết nối
                if (typeof showUserMessage === 'function') {
                    showUserMessage('Lỗi kết nối máy chủ.', 'error');
                } else {
                    alert('Lỗi kết nối máy chủ.');
                }
            },
            complete: function() {
                // Luôn kích hoạt lại nút và trả lại icon cũ dù thành công hay thất bại
                saveButton.prop('disabled', false).html('<i class="bi bi-check-lg"></i>');
            }
        });
    });
});