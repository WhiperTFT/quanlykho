// File: assets/js/sq_form.js
let loadQuoteCallCounter = 0;

// Dành cho INPUT người dùng (quy ước . là nghìn, , là thập phân)
function parseInputNumber(str) {
    if (!str) return 0;
    return parseFloat(
        str.toString()
           .replace(/\./g, '')
           .replace(/,/g, '.')
    ) || 0;
}

// Phòng trường hợp helpers.js load sau – bổ sung parseServerNumber tạm
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
    console.log("Initializing Flatpickr for quote form...");
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".datepicker", {
            dateFormat: "d/m/Y",
            locale: (typeof LANG !== 'undefined' && LANG.language === 'vi' ? 'vn' : 'default'),
            allowInput: true,
        });
        console.log("Flatpickr initialized for quote.");
    } else {
        console.warn("Flatpickr library not found. Datepicker disabled for quote.");
        $('.datepicker').prop('disabled', true);
    }
}

// --- Hàm Khởi Tạo Autocomplete Khách hàng (Customer) ---
function initializeCustomerAutocomplete() {
    console.log("Initializing Customer Autocomplete for quote...");
    const partnerInput = $("#partner_autocomplete");
    const partnerIdInput = $("#partner_id");
    const partnerAddressDisplay = $("#partner_address_display");
    const partnerTaxIdDisplay = $("#partner_tax_id_display");
    const partnerPhoneDisplay = $("#partner_phone_display");
    const partnerEmailDisplay = $("#partner_email_display");
    const partnerContactDisplay = $("#partner_contact_person_display");
    const partnerLoading = $('#partner-loading');
    const partnerTypeFilter = 'customer';

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
                                        value: item.name, id: item.id, address: item.address || '-',
                                        tax_id: item.tax_id || '-', phone: item.phone || '-',
                                        email: item.email || '-', contact_person: item.contact_person || '-'
                                    };
                                } return null;
                            });
                            response(mappedData);
                        } else { console.error("Error fetching customers:", data?.message); response([]); }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        partnerLoading.addClass('d-none'); console.error("Customer Autocomplete AJAX Error:", textStatus, errorThrown); response([]);
                    }
                });
            },
            minLength: 1,
            select: function (event, ui) {
                event.preventDefault();
                partnerInput.val(ui.item.value); partnerIdInput.val(ui.item.id);
                partnerAddressDisplay.text(ui.item.address); partnerTaxIdDisplay.text(ui.item.tax_id);
                partnerPhoneDisplay.text(ui.item.phone); partnerEmailDisplay.text(ui.item.email);
                partnerContactDisplay.text(ui.item.contact_person);
                partnerInput.removeClass('is-invalid').closest('.mb-2').find('.invalid-feedback').text('');
                console.log("Customer selected for quote:", ui.item);
                return false;
            },
            focus: function (event, ui) { event.preventDefault(); },
            change: function (event, ui) {
                if (!ui.item) {
                    partnerIdInput.val(''); partnerAddressDisplay.text('-'); partnerTaxIdDisplay.text('-');
                    partnerPhoneDisplay.text('-'); partnerEmailDisplay.text('-'); partnerContactDisplay.text('-');
                    console.log("Customer selection cleared for quote.");
                }
            }
        });
        console.log("Customer autocomplete initialized for quote.");
    } else {
        console.warn("jQuery UI Autocomplete not found. Customer autocomplete disabled for quote.");
        partnerInput.prop('disabled', true);
    }
}
 
