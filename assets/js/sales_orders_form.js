// File: assets/js/sales_orders_form.js
let loadOrderCallCounter = 0;

// Giữ nguyên: parseInputNumber dành cho INPUT người dùng (quy ước . là nghìn, , là thập phân)
function parseInputNumber(str) {
    if (!str) return 0;
    return parseFloat(
        str.toString()
           .replace(/\./g, '')
           .replace(/,/g, '.')
    ) || 0;
}

 // Dùng parser chuẩn VN từ NumberHelpers nếu có; fallback an toàn nếu chưa load
 if (typeof parseServerNumber !== 'function') {
   function parseServerNumber(val) {
     if (typeof NumberHelpers !== 'undefined' && NumberHelpers.parseVNNumber) {
       return NumberHelpers.parseVNNumber(val);
     }
     // Fallback tối giản: coi '.' là nghìn, ',' là thập phân
     if (val === null || val === undefined) return 0;
     const s = String(val).trim();
     if (!s) return 0;
     return parseFloat(s.replace(/\./g, '').replace(/,/g, '.')) || 0;
   }
 }

// --- Hàm Khởi Tạo Datepicker ---
function initializeDatepicker() {
    console.log("Initializing Flatpickr...");
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".datepicker", {
            dateFormat: "d/m/Y",
            locale: (typeof LANG !== 'undefined' && LANG.language === 'vi' ? 'vn' : 'default'),
            allowInput: true,
        });
        console.log("Flatpickr initialized.");
    } else {
        console.warn("Flatpickr library not found. Datepicker disabled.");
        $('.datepicker').prop('disabled', true);
    }
    console.log("Flatpickr initialization finished.");
}

// --- Hàm Khởi Tạo Autocomplete Nhà Cung Cấp (Supplier) ---
function initializeSupplierAutocomplete() {
    console.log("Initializing Supplier Autocomplete...");
    const partnerInput = $("#partner_autocomplete");
    const partnerIdInput = $("#partner_id");
    const partnerAddressDisplay = $("#partner_address_display");
    const partnerTaxIdDisplay = $("#partner_tax_id_display");
    const partnerPhoneDisplay = $("#partner_phone_display");
    const partnerEmailDisplay = $("#partner_email_display");
    const partnerContactDisplay = $("#partner_contact_person_display");
    const partnerLoading = $('#partner-loading');
    const partnerTypeFilter = 'supplier';

    if (typeof $.ui !== 'undefined' && typeof $.ui.autocomplete !== 'undefined') {
        partnerInput.autocomplete({
            source: function (request, response) {
                partnerLoading.removeClass('d-none');
                partnerInput.removeClass('is-invalid');
                $.ajax({
                    url: AJAX_URL.partner_search,
                    dataType: "json",
                    data: { action: 'search', term: request.term, type: partnerTypeFilter },
                    success: function (data) {
                        partnerLoading.addClass('d-none');
                        if (data.success && Array.isArray(data.data)) {
                            const mappedData = $.map(data.data, function (item) {
                                if (item && typeof item.name !== 'undefined' && typeof item.id !== 'undefined') {
                                    return {
                                        label: item.name + (item.tax_id ? ` (MST: ${item.tax_id})` : ''),
                                        value: item.name,
                                        id: item.id,
                                        address: item.address || '-',
                                        tax_id: item.tax_id || '-',
                                        phone: item.phone || '-',
                                        email: item.email || '-',
                                        contact_person: item.contact_person || '-'
                                    };
                                } return null;
                            });
                            response(mappedData);
                        } else { console.error("Error fetching suppliers:", data?.message); response([]); }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        partnerLoading.addClass('d-none'); console.error("Supplier Autocomplete AJAX Error:", textStatus, errorThrown); response([]);
                    }
                });
            },
            minLength: 1,
            select: function (event, ui) {
                event.preventDefault();
                partnerInput.val(ui.item.value);
                partnerIdInput.val(ui.item.id);
                partnerAddressDisplay.text(ui.item.address);
                partnerTaxIdDisplay.text(ui.item.tax_id);
                partnerPhoneDisplay.text(ui.item.phone);
                partnerEmailDisplay.text(ui.item.email);
                partnerContactDisplay.text(ui.item.contact_person);
                partnerInput.removeClass('is-invalid').closest('.mb-2').find('.invalid-feedback').text('');
                console.log("Supplier selected:", ui.item);
                return false;
            },
            focus: function (event, ui) { event.preventDefault(); },
            change: function (event, ui) {
                if (!ui.item) {
                    partnerIdInput.val('');
                    partnerAddressDisplay.text('-');
                    partnerTaxIdDisplay.text('-');
                    partnerPhoneDisplay.text('-');
                    partnerEmailDisplay.text('-');
                    partnerContactDisplay.text('-');
                    console.log("Supplier selection cleared.");
                }
            }
        });
        console.log("Supplier autocomplete initialized.");
    } else {
        console.warn("jQuery UI Autocomplete not found. Supplier autocomplete disabled.");
        partnerInput.prop('disabled', true);
    }
    console.log("Supplier Autocomplete initialization finished.");
}

