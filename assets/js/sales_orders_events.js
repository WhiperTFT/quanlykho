// File: assets/js/sales_orders_events.js

function setupEventListeners() {
    console.log("setupEventListeners function started for " + APP_CONTEXT.type);

    // --- Listener cho nút Tạo Mới Đơn Hàng ---
    $('#btn-create-new-order').on('click', function () {
        console.log(">>> Listener #btn-create-new-order clicked!");
        resetOrderForm();
        orderFormCard.slideDown();
        orderListTitle.hide();
        $('html, body').animate({ scrollTop: orderFormCard.offset().top - 0 }, 300);
        $('#partner_autocomplete').focus();
    });

    // --- Listener cho nút Hủy Form ---
    $('#btn-cancel-order-form').on('click', function () {
        console.log(">>> Listener #btn-cancel-order-form clicked!");
        orderFormCard.slideUp(function () {
            resetOrderForm();
        });
        orderListTitle.show();
    });

    // --- Listener cho nút Tạo Số Đơn Hàng Tự Động ---
    $('#btn-generate-order-number').on('click', function () {
    console.log(">>> Listener #btn-generate-order-number clicked!");
    const button = $(this);
    button.prop('disabled', true);
    $.ajax({
        url: AJAX_URL.sales_order,
        type: 'GET',
        data: { action: 'generate_order_number' },
        dataType: 'json',
        success: function (response) {
            console.log(">>> Generate Order # AJAX success response:", response);
            if (response.success && response.order_number) {
                $('#order_number')
                    .val(response.order_number)
                    .removeClass('is-invalid')
                    .prop('readonly', false) // Bỏ thuộc tính readonly để cho phép chỉnh sửa
                    .prop('disabled', false) // Bỏ thuộc tính disabled (nếu có)
                    .closest('.input-group')
                    .find('.invalid-feedback').text('');
                showUserMessage(response.message || (LANG['number_generated'] || 'Đã tạo số đơn hàng.'), 'success');
            } else {
                console.error("Error generating order number:", response.message);
                showUserMessage(response.message || (LANG['error_generating_number'] || 'Lỗi khi tạo số đơn hàng.'), 'error');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error(">>> AJAX Generate Order # Error:", textStatus, errorThrown);
            showUserMessage(LANG['server_error'] || 'Lỗi máy chủ.', 'error');
        },
        complete: function () {
            button.prop('disabled', false);
        }
    });
});

    // --- Listener khi thay đổi giá trị VAT Rate ---
    $('#summary-vat-rate').on('input change', calculateSummaryTotals);

    // --- Listener cho nút Thêm Dòng Item ---
    $('#add-item-row').on('click', function () {
        console.log(">>> Listener #add-item-row clicked!");
        addItemRow();
        itemTableBody.find('tr:last .product-autocomplete').focus();
    });

    // --- Listener cho nút Xóa Dòng Item (Delegated Event) ---
    itemTableBody.on('click', '.remove-item-row', function () {
        console.log(">>> Listener .remove-item-row clicked!");
        const row = $(this).closest('tr');
        if (itemTableBody.find('tr').length > 1) {
            row.fadeOut(300, function () {
                $(this).remove();
                updateSTT();
                calculateSummaryTotals();
            });
        } else {
            console.log("Last row, resetting instead of removing.");
            row.find('input[type=text], input[type=number], input[type=hidden]').val('');
            row.find('.product-autocomplete').val('');
            row.find('.product-id').val('');
            row.find('.category-display').val('');
            row.find('input[name$="[category_snapshot]"]').val('');
            row.find('.unit-display').val('');
            row.find('input[name$="[unit_snapshot]"]').val('');
            row.find('.quantity').val(1);
            row.find('.unit-price').val('0');
            row.find('.is-invalid').removeClass('is-invalid');
            row.find('.invalid-feedback').text('');
            calculateLineTotal(row);
        }
    });

    // --- Listener khi thay đổi Số Lượng hoặc Đơn Giá của Item (Delegated Event) ---
    itemTableBody.on('input change', '.quantity, .unit-price', function () {
        calculateLineTotal($(this).closest('tr'));
    });

    // --- Listener khi thay đổi Tiền Tệ ---
    currencySelect.on('change', function () {
        const newCurrencyCode = $(this).val();
        const newCurrencySymbol = (newCurrencyCode === 'VND') ? 'đ' : '$';
        console.log(`Currency changed: ${newCurrencyCode}, Symbol: ${newCurrencySymbol}`);
        itemTableBody.find('.currency-symbol-unit').text(newCurrencySymbol);
        $('.item-row-template .currency-symbol-unit').text(newCurrencySymbol);
        calculateSummaryTotals();
    });

    // --- Listener cho nút Xóa File PDF Mặc Định (Email Modal) ---
    $(document).on('click', '.btn-remove-default-attachment', function() { // Listener gắn vào document để chắc chắn hoạt động nếu modal được load động
        console.log("Removing default PDF attachment display.");
        $('#emailAttachmentDisplay').addClass('d-none');
    });


    // --- Listener cho Submit Form Chính (#order-form) ---
    orderForm.on('submit', function (e) {
        console.log(">>> Listener orderForm submit event triggered!");
        // const currentAction = $('#current_order_action').val(); // Biến này không thấy được sử dụng
        e.preventDefault();

        let isValid = true;
        formErrorMessageDiv.addClass('d-none').text('');
        orderForm.find('.is-invalid').removeClass('is-invalid');
        orderForm.find('.invalid-feedback').text('');

        // Client-side Validation - Header
        if (!$('#partner_id').val()) {
            $('#partner_autocomplete').addClass('is-invalid').closest('.mb-2').find('.invalid-feedback').text(LANG['supplier_required'] || 'Vui lòng chọn nhà cung cấp.').show();
            isValid = false;
        }
        if (!$('#order_date').val()) {
            $('#order_date').addClass('is-invalid').closest('.col-sm-7,.col-md-6').find('.invalid-feedback').text(LANG['order_date_required'] || 'Vui lòng chọn ngày đặt hàng.').show();
            isValid = false;
        }
        if (!$('#order_number').val()) {
            $('#order_number').addClass('is-invalid').closest('.input-group').find('.invalid-feedback').text(LANG['order_number_required'] || 'Vui lòng nhập số đơn hàng.').show();
            isValid = false;
        }
        const vatRateValue = parseInputNumber($('#summary-vat-rate').val());
        if (isNaN(vatRateValue) || vatRateValue < 0 || vatRateValue > 100) {
            $('#summary-vat-rate').addClass('is-invalid').closest('.input-group').find('.invalid-feedback').text(LANG['invalid_vat_rate'] || 'Thuế VAT không hợp lệ (0-100).').show();
            isValid = false;
        }

        // Client-side Validation - Items
        let hasValidItems = false;
        let hasAnyItemRow = itemTableBody.find('tr').length > 0;

        if (!hasAnyItemRow) {
            isValid = false;
            formErrorMessageDiv.text(LANG['order_must_have_items'] || 'Đơn hàng phải có ít nhất một dòng sản phẩm.').removeClass('d-d-none').removeClass('d-none');
        } else {
            itemTableBody.find('tr').each(function (index) {
                const row = $(this),
                    productNameInput = row.find('.product-autocomplete'),
                    quantityInput = row.find('.quantity'),
                    unitPriceInput = row.find('.unit-price');
                let rowIsValid = true;
                let itemRowHasMeaningfulData = productNameInput.val() || parseInputNumber(quantityInput.val()) > 0 || parseInputNumber(unitPriceInput.val()) > 0;

                if (itemRowHasMeaningfulData || itemTableBody.find('tr').length === 1) { // Validate dòng đầu tiên ngay cả khi trống, hoặc các dòng có dữ liệu
                    if (!productNameInput.val()) {
                        productNameInput.addClass('is-invalid').closest('td').find('.invalid-feedback').text(LANG['product_name_required'] || 'Bắt buộc.').show();
                        isValid = false; rowIsValid = false;
                    }
                    const quantityValue = parseInputNumber(quantityInput.val());
                    if (isNaN(quantityValue) || quantityValue <= 0) {
                        quantityInput.addClass('is-invalid').closest('td').find('.invalid-feedback').text(LANG['invalid_quantity'] || 'Số lượng không hợp lệ (> 0).').show();
                        isValid = false; rowIsValid = false;
                    }
                    const unitPriceValue = parseInputNumber(unitPriceInput.val());
                    if (isNaN(unitPriceValue) || unitPriceValue < 0) {
                        unitPriceInput.addClass('is-invalid').closest('td').find('.invalid-feedback').text(LANG['invalid_unit_price'] || 'Đơn giá không hợp lệ (>= 0).').show();
                        isValid = false; rowIsValid = false;
                    }
                    if (rowIsValid && itemRowHasMeaningfulData) { // Chỉ tính là valid item nếu row đó valid VÀ có dữ liệu
                        hasValidItems = true;
                    }
                }
            });
            if (hasAnyItemRow && !hasValidItems) { // Nếu có dòng nhưng không có dòng nào hợp lệ
                isValid = false;
                if (formErrorMessageDiv.hasClass('d-none')) { // Chỉ hiển thị nếu chưa có lỗi chung nào khác
                    formErrorMessageDiv.text(LANG['order_must_have_valid_items'] || 'Đơn hàng phải có ít nhất một dòng sản phẩm hợp lệ (đủ tên, số lượng > 0, giá >= 0).').removeClass('d-d-none').removeClass('d-none');
                }
            }
        }

        if (!isValid) {
            console.warn("Form validation failed. Scrolling to first error.");
            const firstInvalidElement = orderForm.find('.is-invalid').first();
            if (formErrorMessageDiv.is(':visible')) {
                $('html,body').animate({ scrollTop: formErrorMessageDiv.offset().top - 0 }, 300);
            } else if (firstInvalidElement.length) {
                $('html,body').animate({ scrollTop: firstInvalidElement.offset().top - 0 }, 300);
            }
            return;
        }


        let itemsArray = [];
        let processedRows = new Set();
        itemTableBody.find('tr').each(function () {
            const row = $(this);
            const rowIndex = row.data('index') || row.index();
            if (processedRows.has(rowIndex)) return;

            const quantityValue = parseInputNumber(row.find('.quantity').val());
            const unitPriceValue = parseInputNumber(row.find('.unit-price').val());
            const productNameValue = row.find('.product-autocomplete').val();

            // Chỉ thêm item vào array nếu nó có dữ liệu hợp lệ cơ bản
            if (productNameValue && !isNaN(quantityValue) && quantityValue > 0 && !isNaN(unitPriceValue) && unitPriceValue >= 0) {
                const item = {
                    detail_id: row.find('input[name$="[detail_id]"]').val() || null,
                    product_id: row.find('.product-id').val() || null,
                    product_name_snapshot: productNameValue,
                    category_snapshot: row.find('input[name$="[category_snapshot]"]').val(),
                    unit_snapshot: row.find('input[name$="[unit_snapshot]"]').val(),
                    quantity: quantityValue,
                    unit_price: unitPriceValue
                };
                itemsArray.push(item);
                processedRows.add(rowIndex);
            }
        });
        console.log("Items Array trước khi gửi:", itemsArray);

        const orderData = {
            order_id: $('#order_id').val() || null,
            partner_id: $('#partner_id').val(),
            order_date: $('#order_date').val(),
            order_number: $('#order_number').val(),
            quote_id: $('#order_quote_id_form').val() || null,
            currency: currencySelect.val(),
            notes: $('#notes').val(),
            vat_rate: $('#summary-vat-rate').val(),
            items: itemsArray,
            status: $('#order_status_select').val() || 'draft', // Giả sử có select #order_status_select
        };
        console.log("Submitting Order Data:", orderData);

        saveButton.prop('disabled', true); saveButtonText.hide(); saveButtonSpinner.removeClass('d-none');
        const action = orderData.order_id ? 'edit' : 'add';

        $.ajax({
            url: AJAX_URL.sales_order + '?action=' + action,
            type: 'POST', contentType: 'application/json', data: JSON.stringify(orderData), dataType: 'json',
            success: function (response) {
                console.log(">>> Order Save AJAX success response:", response);
                if (response.success) {
                    let savedOrderId = response.order_id || (response.data && response.data.id) || orderData.order_id;
                    showUserMessage(response.message || LANG['save_success'] || 'Đã lưu đơn hàng thành công!', 'success');
                    orderFormCard.slideUp(function () { resetOrderForm(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
                    orderListTitle.show();
                    if (salesOrderDataTable) salesOrderDataTable.draw(false);

                    if (savedOrderId) {
                        console.log(`Order saved/updated. Order ID: ${savedOrderId}. Now calling export_pdf.php to generate and get PDF path.`);
                        const isSignatureVisibleOnForm = $('#buyer-signature').is(':visible');
                        // Gọi export_pdf.php để tạo PDF trên server và lấy đường dẫn
                        $.ajax({
                            url: `${PROJECT_BASE_URL}process/export_pdf.php?id=${savedOrderId}&show_signature=${isSignatureVisibleOnForm}&type=${APP_CONTEXT.type}`, // type có thể là 'order' hoặc 'quote'
                            type: 'GET', dataType: 'json',
                            success: function(exportResponse) {
                                console.log("Response from export_pdf.php:", exportResponse);
                                if (exportResponse && exportResponse.success && exportResponse.pdf_web_path) {
                                    let actualPdfUrlToOpen = exportResponse.pdf_web_path; // Không cần thêm PROJECT_BASE_URL nữa
                                    console.log(`PDF path received: ${actualPdfUrlToOpen}. Opening this path.`);
                                    try {
                                        const newTab = window.open(actualPdfUrlToOpen, '_blank');
                                        if (newTab) { newTab.focus(); console.log("New tab for static PDF initiated."); }
                                        else { console.warn("window.open returned null for static PDF. Popup might be blocked."); showUserMessage("Trình duyệt có thể đã chặn mở tab PDF. Vui lòng kiểm tra cài đặt popup.", "warning"); }
                                    } catch (e) { console.error("Error attempting to open static PDF tab:", e); showUserMessage("Có lỗi khi cố gắng mở tab PDF.", "error");}
                                } else {
                                    console.warn("export_pdf.php did not return a valid PDF path or was not successful. Response:", exportResponse);
                                    const exportMessage = (exportResponse && exportResponse.message) ? exportResponse.message : 'Không thể lấy đường dẫn PDF sau khi tạo.';
                                    showUserMessage('Lỗi xuất PDF: ' + escapeHtml(exportMessage), 'error');
                                }
                            },
                            error: function(xhrExport) {
                                console.error("Error calling export_pdf.php:", xhrExport.status, xhrExport.responseText);
                                showUserMessage("Có lỗi khi yêu cầu tạo file PDF từ server. " + (xhrExport.responseText ? `Chi tiết: ${xhrExport.responseText.substring(0,100)}...` : ''), "error");
                            }
                        });
                    } else { console.warn("Could not trigger PDF generation: savedOrderId is missing or invalid after save. Response:", response); }
                } else { // response.success === false từ server
                    showUserMessage(response.message || LANG['save_error'] || 'Lỗi khi lưu đơn hàng.', 'error');
                     if(response.errors){
                        handleFormValidationErrors(response.errors);
                    }
                    if(response.suggestion && $('#order_number').length){
                        $('#order_number').val(response.suggestion).removeClass('is-invalid').closest('.input-group').find('.invalid-feedback').text('');
                        showUserMessage((LANG['suggestion_applied'] || "Đã áp dụng gợi ý số đơn hàng."), 'info');
                    }
                }
            },
            error: function (xhr) {
                console.error(">>> AJAX Error saving order:", xhr.status, xhr.responseText);
                let errorMessage = LANG['server_error_saving_order'] || 'Lỗi máy chủ khi lưu đơn hàng.';
                formErrorMessageDiv.removeClass('d-d-none').removeClass('d-none');
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res && res.message) {
                        errorMessage = res.message;
                        if (res.suggestion && $('#order_number').length) {
                            $('#order_number').val(res.suggestion).removeClass('is-invalid').closest('.input-group').find('.invalid-feedback').text('');
                            errorMessage += " " + (LANG['suggestion_applied'] || "Đã áp dụng gợi ý số đơn hàng.");
                        } else if (res.errors) {
                            handleFormValidationErrors(res.errors); errorMessage = LANG['validation_failed'] || 'Validation failed.';
                        } else { formErrorMessageDiv.text(errorMessage).removeClass('d-d-none').removeClass('d-none'); }
                    } else { formErrorMessageDiv.text(errorMessage + ` (Status: ${xhr.status})`).removeClass('d-d-none').removeClass('d-none'); }
                } catch (e) {
                    console.error("Error parsing AJAX error responseText:", e, "Response Text:", xhr.responseText);
                    formErrorMessageDiv.text(errorMessage + ` (Status: ${xhr.status}). Chi tiết phản hồi: ${xhr.responseText.substring(0, 200)}...`).removeClass('d-d-none').removeClass('d-none');
                }
                const firstInvalidElement = orderForm.find('.is-invalid').first();
                if (formErrorMessageDiv.is(':visible')) { $('html,body').animate({ scrollTop: formErrorMessageDiv.offset().top - 0 }, 300); }
                else if (firstInvalidElement.length) { $('html,body').animate({ scrollTop: firstInvalidElement.offset().top - 0 }, 300); }
            },
            complete: function () {
                saveButton.prop('disabled', false); saveButtonText.show(); saveButtonSpinner.addClass('d-none');
                console.log("Order Save AJAX request complete.");
            }
        });
    });
    // --- Listener cho Input Chọn File Đính Kèm Thêm (Modal Email) ---
    $('#emailExtraAttachments').on('change', function () {
        console.log(">>> Listener #emailExtraAttachments change event triggered!");
        const files = this.files;
        selectedExtraAttachments = [];
        $('#emailExtraAttachmentsList').empty();

        if (files.length > 0) {
            $('#emailExtraAttachmentsList').html('<strong>Các file đính kèm thêm:</strong>');
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                selectedExtraAttachments.push(file);
                const fileItemHtml = `
                    <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                        <span class="file-name small me-2">${escapeHtml(file.name)}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-attachment" data-index="${selectedExtraAttachments.length - 1}">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>`;
                $('#emailExtraAttachmentsList').append(fileItemHtml);
            }
        } else {
            $('#emailExtraAttachmentsList').html('<span class="text-muted">Chưa có file đính kèm thêm nào được chọn.</span>');
        }
        console.log("Selected extra attachments:", selectedExtraAttachments);
    });

    // --- Listener cho Nút Xóa File Đính Kèm Thêm (Modal Email) ---
    $('#emailExtraAttachmentsList').on('click', '.btn-remove-attachment', function () {
        console.log(">>> Listener .btn-remove-attachment clicked!");
        const fileIndex = $(this).data('index');
        if (fileIndex > -1 && fileIndex < selectedExtraAttachments.length) {
            selectedExtraAttachments.splice(fileIndex, 1);
            console.log(`File at index ${fileIndex} removed from array.`);
            $('#emailExtraAttachmentsList').empty();
            if (selectedExtraAttachments.length > 0) {
                $('#emailExtraAttachmentsList').html('<strong>Các file đính kèm thêm:</strong>');
                selectedExtraAttachments.forEach((file, index) => {
                    const fileItemHtml = `
                        <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                            <span class="file-name small me-2">${escapeHtml(file.name)}</span>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-attachment" data-index="${index}">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>`;
                    $('#emailExtraAttachmentsList').append(fileItemHtml);
                });
            } else {
                $('#emailExtraAttachmentsList').html('<span class="text-muted">Chưa có file đính kèm thêm nào được chọn.</span>');
            }
            console.log("Updated selected extra attachments:", selectedExtraAttachments);
        } else {
            console.warn(`Attempted to remove attachment with invalid index: ${fileIndex}`);
        }
    });

    // --- Listener cho Submit Form Gửi Email (Modal Email) ---
    $('#sendEmailForm').on('submit', function (e) {
        console.log(">>> Listener #sendEmailForm submit event triggered!");
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('#btnSubmitSendEmail');
        const $spinner = $btn.find('.spinner-border');
        const $btnTextNode = $btn.contents().filter(function() { return this.nodeType === 3; }); // Lấy text node
        const originalBtnText = $btnTextNode.text();


            let emailBodyContent = '';
    if (ckEditorInstances['emailBody']) {
        emailBodyContent = ckEditorInstances['emailBody'].getData();
    } else {
        console.warn("CKEditor 5 instance not found for #emailBody.");
        // Fallback nếu CKEditor không được khởi tạo (tùy chọn)
        const emailBodyElement = document.querySelector('#emailBody');
        if (emailBodyElement) {
        emailBodyContent = emailBodyElement.value || emailBodyElement.innerHTML || ''; // Lấy từ value (textarea) hoặc innerHTML (div)
        } else {
        emailBodyContent = '';
        }
    }

    const documentId = $('#sendEmailModal').data('current-document-id');
    const logType = $('#sendEmailModal').data('current-document-type');
    const documentNumber = $('#sendEmailModal').data('current-document-number') || 'N/A';

    const toEmail = trim($('#emailTo').val() || '');
    const ccEmails = trim($('#emailCc').val() || '');
    const subjectValue = trim($('#emailSubject').val() || '');
    const defaultPdfUrlFromInput = $('#emailPdfUrl').val();
    // bodyContent đã lấy từ CKEditor là emailBodyContent

    let validationMessages = [];
    if (!toEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(toEmail)) validationMessages.push('Email người nhận không hợp lệ.');
    if (!subjectValue) validationMessages.push('Vui lòng nhập Tiêu đề.');
    if (!emailBodyContent) validationMessages.push('Vui lòng nhập Nội dung.'); // Sử dụng emailBodyContent
    if (!documentId || !logType) validationMessages.push('Lỗi nội bộ: Không xác định được thông tin tài liệu.');

    if (validationMessages.length > 0) {
        validationMessages.forEach(msg => showUserMessage(msg, 'warning')); // Giả sử bạn có hàm showUserMessage
        console.warn("Client-side email form validation failed."); return;
    }

    const formData = new FormData();
    formData.append('document_id', documentId);
    formData.append('log_type', logType);
    formData.append('to_email', toEmail);
    formData.append('cc_emails', ccEmails);
    formData.append('subject', subjectValue);
    formData.append('body', emailBodyContent); // Gửi nội dung từ CKEditor

        

        const $emailAttachmentDisplay = $('#emailAttachmentDisplay');
        if ($emailAttachmentDisplay.is(':visible') && defaultPdfUrlFromInput) {
            formData.append('default_pdf_url', defaultPdfUrlFromInput);
        }
        selectedExtraAttachments.forEach((file) => {
            formData.append('extra_attachments[]', file, file.name);
        });
        console.log(`Sending email log creation request with FormData for ${logType} ID: ${documentId}`);

        $btn.prop('disabled', true);
        $btnTextNode.text(' Đang tiếp nhận...'); // Cập nhật text node
        $spinner.removeClass('d-none');

        const emailModalEl = document.getElementById('sendEmailModal');
        const emailModalInstance = bootstrap.Modal.getInstance(emailModalEl);
        if (emailModalInstance) { emailModalInstance.hide(); console.log("#sendEmailModal hidden."); }
        else { console.warn("Email modal instance not found to hide."); }

        $.ajax({
            url: PROJECT_BASE_URL + 'includes/send_email_custom.php', // Hoặc create_email_log.php
            type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
            success: function (response) {
                console.log(">>> send_email_custom.php success response:", response);
                if (response && response.success && response.log_id && response.log_type) {
                    const createdLogId = response.log_id;
                    const createdLogType = response.log_type;
                    const initialMessage = response.message || `Yêu cầu gửi email ${createdLogType === 'quote' ? (window.LANG?.sales_quote_short || 'Báo giá') : (window.LANG?.sales_order_short || 'Đơn hàng')} đã được tiếp nhận.`;
                    showUserMessage(initialMessage, 'info');
                    selectedExtraAttachments = [];
                    $('#emailExtraAttachmentsList').html('<span class="text-muted">Chưa có file đính kèm thêm nào được chọn.</span>');
                    $('#emailExtraAttachments').val('');

                    if (typeof startEmailStatusPolling === 'function') {
                        console.log("Calling startEmailStatusPolling with Log ID:", createdLogId, "and Log Type:", createdLogType);
                        setTimeout(() => { startEmailStatusPolling(createdLogId, createdLogType); }, 1500);
                    } else { console.error("startEmailStatusPolling function is not defined."); showUserMessage('Lỗi nội bộ: Chức năng kiểm tra trạng thái email không khả dụng.', 'error'); }
                    if (salesOrderDataTable) { console.log("Refreshing DataTable after email queue request."); salesOrderDataTable.draw(false); }
                } else {
                    console.warn(">>> send_email_custom.php success: response.success is false or missing data.", response);
                    showUserMessage('Lỗi: ' + (response.message || 'Lỗi khi tạo yêu cầu gửi email.'), 'error');
                }
            },
            error: function (xhr) {
                console.error(">>> AJAX error sending data to create_email_log.php:", xhr.status, xhr.responseText);
                let errorMessage = 'Lỗi máy chủ khi xử lý yêu cầu gửi email.';
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res && res.message) errorMessage = 'Lỗi: ' + res.message;
                    else errorMessage += ` (Status: ${xhr.status})`;
                } catch (e) { console.error("Error parsing AJAX error responseText from create_email_log.php:", e, "Response Text:", xhr.responseText); errorMessage += ` (Status: ${xhr.status}) - Phản hồi không phải JSON.`; }
                showUserMessage(errorMessage, 'error');
                console.error("Error response text:", xhr.responseText);
            },
            complete: function () {
                $btn.prop('disabled', false);
                $btnTextNode.text(originalBtnText); // Khôi phục text gốc
                $spinner.addClass('d-none');
                console.log("create_email_log.php AJAX request complete.");
            }
        });
    });


    // --- Listener cho Child Rows (DataTables) ---
    if (orderTableElement && orderTableElement.length) {
        orderTableElement.find('tbody').on('click', 'td.details-control', function (event) { // Thêm event vào đây
            console.log(">>> Listener td.details-control clicked!");
            event.stopPropagation(); // Ngăn chặn event bubbling

            const tr = $(this).closest('tr');
            const row = salesOrderDataTable.row(tr);
            const icon = $(this).find('i');

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
                icon.removeClass('bi-dash-square text-danger').addClass('bi-plus-square text-success');
                console.log("Child row closed.");
            } else {
                const orderData = row.data();
                if (!orderData || !orderData.id) {
                    console.error("Missing order data for child row. Cannot load details."); return;
                }
                const orderId = orderData.id;
                const currency = orderData.currency;
                row.child('<div class="text-center p-2"><div class="spinner-border spinner-border-sm" role="status"></div> Đang tải chi tiết...</div>').show();
                tr.addClass('shown');
                icon.removeClass('bi-plus-square text-success').addClass('bi-dash-square text-danger');

                $.ajax({
                    url: AJAX_URL.sales_order, type: 'GET', data: { action: 'get_details', id: orderId }, dataType: 'json',
                    success: function (response) {
                        console.log(">>> Child row details AJAX success response:", response);
                        if (response.success && response.data?.details) {
                            row.child(formatChildRowDetails(response.data.details, currency)).show();
                            console.log("Child row details loaded and shown.");
                        } else {
                            console.warn(">>> Child row details AJAX success: response.success is false or no details.", response);
                            row.child('<div class="p-2 text-danger">Lỗi khi tải chi tiết đơn hàng.</div>').show();
                        }
                    },
                    error: function (xhr) {
                        console.error(">>> Child row details AJAX Error:", xhr.status, xhr.responseText);
                        row.child('<div class="p-2 text-danger">Lỗi máy chủ khi tải chi tiết đơn hàng.</div>').show();
                    }
                });
            }
        });
    }


    // --- Listener cho Nút Gửi Email (DataTables) ---
    if (orderTableElement && orderTableElement.length) {
        orderTableElement.find('tbody').on('click', '.btn-send-email', function () {
            console.log(">>> Listener .btn-send-email clicked!");
            const $button = $(this);
            const documentId = $button.data('id');
            const documentNumber = String($button.data('order-number') || '');
            const pdfUrl = $button.data('pdf-url');

            console.log(`Data attributes: ID=${documentId}, DocumentNumber=${documentNumber}, PdfUrl=${pdfUrl}, ContextType=${APP_CONTEXT.type}`);
            if (!documentId || !documentNumber) { alert('Lỗi: Thiếu thông tin cần thiết từ nút gửi email.'); console.error('Send email click: Missing required data attributes.'); return; }

            $('#sendEmailModal').data('current-document-id', documentId);
            $('#sendEmailModal').data('current-document-type', APP_CONTEXT.type);
            $('#sendEmailModal').data('current-document-number', documentNumber);

            const $modalTitleSpan = $('#sendEmailModal').find('#modal-send-email-order-number-display');
            if ($modalTitleSpan.length) $modalTitleSpan.text(`${APP_CONTEXT.documentName} ${documentNumber}`);

            console.log(`Requesting default email info for ${APP_CONTEXT.type} ID: ${documentId}...`);
            $.post(PROJECT_BASE_URL + 'includes/get_partner_email.php', { id: documentId, type: APP_CONTEXT.type }, function (response) {
                console.log(">>> get_partner_email.php success response:", response);
                if (response && response.success) {
                    const emailModalEl = document.getElementById('sendEmailModal');
                    // ... (Lấy các element input trong modal như file gốc) ...
                    $('#emailTo').val(response.email || '');
                    $('#emailCc').val(response.cc_emails || '');
                    $('#emailSubject').val(`${APP_CONTEXT.documentName} STV - ${documentNumber}`);
                    const defaultBody = `Kính gửi Quý công ty,\n\nCông ty STV xin gửi đến Quý công ty ${APP_CONTEXT.documentName} số: ${documentNumber}.\nVui lòng xem chi tiết trong file PDF đính kèm.\n\nThanks and best regard!`;
                    $('#emailBody').val(defaultBody);
                    $('#emailPdfUrl').val(pdfUrl || '');

                    if (pdfUrl) {
                        const filename = pdfUrl.substring(pdfUrl.lastIndexOf('/') + 1);
                        $('#emailAttachmentLink').text(filename).attr('href', pdfUrl);
                        $('#emailAttachmentDisplay').removeClass('d-none').addClass('d-flex');
                    } else {
                        $('#emailAttachmentDisplay').addClass('d-none');
                        $('#emailAttachmentLink').text('').attr('href', '#');
                    }
                    selectedExtraAttachments = []; $('#emailExtraAttachments').val('');
                    $('#emailExtraAttachmentsList').html('<span class="text-muted">Chưa có file đính kèm thêm nào được chọn.</span>');

                    if (typeof tinymce !== 'undefined') {
                        const emailBodyEditor = tinymce.get('emailBody');
                        if (emailBodyEditor) emailBodyEditor.setContent(defaultBody);
                    }
                    const emailModal = bootstrap.Modal.getOrCreateInstance(emailModalEl);
                    emailModal.show();
                } else { alert('Lỗi khi lấy thông tin email đối tác: ' + (response.message || 'Không rõ nguyên nhân.')); }
            }, 'json').fail(function (xhr) {
                console.error(">>> AJAX error fetching partner email:", xhr.status, xhr.responseText);
                alert("Lỗi máy chủ khi lấy thông tin email đối tác. Chi tiết: " + xhr.responseText.substring(0, 200));
            });
        });
    }


    // --- Listener cho Nút Xem Logs Email (DataTables) ---
    if (orderTableElement && orderTableElement.length) {
        orderTableElement.find('tbody').on('click', '.btn-view-order-logs', function () {
            console.log(">>> Listener .btn-view-order-logs clicked!");
            const button = $(this);
            const documentId = button.data('order-id'); // Giữ nguyên 'order-id' cho sales_orders
            const documentNumber = button.data('order-number');
            console.log(`Clicked view logs for ${APP_CONTEXT.type} ID: ${documentId}, Number: ${documentNumber}`);
            if (!documentId) { alert('Lỗi: Không xác định được ID tài liệu.'); console.error('View logs click: Missing document ID.'); return; }

            const modalElement = document.getElementById('viewOrderEmailLogsModal');
            if (!modalElement) { console.error("ERROR: Modal element #viewOrderEmailLogsModal not found."); showUserMessage('Lỗi nội bộ: Không tìm thấy cửa sổ xem lịch sử email.', 'error'); return; }

            const modalTitleSpan = document.getElementById('modal-order-log-number'); // Đổi ID này nếu cần cho sales_quotes
            const modalContentDiv = document.getElementById('order-email-logs-content');
            if (!modalTitleSpan || !modalContentDiv) { console.error('ERROR: Missing inner modal elements for logs.'); showUserMessage('Lỗi nội bộ: Cấu trúc cửa sổ log không đầy đủ.', 'error'); return; }

            const orderLogModal = bootstrap.Modal.getOrCreateInstance(modalElement);
            $(modalElement).data('current-document-id', documentId);
            $(modalElement).data('current-document-type', APP_CONTEXT.type);

            modalTitleSpan.textContent = `${APP_CONTEXT.documentName} ${documentNumber || `ID ${documentId}`}`;
            $(modalContentDiv).html('<div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div> Đang tải...</div>');
            orderLogModal.show();

            $.ajax({
                url: PROJECT_BASE_URL + 'process/ajax_email_logs.php', type: 'GET',
                data: { action: 'get_for_document', document_id: documentId, log_type: APP_CONTEXT.type },
                dataType: 'json',
                success: function (response) {
                    console.log(">>> ajax_email_logs.php success response:", response);
                    if (response && response.success) {
                        updateLogModalContent(response.logs);
                    } else {
                        $(modalContentDiv).html(`<p class="text-center text-danger p-3">Lỗi: ${escapeHtml(response.message || 'Lỗi không xác định.')}</p>`);
                    }
                },
                error: function (xhr) {
                    console.error(">>> AJAX error fetching document logs:", xhr.status, xhr.responseText);
                    $(modalContentDiv).html(`<p class="text-center text-danger p-3">Lỗi máy chủ. Chi tiết: ${xhr.responseText.substring(0, 200)}</p>`);
                }
            });
        });
    }


    // --- Listener cho Nút Sửa Đơn Hàng (DataTables) ---
             if (typeof orderTableElement !== 'undefined' && orderTableElement.length) {
    orderTableElement.find('tbody').on('click', '.btn-edit-document', function () { // Class của nút Sửa ĐH
        const $button = $(this);
        let orderIdFromData = $button.data('id'); 

        console.log("SO Edit Click Listener: Raw data-id from button:", orderIdFromData, "| Type:", typeof orderIdFromData);

        let orderIdToLoad;
        // Kiểm tra kỹ giá trị từ data-id
        if (orderIdFromData !== undefined && orderIdFromData !== null && String(orderIdFromData).trim() !== "") {
            if (typeof orderIdFromData === 'string' && orderIdFromData.toLowerCase() === 'false') {
                orderIdToLoad = false; // Giữ nguyên là false nếu data-id là chuỗi "false"
            } else if (typeof orderIdFromData === 'boolean' && orderIdFromData === false) {
                orderIdToLoad = false; // Giữ nguyên là false nếu data-id là boolean false
            } else {
                orderIdToLoad = parseInt(String(orderIdFromData), 10);
                 if (isNaN(orderIdToLoad)) orderIdToLoad = false; // Nếu parse lỗi, gán là false để khớp với lỗi
            }
        } else {
            orderIdToLoad = false; // Gán là false nếu data-id rỗng hoặc không hợp lệ
        }
        
        console.log("SO Edit Click Listener: orderId being passed to loadOrderForEdit:", orderIdToLoad);

        if (typeof loadOrderForEdit === 'function') {
            loadOrderForEdit(orderIdToLoad, "SO_DataTable_EditButton"); // Truyền callSource
        } else {
            console.error("SO Main: loadOrderForEdit function is not defined!");
        }
    });
}


    // --- Listener cho Nút Xóa Đơn Hàng (DataTables) ---
    if (orderTableElement && orderTableElement.length) {
        orderTableElement.find('tbody').on('click', '.btn-delete-document', function () {
            console.log(">>> Listener .btn-delete-document clicked!");
            const orderId = $(this).data('id');
            const orderNumber = $(this).data('number');
            console.log(`Delete Order ID: ${orderId}, Number: ${orderNumber}`);
            if (!orderId || !orderNumber) { alert('Lỗi: Thiếu thông tin để xóa.'); console.error('Delete click: Missing ID or number.'); return; }

            if (confirm(`Bạn có chắc chắn muốn xóa ${APP_CONTEXT.documentName} ${orderNumber} này không?`)) {
                console.log(`User confirmed delete for ID: ${orderId}`);
                $.ajax({
                    url: AJAX_URL.sales_order, // Hoặc AJAX_URL.sales_quote nếu APP_CONTEXT.type là 'quote'
                    type: 'POST', data: { action: 'delete', id: orderId }, dataType: 'json',
                    success: function (response) {
                        console.log(">>> Delete AJAX success response:", response);
                        if (response.success) {
                            showUserMessage(response.message || (LANG['delete_success'] || 'Đã xóa thành công.'), 'success');
                            if (salesOrderDataTable) salesOrderDataTable.draw(false);
                        } else {
                            console.error(">>> Delete AJAX error: response.success is false.", response);
                            showUserMessage(response.message || (LANG['delete_error'] || 'Lỗi khi xóa.'), 'error');
                        }
                    },
                    error: function (xhr) {
                        console.error(">>> AJAX error deleting:", xhr.status, xhr.responseText);
                        let errMsg = LANG['server_error_deleting_order'] || 'Lỗi máy chủ khi xóa.';
                        try { const res = JSON.parse(xhr.responseText); if (res && res.message) errMsg += '\nChi tiết: ' + res.message; else errMsg += '\nChi tiết: ' + xhr.responseText; }
                        catch (e) { errMsg += '\nChi tiết: ' + xhr.responseText; }
                        showUserMessage(errMsg, 'error');
                    }
                });
            } else { console.log(`User cancelled delete for ID: ${orderId}`); }
        });
    }


    // --- Listener cho Bộ lọc Cột & Chi tiết (DataTables) ---
    $('.column-filter-input, #item-details-filter-input').on('keyup', function (event) {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            if (salesOrderDataTable) {
                console.log("Filter debounce timeout executed. Calling DataTables draw()...");
                salesOrderDataTable.draw();
            }
        }, 500);
    });

    // --- Listener cho nút Reset Filters (DataTables) ---
