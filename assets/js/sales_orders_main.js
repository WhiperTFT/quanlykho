// File: assets/js/sales_orders_main.js

function loadAndApplySavedFilters() {
    console.log("--- Bắt đầu hàm loadAndApplySavedFilters ---");
    try {
        const savedYear = localStorage.getItem('salesOrderFilterYear');
        const savedMonth = localStorage.getItem('salesOrderFilterMonth');
        console.log("Đọc từ localStorage: Năm = '" + savedYear + "', Tháng = '" + savedMonth + "'");

        if ($('#filterYear').length === 0 || $('#filterMonth').length === 0) {
            console.error("LỖI: Không tìm thấy ô lọc #filterYear hoặc #filterMonth trên trang.");
            return;
        }
        
        if (savedYear !== null && savedYear !== '') {
            console.log("Đang đặt giá trị cho #filterYear: '" + savedYear + "'");
            $('#filterYear').val(savedYear);
        } else {
            console.log("Không có giá trị Năm được lưu, bỏ qua.");
        }

        if (savedMonth !== null && savedMonth !== '') {
            console.log("Đang đặt giá trị cho #filterMonth: '" + savedMonth + "'");
            $('#filterMonth').val(savedMonth);
        } else {
            console.log("Không có giá trị Tháng được lưu, bỏ qua.");
        }
    } catch (e) {
        console.error('Lỗi nghiêm trọng trong hàm loadAndApplySavedFilters:', e);
    }
    console.log("--- Kết thúc hàm loadAndApplySavedFilters ---");
}

let ckEditorInstances = {};
let ckEditorReady = {};
function getNotesHtmlFromForm($form) {
  // Ưu tiên textarea trong CHÍNH form đang lưu
  const ta = $form.find('textarea[name="notes"]').get(0);
  if (!ta) return '';

  const key1 = ta.id || '';
  const key2 = ta.name || '';
  const ed   = ckEditorInstances[key1] || ckEditorInstances[key2];

  if (ed && typeof ed.getData === 'function') {
    return ed.getData();
  }
  // fallback nếu chưa có editor
  return (ta.value || '');
}

// Đồng bộ mọi CKEditor về <textarea> tương ứng
function syncAllEditorsToTextarea() {
  if (!ckEditorInstances) return;
  Object.entries(ckEditorInstances).forEach(([id, ed]) => {
    try {
      const ta = document.getElementById(id);
      if (!ta) return;
      const wasDisabled = ta.disabled; // disabled thì không submit
      if (wasDisabled) ta.disabled = false;
      ta.value = ed.getData();
      if (wasDisabled) ta.disabled = true;
    } catch (e) {
      console.warn('Sync CKEditor lỗi:', id, e);
    }
  });
}

