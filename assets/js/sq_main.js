// cleaned: console logs optimized, debug system applied
// File: assets/js/sq_main.js
function loadAndApplySavedQuoteFilters() {
    devLog("--- Bắt đầu hàm loadAndApplySavedQuoteFilters ---");
    try {
        // Sử dụng key riêng cho trang báo giá
        const savedYear = localStorage.getItem('salesQuoteFilterYear');
        const savedMonth = localStorage.getItem('salesQuoteFilterMonth');

        devLog("Đọc từ localStorage (Báo giá): Năm=" + savedYear + ", Tháng=" + savedMonth);

        if (savedYear) {
            $('#filterYear').val(savedYear);
        }
        if (savedMonth) {
            $('#filterMonth').val(savedMonth);
        }

    } catch (e) {
        console.error('Không thể tải bộ lọc Báo giá đã lưu.', e);
    }
    devLog("--- Kết thúc hàm loadAndApplySavedQuoteFilters ---");
}
const QuoteToOrderDataBridge = {
    loadData: function() {
        devLog("QuoteToOrderDataBridge.loadData SHIM CALLED");
        const dataString = localStorage.getItem('quoteToOrderData');
        if (dataString) {
            try { return JSON.parse(dataString); } catch(e) { console.error("Error parsing quoteToOrderData from localStorage in SHIM", e); return null; }
        }
        return null;
    },
    clearData: function() {
        devLog("QuoteToOrderDataBridge.clearData SHIM CALLED");
        localStorage.removeItem('quoteToOrderData');
        localStorage.removeItem('triggerCreateOrderFromQuote');
    }
};
// Khai báo và khởi tạo biến toàn cục ở đầu file
let ckEditorInstances = {};

// --- Hàm Khởi Tạo CKEditor ---
// (Định nghĩa hàm ở phạm vi toàn cục là tốt)
function initializeCKEditor(elementId, configOptions = {}) {
    const element = document.querySelector('#' + elementId);
    if (element) {
        ClassicEditor
            .create(element, {
                // Cấu hình toolbar của bạn
                toolbar: {
                    items: [
                        'undo', 'redo', '|', 'heading', '|', 'bold', 'italic', '|', 'link', 'insertImage', 'insertTable', 'blockQuote', 'mediaEmbed', '|', 'bulletedList', 'numberedList'
                    ],
                    shouldNotGroupWhenFull: true
                },
                language: 'vi',
                table: {
                    contentToolbar: [ 'tableColumn', 'tableRow', 'mergeTableCells' ]
                },
                ...configOptions
            })
            .then(editor => {
    ckEditorInstances[elementId] = editor;
    devLog('CKEditor 5 (Quote) initialized for: #' + elementId);

    // Thiết lập chiều cao tối thiểu
    if (editor.ui && editor.ui.view && editor.ui.view.editable && editor.ui.view.editable.element) {
        editor.ui.view.editable.element.style.minHeight = '180px';
    } else {
        devLog('Could not set minHeight for CKEditor (Quote) #' + elementId);
    }

    // ✅ Thêm đoạn này để giảm khoảng cách giữa các dòng (line-height)
    editor.editing.view.change(writer => {
        writer.setStyle('line-height', '0.8', editor.editing.view.document.getRoot());
    });
})
    } else {
        devLog(`Element with ID #${elementId} not found for CKEditor (Quote) initialization.`);
    }
}

