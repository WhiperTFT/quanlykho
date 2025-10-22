<?php
/**
 * refresh_token_keepalive.php (bản tiếng Việt + log dễ đọc)
 * ------------------------------------------------------------
 * Chạy khi khởi động máy (hoặc theo lịch):
 *  - Nạp token hiện có qua gmail_client.php
 *  - Nếu access token hết hạn -> tự refresh & lưu lại
 *  - Gọi nhẹ Gmail API (getProfile) để xác minh token “còn sống”
 *  - Ghi log ra /logs/oauth_keepalive.log (tiếng Việt, giờ VN)
 *  - Ghi thêm /logs/oauth_keepalive.health.json (trạng thái tóm tắt)
 *
 * GỢI Ý LỊCH:
 *   - Windows Task Scheduler:
 *     Program:   C:\xampp\php\php.exe
 *     Arguments: C:\xampp\htdocs\quanlykho\refresh_token_keepalive.php
 *     Trigger:   At startup (hoặc Daily)
 */

date_default_timezone_set('Asia/Ho_Chi_Minh');

require __DIR__ . '/gmail_client.php'; // có make_gmail_client()

// --- Đường dẫn log & health ---
$LOG_DIR   = __DIR__ . '/logs';
$LOG_FILE  = $LOG_DIR . '/oauth_keepalive.log';
$HEALTH    = $LOG_DIR . '/oauth_keepalive.health.json';

// --- Helper: ghi 1 dòng log tiếng Việt, giờ đẹp ---
function keepalive_log(string $message, string $level = 'INFO') use ($LOG_DIR, $LOG_FILE): void {
    try {
        if (!is_dir($LOG_DIR)) {
            @mkdir($LOG_DIR, 0755, true);
        }
        $ts   = date('d-m-Y H:i:s'); // dạng Việt Nam
        $line = "[$ts] [$level] $message" . PHP_EOL;
        @file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {
        // Im lặng nếu log lỗi
    }
}

// --- Helper: ghi health.json “gọn” để giám sát ---
function write_health(array $data) use ($HEALTH): void {
    try {
        $data['updated_at'] = date('c');
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($HEALTH, $json, LOCK_EX);
    } catch (\Throwable $e) {
        // Im lặng
    }
}

// --- Chạy kiểm tra/giữ ấm ---
try {
    $client  = make_gmail_client();                 // auto-refresh nếu hết hạn (ghi đè token.json)
    $service = new Google_Service_Gmail($client);

    // Gọi nhẹ để xác minh token hoạt động
    $profile = $service->users->getProfile('me');
    $email   = $profile->getEmailAddress() ?: '(không rõ)';

    keepalive_log("Token hợp lệ. Email hồ sơ: $email", 'OK');
    write_health([
        'status'     => 'ok',
        'email'      => $email,
        'note'       => 'Token hợp lệ và có thể sử dụng Gmail API.',
    ]);

} catch (\Throwable $e) {
    $msg = $e->getMessage();

    // Nhận diện một số lỗi “đặc trưng”
    $isInvalidGrant = stripos($msg, 'invalid_grant') !== false;     // refresh_token bị thu hồi/hết hiệu lực
    $isDeletedCli   = stripos($msg, 'deleted_client') !== false;    // client_id bị xoá
    $isRedirectErr  = stripos($msg, 'redirect_uri_mismatch') !== false;

    if ($isInvalidGrant) {
        // Lỗi phổ biến nhất: refresh_token bị thu hồi (user đổi mật khẩu / thu hồi quyền / app vẫn ở Testing …)
        keepalive_log("LỖI: invalid_grant – refresh_token có thể đã bị thu hồi. Hãy xóa config/token.json và ủy quyền lại qua /quanlykho/get_token.php.", 'ERROR');
        write_health([
            'status' => 'error',
            'error'  => 'invalid_grant',
            'hint'   => 'Xoá config/token.json và chạy lại get_token.php để ủy quyền.',
        ]);
    } elseif ($isDeletedCli) {
        keepalive_log("LỖI: deleted_client – OAuth Client có thể đã bị xoá trong Google Cloud.", 'ERROR');
        write_health([
            'status' => 'error',
            'error'  => 'deleted_client',
            'hint'   => 'Kiểm tra lại OAuth Client trong Google Cloud Console.',
        ]);
    } elseif ($isRedirectErr) {
        keepalive_log("LỖI: redirect_uri_mismatch – Redirect URI không khớp. Kiểm tra Authorized redirect URIs.", 'ERROR');
        write_health([
            'status' => 'error',
            'error'  => 'redirect_uri_mismatch',
            'hint'   => 'Cập nhật Authorized redirect URIs cho localhost & ddns đúng đường dẫn /quanlykho/callback.php.',
        ]);
    } else {
        // Lỗi khác
        keepalive_log("LỖI KHÁC: $msg", 'ERROR');
        write_health([
            'status' => 'error',
            'error'  => $msg,
            'hint'   => 'Xem chi tiết trong oauth_keepalive.log',
        ]);
    }
}
