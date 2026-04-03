<?php
// includes/init.php
// ==========================
// ⚙️ 1. CẤU HÌNH SESSION TRƯỚC KHI BẮT ĐẦU
// ==========================
$is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Thiết lập thông số session (phải làm trước session_start)
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.gc_maxlifetime', '28800'); // 8h (tùy chỉnh)
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 28800, // session cookie hết khi đóng trình duyệt
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// ==========================
// 🔒 2. QUẢN LÝ IDLE TIMEOUT & REGENERATE ID
// ==========================
define('SESSION_IDLE_TIMEOUT', (int)(ini_get('session.gc_maxlifetime') ?: 28800)); // 8h
$now = time();
if (!empty($_SESSION['user_id'])) {
    $last = (int)($_SESSION['LAST_ACTIVITY'] ?? $now);
    if ($now - $last > SESSION_IDLE_TIMEOUT) {
        // Phiên hết hạn → hủy session và chuyển login
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', $now - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        $base = defined('PROJECT_BASE_URL') ? PROJECT_BASE_URL : '/';
        $current = $_SERVER['REQUEST_URI'] ?? ($base . 'dashboard.php');
        header('Location: ' . $base . 'login.php?message=session_expired&redirect=' . urlencode($current));
        exit;
    }
    $_SESSION['LAST_ACTIVITY'] = $now;
    $now = time();
if (!empty($_SESSION['user_id'])) {
    $last = (int)($_SESSION['LAST_ACTIVITY'] ?? $now);
    if ($now - $last > SESSION_IDLE_TIMEOUT) {
        // Phiên hết hạn → hủy session và chuyển login
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', $now - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        $base = defined('PROJECT_BASE_URL') ? PROJECT_BASE_URL : '/';
        $current = $_SERVER['REQUEST_URI'] ?? ($base . 'dashboard.php');
        header('Location: ' . $base . 'login.php?message=session_expired&redirect=' . urlencode($current));
        exit;
    }
    $_SESSION['LAST_ACTIVITY'] = $now;

    // 🔔 Thêm đoạn này: qua ngày mới thì buộc login lại
    $today = date('Y-m-d');
    if (empty($_SESSION['LOGIN_DATE'])) {
        $_SESSION['LOGIN_DATE'] = $today;
    } elseif ($_SESSION['LOGIN_DATE'] !== $today) {
        // Đã qua ngày khác → hủy session và bắt login lại
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', $now - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        $base = defined('PROJECT_BASE_URL') ? PROJECT_BASE_URL : '/';
        header('Location: ' . $base . 'login.php?message=session_new_day');
        exit;
    }

    // Định kỳ regenerate ID chống fixation (30 phút)
    if (empty($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = $now;
    } elseif ($now - (int)$_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = $now;
    }
}

    // Định kỳ regenerate ID chống fixation (30 phút)
    if (empty($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = $now;
    } elseif ($now - (int)$_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = $now;
    }
}

// ==========================
// 🌐 3. XỬ LÝ NGÔN NGỮ
// ==========================
$lang_code = $_SESSION['lang'] ?? 'vi';
$lang_path = __DIR__ . '/../lang/' . $lang_code . '.php';

if (file_exists($lang_path)) {
    require $lang_path;
} else {
    require __DIR__ . '/../lang/vi.php';
}

// ==========================
// 🌐 4. CẤU HÌNH URL GỐC CỦA PROJECT
// ==========================
$project_web_root_config = '/quanlykho/';
if (!defined('PROJECT_BASE_URL')) {
    $base_url_processed = $project_web_root_config !== '/'
        ? '/' . trim($project_web_root_config, '/\\') . '/'
        : '/';
    define('PROJECT_BASE_URL', $base_url_processed);
}

// ==========================
// 🗄️ 5. KẾT NỐI DATABASE
// ==========================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_check.php';

function db_connect() {
    global $dsn, $options;
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        throw new PDOException("Không thể kết nối database: " . $e->getMessage(), (int)$e->getCode());
    }
}

$pdo = null;
try {
    $dsn_init = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options_init = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn_init, DB_USER, DB_PASS, $options_init);
} catch (PDOException $e) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(503);
        }
        echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu.']);
    } else {
        die("Lỗi kết nối cơ sở dữ liệu. Vui lòng thử lại.");
    }
    exit;
}

// ==========================
// 🌐 6. NGÔN NGỮ ĐA HỖ TRỢ
// ==========================
$default_lang_code = 'vi';
$current_lang_code = $default_lang_code;

if (isset($_GET['lang']) && in_array($_GET['lang'], ['vi', 'en'])) {
    $current_lang_code = $_GET['lang'];
    $_SESSION['lang'] = $current_lang_code;
} elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['vi', 'en'])) {
    $current_lang_code = $_SESSION['lang'];
}

$_SESSION['lang_code'] = $current_lang_code;
$lang_file_path = __DIR__ . '/../lang/' . $current_lang_code . '.php';
if (file_exists($lang_file_path)) {
    require_once $lang_file_path;
} else {
    require_once __DIR__ . '/../lang/vi.php';
}

// ==========================
// 🔑 7. REMEMBER TOKEN (login tự động nếu còn hợp lệ)
// ==========================
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE remember_token = ?");
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        $_SESSION['LAST_ACTIVITY'] = time();
    } else {
        setcookie('remember_token', '', time() - 3600, "/", "", $is_https, true);
    }
}

