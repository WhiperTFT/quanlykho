<?php
// File: includes/init.php

// --- Bắt đầu Session ---
// Quan trọng: Phải gọi trước bất kỳ output nào ra trình duyệt
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- XỬ LÝ THAM SỐ 'LANG' VÀ CHUYỂN HƯỚNG ----
// Khối mã này đã được bạn xác nhận hoạt động tốt.
if (isset($_GET['lang'])) {
    $allowed_langs = ['vi', 'en']; // Các ngôn ngữ được phép
    $selected_lang_param = $_GET['lang'];

    if (in_array($selected_lang_param, $allowed_langs)) {
        $_SESSION['lang'] = $selected_lang_param;

        // Xây dựng URL để chuyển hướng (loại bỏ tham số 'lang')
        $current_uri = strtok($_SERVER['REQUEST_URI'], '?');
        $query_params = [];
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $query_params);
        }
        unset($query_params['lang']);
        $new_query_string = http_build_query($query_params);
        $redirect_url = $current_uri;
        if (!empty($new_query_string)) {
            $redirect_url .= '?' . $new_query_string;
        }
        header("Location: " . $redirect_url);
        exit;
    }
}

// --- CẤU HÌNH ĐƯỜNG DẪN GỐC WEB CỦA DỰ ÁN ---
$project_web_root_config = '/quanlykho/'; // Cấu hình gốc của dự án của bạn
if (!defined('PROJECT_BASE_URL')) {
    if ($project_web_root_config !== '/') {
        $base_url_processed = '/' . trim($project_web_root_config, '/\\') . '/';
    } else {
        $base_url_processed = '/';
    }
    define('PROJECT_BASE_URL', $base_url_processed);
}

// --- LOAD CẤU HÌNH DATABASE VÀ CÁC THIẾT LẬP CƠ BẢN ---
require_once __DIR__ . '/../config/database.php'; // Cung cấp $dsn, $options từ config/database.php

if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', true); // Đặt là false khi lên production
}

// --- KHỞI TẠO KẾT NỐI PDO ---
global $pdo; // Khai báo $pdo là biến toàn cục
try {
    // Các hằng số DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET được define trong database.php
    // Biến $dsn và $options cũng được lấy từ database.php
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("FATAL: PDO Connection Failed in init.php: " . $e->getMessage());
    // Xử lý lỗi một cách thân thiện hơn tùy theo ngữ cảnh (AJAX hoặc trang thường)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(503); // Service Unavailable
        }
        // Cân nhắc trả về key ngôn ngữ thay vì chuỗi cứng
        echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu. Vui lòng thử lại sau.']);
    } else {
        // Có thể hiển thị một trang lỗi thân thiện hơn ở đây
        die("Lỗi kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình và thử lại.");
    }
    exit; // Dừng thực thi script
}

// Kiểm tra lại $pdo sau khối try-catch (mặc dù không nên xảy ra nếu exception được ném)
if ($pdo === null) {
    error_log("CRITICAL: \$pdo is NULL after PDO connection attempt in init.php.");
    // Xử lý tương tự như trên
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); http_response_code(500); }
        echo json_encode(['success' => false, 'message' => 'Lỗi khởi tạo kết nối cơ sở dữ liệu.']);
    } else {
        die("Lỗi khởi tạo kết nối cơ sở dữ liệu.");
    }
    exit;
}


// --- HÀM GHI NHẬT KÝ HOẠT ĐỘNG ---
// Giữ nguyên hàm log_activity của bạn từ file init.php đã cung cấp
// (Đảm bảo $pdo được truyền vào hoặc sử dụng global $pdo bên trong hàm)
function log_activity($activity) {
    global $pdo;
    if (!$pdo instanceof PDO) {
         error_log("log_activity called but PDO connection is not valid.");
         $log_file_fallback = __DIR__ . '/../logs/activity_fallback.log';
         $log_entry_fallback = "[" . date('Y-m-d H:i:s') . "] [FATAL: DB not connected] {$activity}\n";
         file_put_contents($log_file_fallback, $log_entry_fallback, FILE_APPEND | LOCK_EX);
         return;
    }
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $sql = "INSERT INTO activity_logs (user_id, timestamp, activity, ip_address) VALUES (:user_id, :timestamp, :activity, :ip_address)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, $user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindParam(':activity', $activity, PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
        if (!$stmt->execute()) {
             error_log("Error inserting activity log (PDO): " . ($stmt->errorInfo()[2] ?? 'Unknown error'));
        }
    } catch (\PDOException $e) {
         error_log("PDO Error inserting activity log: " . $e->getMessage());
    }
    $log_file = __DIR__ . '/../logs/activity.log';
    $log_entry = "[{$timestamp}] [User ID: " . ($user_id ?? 'N/A') . "] [IP: {$ip_address}] {$activity}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}