// --- Hàm Khởi Tạo Trang Báo Giá ---
function initializePage() {
    devLog("Initializing Sales Quotes page...");
    // ... (code của bạn trong initializePage giữ nguyên) ...
    quoteFormCard = $('#quote-form-card');
    quoteForm = $('#quote-form');
    quoteFormTitle = $('#quote-form-title');
    quoteListTitle = $('#quote-list-title');
    quoteTableElement = $('#sales-quotes-table'); 
    itemTableBody = $('#item-details-body');
    saveButton = $('#btn-save-quote');
    saveButtonText = saveButton.find('.save-text');
    saveButtonSpinner = saveButton.find('.spinner-border');
    formErrorMessageDiv = $('#form-error-message');
    currencySelect = $('#currency_select');
    buyerSignatureImg = $('#buyer-signature');
    toggleSignatureButton = $('#toggle-signature');
    basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));

    if (typeof webSignatureSrc !== 'undefined' && webSignatureSrc && buyerSignatureImg.length) {
        buyerSignatureImg.attr('src', webSignatureSrc);
        const savedState = localStorage.getItem(signatureLocalStorageKey);
        let shouldBeVisible = savedState === null ? true : (savedState === 'true'); // Mặc định là true nếu chưa có trong localStorage
        if (shouldBeVisible) {
            buyerSignatureImg.show();
            if (toggleSignatureButton.length) toggleSignatureButton.text(LANG?.hide_signature ?? 'Ẩn chữ ký');
        } else {
            buyerSignatureImg.hide();
            if (toggleSignatureButton.length) toggleSignatureButton.text(LANG?.show_signature ?? 'Hiện chữ ký');
        }
    } else {
        if (buyerSignatureImg.length) buyerSignatureImg.hide().removeAttr('src');
        if (toggleSignatureButton.length) toggleSignatureButton.hide();
    }

    if (buyerSignatureImg.length) {
        buyerSignatureImg.on('load', function () { devLog("Buyer signature for quote loaded."); })
                         .on('error', function () { $(this).hide(); if (toggleSignatureButton.length) toggleSignatureButton.hide(); });
    }

    // Các hàm initialize này có trong sq_form.js hoặc file khác và hoạt động cho form
    if (typeof initializeDatepicker === 'function') initializeDatepicker();
    if (typeof initializeCustomerAutocomplete === 'function') initializeCustomerAutocomplete();
    if (typeof initializeProductAutocomplete === 'function') {
        initializeProductAutocomplete('#item-details-body'); // Cho item TRONG FORM
        initializeProductAutocomplete('.item-row-template'); // Cho template TRONG FORM
    }

    // Khởi tạo DataTable
    if (quoteTableElement.length) {
        if (typeof initializeSalesQuoteDataTable === 'function') {
            initializeSalesQuoteDataTable(); // Hàm này từ sq_datatable.js
        } else {
            console.error("initializeSalesQuoteDataTable function is not defined (ensure sq_datatable.js is loaded).");
        }
    } else {
        console.error("Sales Quotes Table element ('#sales-quotes-table') not found in DOM.");
    }


    if (quoteFormCard) quoteFormCard.hide();
    devLog("Sales Quotes page basic structure initialized.");
}