// ==========================
// 🕒 8. MÚI GIỜ & QUYỀN
// ==========================
date_default_timezone_set('Asia/Ho_Chi_Minh');

function is_logged_in(): bool {
    return isset($_SESSION['user_id'], $_SESSION['username']) && (int)$_SESSION['user_id'] > 0;
}

function is_admin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Lấy toàn bộ quyền từ DB
try {
    $stmt = $pdo->query("SELECT permission_key FROM permissions ORDER BY group_name, id");
    $all_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    $all_permissions = [];
    error_log('Failed to fetch permissions: ' . $e->getMessage());
}
function has_permission(string $permission): bool {
    if (!is_logged_in()) return false;
    if (is_admin()) return true;
    $user_permissions = $_SESSION['user_permissions'] ?? [];
    return in_array($permission, $user_permissions);
}
function redirect_back($fallback = '/') {
    $location = $_SERVER['HTTP_REFERER'] ?? $fallback;
    header("Location: $location");
    exit;
}

// ==========================
// 🌐 9. ĐA NGÔN NGỮ & HỖ TRỢ KHÁC
// ==========================
if (!function_exists('translate')) {
    function translate($key) {
        global $lang;
        return isset($lang[$key]) && $lang[$key] !== '' ? $lang[$key] : $key;
    }
}

if (!function_exists('get_active_sales_quotes_for_linking')) {
    function get_active_sales_quotes_for_linking(PDO $pdo): array {
        if (!$pdo) return [];
        try {
            $sql = "SELECT sq.id, sq.quote_number, sq.quote_date, p.name AS customer_name, sq.status
                    FROM sales_quotes sq
                    LEFT JOIN partners p ON sq.customer_id = p.id AND p.type = 'customer'
                    WHERE sq.status IN ('sent', 'accepted')
                    ORDER BY sq.id DESC";
            $stmt = $pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            return [];
        }
    }
}

// ==========================
// 🧾 10. LOGGING HÀNH ĐỘNG NGƯỜI DÙNG
// ==========================
require_once __DIR__ . '/../includes/logging.php';

if (!function_exists('log_user_safe')) {
    function log_user_safe($action, $message = '') {
        if (!function_exists('write_user_log') || !isset($GLOBALS['pdo'])) return;
        $pdo = $GLOBALS['pdo'];
        $uid = (int)($_SESSION['user_id'] ?? 0);
        try {
            $rf = new ReflectionFunction('write_user_log');
            $num = $rf->getNumberOfParameters();
            if ($num >= 4) {
                write_user_log($pdo, $uid, (string)$action, (string)$message);
            } else {
                write_user_log($pdo, (string)$action, (string)$message);
            }
        } catch (Throwable $ex) {
            try {
                write_user_log($pdo, $uid, (string)$action, (string)$message);
            } catch (Throwable $ex2) {
                try {
                    write_user_log($pdo, (string)$action, (string)$message);
                } catch (Throwable $ex3) {
                    // bỏ qua
                }
            }
        }
    }
}

// ==========================
// 🧹 11. TỰ ĐỘNG DỌN DẸP LOG (Auto Cleanup)
// ==========================
if (is_admin() && isset($pdo)) {
    $cleanup_dir = __DIR__ . '/../storage/logs';
    $settings_file = $cleanup_dir . '/settings.json';
    
    // Đọc cấu hình số ngày từ file settings.json
    $auto_days = 30; // Mặc định 30 ngày
    if (file_exists($settings_file)) {
        $config_json = @file_get_contents($settings_file);
        if ($config_json) {
            $config_data = json_decode($config_json, true);
            $auto_days = isset($config_data['auto_cleanup_days']) ? (int)$config_data['auto_cleanup_days'] : 30;
        }
    }

    // Chỉ chạy nếu tính năng tự động được bật (auto_days > 0)
    if ($auto_days > 0) {
        if (!is_dir($cleanup_dir)) {
            @mkdir($cleanup_dir, 0755, true);
        }
        $cleanup_log_file = $cleanup_dir . '/last_cleanup.txt';
        $today_date = date('Y-m-d');
        $needs_run = false;
        
        if (!file_exists($cleanup_log_file)) {
            $needs_run = true;
        } else {
            $last_run = trim(@file_get_contents($cleanup_log_file));
            if ($last_run !== $today_date) {
                $needs_run = true;
            }
        }
        
        if ($needs_run) {
            try {
                $threshold = date('Y-m-d H:i:s', strtotime("-{$auto_days} days"));
                $stmt_cleanup = $pdo->prepare("DELETE FROM user_logs WHERE created_at < ?");
                $stmt_cleanup->execute([$threshold]);
                $deleted_rows = $stmt_cleanup->rowCount();
                
                @file_put_contents($cleanup_log_file, $today_date);
                
                if ($deleted_rows > 0) {
                    // Ghi log nhẹ nhàng về việc tự động dọn dẹp
                    $stmt_log = $pdo->prepare("INSERT INTO user_logs (user_id, action, module, description, log_type, device_id) VALUES (?, 'DELETE', 'system', ?, 'info', 'system-auto')");
                    $stmt_log->execute([$_SESSION['user_id'] ?? 0, "Auto Cleanup: Đã dọn dẹp $deleted_rows bản ghi cũ hơn $auto_days ngày."]);
                }
            } catch (Exception $e) {
                error_log("Auto cleanup failed: " . $e->getMessage());
            }
        }
    }
}
?>
