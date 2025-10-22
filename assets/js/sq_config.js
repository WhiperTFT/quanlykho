// File: assets/js/sq_config.js

// --- Khởi tạo các biến và đối tượng DOM (sẽ được gán trong sq_main.js) ---
let quoteFormCard, quoteForm, quoteFormTitle, quoteListTitle, quoteTableElement, itemTableBody, saveButton, saveButtonText, saveButtonSpinner, formErrorMessageDiv, currencySelect, buyerSignatureImg, toggleSignatureButton;

const signatureLocalStorageKey = 'buyerSignatureVisibleState_quote'; // Sử dụng key riêng cho quote
let basePath = ''; // Sẽ được gán trong $(document).ready()
let APP_CONTEXT = window.APP_CONTEXT || { type: 'quote', documentName: 'Báo Giá' }; // Mặc định cho sales_quotes.js
console.log("Using APP_CONTEXT in sq_config.js:", APP_CONTEXT);

let webSignatureSrc = ''; // Đường dẫn ảnh chữ ký từ DB

// --- Biến trạng thái ---
let selectedExtraAttachments = [];
const vatDefaultRate = 10.00; // VAT mặc định (có thể giống hoặc khác sales_orders)
let salesQuoteDataTable = null; // Instance DataTables cho báo giá
let filterTimeout; // Timeout cho debounce filter
let emailStatusPollingInterval = null; // ID của setInterval polling

// --- Hàm tiện ích nhỏ (có thể dùng chung từ một file utils.js tổng nếu muốn) ---
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
    if (!name || typeof name !== 'string') return 'sales_quote'; // Mặc định cho báo giá
    let s = name.replace(/[\s\\/:"*?<>|]+/g, '_');
    s = s.replace(/[^a-zA-Z0-9_.-]/g, '');
    s = s.substring(0, 100);
    return s || 'sales_quote';
}

// --- Cập nhật đường dẫn ảnh chữ ký (logic này nên chạy sớm) ---
if (typeof window.APP_SETTINGS !== 'undefined' && typeof window.APP_SETTINGS.buyerSignatureUrl === 'string' && window.APP_SETTINGS.buyerSignatureUrl.trim() !== '') {
    webSignatureSrc = window.APP_SETTINGS.buyerSignatureUrl;
    console.log("Using signature from DB for quote web form: " + webSignatureSrc);
} else {
    console.log("No signature_path from DB or it's empty for quote.");
}

// Các biến toàn cục khác như AJAX_URL, LANG, PROJECT_BASE_URL, window.APP_SETTINGS
// được giả định là đã được định nghĩa từ trước.