// --- Document Ready ---
$(document).ready(function () {
    devLog("Document ready for Sales Quotes. Starting main initialization...");
    loadAndApplySavedQuoteFilters();
    // Khởi tạo các giá trị ban đầu, các elements, và DataTable
    initializePage();    
    
    // Gắn các listener cho form và các element khác (từ sq_events.js)
    if (typeof setupEventListeners === 'function') {
        setupEventListeners(); 
    } else {
        devLog("setupEventListeners function (from sq_events.js) not found.");
    }

    // --- Thêm SHIM sớm (đặt gần đầu file sq_main.js nếu cần) ---
    if (typeof parseServerNumber !== 'function') {
    window.parseServerNumber = function (val) {
        if (val === null || val === undefined) return 0;
        let s = String(val).trim().replace(/\s+/g,'');
        if (s === '') return 0;
        if (/^\d+(\.\d+)?$/.test(s)) return parseFloat(s);
        if (/^\d+(,\d+)?$/.test(s)) return parseFloat(s.replace(',', '.'));
        if (/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(s)) return parseFloat(s.replace(/\./g,'').replace(',', '.'));
        if (/^\d{1,3}(,\d{3})+(\.\d+)?$/.test(s)) return parseFloat(s.replace(/,/g,''));
        return parseFloat(s.replace(/\./g,'').replace(/,/g,'.')) || 0;
    };
    }

    // ***** GẮN LISTENER CHO CÁC NÚT TRONG DATATABLE Ở ĐÂY *****
    // Code này chạy sau khi initializePage() đã định nghĩa quoteTableElement 
    // và đã gọi initializeSalesQuoteDataTable() để khởi tạo bảng.
    if (typeof quoteTableElement !== 'undefined' && quoteTableElement.length && $.fn.dataTable.isDataTable(quoteTableElement)) {
        devLog("Attaching listeners to DataTable in $(document).ready() of sq_main.js");

        quoteTableElement.on('click', '.btn-create-order-from-quote', function() {
            devLog("Button .btn-create-order-from-quote was clicked! (Listener in sq_main.js doc.ready)");
            const $button = $(this);
            const quoteId = $button.data('quote-id');
            const quoteNumber = $button.data('quote-number');
            devLog(`Quote ID: ${quoteId}, Quote Number: ${quoteNumber}`);

            if (typeof LANG === 'undefined' || typeof PROJECT_BASE_URL === 'undefined') {
                console.error("LANG or PROJECT_BASE_URL is not defined. Cannot proceed.");
                alert("Lỗi cấu hình: Biến ngôn ngữ hoặc đường dẫn gốc chưa được định nghĩa.");
                return;
            }

            Swal.fire({
                title: LANG['processing'] || 'Đang xử lý...',
                text: LANG['fetching_quote_details'] || 'Đang lấy chi tiết báo giá...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: PROJECT_BASE_URL + 'process/sales_quote_handler.php',
                type: 'POST',
                data: { action: 'get_details', id: quoteId },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response && response.success && response.data && response.data.items) {
                        const quoteHeader = response.data.quote || {};
                        const itemsFromServer = response.data.items;
                        // Sắp xếp items theo tên nhà cung cấp để dễ nhìn (Grouping)
                        itemsFromServer.sort((a, b) => {
                            const nameA = (a.supplier_name || "").toLowerCase();
                            const nameB = (b.supplier_name || "").toLowerCase();
                            return nameA.localeCompare(nameB);
                        });

                        // Lấy danh sách NCC duy nhất từ items (Chỉ lấy NCC còn hàng chưa đặt hết)
                        const uniqueSuppliers = [];
                        const supplierMap = {};
                        itemsFromServer.forEach(item => {
                            const qty = parseServerNumber(item.quantity) || 0;
                            const ordered = parseServerNumber(item.ordered_quantity) || 0;
                            const remaining = qty - ordered;

                            if (item.supplier_id && !supplierMap[item.supplier_id] && remaining > 0) {
                                supplierMap[item.supplier_id] = item.supplier_name || `NCC ID: ${item.supplier_id}`;
                                uniqueSuppliers.push({ id: item.supplier_id, name: supplierMap[item.supplier_id] });
                            }
                        });

                        let htmlContent = '<div class="mb-3" style="text-align: left;">';
                        htmlContent += '<label class="form-label small fw-bold">Lọc theo Nhà Cung Cấp:</label>';
                        htmlContent += '<select id="swalSupplierFilter" class="form-select form-select-sm">';
                        htmlContent += '<option value="">-- Tất cả Nhà Cung Cấp --</option>';
                        uniqueSuppliers.forEach(s => {
                            htmlContent += `<option value="${s.id}">${s.name}</option>`;
                        });
                        htmlContent += '</select></div>';
                        
                        htmlContent += '<p style="text-align: left; font-size: 14px;">Vui lòng chọn sản phẩm và nhập số lượng muốn đặt:</p>';
                        htmlContent += '<div class="table-responsive" style="max-height: 400px;"><table class="table table-bordered table-sm" style="font-size: 14px; text-align: left;" id="swalPoItemsTable">';
                        htmlContent += '<thead class="thead-light"><tr><th style="width:40px; text-align:center;"><input type="checkbox" id="selectAllPoItems" checked></th><th>Sản phẩm</th><th>Nhà cung cấp</th><th>SL BG</th><th>Đã đặt</th><th>SL mới</th></tr></thead><tbody>';
                        
                        let visibleRowsCount = 0;
                        itemsFromServer.forEach((item, index) => {
                            const qty = parseServerNumber(item.quantity) || 0;
                            const ordered = parseServerNumber(item.ordered_quantity) || 0;
                            const remaining = qty - ordered;
                            const suggestedQty = remaining > 0 ? remaining : 0;
                            const sName = item.supplier_name || '<span class="text-muted italic">Chưa gán</span>';
                            const itemName = item.item_name || item.product_name_snapshot || 'Sản phẩm không tên';
                            
                            // Ẩn các sản phẩm đã được đặt đủ số lượng (Tránh tạo đơn trùng)
                            const rowStyle = suggestedQty <= 0 ? 'display: none;' : '';
                            if (suggestedQty > 0) visibleRowsCount++;

                            htmlContent += `<tr class="po-item-row" data-supplier-id="${item.supplier_id || ''}" style="${rowStyle}">
                                <td style="text-align:center;"><input type="checkbox" class="po-item-checkbox" data-index="${index}" ${suggestedQty > 0 ? 'checked' : ''}></td>
                                <td style="white-space: normal; word-wrap: break-word; max-width: 200px;">${itemName}</td>
                                <td>${sName}</td>
                                <td>${qty}</td>
                                <td>${ordered}</td>
                                <td><input type="number" class="form-control form-control-sm po-item-qty" data-index="${index}" value="${suggestedQty}" min="0" style="width: 70px;"></td>
                            </tr>`;
                        });
                        
                        if (visibleRowsCount === 0) {
                            htmlContent = '<div class="alert alert-info text-start">Tất cả sản phẩm trong Báo giá này đã được tạo Đơn Đặt Hàng đủ số lượng. Không còn sản phẩm nào để tạo thêm Đơn mới.</div>';
                        } else {
                            htmlContent += '</tbody></table></div>';
                        }

                        Swal.fire({
                            title: `Tạo Đơn từ BG: ${quoteNumber}`,
                            html: htmlContent,
                            width: '800px',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: LANG['yes_create_and_redirect'] || 'Tạo Đơn Đặt Hàng',
                            cancelButtonText: LANG['no_cancel'] || 'Hủy',
                            didOpen: () => {
                                $('#selectAllPoItems').on('change', function() {
                                    $('.po-item-checkbox:visible').prop('checked', this.checked);
                                });

                                const $filter = $('#swalSupplierFilter');
                                $filter.on('change', function() {
                                    const supplierId = $(this).val();
                                    if (supplierId === "") {
                                        $('.po-item-row').show();
                                    } else {
                                        $('.po-item-row').hide();
                                        const visibleRows = $(`.po-item-row[data-supplier-id="${supplierId}"]`);
                                        visibleRows.show();
                                        
                                        // Tự động chọn các item của NCC này (thông minh hơn)
                                        $('.po-item-checkbox').prop('checked', false); // Bỏ chọn hết trước
                                        visibleRows.find('.po-item-checkbox').each(function() {
                                            const idx = $(this).data('index');
                                            const qtyInput = $(`.po-item-qty[data-index="${idx}"]`).val();
                                            if (parseFloat(qtyInput) > 0) {
                                                $(this).prop('checked', true);
                                            }
                                        });
                                    }
                                    // Cập nhật trạng thái checkbox "Chọn tất cả" dựa trên các item đang hiện
                                    const visibleCheckboxes = $('.po-item-checkbox:visible');
                                    const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.filter(':not(:checked)').length === 0;
                                    $('#selectAllPoItems').prop('checked', allVisibleChecked);
                                });

                                // TỰ ĐỘNG: Nếu chỉ có 1 NCC duy nhất trong list, chọn luôn NCC đó
                                if (uniqueSuppliers.length === 1) {
                                    $filter.val(uniqueSuppliers[0].id).trigger('change');
                                    devLog("Auto-selected the only available supplier:", uniqueSuppliers[0].name);
                                }
                            },
                            preConfirm: () => {
                                const selectedItems = [];
                                const selectedSupplierId = $('#swalSupplierFilter').val();
                                let finalSupplierId = selectedSupplierId;
                                let finalSupplierName = selectedSupplierId ? $('#swalSupplierFilter option:selected').text() : null;

                                const selectedSuppliersInRows = new Set();

                                $('.po-item-checkbox:checked:visible').each(function() {
                                    const idx = $(this).data('index');
                                    const qtyInput = $(`.po-item-qty[data-index="${idx}"]`).val();
                                    const parsedQty = parseFloat(qtyInput) || 0;
                                    
                                    if (parsedQty > 0) {
                                        const origItem = itemsFromServer[idx];
                                        selectedItems.push({
                                            detail_id: origItem.detail_id || origItem.id || null, 
                                            product_id: origItem.product_id || null,
                                            product_name_snapshot: origItem.item_name || origItem.product_name_snapshot || '',
                                            category_snapshot: origItem.category_snapshot || '',
                                            unit_snapshot: origItem.unit_snapshot || '',
                                            quantity: parsedQty,
                                            unit_price: parseServerNumber(origItem.unit_price) || 0,
                                            supplier_id: origItem.supplier_id,
                                            supplier_name: origItem.supplier_name
                                        });

                                        if (origItem.supplier_id) {
                                            selectedSuppliersInRows.add(origItem.supplier_id);
                                        }
                                    }
                                });
                                
                                if (selectedItems.length === 0) {
                                    Swal.showValidationMessage('Vui lòng chọn ít nhất 1 sản phẩm với số lượng > 0');
                                    return false;
                                }

                                // THÔNG MINH: Nếu chưa chọn filter NCC nhưng tất cả item được chọn đều cùng 1 NCC
                                if (!finalSupplierId && selectedSuppliersInRows.size === 1) {
                                    const autoDetectedId = Array.from(selectedSuppliersInRows)[0];
                                    finalSupplierId = autoDetectedId;
                                    // Tìm tên NCC từ map
                                    finalSupplierName = supplierMap[autoDetectedId] || `NCC ID: ${autoDetectedId}`;
                                    devLog("Auto-detected single supplier from selection:", finalSupplierName);
                                }

                                return {
                                    items: selectedItems,
                                    supplierId: finalSupplierId,
                                    supplierName: finalSupplierName
                                };
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const selectedData = result.value;
                                const vatInt = Math.round(parseServerNumber(quoteHeader.vat_rate));
                                const currencyCode = quoteHeader.currency || 'VND';

                                const quoteToOrderData = {
                                    quoteId: quoteId,
                                    quoteNumber: quoteNumber,
                                    vat_rate: vatInt,
                                    currency: currencyCode,
                                    items: selectedData.items,
                                    preSelectedSupplierId: selectedData.supplierId,
                                    preSelectedSupplierName: selectedData.supplierName
                                };
                                localStorage.setItem('quoteToOrderData', JSON.stringify(quoteToOrderData));
                                localStorage.setItem('triggerCreateOrderFromQuote', 'true');
                                window.location.href = PROJECT_BASE_URL + 'sales_orders.php';
                            }
                        });

                    } else {
                        console.error("Error fetching quote details from server:", response.message);
                        Swal.fire(LANG['error_title'] || 'Lỗi', (response.message || LANG['error_fetching_quote_details_text'] || 'Không thể lấy chi tiết báo giá.'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    console.error("AJAX Error fetching quote details:", status, error, xhr.responseText);
                    Swal.fire(LANG['error_title'] || 'Lỗi Máy Chủ', LANG['server_error_fetching_quote_details_text'] || 'Lỗi máy chủ khi lấy chi tiết báo giá.', 'error');
                }
            });
        });

        quoteTableElement.on('click', '.btn-view-related-order', function(e) {
            e.preventDefault();
            devLog("Button .btn-view-related-order was clicked! (Listener in sq_main.js doc.ready)");
            const orderId = $(this).data('order-id');
            if (orderId) {
                localStorage.setItem('viewSpecificOrderId', orderId.toString());
                window.location.href = PROJECT_BASE_URL + 'sales_orders.php';
            }
        });

    } else {
        console.error("quoteTableElement is not a valid DataTable or not found when trying to attach listeners in sq_main.js doc.ready.");
        if (typeof quoteTableElement === 'undefined' || !quoteTableElement.length) {
            console.error("Reason: quoteTableElement variable is undefined or refers to an empty selection.");
        } else if (!$.fn.dataTable.isDataTable(quoteTableElement)) {
            console.error("Reason: quoteTableElement is a jQuery object but not initialized as a DataTable.");
        }
    }

    // Khối khởi tạo CKEditor và Draggable
    if (typeof ClassicEditor !== 'undefined') {
        devLog("Attempting to initialize CKEditors for Quote page (doc.ready)...");
        if ($('#notes').length) { 
            initializeCKEditor('notes');
        } else {
            // devLog('#notes element not found on this quote page (inside document.ready).');
        }
        if ($('#emailBody').length) { 
            initializeCKEditor('emailBody');
        } else {
            // devLog('#emailBody element (likely in email modal) not found (inside document.ready).');
        }
    } else {
        devLog("CKEditor 5 library (ClassicEditor) not found for Quote page (inside document.ready).");
    }

    if (typeof $.ui !== 'undefined' && typeof $.ui.draggable !== 'undefined' && buyerSignatureImg && buyerSignatureImg.length) {
        try {
            buyerSignatureImg.draggable({ containment: '#pdf-export-content', scroll: false }); // Đảm bảo #pdf-export-content tồn tại
            devLog("Signature draggable for quote initialized.");
        } catch (e) { console.error("Signature Draggable (quote) initialization error:", e); }
    } else {
        // ...
    }
    devLog("Full quote page initialization complete.");
}); // End $(document).ready
$('#btn-save-quote').on('click', function () {
    // Đồng bộ nội dung CKEditor về textarea (nếu có)
    if (ckEditorInstances['notes']) {
        $('#notes').val(ckEditorInstances['notes'].getData());
    }

    const formData = $('#quote-form').serialize(); // giả sử ID form là #quote-form

    $.ajax({
        url: 'process/sales_quote_handler.php', // xử lý riêng cho quote
        type: 'POST',
        data: formData + '&action=save_quote', // hoặc 'add' / 'edit' tùy theo logic backend
        success: function(response) {
            // Xử lý khi lưu thành công
            devLog('Lưu báo giá thành công:', response);
            // Hiển thị thông báo, đóng form, v.v.
        },
        error: function(xhr) {
            console.error('Lỗi khi lưu báo giá:', xhr.responseText);
            // Hiển thị thông báo lỗi nếu cần
        }
    });
});