// --- Hàm Khởi Tạo Autocomplete Sản Phẩm (Product) ---
function initializeProductAutocomplete(containerSelector) {
    console.log(`Initializing Product Autocomplete for: ${containerSelector}...`);
    const targetElements = $(containerSelector).find('.product-autocomplete');
    if (typeof $.ui !== 'undefined' && typeof $.ui.autocomplete !== 'undefined') {
        targetElements.each(function () {
            const inputElement = $(this);
            if (!inputElement.data('ui-autocomplete')) {
                console.log("Initializing NEW product autocomplete for:", this);
                inputElement.autocomplete({
                    source: function (request, response) {
                        const inputField = $(this.element);
                        inputField.removeClass('is-invalid').closest('td').find('.invalid-feedback').text('');
                        $.ajax({
                            url: AJAX_URL.product_search,
                            dataType: "json",
                            data: { action: 'search', term: request.term },
                            success: function (data) {
                                if (data.success && Array.isArray(data.data)) {
                                    const mappedData = $.map(data.data, function (item) {
                                        if (item && typeof item.name !== 'undefined' && typeof item.id !== 'undefined') {
                                            return {
                                                label: item.name + (item.category_name ? ` (${item.category_name})` : ''),
                                                value: item.name,
                                                id: item.id,
                                                description: item.description || '',
                                                category_name: item.category_name || '',
                                                unit_name: item.unit_name || ''
                                            };
                                        } return null;
                                    });
                                    response(mappedData);
                                } else { console.error("Error fetching products:", data?.message); response([]); }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error("Product Autocomplete AJAX Error:", textStatus, errorThrown); response([]);
                            }
                        });
                    },
                    minLength: 1,
                    select: function (event, ui) {
                        event.preventDefault();
                        const row = $(this).closest('tr');
                        $(this).val(ui.item.value);
                        row.find('.product-id').val(ui.item.id);
                        row.find('.category-display').val(ui.item.category_name);
                        row.find('input[name$="[category_snapshot]"]').val(ui.item.category_name);
                        row.find('.unit-display').val(ui.item.unit_name);
                        row.find('input[name$="[unit_snapshot]"]').val(ui.item.unit_name);
                        $(this).removeClass('is-invalid').closest('td').find('.invalid-feedback').text('');
                        console.log("Product selected:", ui.item);
                        calculateLineTotal(row);
                        return false;
                    },
                    focus: function (event, ui) { event.preventDefault(); },
                    change: function (event, ui) {
                        const row = $(this).closest('tr');
                        if (!ui.item) {
                            row.find('.product-id').val('');
                            row.find('.category-display').val('');
                            row.find('input[name$="[category_snapshot]"]').val('');
                            row.find('.unit-display').val('');
                            row.find('input[name$="[unit_snapshot]"]').val('');
                            console.log("Product selection cleared for row:", row.index());
                        }
                        calculateLineTotal(row);
                    }
                });
            } else { console.log("Autocomplete already initialized for:", this); }
        });
        console.log(`Product autocomplete initialization attempt complete for selector: ${containerSelector}`);
    } else {
        console.warn("jQuery UI Autocomplete not found. Product autocomplete disabled.");
        targetElements.prop('disabled', true);
    }
    console.log("Product Autocomplete initialization finished.");
}

