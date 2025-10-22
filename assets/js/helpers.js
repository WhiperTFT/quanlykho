// File: assets/js/helpers.js
// Chứa các hàm JavaScript dùng chung toàn cục

// Đảm bảo biến lang từ set_js_vars.php đã được load
// if (typeof lang === 'undefined') {
//     console.warn("Global 'lang' variable not found. Translate function might not work correctly.");
// }

/**
 * Dịch một chuỗi dựa trên key.
 * Yêu cầu biến `lang` (mảng key-value) được định nghĩa trong phạm vi toàn cục.
 *
 * @param {string} key Khóa cần dịch.
 * @returns {string} Chuỗi đã dịch hoặc key gốc nếu không tìm thấy bản dịch.
 */
if (typeof translate !== 'function') {
    window.translate = function(key) {
        if (typeof lang !== 'undefined' && lang[key] && lang[key] !== '') {
            return lang[key];
        }
        // Fallback: Trả về key nếu không tìm thấy trong mảng ngôn ngữ
        return key;
    };
}

/**
 * Định dạng số theo locale và cài đặt tiền tệ.
 * Yêu cầu các biến global: siteLocaleForNumber, siteCurrency, siteCurrencyPosition, siteCurrencySpace
 *
 * @param {number|string} num Số cần định dạng.
 * @param {string} currencySymbol Ký hiệu tiền tệ (để định dạng tiền tệ).
 * @param {number} decimals Số chữ số sau dấu thập phân (mặc định 0).
 * @returns {string} Chuỗi số đã định dạng.
 */
if (typeof formatNumber !== 'function') {
    window.formatNumber = function(num, currencySymbol = '', decimals = 0) {
         if (isNaN(parseFloat(num))) return num; // Trả về nguyên nếu không phải số

         // Sử dụng locale mặc định 'en-US' nếu siteLocaleForNumber chưa được định nghĩa
         let locale = typeof siteLocaleForNumber !== 'undefined' ? siteLocaleForNumber : 'en-US';
         // Sử dụng số thập phân mặc định hoặc theo cài đặt nếu có
         let actualDecimals = currencySymbol && typeof siteCurrencyDecimalPlaces !== 'undefined' ? siteCurrencyDecimalPlaces : decimals;

         let numStr = parseFloat(num).toLocaleString(locale, {
             minimumFractionDigits: actualDecimals, // Đảm bảo luôn có ít nhất số thập phân này
             maximumFractionDigits: actualDecimals, // Không hiển thị quá số thập phân này
             useGrouping: true // Bật phân nhóm hàng nghìn
         });

         // Sử dụng vị trí tiền tệ mặc định nếu biến global chưa có
         let currencyPosition = typeof siteCurrencyPosition !== 'undefined' ? siteCurrencyPosition : 'after'; // 'before' hoặc 'after'
         let currencySpace = typeof siteCurrencySpace !== 'undefined' ? siteCurrencySpace : true; // true hoặc false

         if (currencySymbol) {
             if (currencyPosition === 'before') {
                 return htmlspecialchars(currencySymbol) + (currencySpace ? ' ' : '') + numStr; // Escape ký hiệu tiền tệ
             } else {
                 return numStr + (currencySpace ? ' ' : '') + htmlspecialchars(currencySymbol); // Escape ký hiệu tiền tệ
             }
         }
         return numStr;
    };
}

/**
 * Định dạng chuỗi ngày.
 * Yêu cầu biến global: siteDateFormat
 *
 * @param {string} dateString Chuỗi ngày (ví dụ: 'YYYY-MM-DD').
 * @returns {string} Chuỗi ngày đã định dạng.
 */
if (typeof formatDate !== 'function') {
    window.formatDate = function(dateString) {
         if (!dateString || dateString === '0000-00-00' || dateString === 'N/A') return ''; // Xử lý ngày null/rỗng/default
         // Giả sử input dateString có định dạng 'YYYY-MM-DD' (từ PHP handler)
         const date = new Date(dateString);
         if (isNaN(date.getTime())) return dateString; // Invalid date object

         const day = ('0' + date.getDate()).slice(-2);
         const month = ('0' + (date.getMonth() + 1)).slice(-2);
         const year = date.getFullYear();

         // Sử dụng định dạng ngày mặc định hoặc theo biến global siteDateFormat
         let dateFormat = typeof siteDateFormat !== 'undefined' ? siteDateFormat : 'dd/mm/yy'; // Mặc định dd/mm/yy

         // Sử dụng switch/case để xử lý các định dạng phổ biến
         switch (dateFormat.toLowerCase()) {
             case 'mm/dd/yy': return `${month}/${day}/${year}`;
             case 'yy-mm-dd': return `${year}-${month}-${day}`;
             case 'dd-mm-yy': return `${day}-${month}-${year}`;
             case 'dd/mm/yyyy': return `${day}/${month}/${year}`; // dd/mm/yyyy
             case 'mm/dd/yyyy': return `${month}/${day}/${year}`; // mm/dd/yyyy
             case 'yyyy-mm-dd': return `${year}-${month}-${day}`; // yyyy-mm-dd
             // Thêm các định dạng khác nếu cần
             default: return `${day}/${month}/${year}`; // Mặc định
         }
     };
}


/**
 * Chuyển đổi các ký tự đặc biệt HTML thành các thực thể HTML.
 *
 * @param {string|number} str Chuỗi hoặc số cần escape.
 * @returns {string|number} Chuỗi đã escape hoặc giá trị gốc nếu không phải chuỗi/số.
 */
if (typeof htmlspecialchars !== 'function') {
     window.htmlspecialchars = function(str) {
         if (typeof str !== 'string' && typeof str !== 'number') return str; // Áp dụng cho cả số nếu cần
         str = String(str); // Chuyển sang string
          var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
          return str.replace(/[&<>"']/g, function(m) { return map[m]; });
     };
}

// Hàm kiểm tra sự tồn tại của hàm JS global (có thể giữ lại ở đây hoặc ở delivery_comparison.js)
// Nếu bạn muốn dùng nó ở nhiều nơi, đặt nó ở đây.
if (typeof function_exists === 'undefined') {
    window.function_exists = function(function_name) {
        return typeof window[function_name] === 'function';
    };
}