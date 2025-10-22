// File: assets/js/sq_main.js
function loadAndApplySavedQuoteFilters() {
    console.log("--- Bắt đầu hàm loadAndApplySavedQuoteFilters ---");
    try {
        // Sử dụng key riêng cho trang báo giá
        const savedYear = localStorage.getItem('salesQuoteFilterYear');
        const savedMonth = localStorage.getItem('salesQuoteFilterMonth');

        console.log("Đọc từ localStorage (Báo giá): Năm=" + savedYear + ", Tháng=" + savedMonth);

        if (savedYear) {
            $('#filterYear').val(savedYear);
        }
        if (savedMonth) {
            $('#filterMonth').val(savedMonth);
        }

    } catch (e) {
        console.error('Không thể tải bộ lọc Báo giá đã lưu.', e);
    }
    console.log("--- Kết thúc hàm loadAndApplySavedQuoteFilters ---");
}
const QuoteToOrderDataBridge = {
    loadData: function() {
        console.warn("QuoteToOrderDataBridge.loadData SHIM CALLED");
        const dataString = localStorage.getItem('quoteToOrderData');
        if (dataString) {
            try { return JSON.parse(dataString); } catch(e) { console.error("Error parsing quoteToOrderData from localStorage in SHIM", e); return null; }
        }
        return null;
    },
    clearData: function() {
        console.warn("QuoteToOrderDataBridge.clearData SHIM CALLED");
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
                        'undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'strikethrough', 'underline', 'subscript', 'superscript',
                        '|', 'fontColor', 'fontBackgroundColor', '|', 'link', 'insertImage', 'insertTable', 'blockQuote', 'mediaEmbed',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent', '|', 'alignment', '|', 'codeBlock', 'sourceEditing',
                        '|', 'removeFormat', 'selectAll', 'findAndReplace', '|', 'horizontalLine', 'pageBreak'
                        // '|', 'undo', 'redo' // Lặp lại, có thể bỏ bớt một cặp
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
    console.log('CKEditor 5 (Quote) initialized for: #' + elementId);

    // Thiết lập chiều cao tối thiểu
    if (editor.ui && editor.ui.view && editor.ui.view.editable && editor.ui.view.editable.element) {
        editor.ui.view.editable.element.style.minHeight = '180px';
    } else {
        console.warn('Could not set minHeight for CKEditor (Quote) #' + elementId);
    }

    // ✅ Thêm đoạn này để giảm khoảng cách giữa các dòng (line-height)
    editor.editing.view.change(writer => {
        writer.setStyle('line-height', '0.8', editor.editing.view.document.getRoot());
    });
})
    } else {
        console.warn(`Element with ID #${elementId} not found for CKEditor (Quote) initialization.`);
    }
}

// --- Hàm Khởi Tạo Trang Báo Giá ---
function initializePage() {
    console.log("Initializing Sales Quotes page...");
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
        buyerSignatureImg.on('load', function () { console.log("Buyer signature for quote loaded."); })
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
    console.log("Sales Quotes page basic structure initialized.");
}

