// File: assets/js/sq_helpers.js

// === Chuẩn hóa số đến từ Server/DB (MySQL/API) ===
// Dùng khi LOAD dữ liệu từ DB vào form (edit/xem).
function parseServerNumber(val) {
    if (val === null || val === undefined) return 0;
    let s = String(val).trim();
    if (s === '') return 0;
    s = s.replace(/\s+/g, '');

    // 1) 123.456 (dấu . là thập phân) hoặc 100.000
    if (/^\d+(\.\d+)?$/.test(s)) return parseFloat(s);
    // 2) 123,456 (dấu , là thập phân)
    if (/^\d+(,\d+)?$/.test(s)) return parseFloat(s.replace(',', '.'));
    // 3) 1.234,56 (chấm nghìn, phẩy thập phân)
    if (/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(s)) return parseFloat(s.replace(/\./g, '').replace(',', '.'));
    // 4) 1,234.56 (phẩy nghìn, chấm thập phân)
    if (/^\d{1,3}(,\d{3})+(\.\d+)?$/.test(s)) return parseFloat(s.replace(/,/g, ''));
    // 5) Fallback
    return parseFloat(s.replace(/\./g, '').replace(/,/g, '.')) || 0;
}

// --- Hàm Hiển Thị Thông Báo Người Dùng ---
function showUserMessage(message, type = 'success') {
    let alertContainer = $('#alert-container');
    if (!alertContainer.length) {
        $('body').append('<div id="alert-container" class="position-fixed top-0 end-0 p-3" style="z-index:1100"></div>');
        alertContainer = $('#alert-container');
    }
    if (alertContainer.length) {
        let alertType = (type === 'error' ? 'danger' : (type === 'warning' ? 'warning' : (type === 'info' ? 'info' : 'success')));
        let alertId = 'alert-' + Date.now();
        let alertHtml = `<div id="${alertId}" class="alert alert-${alertType} alert-dismissible fade show" role="alert" style="min-width:250px;">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        alertContainer.append(alertHtml);

        let newAlert = $('#' + alertId);
        setTimeout(() => {
            try {
                const bsAlert = bootstrap.Alert.getInstance(newAlert[0]);
                if (bsAlert) {
                    bsAlert.close();
                } else {
                    newAlert.remove();
                }
            } catch (e) {
                console.warn("Could not close alert:", e, newAlert);
                newAlert.remove();
            }
        }, 5000);
    } else {
        alert((type === 'error' ? 'ERROR: ' : (type === 'warning' ? 'WARNING: ' : (type === 'info' ? 'INFO: ' : ''))) + message);
    }
}

// --- Hàm Xử lý Lỗi Validation từ Server ---
function handleFormValidationErrors(errors) {
    formErrorMessageDiv.text(LANG['validation_failed'] || 'Vui lòng kiểm tra và sửa các lỗi trong form.').removeClass('d-none');
    const addError = (fieldName, errorMessage) => {
        let inputElement = $(`#${fieldName}`);
        if (inputElement.length === 0 && fieldName === 'partner_id') {
            inputElement = $('#partner_autocomplete');
        } else if (inputElement.length === 0 && fieldName === 'vat_rate') {
            inputElement = $('#summary-vat-rate');
        }

        if (inputElement.length) {
            inputElement.addClass('is-invalid');
            let feedbackDiv = inputElement.closest('.mb-2, .mb-3, .input-group, .col-sm-7, .col-md-6, .col-sm-5').find('.invalid-feedback').first();
            if (feedbackDiv.length) {
                feedbackDiv.text(errorMessage).show();
            } else {
                inputElement.after(`<div class="invalid-feedback d-block">${escapeHtml(errorMessage)}</div>`);
            }
        } else {
            console.warn(`Input field not found for validation error: "${fieldName}"`);
            formErrorMessageDiv.append(`<br/>(${fieldName}): ${escapeHtml(errorMessage)}`);
        }
    };

    $.each(errors, (fieldName, messages) => {
        if (fieldName !== 'items' && fieldName !== 'items_general' && Array.isArray(messages) && messages.length > 0) {
            addError(fieldName, messages[0]);
        }
    });

    if (errors['items_general'] && Array.isArray(errors['items_general']) && errors['items_general'].length > 0) {
        errors['items_general'].forEach(msg => formErrorMessageDiv.append('<br/>' + escapeHtml(msg)));
    }

    if (errors.items && typeof errors.items === 'object') {
        $.each(errors.items, (itemIndex, itemErrors) => {
            const row = itemTableBody.find('tr').eq(parseInt(itemIndex));
            if (row.length && Array.isArray(itemErrors)) {
                itemErrors.forEach(errorMessage => {
                    let errorHandled = false;
                    if (errorMessage.toLowerCase().includes('product')) {
                        row.find('.product-autocomplete').addClass('is-invalid').closest('td').find('.invalid-feedback').text(errorMessage).show();
                        errorHandled = true;
                    } else if (errorMessage.toLowerCase().includes('quantity')) {
                        row.find('.quantity').addClass('is-invalid').closest('td').find('.invalid-feedback').text(errorMessage).show();
                        errorHandled = true;
                    } else if (errorMessage.toLowerCase().includes('price')) {
                        row.find('.unit-price').addClass('is-invalid').closest('td').find('.invalid-feedback').text(errorMessage).show();
                        errorHandled = true;
                    }
                    if (!errorHandled) {
                        row.find('td:last').append(`<div class="text-danger small invalid-feedback d-block">${escapeHtml(errorMessage)}</div>`);
                    }
                });
            }
        });
    }

    if (formErrorMessageDiv.is(':visible') && !formErrorMessageDiv.hasClass('d-none')) {
        $('html,body').animate({ scrollTop: formErrorMessageDiv.offset().top - 20 }, 300);
    } else {
        const firstInvalidElement = quoteForm.find('.is-invalid').first();
        if (firstInvalidElement.length) {
            $('html,body').animate({ scrollTop: firstInvalidElement.offset().top - 20 }, 300);
        }
    }
}


