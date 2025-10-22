<?php
// File: config/set_js_vars.php

// $currentLang và $lang (mảng PHP) PHẢI được thiết lập bởi init.php TRƯỚC KHI header.php gọi file này.
// init.php cũng nên định nghĩa $project_web_root.
// Nếu $project_web_root chưa có, bạn cần định nghĩa lại ở đây hoặc trong init.php

// --- CẤU HÌNH THỦ CÔNG ĐƯỜNG DẪN GỐC WEB CỦA DỰ ÁN --- (NẾU CHƯA CÓ TỪ INIT.PHP)
if (!isset($project_web_root)) { // Chỉ định nghĩa nếu chưa có
    $project_web_root = '/quanlykho/'; // Đảm bảo giá trị này đúng
    if ($project_web_root !== '/') {
        $project_web_root = '/' . trim($project_web_root, '/\\') . '/';
    }
}
// Ngôn ngữ hiện tại - init.php nên đã set biến này
// $currentLang đã được set bởi init.php (ví dụ: $_SESSION['lang'] ?? 'vi')
// Mảng $lang (PHP) cũng đã được load bởi init.php

// Các thiết lập về tiền tệ và định dạng
$currency_symbol = $company_settings['currency_symbol'] ?? '₫'; 
$currency_code = $company_settings['currency_code'] ?? 'VND';   
$currency_position = $company_settings['currency_position'] ?? 'after'; 
$currency_space_between = $company_settings['currency_space_between'] ?? true; 
$decimal_separator = $company_settings['decimal_separator'] ?? ','; 
$thousands_separator = $company_settings['thousands_separator'] ?? '.'; 
$date_format_php = $company_settings['date_format'] ?? 'd/m/Y'; 

$date_format_js = str_replace(['d', 'm', 'Y', 'y'], ['dd', 'mm', 'yyyy', 'yy'], $date_format_php);
if ($date_format_php === 'd/m/Y') $date_format_js = 'dd/mm/yy';
elseif ($date_format_php === 'm/d/Y') $date_format_js = 'mm/dd/yy';
elseif ($date_format_php === 'Y-m-d') $date_format_js = 'yy-mm-dd';

// Xuất ra các biến JavaScript
echo '<script>';
echo "var PROJECT_BASE_URL = '" . htmlspecialchars($project_web_root, ENT_QUOTES, 'UTF-8') . "';\n";
echo "var siteLang = '" . htmlspecialchars($current_lang ?? 'vi', ENT_QUOTES, 'UTF-8') . "';\n"; // $current_lang từ init.php
//echo "var lang = " . json_encode($lang ?? []) . ";\n"; // $lang (PHP array) từ init.php

echo "var siteCurrency = '" . htmlspecialchars($currency_symbol, ENT_QUOTES, 'UTF-8') . "';\n";
echo "var siteCurrencyCode = '" . htmlspecialchars($currency_code, ENT_QUOTES, 'UTF-8') . "';\n";
echo "var siteCurrencyPosition = '" . htmlspecialchars($currency_position, ENT_QUOTES, 'UTF-8') . "';\n";
echo "var siteCurrencySpace = " . ($currency_space_between ? 'true' : 'false') . ";\n";
echo "var siteDecimalSeparator = '" . htmlspecialchars($decimal_separator, ENT_QUOTES, 'UTF-8') . "';\n";
echo "var siteThousandsSeparator = '" . htmlspecialchars($thousands_separator, ENT_QUOTES, 'UTF-8') . "';\n";
echo "var siteLocaleForNumber = siteLang === 'vi' ? 'vi-VN' : 'en-US';\n";
echo "var siteDateFormat = '" . htmlspecialchars($date_format_js, ENT_QUOTES, 'UTF-8') . "';\n";

// Hàm translate JS nếu cần (tốt hơn là đặt trong script.js và load script.js sau set_js_vars)
echo "if (typeof translate !== 'function') {";
echo "    function translate(key) {";
echo "        if (typeof lang !== 'undefined' && lang[key] && lang[key] !== '') { return lang[key]; }";
echo "        return key;";
echo "    }";
echo "}\n";
echo '</script>';
?>