$('#reset-filters-sales-orders-table').on('click', function () {
    console.log(">>> Listener #reset-filters-sales-orders-table clicked! Resetting filters.");
    if (salesOrderDataTable) {
        $('.column-filter-input, #item-details-filter-input').val('');
        $('#filterYear').val(''); 
        $('#filterMonth').val(''); 
        salesOrderDataTable.draw();

        // --- BẮT ĐẦU ĐOẠN CODE MỚI ---
        try {
            console.log('Đang xóa bộ lọc đã lưu...');
            localStorage.removeItem('salesOrderFilterYear');
            localStorage.removeItem('salesOrderFilterMonth');
            console.log('Đã xóa bộ lọc.');
        } catch (e) {
            console.error('Không thể sử dụng localStorage.', e);
        }
        // --- KẾT THÚC ĐOẠN CODE MỚI ---
    }
});

    // --- Listener cho bộ lọc Năm và Tháng (DataTables) ---
$('#filterYear, #filterMonth').on('change', function () {
    console.log("Year or Month filter changed. Redrawing DataTable.");
    if (salesOrderDataTable) {
        salesOrderDataTable.draw();

        // --- BẮT ĐẦU ĐOẠN CODE MỚI ---
        try {
            const yearValue = $('#filterYear').val();
            const monthValue = $('#filterMonth').val();

            localStorage.setItem('salesOrderFilterYear', yearValue);
            localStorage.setItem('salesOrderFilterMonth', monthValue);
            
            console.log('Đã lưu bộ lọc: Năm=' + yearValue + ', Tháng=' + monthValue);
        } catch (e) {
            console.error('Không thể sử dụng localStorage.', e);
        }
        // --- KẾT THÚC ĐOẠN CODE MỚI ---
    }
});

    // --- Listener cho nút Export PDF và Toggle Signature ---
    $('#btn-download-pdf').on('click', function () {
        console.log(">>> Listener #btn-download-pdf clicked!");
        if (!$(this).prop('disabled')) downloadOrderPDF();
    });

    toggleSignatureButton.on('click', function () {
        console.log(">>> Listener #toggle-signature clicked!");
        buyerSignatureImg.toggle();
        const isVisible = buyerSignatureImg.is(':visible');
        $(this).text(isVisible ? (LANG.hide_signature ?? 'Ẩn chữ ký') : (LANG.show_signature ?? 'Hiện chữ ký'));
        // Lưu trạng thái vào localStorage chỉ khi có ảnh hợp lệ
        if (webSignatureSrc) {
            localStorage.setItem(signatureLocalStorageKey, isVisible.toString());
            console.log(`Signature visibility state saved to localStorage: ${isVisible}`);
        }
    });

    // --- Listener cho Expand/Collapse All Child Rows (DataTables) ---
    $('#expand-collapse-all').on('click', function () {
        console.log(">>> Listener #expand-collapse-all clicked!");
        const button = $(this);
        const icon = button.find('i');
        const isExpanding = icon.hasClass('bi-arrows-expand');

        if (salesOrderDataTable) {
            salesOrderDataTable.rows().nodes().each(function (rowNode, index) {
                const row = salesOrderDataTable.row(rowNode);
                const controlCell = $(rowNode).find('td.details-control');
                if (isExpanding) {
                    if (!row.child.isShown()) controlCell.trigger('click');
                } else {
                    if (row.child.isShown()) controlCell.trigger('click');
                }
            });
            if (isExpanding) {
                icon.removeClass('bi-arrows-expand me-1').addClass('bi-arrows-collapse me-1');
                button.contents().last().replaceWith(LANG['collapse_all'] ?? 'Collapse All');
            } else {
                icon.removeClass('bi-arrows-collapse me-1').addClass('bi-arrows-expand me-1');
                button.contents().last().replaceWith(LANG['expand_all'] ?? 'Expand All');
            }
        } else { console.warn("DataTables instance not initialized."); }
    });

    console.log("All event listeners set up.");
} // End setupEventListeners
$(document).ready(function() {
    const tableId = '#sales-orders-table';
    const tableElement = $(tableId);

    // Hàm chung để lưu dữ liệu qua AJAX
    function updateOrderField(id, field, value, element, callback) {
        $.ajax({
            url: 'process/ajax_update_document.php',
            type: 'POST',
            data: { id: id, type: 'order', field: field, value: value },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (typeof showUserMessage === 'function') {
                        // Không hiển thị thông báo thành công cho mỗi lần lưu để tránh làm phiền
                        // showUserMessage('Đã lưu!', 'success');
                    }
                    if (callback) callback(true);
                } else {
                    if (typeof showUserMessage === 'function') {
                        showUserMessage('Lỗi: ' + response.message, 'error');
                    }
                    if (callback) callback(false);
                }
            },
            error: function() {
                if (typeof showUserMessage === 'function') {
                    showUserMessage('Lỗi kết nối máy chủ.', 'error');
                }
                if (callback) callback(false);
            }
        });
    }

    // =============================================================
    // === LOGIC CHO CỘT GHI CHÚ ===
    // =============================================================
    tableElement.on('input', '.note-input', function() {
        $(this).siblings('.btn-save-note').removeClass('d-none');
    });

    tableElement.on('click', '.btn-save-note', function(e) {
        e.preventDefault();
        const saveButton = $(this);
        const textarea = saveButton.siblings('.note-input');
        saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        
        updateOrderField(textarea.data('id'), 'ghi_chu', textarea.val(), textarea, function(success) {
            if (success) {
                if (typeof showUserMessage === 'function') showUserMessage('Đã lưu ghi chú!', 'success');
                saveButton.addClass('d-none');
            }
            saveButton.prop('disabled', false).html('<i class="bi bi-check-lg"></i>');
        });
    });

    // =============================================================
    // === LOGIC TỰ ĐỘNG LƯU "TIỀN XE" KHI RỜI Ô INPUT ===
    // =============================================================
    tableElement.on('blur', '.shipping-cost-input', function() {
        const input = $(this);
        const id = input.data('id');
        const value = input.val().replace(/\./g, ''); // Xóa dấu chấm phân cách
        updateOrderField(id, 'tien_xe', value, input);
    });
    
    // Tự động format số khi nhập
    tableElement.on('input', '.shipping-cost-input', function(e) {
        var $this = $(this);
        var num = $this.val().replace(/\./g, '');
        if (!isNaN(num)) {
           $this.val(num.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'));
        }
    });

    // =============================================================
    // === LOGIC AUTOCOMPLETE CHO CỘT TÀI XẾ ===
    // =============================================================
    // Phải dùng 'draw.dt' để gắn lại event cho các trang sau của bảng
    tableElement.on('draw.dt', function() {
        if (typeof $.fn.autocomplete === 'undefined') {
            console.error("jQuery UI Autocomplete is not loaded!");
            return;
        }

        $('.driver-autocomplete').each(function() {
            if (typeof $(this).autocomplete("instance") !== "undefined") {
                $(this).autocomplete("destroy");
            }
        });

        $('.driver-autocomplete').autocomplete({
            source: function(request, response) {
                console.log("Autocomplete: Đang gửi yêu cầu tìm kiếm cho từ khóa ->", request.term);
                $.ajax({
                    url: 'process/ajax_get_drivers.php',
                    dataType: "json",
                    data: {
                        term: request.term
                    },
                    success: function(data) {
                        console.log("Autocomplete: Đã nhận được dữ liệu từ server ->", data);
                        if (!data || !data.length) {
                            console.log("Autocomplete: Không tìm thấy kết quả nào.");
                        }
                        response(data); // Trả dữ liệu cho widget autocomplete
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("Autocomplete LỖI AJAX:", textStatus, errorThrown);
                        console.error("Nội dung phản hồi từ Server:", jqXHR.responseText);
                    }
                });
            },
            minLength: 1,
            select: function(event, ui) {
                event.preventDefault();
                const input = $(this);
                const orderId = input.data('id');
                input.val(ui.item.value);
                const driverContainer = input.closest('.driver-container');
                driverContainer.attr('data-bs-original-title', ui.item.details).tooltip('dispose').tooltip();
                console.log(`Autocomplete: Đã chọn tài xế ID ${ui.item.id}. Đang lưu cho đơn hàng ID ${orderId}.`);
                updateOrderField(orderId, 'driver_id', ui.item.id, input);
            },
            change: function(event, ui) {
                 if (!ui.item && $(this).val() === '') {
                    const orderId = $(this).data('id');
                    console.log(`Autocomplete: Đã xóa trắng ô tài xế. Đang xóa tài xế khỏi đơn hàng ID ${orderId}.`);
                    updateOrderField(orderId, 'driver_id', null, $(this));
                 }
            }
        });

        $('[data-bs-toggle="tooltip"]').tooltip();
    });
    
});
// ===================================================================================
// BẮT SỰ KIỆN "CHANGE" CHO DROPDOWN TRẠNG THÁI TRỰC TIẾP TRÊN BẢNG
// ===================================================================================
$(document).on('change', '.status-dropdown-select', function() {
    const selectElement = $(this);
    const orderId = selectElement.data('id');
    const newStatus = selectElement.val(); // Lấy giá trị của option được chọn
    const statusText = selectElement.find('option:selected').text();

    // Hỏi lại để chắc chắn
    Swal.fire({
        title: LANG['confirm_change_status'] || 'Xác nhận thay đổi?',
        html: `${LANG['are_you_sure_to_change_status_to'] || 'Bạn có chắc muốn đổi trạng thái thành'}<br><b>"${statusText}"</b>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: `${LANG['yes_change_it'] || 'Vâng, thay đổi!'}`,
        cancelButtonText: `${LANG['cancel'] || 'Hủy'}`
    }).then((result) => {
        if (result.isConfirmed) {
            // Gửi yêu cầu AJAX đi
            $.ajax({
                url: PROJECT_BASE_URL + 'process/sales_order_handler.php', // URL không đổi
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'update_status',
                    id: orderId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        showUserMessage(response.message || 'Cập nhật trạng thái thành công!', 'success');
                        // Tải lại bảng để đồng bộ hoàn toàn (ví dụ: nút Sửa/Xóa có thể ẩn/hiện)
                        if (typeof salesOrderDataTable !== 'undefined') {
                            salesOrderDataTable.ajax.reload(null, false);
                        }
                    } else {
                        showUserMessage(response.message || 'Cập nhật thất bại.', 'error');
                        // Nếu thất bại, tải lại bảng để dropdown trả về trạng thái cũ
                        if (typeof salesOrderDataTable !== 'undefined') {
                            salesOrderDataTable.ajax.reload(null, false);
                        }
                    }
                },
                error: function() {
                    showUserMessage('Lỗi kết nối máy chủ.', 'error');
                    if (typeof salesOrderDataTable !== 'undefined') {
                        salesOrderDataTable.ajax.reload(null, false);
                    }
                }
            });
        } else {
            // Nếu người dùng bấm "Hủy", tải lại bảng để dropdown quay về giá trị ban đầu
            if (typeof salesOrderDataTable !== 'undefined') {
                salesOrderDataTable.ajax.reload(null, false);
            }
        }
    });
});