// --- Hàm Reset Form Đơn Hàng ---
function resetOrderForm(isEdit = false) {
    console.log("Resetting order form. Is Edit:", isEdit);
    if (orderForm && orderForm.length) orderForm[0].reset();
    if (orderForm && orderForm.length) orderForm.find('input[type="hidden"]').val('');
    $('#order_quote_id_form').val('');

    if (itemTableBody && itemTableBody.length) itemTableBody.empty();
    addItemRow(); // Thêm một dòng item trống

    $('#partner_autocomplete').val('');
    $('#partner_id').val('');
    $('#partner_address_display, #partner_tax_id_display, #partner_phone_display, #partner_email_display, #partner_contact_person_display').text('-');

    $('#summary-subtotal, #summary-vattotal, #summary-grandtotal').text('0');
    $('#input-subtotal, #input-vattotal, #input-grandtotal').val('0.00');

    const datepickerInput = document.querySelector(".datepicker");
    if (datepickerInput && datepickerInput._flatpickr) {
        datepickerInput._flatpickr.clear();
    }

    $('#order_number').val('');
    // CKEditor
    if (ckEditorInstances['notes']) {
        ckEditorInstances['notes'].setData('');
    } else {
        $('#notes').val('');
        console.warn('CKEditor instance for "notes" not found. Cleared textarea directly.');
    }
    if (ckEditorInstances['emailBody']) {
        ckEditorInstances['emailBody'].setData('');
    } else {
        $('#emailBody').val('');
        console.warn('CKEditor instance for "emailBody" not found. Cleared textarea directly.');
    }

    currencySelect.val('VND');
    const itemCurrencySymbol = (currencySelect.val() === 'VND') ? 'đ' : '$';
    $('.item-row-template').find('.currency-symbol-unit').text(itemCurrencySymbol);

    const defaultVatInt = Math.round(parseFloat(vatDefaultRate || 0));
    $('#summary-vat-rate').val(defaultVatInt);
    $('#input-vatrate').val(defaultVatInt);

    if (orderForm && orderForm.length) {
      orderForm.find('.is-invalid').removeClass('is-invalid');
      orderForm.find('.invalid-feedback').text('');
    }
    if (formErrorMessageDiv && formErrorMessageDiv.length) formErrorMessageDiv.addClass('d-none').text('');
    
    if (orderFormCard && orderFormCard.length) orderFormCard.removeClass('view-mode');

    if (orderForm && orderForm.length) {
      orderForm.find('input, select, textarea').not('[readonly]').prop('disabled', false);
      orderForm.find('#add-item-row, .remove-item-row, #btn-generate-order-number').show().prop('disabled', false);
    }

    if (saveButton && saveButton.length) saveButton.show().prop('disabled', false);
    if (saveButtonText && saveButtonText.length) saveButtonText.show();
    if (saveButtonSpinner && saveButtonSpinner.length) saveButtonSpinner.addClass('d-none');

    const formTitleKey = isEdit ? 'edit_order' : 'create_new_order';
    const saveButtonTextKey = isEdit ? 'update' : 'save_order';
    if (orderFormTitle && orderFormTitle.length) orderFormTitle.text(LANG[formTitleKey] || (isEdit ? 'Edit Order' : 'Create New Order'));
    if (saveButtonText && saveButtonText.length) saveButtonText.text(LANG[saveButtonTextKey] || (isEdit ? 'Update' : 'Save Order'));

    updateSTT();
    $('#btn-download-pdf').prop('disabled', true);
    console.log("Order form reset.");
}