// --- Document Ready ---
$(document).ready(function () {
    console.log("Document ready for Sales Quotes. Starting main initialization...");
    loadAndApplySavedQuoteFilters();
    // Khởi tạo các giá trị ban đầu, các elements, và DataTable
    initializePage();    
    
    // Gắn các listener cho form và các element khác (từ sq_events.js)
    if (typeof setupEventListeners === 'function') {
        setupEventListeners(); 
    } else {
        console.warn("setupEventListeners function (from sq_events.js) not found.");
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
        console.log("Attaching listeners to DataTable in $(document).ready() of sq_main.js");

        quoteTableElement.on('click', '.btn-create-order-from-quote', function() {
            console.log("Button .btn-create-order-from-quote was clicked! (Listener in sq_main.js doc.ready)");
            const $button = $(this);
            const quoteId = $button.data('quote-id');
            const quoteNumber = $button.data('quote-number');
            console.log(`Quote ID: ${quoteId}, Quote Number: ${quoteNumber}`);

            // Đảm bảo LANG và PROJECT_BASE_URL đã được định nghĩa (thường từ set_js_vars.php)
            if (typeof LANG === 'undefined' || typeof PROJECT_BASE_URL === 'undefined') {
                console.error("LANG or PROJECT_BASE_URL is not defined. Cannot proceed.");
                alert("Lỗi cấu hình: Biến ngôn ngữ hoặc đường dẫn gốc chưa được định nghĩa.");
                return;
            }

            Swal.fire({
                title: LANG['confirm_action_title'] || 'Xác nhận hành động',
                text: LANG['confirm_create_order_from_quote_text'] || `Bạn có muốn tạo Đơn Đặt Hàng từ Báo giá ${quoteNumber} và chuyển trang không?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: LANG['yes_create_and_redirect'] || 'Có, tạo và chuyển',
                cancelButtonText: LANG['no_cancel'] || 'Không, hủy'
            }).then((result) => {
                if (result.isConfirmed) {
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

                            const extractedItems = itemsFromServer.map(item => ({
                            product_id: item.product_id || null,
                            product_name_snapshot: item.product_name_snapshot || '',
                            category_snapshot: item.category_snapshot || '',
                            unit_snapshot: item.unit_snapshot || '',
                            // Ép số thuần từ server:
                            quantity: parseServerNumber(item.quantity) || 0,
                            unit_price: parseServerNumber(item.unit_price) || 0
                            }));

                            const vatInt = Math.round(parseServerNumber(quoteHeader.vat_rate));
                            const currencyCode = quoteHeader.currency || 'VND';

                            const quoteToOrderData = {
                            quoteId: quoteId,
                            quoteNumber: quoteNumber,
                            vat_rate: vatInt,         // ✅ integer
                            currency: currencyCode,   // ✅ VND/USD
                            items: extractedItems     // ✅ số thuần
                            };
                                localStorage.setItem('quoteToOrderData', JSON.stringify(quoteToOrderData));
                                localStorage.setItem('triggerCreateOrderFromQuote', 'true');
                                window.location.href = PROJECT_BASE_URL + 'sales_orders.php';
                            } else {
                                console.error("Error fetching quote details from server:", response.message);
                                Swal.fire(LANG['error_title'] || 'Lỗi', (response.message || LANG['error_fetching_quote_details_text'] || 'Không thể lấy chi tiết báo giá.'), 'error');
                                localStorage.removeItem('quoteToOrderData');
                                localStorage.removeItem('triggerCreateOrderFromQuote');
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.close();
                            console.error("AJAX Error fetching quote details:", status, error, xhr.responseText);
                            Swal.fire(LANG['error_title'] || 'Lỗi Máy Chủ', LANG['server_error_fetching_quote_details_text'] || 'Lỗi máy chủ khi lấy chi tiết báo giá.', 'error');
                            localStorage.removeItem('quoteToOrderData');
                            localStorage.removeItem('triggerCreateOrderFromQuote');
                        }
                    });
                } else {
                    localStorage.removeItem('quoteToOrderData');
                    localStorage.removeItem('triggerCreateOrderFromQuote');
                }
            });
        });

        quoteTableElement.on('click', '.btn-view-related-order', function(e) {
            e.preventDefault();
            console.log("Button .btn-view-related-order was clicked! (Listener in sq_main.js doc.ready)");
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
        console.log("Attempting to initialize CKEditors for Quote page (doc.ready)...");
        if ($('#notes').length) { 
            initializeCKEditor('notes');
        } else {
            // console.warn('#notes element not found on this quote page (inside document.ready).');
        }
        if ($('#emailBody').length) { 
            initializeCKEditor('emailBody');
        } else {
            // console.warn('#emailBody element (likely in email modal) not found (inside document.ready).');
        }
    } else {
        console.warn("CKEditor 5 library (ClassicEditor) not found for Quote page (inside document.ready).");
    }

    if (typeof $.ui !== 'undefined' && typeof $.ui.draggable !== 'undefined' && buyerSignatureImg && buyerSignatureImg.length) {
        try {
            buyerSignatureImg.draggable({ containment: '#pdf-export-content', scroll: false }); // Đảm bảo #pdf-export-content tồn tại
            console.log("Signature draggable for quote initialized.");
        } catch (e) { console.error("Signature Draggable (quote) initialization error:", e); }
    } else {
        // ...
    }
    console.log("Full quote page initialization complete.");
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
            console.log('Lưu báo giá thành công:', response);
            // Hiển thị thông báo, đóng form, v.v.
        },
        error: function(xhr) {
            console.error('Lỗi khi lưu báo giá:', xhr.responseText);
            // Hiển thị thông báo lỗi nếu cần
        }
    });
});
