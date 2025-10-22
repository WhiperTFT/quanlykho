// File: assets/js/sales_orders_config.js

// --- Khởi tạo các biến và đối tượng DOM ---
// Các biến này sẽ được gán giá trị cụ thể trong $(document).ready() ở file main
let orderFormCard, orderForm, orderFormTitle, orderListTitle, orderTableElement, itemTableBody, saveButton, saveButtonText, saveButtonSpinner, formErrorMessageDiv, currencySelect, buyerSignatureImg, toggleSignatureButton;

const signatureLocalStorageKey = 'buyerSignatureVisibleState';
let basePath = ''; // Sẽ được gán trong $(document).ready()
let APP_CONTEXT = window.APP_CONTEXT || { type: 'order', documentName: 'Tài liệu' };
console.log("Using APP_CONTEXT in sales_orders_config.js:", APP_CONTEXT);

let webSignatureSrc = ''; // Đường dẫn ảnh chữ ký từ DB

// --- Biến trạng thái ---
let selectedExtraAttachments = [];
// let itemIndex = 1; // Biến này có vẻ không còn cần thiết với logic hiện tại, cân nhắc loại bỏ nếu không dùng.
const vatDefaultRate = 10.00; // VAT mặc định
let salesOrderDataTable = null; // Instance DataTables
let filterTimeout; // Timeout cho debounce filter
let emailStatusPollingInterval = null; // ID của setInterval polling

// --- Hàm tiện ích nhỏ ---
function trim(str) {
    if (typeof str !== 'string') {
        return str;
    }
    return str.replace(/^\s+|\s+$/g, '');
}

function escapeHtml(unsafe) {
    if (unsafe === null || typeof unsafe === 'undefined') return '';
    return unsafe.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function sanitizeFilename(name) {
    if (!name || typeof name !== 'string') return 'sales_order';
    let s = name.replace(/[\s\\/:"*?<>|]+/g, '_');
    s = s.replace(/[^a-zA-Z0-9_.-]/g, '');
    s = s.substring(0, 100);
    return s || 'sales_order';
}

// --- CẬP NHẬT ĐƯỜNG DẪN ẢNH CHỮ KÝ CHO FORM WEB (logic này nên chạy sớm) ---
// Phần này có thể cần điều chỉnh để chạy sau khi DOM sẵn sàng nếu APP_SETTINGS được load động
// Hoặc đảm bảo APP_SETTINGS có sẵn khi file này được parse
if (typeof window.APP_SETTINGS !== 'undefined' && typeof window.APP_SETTINGS.buyerSignatureUrl === 'string' && window.APP_SETTINGS.buyerSignatureUrl.trim() !== '') {
    webSignatureSrc = window.APP_SETTINGS.buyerSignatureUrl;
    console.log("Using signature from DB for web form: " + webSignatureSrc);
    // Việc gán src cho buyerSignatureImg sẽ thực hiện trong $(document).ready()
} else {
    console.log("No signature_path from DB or it's empty.");
}

// Các biến toàn cục khác như AJAX_URL, LANG, PROJECT_BASE_URL, window.APP_SETTINGS
// được giả định là đã được định nghĩa từ trước (ví dụ: trong file PHP hoặc một script khác được nhúng trước).