// --- Hàm Khởi Tạo Autocomplete Sản Phẩm (Product) ---
function initializeProductAutocomplete(containerSelector) {
    console.log(`Initializing Product Autocomplete for quote: ${containerSelector}...`);
    const targetElements = $(containerSelector).find('.product-autocomplete');
    if (typeof $.ui !== 'undefined' && typeof $.ui.autocomplete !== 'undefined') {
        targetElements.each(function () {
            const inputElement = $(this);
            if (!inputElement.data('ui-autocomplete')) {
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
                                                value: item.name, id: item.id, description: item.description || '',
                                                category_name: item.category_name || '', unit_name: item.unit_name || ''
                                            };
                                        } return null;
                                    });
                                    response(mappedData);
                                } else { console.error("Error fetching products for quote:", data?.message); response([]); }
                            },
                            error: function (jqXHR, textStatus, errorThrown) {
                                console.error("Product Autocomplete AJAX Error for quote:", textStatus, errorThrown); response([]);
                            }
                        });
                    },
                    minLength: 1,
                    select: function (event, ui) {
                        event.preventDefault();
                        const row = $(this).closest('tr');
                        $(this).val(ui.item.value); row.find('.product-id').val(ui.item.id);
                        row.find('.category-display').val(ui.item.category_name);
                        row.find('input[name$="[category_snapshot]"]').val(ui.item.category_name);
                        row.find('.unit-display').val(ui.item.unit_name);
                        row.find('input[name$="[unit_snapshot]"]').val(ui.item.unit_name);
                        $(this).removeClass('is-invalid').closest('td').find('.invalid-feedback').text('');
                        console.log("Product selected for quote:", ui.item);
                        calculateLineTotal(row);
                        return false;
                    },
                    focus: function (event, ui) { event.preventDefault(); },
                    change: function (event, ui) {
                        const row = $(this).closest('tr');
                        if (!ui.item) {
                            row.find('.product-id').val(''); row.find('.category-display').val('');
                            row.find('input[name$="[category_snapshot]"]').val('');
                            row.find('.unit-display').val(''); row.find('input[name$="[unit_snapshot]"]').val('');
                            console.log("Product selection cleared for quote row:", row.index());
                        }
                        calculateLineTotal(row);
                    }
                });
            }
        });
    } else {
        console.warn("jQuery UI Autocomplete not found. Product autocomplete disabled for quote.");
        targetElements.prop('disabled', true);
    }
}

// --- Hàm Reset Form Báo Giá ---
function resetQuoteForm(isEdit = false) {
    console.log("Resetting quote form. Is Edit:", isEdit);
    if (quoteForm && quoteForm.length) quoteForm[0].reset();
    if (quoteForm && quoteForm.length) quoteForm.find('input[type="hidden"]').val('');
    
    if (itemTableBody && itemTableBody.length) itemTableBody.empty();
    addItemRow();

    $('#partner_autocomplete').val(''); $('#partner_id').val('');
    $('#partner_address_display, #partner_tax_id_display, #partner_phone_display, #partner_email_display, #partner_contact_person_display').text('-');
    $('#summary-subtotal, #summary-vattotal, #summary-grandtotal').text('0');
    $('#input-subtotal, #input-vattotal, #input-grandtotal').val('0.00');

    const datepickerInput = document.querySelector(".datepicker");
    if (datepickerInput && datepickerInput._flatpickr) {
        datepickerInput._flatpickr.clear();
    }

    $('#quote_number').val('');

    if (ckEditorInstances && ckEditorInstances['notes']) {
        ckEditorInstances['notes'].setData('');
    } else {
        $('#notes').val('');
        if (!ckEditorInstances) console.warn('ckEditorInstances object is not defined.');
        else console.warn('CKEditor instance for "notes" (quote) not found. Cleared textarea directly.');
    }

    if (ckEditorInstances && ckEditorInstances['emailBody']) {
        ckEditorInstances['emailBody'].setData('');
    } else {
        $('#emailBody').val('');
        if (!ckEditorInstances) console.warn('ckEditorInstances object is not defined.');
        else console.warn('CKEditor instance for "emailBody" (quote) not found. Cleared textarea directly.');
    }

    if (currencySelect && currencySelect.length) currencySelect.val('VND');
    const itemCurrencySymbol = (currencySelect && currencySelect.val() === 'VND') ? 'đ' : '$';
    $('.item-row-template').find('.currency-symbol-unit').text(itemCurrencySymbol);

    // VAT mặc định -> integer
    const defaultVatInt = Math.round(parseFloat(vatDefaultRate || 0));
    $('#summary-vat-rate').val(defaultVatInt);
    $('#input-vatrate').val(defaultVatInt);

    if (quoteForm && quoteForm.length) {
        quoteForm.find('.is-invalid').removeClass('is-invalid');
        quoteForm.find('.invalid-feedback').text('');
    }
    if (formErrorMessageDiv && formErrorMessageDiv.length) formErrorMessageDiv.addClass('d-none').text('');
    if (quoteFormCard && quoteFormCard.length) quoteFormCard.removeClass('view-mode');

    if (quoteForm && quoteForm.length) {
        quoteForm.find('input, select, textarea').not('[readonly]').prop('disabled', false);
        quoteForm.find('#add-item-row-sq, .remove-item-row-sq, #btn-generate-quote-number').show().prop('disabled', false);
    }
    
    if (saveButton && saveButton.length) saveButton.show().prop('disabled', false);
    if (saveButtonText && saveButtonText.length) saveButtonText.show();
    if (saveButtonSpinner && saveButtonSpinner.length) saveButtonSpinner.addClass('d-none');

    const formTitleKey = isEdit ? 'edit_quote' : 'create_new_quote';
    const saveButtonTextKey = isEdit ? 'update' : 'save_quote';
    if (quoteFormTitle && quoteFormTitle.length) quoteFormTitle.text(LANG[formTitleKey] || (isEdit ? 'Sửa Báo Giá' : 'Tạo Báo Giá Mới'));
    if (saveButtonText && saveButtonText.length) saveButtonText.text(LANG[saveButtonTextKey] || (isEdit ? 'Cập nhật' : 'Lưu Báo Giá'));
    
    updateSTT();
    $('#btn-download-pdf').prop('disabled', true);
    console.log("Quote form reset complete.");
}