// --- Hàm Thêm Dòng Item vào Form ---
function addItemRow(data = {}) {
    console.log("Adding item row. Data:", data);
    const templateRow = $('.item-row-template').clone();
    templateRow.removeClass('item-row-template d-none').removeAttr('style');
    const newItemIndex = itemTableBody.find('tr').length;

    const currentCurrencyCode = currencySelect.val();
    const currencySymbol = (currentCurrencyCode === 'VND') ? 'đ' : '$';
    templateRow.find('.currency-symbol-unit').text(currencySymbol);
    templateRow.find('.stt-col').text(newItemIndex + 1);

    templateRow.find('input, select').each(function () {
        const currentName = $(this).attr('name');
        if (currentName) {
            $(this).attr('name', currentName.replace(/items\[\d+\]/, `items[${newItemIndex}]`));
        }
        $(this).removeClass('is-invalid').closest('td').find('.invalid-feedback').text('');

        // Khi load dòng từ dữ liệu server (edit), dùng parseServerNumber
        if ($(this).is('input:not(.quantity, .unit-price), select, textarea')) {
            $(this).val('');
        } else if ($(this).hasClass('quantity')) {
            $(this).val(data.quantity !== undefined ? parseServerNumber(data.quantity) || 0 : 1);
        } else if ($(this).hasClass('unit-price')) {
            $(this).val(data.unit_price !== undefined ? parseServerNumber(data.unit_price) : 0);
        }
    });

    if (data.id) templateRow.find('td:first-child').append(`<input type="hidden" name="items[${newItemIndex}][detail_id]" value="${data.id}">`);
    if (data.product_id) templateRow.find('.product-id').val(data.product_id);
    if (data.product_name_snapshot) templateRow.find('.product-autocomplete').val(data.product_name_snapshot);
    if (data.category_snapshot) { templateRow.find('.category-display').val(data.category_snapshot); templateRow.find('input[name$="[category_snapshot]"]').val(data.category_snapshot); }
    if (data.unit_snapshot) { templateRow.find('.unit-display').val(data.unit_snapshot); templateRow.find('input[name$="[unit_snapshot]"]').val(data.unit_snapshot); }

    itemTableBody.append(templateRow);
    initializeProductAutocomplete(templateRow);
     if (window.NumberHelpers) {
   const qEl = templateRow.find('.quantity')[0];
   const pEl = templateRow.find('.unit-price')[0];
   if (qEl) NumberHelpers.formatFieldOnBlur(qEl);
   if (pEl) NumberHelpers.formatFieldOnBlur(pEl);
   NumberHelpers.recalcRow(templateRow[0]);
 } else {
   calculateLineTotal(templateRow); // giữ fallback cũ nếu cần
 }

    console.log("Added item row with index:", newItemIndex, "Data:", data);
    return templateRow;
}

/**
 * Thiết lập chế độ xem hoặc sửa cho form Đơn Đặt Hàng.
 * @param {boolean} isView True nếu là chế độ xem, False nếu là chế độ sửa.
 * @param {string} orderNumber Số đơn hàng (để hiển thị trên tiêu đề).
 */