// --- XỬ LÝ NGÔN NGỮ ---
global $lang, $current_lang; // Khai báo $lang và $current_lang là biến toàn cục
$default_lang = 'vi'; // Ngôn ngữ mặc định
$current_lang_code = $_SESSION['lang'] ?? $default_lang;
$lang_file_path = __DIR__ . "/../lang/{$current_lang_code}.php";

if (file_exists($lang_file_path)) {
    require_once $lang_file_path; // File này nên định nghĩa mảng $lang
} else {
    // Nếu file ngôn ngữ đã chọn không tồn tại, fallback về ngôn ngữ mặc định
    error_log("WARNING: Language file not found: {$lang_file_path}. Falling back to default '{$default_lang}'.");
    $current_lang_code = $default_lang;
    require_once __DIR__ . "/../lang/{$default_lang}.php";
}
$current_lang = $current_lang_code; // Gán mã ngôn ngữ hiện tại cho biến toàn cục $current_lang


// --- MÚI GIỜ MẶC ĐỊNH ---
date_default_timezone_set('Asia/Ho_Chi_Minh'); // Hoặc múi giờ phù hợp với bạn


// --- KHỞI TẠO CÁC CÀI ĐẶT CHUNG CHO set_js_vars.php VÀ TOÀN BỘ ỨNG DỤNG ---
global $company_settings_g, $user_date_format_php;