// --- Hàm định dạng chi tiết Child Row (DataTables) ---
function formatChildRowDetails(details, currency) {
    if (!details || details.length === 0) {
        return `<div class="p-3 text-muted text-center">${LANG['no_items_in_quote'] || 'Không tìm thấy sản phẩm trong báo giá.'}</div>`;
    }

    let htmlContent = `<div class="child-row-container p-2">
        <h6 class="ms-3 mt-1 mb-2">${LANG['item_details'] || 'Chi tiết sản phẩm'}:</h6>
        <table class="table table-sm table-bordered table-striped child-row-details-table" style="width:95%;margin:auto;">
            <thead class="table-light">
                <tr>
                    <th style="width:30px;">#</th>
                    <th>${LANG['product'] || 'Sản phẩm'}</th>
                    <th>${LANG['category'] || 'Danh mục'}</th>
                    <th>${LANG['unit'] || 'Đơn vị'}</th>
                    <th class="text-end">${LANG['quantity'] || 'Số lượng'}</th>
                    <th class="text-end">${LANG['unit_price'] || 'Đơn giá'}</th>
                    <th class="text-end">${LANG['line_subtotal'] || 'Tổng cộng dòng'}</th>
                </tr>
            </thead>
            <tbody>`;

    let quantityFormatter = { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 2 };
    let priceFormatter = (currency === 'VND'
        ? { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 }
        : { style: 'decimal', minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let displayLocale = (typeof LANG !== 'undefined' && LANG.language === 'vi' ? 'vi-VN' : 'en-US');
    let currencySymbolDisplay = (currency === 'VND' ? ' đ' : (currency === 'USD' ? ' $' : ` ${currency}`));

    details.forEach((item, index) => {
        // DỮ LIỆU SERVER -> parseServerNumber khi hiển thị
        const quantity = parseServerNumber(item.quantity) || 0;
        const unitPrice = parseServerNumber(item.unit_price) || 0;
        const lineSubtotal = quantity * unitPrice;

        htmlContent += `<tr>
            <td class="text-center">${index + 1}</td>
            <td>${escapeHtml(item.product_name_snapshot)}</td>
            <td>${escapeHtml(item.category_snapshot) || '-'}</td>
            <td>${escapeHtml(item.unit_snapshot) || '-'}</td>
            <td class="text-end">${quantity.toLocaleString(displayLocale, quantityFormatter)}</td>
            <td class="text-end">${unitPrice.toLocaleString(displayLocale, priceFormatter)}${currencySymbolDisplay}</td>
            <td class="text-end fw-bold">${lineSubtotal.toLocaleString(displayLocale, priceFormatter)}${currencySymbolDisplay}</td>
        </tr>`;
    });

    htmlContent += `</tbody></table></div>`;
    return htmlContent;
}

// === Hàm Định Dạng Số Linh Hoạt Theo 'vi-VN' ===
function formatFlexibleNumber(number) {
    const displayLocale = (typeof LANG !== 'undefined' && LANG.language === 'vi' ? 'vi-VN' : 'en-US');

    const formatted = new Intl.NumberFormat(displayLocale, {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 10
    }).format(number);

    if (formatted.includes(',') && formatted.match(/,0+$/)) {
        return formatted.replace(/,0+$/, '');
    }
    return formatted;
}

// === Hàm Tính Tổng Tiền Dòng (mỗi hàng item) ===
function calculateLineTotal(row) {
    const currentCurrency = currencySelect.val();
    // INPUT USER -> parseInputNumber
    const quantity = parseInputNumber(row.find('.quantity').val()) || 0;
    const unitPrice = parseInputNumber(row.find('.unit-price').val()) || 0;
    const lineTotal = quantity * unitPrice;

    row.find('.line-total').val(formatFlexibleNumber(lineTotal));
    calculateSummaryTotals(); // Cập nhật lại tổng đơn
}

// === Hàm Tính Tổng Cộng Báo Giá ===
function calculateSummaryTotals() {
    const currentCurrency = currencySelect.val();

    // VAT từ input -> ép integer
    let vatRate = parseInputNumber($('#summary-vat-rate').val()) || 0;
    vatRate = Math.round(vatRate);

    let subTotal = 0;

    // Tính subtotal theo từng dòng (đọc từ INPUT USER)
    itemTableBody.find('tr').each(function () {
        const quantity = parseInputNumber($(this).find('.quantity').val()) || 0;
        const unitPrice = parseInputNumber($(this).find('.unit-price').val()) || 0;
        if (quantity > 0 && unitPrice >= 0) {
            subTotal += quantity * unitPrice;
        }
    });

    // Làm tròn hiển thị theo tiền tệ
    const roundForCurrency = (val) => (currentCurrency === 'VND'
        ? Math.round(val)                  // VND: 0 chữ số
        : Math.round(val * 100) / 100      // USD/khác: 2 chữ số
    );

    const displaySubTotal = roundForCurrency(subTotal);
    const vatTotal = roundForCurrency(displaySubTotal * (vatRate / 100));
    const grandTotal = roundForCurrency(displaySubTotal + vatTotal);

    const displayLocale = (typeof LANG !== 'undefined' && LANG.language === 'vi' ? 'vi-VN' : 'en-US');
    const currencySymbolDisplay = (currentCurrency === 'VND' ? ' đ' : (currentCurrency === 'USD' ? ' $' : ` ${currentCurrency}`));

    // Hiển thị số đã làm tròn theo tiền tệ
    $('#summary-subtotal').text(formatFlexibleNumber(displaySubTotal) + currencySymbolDisplay);
    $('#summary-vattotal').text(formatFlexibleNumber(vatTotal) + currencySymbolDisplay);
    $('#summary-grandtotal').text(formatFlexibleNumber(grandTotal) + currencySymbolDisplay);

    // Giá trị submit về backend (giữ 2 số lẻ để chính xác)
    $('#input-subtotal').val(subTotal.toFixed(2));
    $('#input-vattotal').val((subTotal * vatRate / 100).toFixed(2));
    $('#input-grandtotal').val((subTotal * (1 + vatRate / 100)).toFixed(2));
    $('#input-vatrate').val(vatRate); // integer
}

// --- Hàm Cập Nhật Số Thứ Tự và Name Attribute của Item Rows ---
function updateSTT() {
    if (itemTableBody && itemTableBody.length) {
        itemTableBody.find('tr').each(function (index) {
            $(this).find('.stt-col').text(index + 1);
            $(this).find('input, select').each(function () {
                const currentName = $(this).attr('name');
                if (currentName) {
                    $(this).attr('name', currentName.replace(/items\[\d+\]/, `items[${index}]`));
                }
            });
        });
    }
}