// Định nghĩa hàm populateAndSelectQuoteInDropdown ở phạm vi toàn cục
function populateAndSelectQuoteInDropdown($selectElement, preselectQuoteId = null, preselectQuoteNumber = null) {
    if (!$selectElement.length) {
        console.warn("SO Page: Target select element for quotes not found.");
        return;
    }

    const currentEditingOrderId = $('#order_id').val() ? parseInt($('#order_id').val(), 10) : null;

    $selectElement.html($('<option>', { value: '', text: LANG['loading_quotes'] || 'Đang tải báo giá...' })).prop('disabled', true);

    $.ajax({
        url: PROJECT_BASE_URL + 'process/sales_order_handler.php',
        type: 'POST',
        data: {
            action: 'get_available_quotes_for_order_linking',
            current_so_id: currentEditingOrderId // Gửi ID đơn hàng hiện tại (nếu đang sửa)
        },
        dataType: 'json',
        success: function(response) {
            $selectElement.empty().prop('disabled', false);
            $selectElement.append($('<option>', { value: '', text: LANG['select_quote_to_link'] || '-- Chọn Báo giá liên kết --' }));

            let quoteForPreselectionActuallyAdded = false;

            if (response && response.success && Array.isArray(response.quotes)) {
                response.quotes.forEach(function(quote) {
                    let optionText = quote.quote_number;
                    if (quote.customer_name) optionText += ` - ${quote.customer_name}`;

                    let shouldDisplayThisQuote = false;

                    if (preselectQuoteId && quote.id.toString() === preselectQuoteId.toString()) {
                        shouldDisplayThisQuote = true;
                        quoteForPreselectionActuallyAdded = true;
                    } else {
                        if (!quote.is_already_linked_to_another_order) {
                            shouldDisplayThisQuote = true;
                        }
                    }

                    if (shouldDisplayThisQuote) {
                        const $option = $('<option>', {
                            value: quote.id,
                            text: optionText
                        });
                        $selectElement.append($option);
                    }
                });
            }

            if (preselectQuoteId) {
                if (quoteForPreselectionActuallyAdded) {
                    $selectElement.val(preselectQuoteId);
                } else if (preselectQuoteNumber) {
                    console.warn(`SO Page: Quote ID ${preselectQuoteId} (Num: ${preselectQuoteNumber}) for preselection not in filtered list. Adding temp.`);
                    const $tempOption = $('<option>', {
                        value: preselectQuoteId,
                        text: `${preselectQuoteNumber} (${LANG['current_link_or_creating_from'] || 'Đang liên kết/Tạo từ BG này'})`,
                        selected: true
                    });
                    $selectElement.prepend($tempOption);
                }
            }

            if ($selectElement.data('select2')) {
                $selectElement.trigger('change.select2');
            } else {
                $selectElement.trigger('change');
            }
        },
        error: function(xhr, status, error) {
            console.error("Lỗi khi tải danh sách báo giá cho dropdown:", status, error, xhr.responseText);
            $selectElement.empty().prop('disabled', false);
            $selectElement.append($('<option>', { value: '', text: LANG['error_loading_data'] || 'Lỗi tải dữ liệu' }));
        }
    });
}
// --- Document Ready ---
$(document).ready(function () {
    console.log("Document ready for Sales Orders. Starting main initialization...");
    loadAndApplySavedFilters();
    initializePage();    
    if (typeof setupEventListeners === 'function') { // Từ sales_orders_events.js
        setupEventListeners(); 
    } else {
        console.warn("setupEventListeners (for SO) function not found.");
    } 
// --- Hàm Khởi Tạo CKEditor ---
// (Định nghĩa hàm ở phạm vi toàn cục là tốt)
function initializeCKEditor(elementId, configOptions = {}) {
  if (ckEditorInstances[elementId]) return;           // đã init
  if (ckEditorReady[elementId]) return;               // đang init

  const element = document.querySelector('#' + elementId);
  if (!element) {
    console.warn(`Element with ID #${elementId} not found for CKEditor.`);
    return;
  }

  ckEditorReady[elementId] = ClassicEditor
    .create(element, {
      toolbar: {
        items: [
          'undo','redo','heading','|','bold','italic','underline','strikethrough',
          '|','fontColor','fontBackgroundColor','link','insertImage','insertTable',
          'blockQuote','mediaEmbed','bulletedList','numberedList','outdent','indent',
          '|','alignment','codeBlock','sourceEditing','removeFormat','selectAll',
          'findAndReplace','horizontalLine','pageBreak'
        ],
        shouldNotGroupWhenFull: true
      },
      language: 'vi',
      table: { contentToolbar: ['tableColumn','tableRow','mergeTableCells'] },
      ...configOptions
    })
    .then(editor => {
  // --- Lưu instance theo cả ID và NAME để truy ngược đúng form/textarea ---
  ckEditorInstances[elementId] = editor;
  try {
    const el = document.getElementById(elementId);
    const nm = el?.getAttribute('name');
    if (nm && !ckEditorInstances[nm]) {
      ckEditorInstances[nm] = editor;
    }
    // Gắn metadata lên chính <textarea> (hữu ích khi có nhiều bản #notes)
    if (el) {
      el.dataset.ckInstanceKey = elementId;
      if (nm) el.dataset.ckInstanceName = nm;
    }
  } catch (e) {
    console.warn('CKEditor metadata attach failed:', e);
  }

  // --- Set chiều cao & line-height như cũ ---
  if (editor.ui?.view?.editable?.element) {
    editor.ui.view.editable.element.style.minHeight = '180px';
  }
  editor.editing.view.change(writer => {
    writer.setStyle('line-height', '0.8', editor.editing.view.document.getRoot());
  });

  // --- Đồng bộ realtime: mỗi lần gõ là đẩy về <textarea> đúng của editor này ---
  try {
    const ta = document.getElementById(elementId);
    if (ta) {
      const pushToTextarea = () => {
        const wasDisabled = ta.disabled;
        if (wasDisabled) ta.disabled = false;     // disabled thì không submit
        ta.value = editor.getData();
        if (wasDisabled) ta.disabled = true;
      };
      // Sync ngay lần đầu và mọi lần thay đổi
      pushToTextarea();
      editor.model.document.on('change:data', pushToTextarea);
    }
  } catch (e) {
    console.warn('CKEditor live sync to textarea failed:', e);
  }

  return editor;
})
};

// --- Hàm Khởi Tạo Trang Chính ---
function initializePage() {
    console.log("Initializing Sales Orders page starting...");
    // Gán các biến DOM elements (đã khai báo trong sales_orders_config.js)
    orderFormCard = $('#order-form-card');
    orderForm = $('#order-form');
    orderFormTitle = $('#order-form-title');
    orderListTitle = $('#order-list-title');
    orderTableElement = $('#sales-orders-table');
    itemTableBody = $('#item-details-body');
    if (!itemTableBody.length) {
        console.warn("SO Main: initializePage - #item-details-body NOT FOUND in DOM!");
    }
    saveButton = $('#btn-save-order');
    saveButtonText = saveButton.find('.save-text');
    saveButtonSpinner = saveButton.find('.spinner-border');
    formErrorMessageDiv = $('#form-error-message');
    currencySelect = $('#currency_select');
    buyerSignatureImg = $('#buyer-signature');
    toggleSignatureButton = $('#toggle-signature');
    basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    // Cập nhật ảnh chữ ký và trạng thái ẩn/hiện từ localStorage
    if (webSignatureSrc) { // webSignatureSrc từ sales_orders_config.js
        buyerSignatureImg.attr('src', webSignatureSrc);
        const savedState = localStorage.getItem(signatureLocalStorageKey);
        let shouldBeVisible = true;
        if (savedState !== null) shouldBeVisible = (savedState === 'true');

        if (shouldBeVisible) {
            buyerSignatureImg.show();
            toggleSignatureButton.text(LANG?.hide_signature ?? 'Ẩn chữ ký');
        } else {
            buyerSignatureImg.hide();
            toggleSignatureButton.text(LANG?.show_signature ?? 'Hiện chữ ký');
        }
        console.log(`Signature visibility restored from localStorage: ${shouldBeVisible}`);
    } else {
        buyerSignatureImg.hide().removeAttr('src');
        toggleSignatureButton.hide();
        console.log("No signature image, hiding toggle button and image.");
    }
    buyerSignatureImg.on('load', function () {
        console.log("Buyer signature image loaded. Dimensions:", this.naturalWidth, "x", this.naturalHeight);
    }).on('error', function () {
        console.warn("Failed to load buyer signature image from src: " + $(this).attr('src'));
        $(this).hide();
        toggleSignatureButton.hide();
    });
    initializeDatepicker();
    initializeSupplierAutocomplete();
    initializeProductAutocomplete('#item-details-body'); // Cho các dòng đã có
    initializeProductAutocomplete('.item-row-template'); // Cho template
    initializeSalesOrderDataTable();
    orderFormCard.hide();
    // setupEventListeners(); // Sẽ được gọi sau khi page initialized
    console.log("SO Main: initializePage - END");
}
   
    
// Khởi tạo cho các editor
if (typeof ClassicEditor !== 'undefined') {
    // Giả sử #notes và #emailBody là các textarea hoặc div trong HTML của bạn
    // Ví dụ: <textarea id="notes"></textarea> hoặc <div id="notes"></div>
    // Ví dụ: <textarea id="emailBody"></textarea> hoặc <div id="emailBody"></div>
    initializeCKEditor('notes');
    initializeCKEditor('emailBody');
} else {
    console.warn("CKEditor 5 library (ClassicEditor) not found.");
}
    // Khởi tạo jQuery UI Draggable cho ảnh chữ ký
    console.log("SO Main: handlePageLoadActions - START");
    console.log("SO Main: handlePageLoadActions - itemTableBody at entry:", itemTableBody); // <<--- THÊM LOG
    
    if (typeof $.ui !== 'undefined' && typeof $.ui.draggable !== 'undefined' && buyerSignatureImg.length) {
        try {
            buyerSignatureImg.draggable({
                containment: '#pdf-export-content', // Đảm bảo element này tồn tại và bao quanh chữ ký
                scroll: false
            });
            console.log("Signature draggable initialized after document ready.");
        } catch (e) {
            console.error("Signature Draggable initialization error:", e);
        }
    } else {
        if (!buyerSignatureImg.length) console.warn("Buyer signature image element not found for draggable.");
        else console.warn("jQuery UI Draggable not found. Signature will not be draggable.");
    }
    console.log("Full page initialization complete.");
// ==== Helper: parse số kiểu VN ('.' ngăn nghìn, ',' thập phân) ====
function parseNumberVN(x) {
  if (x == null) return 0;
  let s = String(x).trim();
  if (!s) return 0;
  s = s.replace(/\s+/g, '');

  // 1) Dạng VN: 1.234,56 hoặc 1.234 hoặc 12,5
  if (/^\d{1,3}(\.\d{3})*(,\d+)?$/.test(s)) {
    s = s.replace(/\./g, '').replace(',', '.');
    const v = parseFloat(s);
    return isNaN(v) ? 0 : v;
  }
  // 2) Dạng quốc tế: 1234.56 hoặc 1234
  s = s.replace(/,/g, '.');
  const v = parseFloat(s);
  return isNaN(v) ? 0 : v;
}

// ==== Thu thập dữ liệu form để gửi JSON cho PHP ====
function collectSalesOrderFormData() {
  const $form = $('#orderForm');
      // Đồng bộ CKEditor về textarea trước khi đọc dữ liệu
  if (typeof syncAllEditorsToTextarea === 'function') {
    syncAllEditorsToTextarea();
  }
  // Header
  const order_id     = ($form.find('#order_id').val() || '').trim() || null;
  const partner_id   = ($form.find('#partner_id').val() || $form.find('[name="partner_id"]').val() || '').trim();
  const order_date   = ($form.find('#order_date_display').val() || $form.find('#order_date').val() || '').trim(); // d/m/Y
  const order_number = ($form.find('#order_number').val() || $form.find('[name="order_number"]').val() || '').trim();
  const currency     = String(($form.find('#currency').val() || $form.find('[name="currency"]').val() || 'VND')).toUpperCase();
  const vat_rate_raw = ($form.find('#summary-vat-rate').val() || $form.find('#vat_rate').val() || $form.find('[name="vat_rate"]').val() || '10');
  const vat_rate     = parseNumberVN(vat_rate_raw);
  const notes = getNotesHtmlFromForm($form).trim();


  // Items
  const items = [];
  $('#itemsBody tr').each(function () {
    const $tr = $(this);

    // Tìm theo class trước, thiếu thì fallback theo name
    const detail_id = ($tr.find('.detail-id').val() || $tr.find('[name$="[detail_id]"]').val() || '').trim() || null;
    const product_id = ($tr.find('.product-id').val() || $tr.find('[name$="[product_id]"]').val() || '').trim() || null;
    const product_name_snapshot = ($tr.find('.product-autocomplete').val() || $tr.find('[name$="[product_name_snapshot]"]').val() || '').trim();
    const category_snapshot = ($tr.find('.category-snapshot').val() || $tr.find('[name$="[category_snapshot]"]').val() || '').trim();
    const unit_snapshot = ($tr.find('.unit-snapshot').val() || $tr.find('[name$="[unit_snapshot]"]').val() || '').trim();

    const qty_raw = ($tr.find('.quantity').val() || $tr.find('[name$="[quantity]"]').val() || '0');
    const price_raw = ($tr.find('.unit-price').val() || $tr.find('[name$="[unit_price]"]').val() || '0');

    const quantity = parseNumberVN(qty_raw);
    const unit_price = parseNumberVN(price_raw);

    // Chỉ push item hợp lệ (tên sp hoặc product_id có, quantity > 0)
    if ((product_name_snapshot || product_id) && quantity > 0) {
      items.push({
        detail_id: detail_id ? parseInt(detail_id, 10) : null,
        product_id: product_id ? parseInt(product_id, 10) : null,
        product_name_snapshot,
        category_snapshot,
        unit_snapshot,
        quantity,
        unit_price
      });
    }
  });

  return {
    order_id,
    partner_id: partner_id ? parseInt(partner_id, 10) : null,
    order_date,               // server sẽ parse theo $user_settings['date_format_php']
    order_number,
    currency,                 // 'VND' | 'USD'
    vat_rate,                 // number 0–100
    notes,
    quote_id: quote_id ? parseInt(quote_id, 10) : null,
    items
  };
}

// Nếu cần dùng ở file khác:
window.collectSalesOrderFormData = collectSalesOrderFormData;

function handlePageLoadActions() {
    const triggerCreateOrder = localStorage.getItem('triggerCreateOrderFromQuote');
    const viewSpecificOrderId = localStorage.getItem('viewSpecificOrderId');

    if (triggerCreateOrder === 'true') {
        localStorage.removeItem('triggerCreateOrderFromQuote');
        const dataString = localStorage.getItem('quoteToOrderData');
        localStorage.removeItem('quoteToOrderData');

        if (dataString) {
  try {
    const quoteData = JSON.parse(dataString);
    console.log("Data loaded from localStorage for SO creation:", quoteData);

    if (typeof resetOrderForm === 'function') {
      resetOrderForm(false);
    } else {
      console.error("resetOrderForm function is not defined.");
      return;
    }
    orderFormCard.slideDown();
    orderListTitle.hide();
    $('html, body').animate({ scrollTop: orderFormCard.offset().top - 20 }, 300);

    // 1) Chọn báo giá đã tạo/đang liên kết (nếu có)
    const $linkedQuoteSelect = $('#order_quote_id_form');
    if ($linkedQuoteSelect.length) {
      populateAndSelectQuoteInDropdown($linkedQuoteSelect, quoteData.quoteId, quoteData.quoteNumber);
    } else {
      console.warn("Dropdown '#order_quote_id_form' not found.");
    }

    // 2) Currency & VAT (ép integer) từ payload
    if (typeof currencySelect !== 'undefined' && currencySelect.length) {
      currencySelect.val(quoteData.currency || 'VND').trigger('change');
    }
    const vatInt = Math.round(
      (typeof parseServerNumber === 'function') ? parseServerNumber(quoteData.vat_rate) : parseFloat(quoteData.vat_rate || 0)
    );
    $('#summary-vat-rate').val(vatInt);
    $('#input-vatrate').val(vatInt);

    // 3) Điền dữ liệu vào bảng items bằng addItemRow(data)
    if (typeof addItemRow === 'function' && Array.isArray(quoteData.items) && quoteData.items.length > 0) {
      if (itemTableBody.length) itemTableBody.empty();

      quoteData.items.forEach(function (it) {
        // Truyền thẳng vào addItemRow để nó tự parseServerNumber khi gán input
        addItemRow({
          product_id: it.product_id || '',
          product_name_snapshot: it.product_name_snapshot || '',
          category_snapshot: it.category_snapshot || '',
          unit_snapshot: it.unit_snapshot || '',
          quantity: (typeof parseServerNumber === 'function') ? parseServerNumber(it.quantity) : it.quantity,
          unit_price: (typeof parseServerNumber === 'function') ? parseServerNumber(it.unit_price) : it.unit_price
        });
      });

      if (typeof calculateSummaryTotals === 'function') {
        calculateSummaryTotals();
      }
    } else {
      if (typeof addItemRow !== 'function') console.error("addItemRow function is not defined in sales_orders_form.js");
      if (!quoteData.items || quoteData.items.length === 0) console.warn("No items data found in localStorage to populate order details.");
    }
  } catch (e) {
    console.error("Error processing data from localStorage for SO creation:", e);
    if (typeof resetOrderForm === 'function') resetOrderForm(false);
  }
}

    } else if (viewSpecificOrderId) {
        localStorage.removeItem('viewSpecificOrderId');
        const orderIdToView = parseInt(viewSpecificOrderId, 10);
        if (!isNaN(orderIdToView) && typeof loadOrderForEdit === 'function') {
            console.log("Loading specific order for view/edit:", orderIdToView);
            loadOrderForEdit(orderIdToView);
        } else {
            if (typeof loadOrderForEdit !== 'function') console.error("loadOrderForEdit function is not defined.");
            else console.warn("Invalid orderId found in localStorage for viewing:", viewSpecificOrderId);
        }
    } else {
        const $linkedQuoteSelect = $('#order_quote_id_form');
        if ($linkedQuoteSelect.length) {
            // Gọi hàm toàn cục mà không có preselect
            populateAndSelectQuoteInDropdown($linkedQuoteSelect, null, null);
        }
    }
}

    // Gọi hàm xử lý chính sau khi mọi thứ đã sẵn sàng
    handlePageLoadActions();

    // Khởi tạo jQuery UI Draggable (nếu có)
    if (typeof $.ui !== 'undefined' && typeof $.ui.draggable !== 'undefined' && buyerSignatureImg && buyerSignatureImg.length) {
        try {
            buyerSignatureImg.draggable({ containment: '#pdf-export-content', scroll: false });
            console.log("Signature draggable for order initialized.");
        } catch (e) { console.error("Signature Draggable (order) initialization error:", e); }
    }
    console.log("Full order page initialization complete.");
}); 
$('#btnSaveOrder').off('click.saveOrder').on('click.saveOrder', async function (e) {
  e.preventDefault();

  // Đợi CKEditor #notes sẵn sàng nếu đang khởi tạo
  if (ckEditorReady && ckEditorReady['notes'] && typeof ckEditorReady['notes'].then === 'function') {
    try { await ckEditorReady['notes']; } catch (e) {}
  }

  // Đồng bộ editor -> textarea (phòng khi còn pending actions)
  if (typeof syncAllEditorsToTextarea === 'function') {
    syncAllEditorsToTextarea();
  }

  const payload = collectSalesOrderFormData();
  console.log('[DEBUG] Notes to save =', payload.notes); // kiểm tra đúng ngay lần 1
  const isEdit = !!(payload.order_id && Number(payload.order_id) > 0);
  const action = isEdit ? 'edit' : 'add';

  $.ajax({
    url: 'process/sales_order_handler.php?action=' + action,
    method: 'POST',
    data: JSON.stringify(payload),
    contentType: 'application/json; charset=utf-8',
    dataType: 'json',
    success: function (res) { /* ... */ },
    error: function (xhr) { console.error('Lỗi khi lưu đơn hàng:', xhr.status, xhr.responseText); }
  });
});