// 1. Lấy thông tin công ty và các cài đặt chung từ CSDL
// Biến $company_settings_g sẽ được sử dụng bởi set_js_vars.php và có thể cả các phần khác của ứng dụng.
$company_settings_g = []; // Khởi tạo với giá trị mặc định
try {
    if (isset($pdo)) {
        $stmt_company_info = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
        $company_info_data = $stmt_company_info->fetch(PDO::FETCH_ASSOC);

        if ($company_info_data) {
            // Ánh xạ các trường từ bảng company_info vào $company_settings_g
            // Đồng thời cung cấp các giá trị mặc định cho các cài đặt mà bảng company_info có thể chưa có.
            $company_settings_g = [
                'company_name_vi'     => $company_info_data['name_vi'] ?? '',
                'company_name_en'     => $company_info_data['name_en'] ?? '',
                'company_address_vi'  => $company_info_data['address_vi'] ?? '',
                'company_address_en'  => $company_info_data['address_en'] ?? '',
                'company_tax_id'      => $company_info_data['tax_id'] ?? '',
                'company_phone'       => $company_info_data['phone'] ?? '',
                'company_email'       => $company_info_data['email'] ?? '',
                'company_website'     => $company_info_data['website'] ?? '',
                'logo_path'           => $company_info_data['logo_path'] ?? null,
                'signature_path'      => $company_info_data['signature_path'] ?? null,

                // Các cài đặt về tiền tệ:
                // QUAN TRỌNG: Các trường này ('currency_symbol', 'currency_code', etc.)
                // cần được thêm vào bảng `company_info` của bạn nếu bạn muốn chúng được quản lý từ CSDL.
                // Nếu không, chúng sẽ lấy giá trị mặc định ở đây.
                'currency_symbol'     => $company_info_data['currency_symbol'] ?? '₫',
                'currency_code'       => $company_info_data['currency_code'] ?? 'VND',
                'currency_position'   => $company_info_data['currency_position'] ?? 'after', // 'before' or 'after'
                'currency_space_between' => isset($company_info_data['currency_space_between']) ? (bool)$company_info_data['currency_space_between'] : true,

                // Các cài đặt về định dạng số:
                'decimal_separator'   => $company_info_data['decimal_separator'] ?? '.',
                'thousands_separator' => $company_info_data['thousands_separator'] ?? ',',

                // Định dạng ngày tháng mặc định của hệ thống (theo PHP)
                'date_format_php'     => $company_info_data['date_format_php'] ?? 'd/m/Y',
                // Thêm các cài đặt khác nếu cần
            ];
        } else {
            // Không tìm thấy thông tin công ty, sử dụng mảng mặc định hoàn toàn
            error_log("NOTICE: Company info (id=1) not found in database. Using default application settings.");
             $company_settings_g = [ /* Các giá trị mặc định như ở trên hoặc một bộ riêng */
                'currency_symbol'     => '₫', 'currency_code' => 'VND', 'currency_position' => 'after',
                'currency_space_between' => true, 'decimal_separator' => '.', 'thousands_separator' => ',',
                'date_format_php'     => 'd/m/Y',
             ];
        }
    } else {
        error_log("WARNING: \$pdo is not available in init.php when trying to fetch company_info for global settings. Using defaults.");
        // Fallback nếu $pdo không có (dù không nên xảy ra)
        $company_settings_g = [ /* Các giá trị mặc định */
            'currency_symbol'     => '₫', 'currency_code' => 'VND', 'currency_position' => 'after',
            'currency_space_between' => true, 'decimal_separator' => '.', 'thousands_separator' => ',',
            'date_format_php'     => 'd/m/Y',
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching company_info for global settings in init.php: " . $e->getMessage());
    // Fallback khi có lỗi CSDL
    $company_settings_g = [ /* Các giá trị mặc định */
        'currency_symbol'     => '₫', 'currency_code' => 'VND', 'currency_position' => 'after',
        'currency_space_between' => true, 'decimal_separator' => '.', 'thousands_separator' => ',',
        'date_format_php'     => 'd/m/Y',
    ];
}

// 2. Lấy định dạng ngày tháng của người dùng (ví dụ: từ session hoặc cài đặt riêng của người dùng)
// Biến này sẽ được sử dụng bởi set_js_vars.php để cung cấp định dạng ngày cho JavaScript (ví dụ: Flatpickr).
if (isset($_SESSION['user_id']) && isset($_SESSION['user_preferences']['date_format_php']) && !empty($_SESSION['user_preferences']['date_format_php'])) {
    // Giả sử bạn lưu cài đặt định dạng ngày của người dùng trong session khi họ đăng nhập
    $user_date_format_php = $_SESSION['user_preferences']['date_format_php'];
} else {
    // Nếu người dùng không có cài đặt riêng, sử dụng định dạng mặc định của hệ thống/công ty
    $user_date_format_php = $company_settings_g['date_format_php'];
}


// --- CÁC HÀM TIỆN ÍCH CHUNG ---
// (is_logged_in, is_admin, has_permission, translate, get_active_sales_quotes_for_linking)
// Giữ nguyên các hàm này từ file init.php bạn đã cung cấp.

/**
 * Kiểm tra người dùng đã đăng nhập hay chưa.
 * @return bool True nếu đã đăng nhập, False nếu chưa.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']); // Giả sử 'user_id' được set vào session khi đăng nhập thành công
}

/**
 * Kiểm tra người dùng có phải là admin hay không.
 * @return bool True nếu là admin, False nếu không phải.
 */
function is_admin(): bool {
    // Giả sử 'role' được set vào session và 'admin' là giá trị cho vai trò quản trị viên
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Danh sách các quyền có sẵn trong hệ thống (nên đồng bộ với CSDL nếu có bảng `permissions`)
// Sử dụng prefix để nhóm các quyền liên quan đến từng module
$available_permissions = [
    'dashboard_view',
    'operations_menu_view',
    'catalog_view',
    'sales_orders_create',
    'quotes_view',
    'warehouse_dispatch_view',
    'orders_list_view',
    'warehouse_status_view',

    'management_menu_view',
    'company_info_view',
    'company_info_edit',
    'partners_view',
    'partners_add',
    'partners_edit',
    'partners_delete',
    'units_view',
    'units_add',
    'units_edit',
    'units_delete',
    'users_view',
    'users_add',
    'users_edit',
    'users_delete',
    'drivers_view', // Ví dụ thêm quyền cho module tài xế
    'drivers_add',
    'drivers_edit',
    'drivers_delete',
    // Thêm các quyền khác cho các module khác ở đây
];

/**
 * Kiểm tra xem người dùng hiện tại có quyền cụ thể hay không.
 * Admin luôn có tất cả các quyền.
 *
 * @param string $permission Tên của quyền cần kiểm tra (ví dụ: 'users_view')
 * @return bool True nếu người dùng có quyền, False nếu ngược lại.
 */
function has_permission(string $permission): bool {
    if (!is_logged_in()) {
        return false;
    }
    if (is_admin()) { // Admin có mọi quyền
        return true;
    }
    // $_SESSION['user_permissions'] được kỳ vọng là một mảng các chuỗi quyền,
    // được nạp vào session khi người dùng đăng nhập.
    $user_permissions = $_SESSION['user_permissions'] ?? [];
    return in_array($permission, $user_permissions);
}

/**
 * Hàm dịch ngôn ngữ.
 * @param string $key Key ngôn ngữ cần dịch.
 * @return string Chuỗi đã dịch hoặc key gốc nếu không tìm thấy.
 */
if (!function_exists('translate')) {
    function translate($key) {
        global $lang; // Sử dụng biến $lang toàn cục đã được load từ file ngôn ngữ
        if (isset($lang[$key]) && !empty($lang[$key])) {
            return $lang[$key];
        }
        // Nếu không tìm thấy key, trả về key gốc (để dễ debug) hoặc một thông báo lỗi.
        // if (DEBUG_MODE) { return "[Missing translation: {$key}]"; }
        return $key;
    }
}


/**
 * Lấy danh sách báo giá bán hàng đang hoạt động để liên kết.
 * (Giữ nguyên hàm từ file init.php của bạn)
 * @param PDO $pdo Đối tượng kết nối PDO.
 * @return array Danh sách các báo giá.
 */
if (!function_exists('get_active_sales_quotes_for_linking')) {
    function get_active_sales_quotes_for_linking(PDO $pdo): array {
        $quotes = [];
        try {
            // Thêm customer_id vào SELECT để JS có thể tự động chọn khách hàng khi liên kết
            $sql = "SELECT sq.id, sq.quote_number, sq.quote_date, p.name as customer_name, sq.customer_id, sq.status
                    FROM sales_quotes sq
                    LEFT JOIN partners p ON sq.customer_id = p.id AND p.type = 'customer'
                    WHERE sq.status IN ('sent', 'accepted')
                    ORDER BY sq.quote_date DESC, sq.id DESC"; // Sắp xếp theo ngày gần nhất
            
            $stmt = $pdo->query($sql);
            if ($stmt) {
                $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                error_log("DEBUG: PDO query failed in get_active_sales_quotes_for_linking. ErrorInfo: " . print_r($pdo->errorInfo(), true));
            }
        } catch (PDOException $e) {
            error_log("DEBUG: PDOException in get_active_sales_quotes_for_linking: " . $e->getMessage());
        }
        return $quotes;
    }
}


// --- Ghi chú quan trọng về việc nạp quyền người dùng vào session khi đăng nhập ---
/*
Trong file xử lý đăng nhập của bạn (ví dụ: process/login_process.php),
sau khi xác thực người dùng thành công và lấy được thông tin người dùng từ CSDL,
bạn cần đảm bảo rằng cột `permissions` (thường lưu dưới dạng JSON) được giải mã
và lưu vào `$_SESSION['user_permissions']` dưới dạng một mảng PHP.

Ví dụ:
if ($user_data && password_verify($password, $user_data['password'])) { // Đăng nhập thành công
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['role'] = $user_data['role']; // 'admin', 'user', 'manager', etc.
    $_SESSION['full_name'] = $user_data['full_name'];

    // Giải mã JSON permissions và lưu vào session
    // Cột permissions trong bảng users nên có kiểu TEXT và lưu trữ một mảng JSON các chuỗi quyền.
    // Ví dụ: ["users_view", "partners_edit", "catalog_view"]
    $_SESSION['user_permissions'] = json_decode($user_data['permissions'] ?? '[]', true) ?? [];

    // Lưu các cài đặt riêng của người dùng (nếu có)
    $_SESSION['user_preferences'] = [
        'date_format_php' => $user_data['date_format_preference'] ?? ($company_settings_g['date_format_php'] ?? 'd/m/Y'),
        // Các cài đặt khác
    ];

    log_activity(translate('log_login_success') . ' (' . $user_data['username'] . ')');
    header("Location: ../dashboard.php"); // Hoặc trang mặc định sau khi đăng nhập
    exit();
}
*/
?>