// --- Hàm Thêm Dòng Item vào Form Báo Giá ---
function addItemRow(data = {}) {
    console.log("Adding item row to quote. Data:", data);
    const templateRow = $('.item-row-template').clone();
    templateRow.removeClass('item-row-template d-none').removeAttr('style');
    const newItemIndex = itemTableBody.find('tr').length;

    const currentCurrencyCode = currencySelect.val();
    const currencySymbol = (currentCurrencyCode === 'VND') ? 'đ' : '$';
    templateRow.find('.currency-symbol-unit').text(currencySymbol);
    templateRow.find('.stt-col').text(newItemIndex + 1);

    templateRow.find('input, select').each(function () {
        const currentName = $(this).attr('name');
        if (currentName) { $(this).attr('name', currentName.replace(/items\[\d+\]/, `items[${newItemIndex}]`)); }
        $(this).removeClass('is-invalid').closest('td').find('.invalid-feedback').text('');

        // Khi thêm từ dữ liệu SERVER (edit) -> parseServerNumber
        if ($(this).is('input:not(.quantity, .unit-price), select, textarea')) {
            $(this).val('');
        } else if ($(this).hasClass('quantity')) {
            $(this).val(data.quantity !== undefined ? parseServerNumber(data.quantity) || 0 : 1);
        } else if ($(this).hasClass('unit-price')) {
            $(this).val(data.unit_price !== undefined ? parseServerNumber(data.unit_price) || 0 : 0);
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

    console.log("Added item row to quote with index:", newItemIndex);
    return templateRow;
}

/**
 * Thiết lập chế độ xem hoặc sửa cho form Báo Giá.
 * @param {boolean} isView True nếu là chế độ xem, False nếu là chế độ sửa.
 * @param {string} quoteNumber Số báo giá (để hiển thị trên tiêu đề).
 */
function setQuoteFormViewMode(isView, quoteNumber = '') {
    console.log(`SQ Form: Setting view mode to: ${isView} for quote: ${quoteNumber}`);

    if (typeof quoteForm === 'undefined' || !quoteForm.length) {
        console.error("setQuoteFormViewMode: Biến quoteForm chưa được định nghĩa hoặc không tìm thấy form.");
        return;
    }

    const formTitleText = isView ? (LANG['view_quote_details'] || 'Xem chi tiết Báo Giá') : (LANG['edit_quote'] || 'Sửa Báo Giá');
    const cancelBtnText = isView ? (LANG['close'] || 'Đóng') : (LANG['cancel'] || 'Hủy');

    if (typeof quoteFormTitle !== 'undefined' && quoteFormTitle.length) {
        quoteFormTitle.text(formTitleText + (quoteNumber ? ` (${quoteNumber})` : ''));
    }

    if (isView) {
        quoteForm.addClass('view-mode');
        quoteForm.find('input, select, textarea').not('#btn-cancel-quote-form, #btn-download-sq-pdf, #toggle-sq-signature').prop('disabled', true);
        quoteForm.find('#add-item-row-sq, .remove-item-row-sq, #btn-generate-quote-number').hide();
        if (typeof saveButton !== 'undefined' && saveButton.length) saveButton.hide();
    } else {
        quoteForm.removeClass('view-mode');
        quoteForm.find('input, select, textarea').not('[readonly]').prop('disabled', false);
        quoteForm.find('#add-item-row-sq, .remove-item-row-sq, #btn-generate-quote-number').show();
        if (typeof saveButton !== 'undefined' && saveButton.length) {
            saveButton.show();
            if (typeof saveButtonText !== 'undefined' && saveButtonText.length) saveButtonText.text(LANG['update'] || 'Cập nhật');
        }
    }
    $('#btn-cancel-quote-form').text(cancelBtnText);
    $('#btn-download-sq-pdf').prop('disabled', !quoteNumber && isView);
}


/**
 * Tải dữ liệu báo giá để sửa hoặc xem.
 * @param {number|string} quoteId ID của báo giá cần tải.
 * @param {string} callSource Nguồn gốc của lời gọi hàm (để debug).
 */
function loadQuoteForEdit(quoteId, callSource = "UnknownSQ_Default") {
    loadQuoteCallCounter++;
    const currentCallNumber = loadQuoteCallCounter;

    console.log(`SQ Form: CALL #${currentCallNumber} - loadQuoteForEdit initiated. Source: ${callSource}, Received quoteId:`, quoteId, "Type:", typeof quoteId);

    let parsedQuoteId = null;
    if (quoteId !== false && quoteId !== null && quoteId !== undefined && String(quoteId).trim() !== "") {
        parsedQuoteId = parseInt(String(quoteId), 10);
    }

    if (parsedQuoteId === null || isNaN(parsedQuoteId) || parsedQuoteId <= 0) {
        console.warn(`SQ Form: CALL #${currentCallNumber} - Invalid quote ID. Source: ${callSource}, Original Value:`, quoteId, "Parsed Value:", parsedQuoteId, "Type of Original:", typeof quoteId);
        if (typeof showUserMessage === 'function') {
            showUserMessage(LANG['error_invalid_quote_id_for_edit_or_view'] || 'Mã báo giá không hợp lệ để sửa/xem.', 'error');
        } else {
            alert(LANG['error_invalid_quote_id_for_edit_or_view'] || 'Mã báo giá không hợp lệ để sửa/xem.');
        }
        return; 
    } else {
        console.log(`SQ Form: CALL #${currentCallNumber} - Valid ID. Loading quote for edit/view. Source: ${callSource}, ID:`, parsedQuoteId);
    }

    $.ajax({
        url: AJAX_URL.sales_quote, 
        type: 'GET',
        data: { action: 'get_details', id: parsedQuoteId },
        dataType: 'json',
        beforeSend: function () {
            if (typeof quoteFormCard !== 'undefined' && quoteFormCard.length) {
                quoteFormCard.addClass('opacity-50');
            }
            $('#btn-save-quote, #btn-cancel-quote-form, #btn-download-sq-pdf').prop('disabled', true);
        },
        success: function (response) {
            console.log(`SQ Form: CALL #${currentCallNumber} - Quote details received (Source: ${callSource}):`, response);
            if (response && response.success && response.data && response.data.quote) {
                if(typeof resetQuoteForm !== 'function') {
                    console.error("SQ Form: resetQuoteForm function is not defined!");
                } else {
                   resetQuoteForm(true);
                }

                const quoteHeader = response.data.quote;
                const quoteItems = response.data.items;

                $('#quote_id').val(quoteHeader.id);
                $('#quote_number').val(quoteHeader.quote_number);
                 
                const quoteDateInput = document.querySelector("#quote_date");
                if (quoteDateInput && quoteDateInput._flatpickr) {
                    const flatpickrInstance = quoteDateInput._flatpickr;
                    let displayFormat = (typeof USER_SETTINGS !== 'undefined' && USER_SETTINGS.date_format_display) ? USER_SETTINGS.date_format_display : 'd/m/Y';
                    if (quoteHeader.quote_date_formatted) {
                        try { flatpickrInstance.setDate(quoteHeader.quote_date_formatted, true, displayFormat); }
                        catch (e) { 
                            console.error("SQ Form: Error setting datepicker with quote_date_formatted:", e);
                            if(quoteHeader.quote_date) flatpickrInstance.setDate(quoteHeader.quote_date, true, 'Y-m-d');
                        }
                    } else if (quoteHeader.quote_date) {
                        try { flatpickrInstance.setDate(quoteHeader.quote_date, true, 'Y-m-d'); }
                        catch (e) { console.error("SQ Form: Error setting datepicker with quote_date (Y-m-d):", e); }
                    }
                } else {
                     console.warn("SQ Form: Flatpickr instance for #quote_date not found.");
                }

                // Khách hàng
                const customerSnapshot = quoteHeader.customer_info_snapshot;
                if (quoteHeader.customer_id && customerSnapshot) {
                    try {
                        const parsedInfo = (typeof customerSnapshot === 'string') ? JSON.parse(customerSnapshot) : customerSnapshot;
                        if (parsedInfo && typeof parsedInfo === 'object') {
                            $('#partner_id').val(quoteHeader.customer_id);
                            $('#partner_autocomplete').val(parsedInfo.name || '');
                            $('#partner_address_display').text(parsedInfo.address || '-');
                            $('#partner_tax_id_display').text(parsedInfo.tax_id || '-');
                            $('#partner_phone_display').text(parsedInfo.phone || '-');
                            $('#partner_email_display').text(parsedInfo.email || '-');
                            $('#partner_contact_person_display').text(parsedInfo.contact_person || '-');
                        } else { throw new Error("Parsed customer info is not an object."); }
                    } catch (e) {
                        console.error("SQ Form: Error processing customer_info_snapshot for quote " + (quoteHeader.id || parsedQuoteId) + ":", e);
                        $('#partner_id').val(''); $('#partner_autocomplete').val('');
                        $('#partner_address_display, #partner_tax_id_display, #partner_phone_display, #partner_email_display, #partner_contact_person_display').text('-');
                    }
                } else {
                    console.warn("SQ Form: Missing customer_id or customer_info_snapshot for quote " + (quoteHeader.id || parsedQuoteId));
                     $('#partner_id').val(''); $('#partner_autocomplete').val('');
                     $('#partner_address_display, #partner_tax_id_display, #partner_phone_display, #partner_email_display, #partner_contact_person_display').text('-');
                }

                if (typeof currencySelect !== 'undefined' && currencySelect.length) {
                     currencySelect.val(quoteHeader.currency || 'VND');
                }

                const notesContent = quoteHeader.notes || '';
                if (typeof ckEditorInstances !== 'undefined' && ckEditorInstances && ckEditorInstances['notes']) {
                    ckEditorInstances['notes'].setData(notesContent);
                } else {
                    $('#notes').val(notesContent);
                }

                // VAT từ DB -> parseServerNumber -> integer
                const vatRateValServer = (typeof parseServerNumber === 'function')
                    ? parseServerNumber(quoteHeader.vat_rate)
                    : parseFloat(quoteHeader.vat_rate || 0);
                const defaultVatInt = Math.round(parseFloat(vatDefaultRate || 0));
                const vatInt = isNaN(vatRateValServer) ? defaultVatInt : Math.round(vatRateValServer);

                $('#summary-vat-rate').val(vatInt);
                $('#input-vatrate').val(vatInt);

                // Items
                if (typeof itemTableBody !== 'undefined' && itemTableBody.length) {
                    itemTableBody.empty();
                    if (Array.isArray(quoteItems) && quoteItems.length > 0) {
                        quoteItems.forEach(item => {
                            if(typeof addItemRow === "function") {
                                addItemRow(item);
                            } else { console.error("SQ Form: addItemRow function is not defined."); }
                        });
                    } else {
                        if(typeof addItemRow === "function") addItemRow();
                        else { console.error("SQ Form: addItemRow function is not defined."); }
                    }
                } else {
                    console.warn("SQ Form: itemTableBody (for quote form items) is not defined or not found.");
                }

                if (typeof calculateSummaryTotals  === "function") {
                    calculateSummaryTotals ();
                } else { console.error("SQ Form: calculateSummaryTotals function is not defined.");}
                
                $('#btn-download-sq-pdf').prop('disabled', !quoteHeader.quote_number);

                // Chế độ xem/sửa
                let currentQuoteFormIsViewMode = (quoteHeader.status !== 'draft');
                if (typeof setQuoteFormViewMode === "function") {
                    setQuoteFormViewMode(currentQuoteFormIsViewMode, quoteHeader.quote_number);
                } else {
                    console.error("SQ Form: setQuoteFormViewMode function is not defined.");
                }

                if (typeof quoteFormCard !== 'undefined' && quoteFormCard.length) quoteFormCard.slideDown();
                if (typeof quoteListTitle !== 'undefined' && quoteListTitle.length) quoteListTitle.hide();
                
                if (typeof quoteFormCard !== 'undefined' && quoteFormCard.length && !currentQuoteFormIsViewMode) {
                    $('html, body').animate({ scrollTop: quoteFormCard.offset().top - 20 }, 300);
                }

            } else {
                let error_msg_load = response.message || LANG['error_loading_quote_details_data'] || 'Lỗi khi tải dữ liệu chi tiết báo giá hoặc dữ liệu không đầy đủ.';
                if(typeof showUserMessage === 'function') showUserMessage(error_msg_load, 'error');
                else alert(error_msg_load);
                
                if(typeof resetQuoteForm === 'function') resetQuoteForm();
                if (typeof quoteFormCard !== 'undefined' && quoteFormCard.length) quoteFormCard.removeClass('view-mode');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
             console.error("SQ Form: AJAX Error loading quote details:", textStatus, errorThrown, jqXHR.responseText);
            let error_msg_server = LANG['server_error_loading_details'] || 'Lỗi máy chủ khi tải chi tiết báo giá.';
            if(typeof showUserMessage === 'function') showUserMessage(error_msg_server, 'error');
            else alert(error_msg_server);
            
            if(typeof resetQuoteForm === 'function') resetQuoteForm();
            if (typeof quoteFormCard !== 'undefined' && quoteFormCard.length) quoteFormCard.removeClass('view-mode');
        },
        complete: function () {
            if (typeof quoteFormCard !== 'undefined' && quoteFormCard.length) {
                quoteFormCard.removeClass('opacity-50');
                if (!quoteFormCard.hasClass('view-mode')) {
                    if (typeof saveButton !== 'undefined' && saveButton.length) {
                        saveButton.prop('disabled', false);
                    }
                }
            }
            $('#btn-cancel-quote-form, #btn-download-sq-pdf').prop('disabled', false);
        }
    });
}

// === Event: làm tròn VAT rate về số nguyên ngay khi nhập ===
$(document).on('change blur input', '#summary-vat-rate', function () {
    const v = parseInputNumber($(this).val()) || 0;
    const iv = Math.round(v);
    if (String(iv) !== $(this).val()) {
        $(this).val(iv);
    }
    if (typeof calculateSummaryTotals === 'function') {
        calculateSummaryTotals();
    }
});

// === Event: đổi tiền tệ -> cập nhật ký hiệu & tính lại tổng ===
$(document).on('change', '#currency', function () {
    const currentCurrencyCode = $(this).val();
    const currencySymbol = (currentCurrencyCode === 'VND') ? 'đ' : '$';
    // cập nhật ký hiệu ở các dòng hiện có
    if (typeof itemTableBody !== 'undefined' && itemTableBody.length) {
        itemTableBody.find('.currency-symbol-unit').text(currencySymbol);
    }
    if (typeof calculateSummaryTotals === 'function') {
        calculateSummaryTotals();
    }
});

// === Trợ giúp: thêm option và select cho các select2/normal select ===
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
