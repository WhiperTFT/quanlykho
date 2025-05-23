<?php
// File: config/set_js_vars.php
// Mục đích: Truyền các biến và cấu hình từ PHP sang JavaScript.
// File này nên được nạp trong thẻ <head> của trang, sau khi init.php đã chạy.

global $lang, $current_lang, $pdo; // Các biến này được kỳ vọng đã có từ init.php

// 1. Đảm bảo các biến PHP cần thiết đã được khởi tạo từ init.php
if (!defined('PROJECT_BASE_URL')) {
    // Đây là lỗi nghiêm trọng nếu init.php không định nghĩa.
    // Gán một giá trị fallback cứng để tránh lỗi JS, nhưng cần sửa ở init.php.
    error_log("CRITICAL: PHP constant PROJECT_BASE_URL is not defined! Falling back in set_js_vars.php.");
    define('PROJECT_BASE_URL', '/quanlykho/'); // Sửa giá trị này cho đúng với dự án của bạn
}

if (!isset($lang) || !is_array($lang)) {
    error_log("WARNING: Global \$lang is not available in set_js_vars.php. JS LANG object will be empty.");
    $lang = [];
}

if (!isset($current_lang) || empty($current_lang)) {
    error_log("WARNING: Global \$current_lang is not available in set_js_vars.php. Falling back for siteLang.");
    $current_lang = $lang['language'] ?? 'vi'; // Lấy từ mảng $lang hoặc mặc định
}

// 2. Lấy cài đặt công ty/ứng dụng
// Ưu tiên sử dụng biến global $company_settings_g nếu init.php đã chuẩn bị
global $company_settings_g;
if (!isset($company_settings_g) || !is_array($company_settings_g)) {
    // Nếu init.php không cung cấp, thử tải từ DB (không khuyến khích thực hiện query trong file này)
    // Tạm thời sử dụng giá trị mặc định nếu không có $company_settings_g
    error_log("WARNING: Global \$company_settings_g not provided by init.php. Using defaults for currency/date settings in set_js_vars.php.");
    $company_settings_for_js = [
        'currency_symbol'     => '₫',
        'currency_code'       => 'VND',
        'currency_position'   => 'after', // 'before' or 'after'
        'currency_space_between' => true,
        'decimal_separator'   => '.',
        'thousands_separator' => ',',
        'date_format_php'     => 'd/m/Y', // Định dạng PHP date()
        // Thêm các settings khác nếu cần
    ];
} else {
    $company_settings_for_js = $company_settings_g;
}

// Chuyển đổi định dạng ngày PHP sang định dạng flatpickr/JS (ví dụ đơn giản)
// File helpers.js của bạn đã có siteDateFormat, nên biến này quan trọng.
// init.php nên chuẩn bị $user_date_format_php (ví dụ: 'd/m/Y')
global $user_date_format_php; // Kỳ vọng init.php set biến này (vd: từ session user)
$php_date_format = $user_date_format_php ?? ($company_settings_for_js['date_format_php'] ?? 'd/m/Y');
$js_date_format = str_replace(
    ['d', 'm', 'Y', 'y', 'H', 'i', 's', 'a', 'A'],
    ['d', 'm', 'Y', 'y', 'H', 'i', 'S', 'K', 'K'], // 'S' cho giây trong flatpickr, 'K' cho AM/PM
    $php_date_format
);
// Nếu format của bạn phức tạp hơn, bạn cần logic chuyển đổi tốt hơn.
// Ví dụ: 'd/m/Y H:i' -> 'd/m/Y H:i' (flatpickr hiểu được)


// --- Bắt đầu xuất JavaScript ---
?>
<script>
  // Sử dụng const cho các biến không thay đổi giá trị
  const PROJECT_BASE_URL = '<?php echo rtrim(PROJECT_BASE_URL, '/') . '/'; ?>'; // Đảm bảo có dấu / ở cuối
  const LANG = <?php echo json_encode($lang); ?>; // Đối tượng ngôn ngữ cho JavaScript

  // Đối tượng chứa các URL AJAX quan trọng
  

  // Các cài đặt chung của trang web
  const siteLang = '<?php echo htmlspecialchars($current_lang, ENT_QUOTES, 'UTF-8'); ?>'; // ví dụ: 'vi' hoặc 'en'
  
  // Cài đặt tiền tệ (lấy từ $company_settings_for_js đã chuẩn bị ở trên)
  const siteCurrency = '<?php echo htmlspecialchars($company_settings_for_js['currency_symbol'] ?? '₫', ENT_QUOTES, 'UTF-8'); ?>';
  const siteCurrencyCode = '<?php echo htmlspecialchars($company_settings_for_js['currency_code'] ?? 'VND', ENT_QUOTES, 'UTF-8'); ?>';
  const siteCurrencyPosition = '<?php echo htmlspecialchars($company_settings_for_js['currency_position'] ?? 'after', ENT_QUOTES, 'UTF-8'); ?>'; // 'before' hoặc 'after'
  const siteCurrencySpace = <?php echo !empty($company_settings_for_js['currency_space_between']) ? 'true' : 'false'; ?>;
  
  // Cài đặt định dạng số
  const siteDecimalSeparator = '<?php echo htmlspecialchars($company_settings_for_js['decimal_separator'] ?? '.', ENT_QUOTES, 'UTF-8'); ?>';
  const siteThousandsSeparator = '<?php echo htmlspecialchars($company_settings_for_js['thousands_separator'] ?? ',', ENT_QUOTES, 'UTF-8'); ?>';
  // Locale cho hàm toLocaleString của JavaScript (ví dụ: 'vi-VN' hoặc 'en-US')
  const siteLocaleForNumber = siteLang === 'vi' ? 'vi-VN' : 'en-US';
  
  // Cài đặt định dạng ngày tháng (cho flatpickr hoặc các thư viện JS khác)
  const siteDateFormat = '<?php echo htmlspecialchars($js_date_format, ENT_QUOTES, 'UTF-8'); ?>'; // ví dụ: 'd/m/Y'

  // Các biến APP_SETTINGS và APP_CONTEXT nếu bạn muốn định nghĩa chúng ở đây một cách tập trung
  // window.APP_SETTINGS = window.APP_SETTINGS || {};
  // window.APP_CONTEXT = window.APP_CONTEXT || {};

  // Debug: Kiểm tra các biến đã được gán đúng chưa
  // console.log('JS Vars from set_js_vars.php:', {
  //     PROJECT_BASE_URL,
  //     LANG,
  //     AJAX_URL,
  //     siteLang,
  //     siteCurrency,
  //     siteCurrencyCode,
  //     siteCurrencyPosition,
  //     siteCurrencySpace,
  //     siteDecimalSeparator,
  //     siteThousandsSeparator,
  //     siteLocaleForNumber,
  //     siteDateFormat
  // });
</script>