function setOrderFormViewMode(isView, orderNumber = '') {
    console.log(`SO Form: Setting view mode to: ${isView} for order: ${orderNumber}`);

    if (typeof orderForm === 'undefined' || !orderForm.length) {
        console.error("setOrderFormViewMode: Biến orderForm chưa được định nghĩa hoặc không tìm thấy form.");
        return;
    }

    const formTitleText = isView ? (LANG['view_order_details'] || 'Xem chi tiết Đơn hàng') : (LANG['edit_order'] || 'Sửa Đơn hàng');
    const cancelBtnText = isView ? (LANG['close'] || 'Đóng') : (LANG['cancel'] || 'Hủy');

    if (typeof orderFormTitle !== 'undefined' && orderFormTitle.length) {
        orderFormTitle.text(formTitleText + (orderNumber ? ` (${orderNumber})` : ''));
    }

    if (isView) {
        orderForm.addClass('view-mode');
        orderForm.find('input, select, textarea').not('#btn-cancel-order-form, #btn-download-so-pdf, #toggle-so-signature').prop('disabled', true);
        orderForm.find('#add-item-row, .remove-item-row, #btn-generate-order-number').hide();
        if (typeof saveButton !== 'undefined' && saveButton.length) saveButton.hide();
    } else {
        orderForm.removeClass('view-mode');
        orderForm.find('input, select, textarea').not('[readonly]').prop('disabled', false);
        orderForm.find('#add-item-row, .remove-item-row, #btn-generate-order-number').show();
        if (typeof saveButton !== 'undefined' && saveButton.length) {
            saveButton.show();
            if (typeof saveButtonText !== 'undefined' && saveButtonText.length) saveButtonText.text(LANG['update'] || 'Cập nhật');
        }
    }
    $('#btn-cancel-order-form').text(cancelBtnText);
    $('#btn-download-so-pdf').prop('disabled', !orderNumber && isView);
}

/**
 * Tải dữ liệu đơn hàng để sửa hoặc xem.
 * @param {number|string} orderId ID của đơn hàng cần tải.
 * @param {string} callSource Nguồn gốc của lời gọi hàm (để debug).
 */
