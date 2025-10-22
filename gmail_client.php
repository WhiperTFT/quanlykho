<?php
/**
 * gmail_client.php
 * ------------------------------------------------------------
 * Tạo Google_Client + Gmail Service, tự refresh token khi hết hạn
 * và ghi đè lại /config/token.json một cách an toàn.
 *
 * YÊU CẦU:
 * - composer require google/apiclient
 * - Đã có file /quanlykho/config/client_secret.json
 * - Đã cấp quyền và tạo token tại /quanlykho/config/token.json
 *
 * BẢO MẬT:
 * - Chặn tải token.json qua web bằng .htaccess
 * - (Khuyến nghị) đặt token.json ra ngoài webroot nếu có thể
 */

require __DIR__ . '/vendor/autoload.php';

// ====== Cấu hình đường dẫn ======
const QLK_CONFIG_DIR        = __DIR__ . '/config';
const QLK_TOKEN_PATH        = QLK_CONFIG_DIR . '/token.json';
const QLK_CLIENT_SECRET     = QLK_CONFIG_DIR . '/client_secret.json';

// Log “nhẹ” (tùy chọn): đổi thành null nếu không muốn log ra file
const QLK_LOG_DIR           = __DIR__ . '/logs';
const QLK_GMAIL_LOG_PATH    = QLK_LOG_DIR . '/gmail_client.log';

/**
 * Ghi 1 dòng log an toàn (tạo thư mục nếu chưa có)
 */
function qlk_gmail_log(string $message): void {
    try {
        if (!QLK_GMAIL_LOG_PATH) return;
        if (!is_dir(QLK_LOG_DIR)) {
            @mkdir(QLK_LOG_DIR, 0755, true);
        }
        $line = '[' . date('c') . '] ' . $message . PHP_EOL;
        @file_put_contents(QLK_GMAIL_LOG_PATH, $line, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {
        // im lặng nếu log lỗi
    }
}

/**
 * Ghi JSON “an toàn” ra file (ghi vào file tạm rồi rename).
 */
function qlk_write_json_atomic(string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Không thể tạo thư mục: $dir");
        }
    }
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new \RuntimeException('Không thể encode JSON token.');
    }
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new \RuntimeException("Không thể ghi file tạm: $tmp");
    }
    if (!@rename($tmp, $path)) {
        // nếu rename thất bại, thử copy + unlink
        if (!@copy($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Không thể thay thế token: $path");
        }
        @unlink($tmp);
    }
}

/**
 * Tạo Google_Client đã nạp token và auto-refresh nếu hết hạn.
 * - Trả về Google_Client đã sẵn sàng sử dụng.
 */
function make_gmail_client(): Google_Client {
    if (!is_file(QLK_CLIENT_SECRET)) {
        throw new \RuntimeException("Thiếu file client_secret.json tại: " . QLK_CLIENT_SECRET);
    }
    if (!is_file(QLK_TOKEN_PATH)) {
        throw new \RuntimeException(
            "Chưa có token.json. Hãy mở /quanlykho/get_token.php để cấp quyền trước."
        );
    }

    // Đọc token từ file
    $raw = file_get_contents(QLK_TOKEN_PATH);
    $token = json_decode($raw, true);
    if (!is_array($token)) {
        throw new \RuntimeException("token.json không hợp lệ (không parse được JSON).");
    }

    // Khởi tạo client
    $client = new Google_Client();
    $client->setApplicationName("Gmail OAuth App");
    $client->setScopes('https://www.googleapis.com/auth/gmail.send');
    $client->setAuthConfig(QLK_CLIENT_SECRET);
    $client->setAccessType('offline');
    $client->setIncludeGrantedScopes(true);
    // KHÔNG setRedirectUri ở đây (chỉ cần khi xin quyền lần đầu)
    $client->setAccessToken($token);

    // Nếu access token hết hạn => dùng refresh_token để lấy token mới
    if ($client->isAccessTokenExpired()) {
        qlk_gmail_log('Access token hết hạn, thực hiện refresh...');
        $refreshToken = $client->getRefreshToken();

        if (!$refreshToken) {
            // Không có refresh_token => phải re-auth
            throw new \RuntimeException(
                "Thiếu refresh_token. Hãy xóa config/token.json và ủy quyền lại (prompt=consent select_account)."
            );
        }

        $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (isset($newToken['error'])) {
            // Ví dụ: invalid_grant => refresh_token bị thu hồi/hết hiệu lực
            qlk_gmail_log('Lỗi refresh token: ' . ($newToken['error_description'] ?? $newToken['error']));
            throw new \RuntimeException("Lỗi refresh token: " . ($newToken['error_description'] ?? $newToken['error']));
        }

        // LƯU Ý: google/apiclient trả về mảng token mới, nhưng đôi khi không chứa refresh_token
        // => Ghép refresh_token cũ nếu cần để đảm bảo lần sau vẫn refresh được.
        $merged = array_merge($token, $newToken);
        if (empty($merged['refresh_token']) && !empty($refreshToken)) {
            $merged['refresh_token'] = $refreshToken;
        }

        // Lưu lại token mới về file
        qlk_write_json_atomic(QLK_TOKEN_PATH, $merged);
        qlk_gmail_log('Đã refresh & ghi đè token.json thành công.');
    }

    return $client;
}

/**
 * Tạo Gmail Service tiện dụng.
 */
function make_gmail_service(): Google_Service_Gmail {
    $client = make_gmail_client();
    return new Google_Service_Gmail($client);
}

/**
 * (Tuỳ chọn) Gửi email đơn giản (text/plain) — tiện test nhanh.
 * Dùng: gmail_send_simple('to@example.com', 'Tiêu đề', "Nội dung");
 */
function gmail_send_simple(string $to, string $subject, string $bodyText, ?string $from = null): string {
    $service = make_gmail_service();

    // Header cơ bản
    $headers  = '';
    if ($from) $headers .= "From: $from\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";

    $raw = $headers . $bodyText;

    // Base64 URL-safe
    $base64 = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

    $msg = new Google_Service_Gmail_Message();
    $msg->setRaw($base64);

    $res = $service->users_messages->send('me', $msg);
    $id  = $res->getId() ?? '';
    qlk_gmail_log("Đã gửi email đơn giản -> $to; messageId=$id");

    return $id;
}