function loadOrderForEdit(orderId, callSource = "UnknownSO_Default") {
    loadOrderCallCounter++;
    const currentCallNumber = loadOrderCallCounter;

    console.log(`SO Form: CALL #${currentCallNumber} - loadOrderForEdit initiated. Source: ${callSource}, Received orderId:`, orderId, "Type:", typeof orderId);

    let parsedOrderId = null;
    if (orderId !== false && orderId !== null && orderId !== undefined && String(orderId).trim() !== "") {
        parsedOrderId = parseInt(String(orderId), 10);
    }

    if (parsedOrderId === null || isNaN(parsedOrderId) || parsedOrderId <= 0) {
        console.warn(`SO Form: CALL #${currentCallNumber} - Invalid order ID. Source: ${callSource}, Original Value:`, orderId, "Parsed Value:", parsedOrderId, "Type of Original:", typeof orderId);
        console.trace(`SO Form: CALL #${currentCallNumber} - Stack trace for invalid ID from ${callSource}`);
        return;
    } else {
        console.log(`SO Form: CALL #${currentCallNumber} - Valid ID. Loading order for edit/view. Source: ${callSource}, ID:`, parsedOrderId);
    }

    $.ajax({
        url: AJAX_URL.sales_order,
        type: 'GET',
        data: { action: 'get_details', id: parsedOrderId },
        dataType: 'json',
        beforeSend: function () {
            if (typeof orderFormCard !== 'undefined' && orderFormCard.length) {
                orderFormCard.addClass('opacity-50');
            }
            $('#btn-save-order, #btn-cancel-order-form, #btn-download-so-pdf').prop('disabled', true);
        },
        success: function (response) {
            console.log(`SO Form: CALL #${currentCallNumber} - Order details received (Source: ${callSource}):`, response);
            if (response && response.success && response.data && response.data.header) {
                if(typeof resetOrderForm !== 'function') {
                    console.error("SO Form: resetOrderForm function is not defined!");
                } else {
                   resetOrderForm(true);
                }

                const orderHeader = response.data.header;
                const orderDetails = response.data.details;

                // Header
                $('#order_id').val(orderHeader.id);
                $('#order_number').val(orderHeader.order_number);
                
                const orderDateInput = document.querySelector("#order_date");
                if (orderDateInput && orderDateInput._flatpickr) {
                    const flatpickrInstance = orderDateInput._flatpickr;
                    let displayFormat = (typeof USER_SETTINGS !== 'undefined' && USER_SETTINGS.date_format_display) ? USER_SETTINGS.date_format_display : 'd/m/Y';
                    if (orderHeader.order_date_formatted) {
                        try { flatpickrInstance.setDate(orderHeader.order_date_formatted, true, displayFormat); }
                        catch (e) { 
                            console.error("SO Form: Error setting datepicker with order_date_formatted:", e);
                            if(orderHeader.order_date) flatpickrInstance.setDate(orderHeader.order_date, true, 'Y-m-d');
                        }
                    } else if (orderHeader.order_date) {
                        try { flatpickrInstance.setDate(orderHeader.order_date, true, 'Y-m-d'); }
                        catch (e) { console.error("SO Form: Error setting datepicker with order_date (Y-m-d):", e); }
                    }
                } else {
                     console.warn("SO Form: Flatpickr instance for #order_date not found.");
                }

                // Supplier
                const supplierSnapshot = orderHeader.supplier_info_snapshot;
                if (orderHeader.supplier_id && supplierSnapshot) {
                    try {
                        const parsedInfo = (typeof supplierSnapshot === 'string') ? JSON.parse(supplierSnapshot) : supplierSnapshot;
                        if (parsedInfo && typeof parsedInfo === 'object') {
                            $('#partner_id').val(orderHeader.supplier_id);
                            $('#partner_autocomplete').val(parsedInfo.name || '');
                            $('#partner_address_display').text(parsedInfo.address || '-');
                            $('#partner_tax_id_display').text(parsedInfo.tax_id || '-');
                            $('#partner_phone_display').text(parsedInfo.phone || '-');
                            $('#partner_email_display').text(parsedInfo.email || '-');
                            $('#partner_contact_person_display').text(parsedInfo.contact_person || '-');
                        } else { throw new Error("Parsed supplier info is not an object."); }
                    } catch (e) {
                        console.error("SO Form: Error processing supplier_info_snapshot:", e);
                        $('#partner_id').val(''); $('#partner_autocomplete').val('');
                        $('#partner_address_display, #partner_tax_id_display, #partner_phone_display, #partner_email_display, #partner_contact_person_display').text('-');
                    }
                } else {
                    console.warn("SO Form: Missing supplier_id or supplier_info_snapshot");
                    $('#partner_id').val(''); $('#partner_autocomplete').val('');
                    $('#partner_address_display, #partner_tax_id_display, #partner_phone_display, #partner_email_display, #partner_contact_person_display').text('-');
                }

                // Liên kết báo giá
                const linkedQuoteIdVal = orderHeader.quote_id; 
                const linkedQuoteNumberVal = orderHeader.linked_quote_number || null;
                const $linkedQuoteSelectElement = $('#order_quote_id_form');
                if ($linkedQuoteSelectElement.length) {
                    if (typeof populateAndSelectQuoteInDropdown === 'function') {
                        populateAndSelectQuoteInDropdown($linkedQuoteSelectElement, linkedQuoteIdVal, linkedQuoteNumberVal);
                    } else {
                        console.error("SO Form: populateAndSelectQuoteInDropdown function is not defined.");
                    }
                } else {
                     console.warn("SO Form: Dropdown #order_quote_id_form not found.");
                }

                if (typeof currencySelect !== 'undefined' && currencySelect.length) {
                    currencySelect.val(orderHeader.currency || 'VND'); 
                }

                const notesContentSO = orderHeader.notes || '';
                if (typeof ckEditorInstances !== 'undefined' && ckEditorInstances && ckEditorInstances['notes']) {
                    ckEditorInstances['notes'].setData(notesContentSO);
                } else {
                    $('#notes').val(notesContentSO);
                }

                // VAT Rate từ server => parseServerNumber (tránh 10.00 thành 1000)
                const vatRateSOVal = (typeof parseServerNumber === 'function')
                ? parseServerNumber(orderHeader.vat_rate)
                : parseFloat(orderHeader.vat_rate || 0);

                const defaultVatInt = Math.round(parseFloat(vatDefaultRate || 0));
                const vatInt = isNaN(vatRateSOVal) ? defaultVatInt : Math.round(vatRateSOVal);

                $('#summary-vat-rate').val(vatInt);
                $('#input-vatrate').val(vatInt);

                // Items
                if (typeof itemTableBody !== 'undefined' && itemTableBody.length) {
                    itemTableBody.empty();
                    if (Array.isArray(orderDetails) && orderDetails.length > 0) {
                        orderDetails.forEach(item => {
                            if(typeof addItemRow === "function") { addItemRow(item); } 
                            else { console.error("SO Form: addItemRow function is not defined."); }
                        });
                    } else {
                        if(typeof addItemRow === "function") addItemRow();
                        else { console.error("SO Form: addItemRow function is not defined."); }
                    }
                } else {
                    console.warn("SO Form: itemTableBody is not defined or not found.");
                }

                if (typeof calculateSummaryTotals === "function") { calculateSummaryTotals(); } 
                else { console.error("SO Form: calculateSummaryTotals function is not defined.");}
                
                $('#btn-download-so-pdf').prop('disabled', !orderHeader.order_number);

                let currentSOFormIsViewMode = (orderHeader.status !== 'draft');
                if (typeof setOrderFormViewMode === "function") {
                    setOrderFormViewMode(currentSOFormIsViewMode, orderHeader.order_number);
                } else {
                    console.error("SO Form: setOrderFormViewMode function is not defined.");
                }

                if (typeof orderFormCard !== 'undefined' && orderFormCard.length) orderFormCard.slideDown();
                if (typeof orderListTitle !== 'undefined' && orderListTitle.length) orderListTitle.hide();
                
                if (typeof orderFormCard !== 'undefined' && orderFormCard.length && !currentSOFormIsViewMode) {
                    $('html, body').animate({ scrollTop: orderFormCard.offset().top - 20 }, 300);
                }

            } else {
                if(typeof showUserMessage === 'function') showUserMessage(response.message || LANG['error_loading_order_details_data'] || 'Lỗi khi tải dữ liệu chi tiết đơn hàng hoặc dữ liệu không đầy đủ.', 'error');
                else alert(response.message || LANG['error_loading_order_details_data'] || 'Lỗi khi tải dữ liệu chi tiết đơn hàng hoặc dữ liệu không đầy đủ.');
                
                if(typeof resetOrderForm === 'function') resetOrderForm(); else console.error("SO Form: resetOrderForm is not defined");
                if (typeof orderFormCard !== 'undefined' && orderFormCard.length) orderFormCard.removeClass('view-mode');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error("SO Form: AJAX Error loading order details:", textStatus, errorThrown, jqXHR.responseText);
            if(typeof showUserMessage === 'function') showUserMessage(LANG['server_error_loading_details'] || 'Lỗi máy chủ khi tải chi tiết đơn hàng.', 'error');
            else alert(LANG['server_error_loading_details'] || 'Lỗi máy chủ khi tải chi tiết đơn hàng.');
            
            if(typeof resetOrderForm === 'function') resetOrderForm();
            if (typeof orderFormCard !== 'undefined' && orderFormCard.length) orderFormCard.removeClass('view-mode');
        },
        complete: function () {
            if (typeof orderFormCard !== 'undefined' && orderFormCard.length) {
                orderFormCard.removeClass('opacity-50');
                if (!orderFormCard.hasClass('view-mode')) {
                    if(typeof saveButton !== 'undefined' && saveButton.length) saveButton.prop('disabled', false);
                }
            }
            $('#btn-cancel-order-form, #btn-download-so-pdf').prop('disabled', false);
        }
    });

    // === Bridge điền items từ báo giá (nếu bạn dùng) ===
    function populateOrderItemsFromQuoteData(itemsToFill) {
        if (!itemsToFill || !Array.isArray(itemsToFill) || itemsToFill.length === 0) {
            console.warn("[SalesOrdersForm] Không có items nào để điền từ dữ liệu báo giá.");
            return;
        }

        const itemTableBody = $('#item-details-body');
        if (!itemTableBody.length) {
            console.error("[SalesOrdersForm] Không tìm thấy tbody #item-details-body.");
            return;
        }
        itemTableBody.empty();

        itemsToFill.forEach(function(item) {
            if (typeof addNewItemRow !== 'function') {
                console.error("[SalesOrdersForm] Hàm addNewItemRow() không tồn tại.");
                return false;
            }
            addNewItemRow(false);

            const newRow = itemTableBody.find('.item-row:not(.item-row-template):last-child');
            if (!newRow.length) {
                console.error("[SalesOrdersForm] Không thể tìm thấy dòng mới vừa được thêm bởi addNewItemRow().");
                return false;
            }

            const categorySelect = newRow.find('select.item-category-id');
            if (categorySelect.length) {
                if (item.category_id && item.category_name) {
                    appendAndSelectOption(categorySelect, item.category_id, item.category_name);
                }
            }

            const productSelect = newRow.find('select.item-product-id');
            if (productSelect.length) {
                let productDisplayText = item.product_name || '';
                if (item.product_code) {
                    productDisplayText = `${item.product_name} (${item.product_code})`;
                }
                if (item.product_id && productDisplayText) {
                    const productOption = appendAndSelectOption(productSelect, item.product_id, productDisplayText, true);
                    if (productOption) {
                        $(productOption).data('product_code', item.product_code);
                        $(productOption).data('name_no_code', item.product_name);
                        $(productOption).data('unit_id', item.unit_id);
                        $(productOption).data('category_id', item.category_id);
                        productSelect.trigger('change');
                    }
                }
            }
            
            const unitSelect = newRow.find('select.item-unit-id');
            if (unitSelect.length) {
                if (item.unit_id && item.unit_name) {
                    if(unitSelect.val() !== item.unit_id.toString()){
                        appendAndSelectOption(unitSelect, item.unit_id, item.unit_name);
                    }
                }
            }
            
            const quantityInput = newRow.find('input.quantity');
            if (quantityInput.length) {
                // Dữ liệu từ báo giá (server) => parseServerNumber
                quantityInput.val(parseServerNumber(item.quantity || 0)).trigger('input');
            }

            const unitPriceInput = newRow.find('input.unit-price');
            if(unitPriceInput.length && !unitPriceInput.prop('readonly')){
                // để người dùng/logic giá set sau
            }
        });

        if (typeof updateAllCalculations === 'function') {
            updateAllCalculations();
        } else {
            console.warn("[SalesOrdersForm] Hàm updateAllCalculations() không tồn tại.");
        }
        console.info("[SalesOrdersForm] Đã điền các mục sản phẩm từ báo giá vào đơn hàng.");
    }

    function appendAndSelectOption($selectElement, value, text, returnOption = false) {
        if (!$selectElement || !$selectElement.length) return;
        value = value !== null && value !== undefined ? value.toString() : '';
        text = text || value;

        let option = $selectElement.find(`option[value="${value}"]`);
        if (option.length === 0) {
            option = new Option(text, value, false, false);
            $selectElement.append(option);
        }
        
        $selectElement.val(value);
        if ($selectElement.data('select2')) {
            $selectElement.trigger('change.select2');
        } else {
            $selectElement.trigger('change');
        }
        return returnOption ? option[0] : undefined;
    }
}; // đóng scope loadOrderForEdit nếu file của bạn đang bọc theo kiểu này
