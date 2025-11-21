<?php
// includes/gmail_client.php
// Helper tạo Google_Service_Gmail dùng chung cho mini webmail

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Lấy instance Google_Service_Gmail đã auth sẵn.
 *
 * @return Google_Service_Gmail
 * @throws RuntimeException
 */
function get_gmail_service(): Google_Service_Gmail
{
    static $service = null;

    if ($service instanceof Google_Service_Gmail) {
        return $service;
    }

    // Dùng đúng kiểu class như trong get_token.php của bạn
    $client = new Google_Client();
    $client->setApplicationName('QuanLyKho Gmail');
    $client->setAuthConfig(__DIR__ . '/../config/client_secret.json');
    $client->setAccessType('offline');
    $client->setIncludeGrantedScopes(true);

    // Phải trùng với scopes dùng trong get_token.php
    $client->setScopes([
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.send',
        'https://www.googleapis.com/auth/gmail.modify',
    ]);

    $tokenPath = __DIR__ . '/../config/token.json';
    if (!file_exists($tokenPath)) {
        throw new RuntimeException('Chưa có config/token.json. Hãy chạy get_token.php để lấy token trước.');
    }

    $accessToken = json_decode(file_get_contents($tokenPath), true);
    if (!is_array($accessToken)) {
        throw new RuntimeException('File config/token.json không hợp lệ.');
    }

    $client->setAccessToken($accessToken);

    // Tự refresh token nếu hết hạn
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        } else {
            throw new RuntimeException('Không có refresh token. Xóa token.json và chạy lại get_token.php.');
        }
    }

    $service = new Google_Service_Gmail($client);
    return $service;
}

/**
 * Lấy headers cơ bản (From, To, Subject, Date) từ message
 */
function gmail_get_basic_headers($message): array
{
    $headers = $message->getPayload()->getHeaders();
    $result = [
        'From'    => '',
        'To'      => '',
        'Subject' => '',
        'Date'    => '',
    ];

    foreach ($headers as $h) {
        $name = $h->getName();
        if (isset($result[$name])) {
            $result[$name] = $h->getValue();
        }
    }

    return $result;
}

/**
 * Hàm decode base64 URL-safe của Gmail
 */
function gmail_decode_body(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }
    $data = strtr($data, '-_', '+/');
    // Thêm padding nếu thiếu
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($data) ?: '';
}

/**
 * Đệ quy tìm part có mime type mong muốn trong cấu trúc multipart lồng nhau
 */
function gmail_find_part_by_mime($payloadOrPart, string $mime)
{
    // Nếu chính nó đã là mime cần tìm
    if ($payloadOrPart->getMimeType() === $mime) {
        $body = $payloadOrPart->getBody();
        if ($body && $body->getData()) {
            return $payloadOrPart;
        }
    }

    // Duyệt các parts (nếu có)
    $parts = $payloadOrPart->getParts();
    if ($parts) {
        foreach ($parts as $p) {
            // Nếu part này có mime đúng + có data → trả luôn
            if ($p->getMimeType() === $mime && $p->getBody() && $p->getBody()->getData()) {
                return $p;
            }

            // Nếu còn parts con → đệ quy
            if ($p->getParts()) {
                $found = gmail_find_part_by_mime($p, $mime);
                if ($found) {
                    return $found;
                }
            }
        }
    }

    return null;
}

/**
 * Lấy body của email.
 * Ưu tiên text/html, fallback text/plain (được escape & nl2br).
 */
function gmail_get_body_html($payload): string
{
    // 1) Nếu payload có body trực tiếp → dùng luôn
    $bodyData = $payload->getBody() ? $payload->getBody()->getData() : null;
    if ($bodyData) {
        return gmail_decode_body($bodyData);
    }

    // 2) Thử tìm part text/html trong toàn bộ cấu trúc multipart (đệ quy)
    $htmlPart = gmail_find_part_by_mime($payload, 'text/html');
    if ($htmlPart) {
        $data = $htmlPart->getBody()->getData();
        if ($data) {
            return gmail_decode_body($data);
        }
    }

    // 3) Nếu không có HTML, thử text/plain
    $plainPart = gmail_find_part_by_mime($payload, 'text/plain');
    if ($plainPart) {
        $data = $plainPart->getBody()->getData();
        if ($data) {
            $text = gmail_decode_body($data);
            return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        }
    }

    // 4) Bất đắc dĩ đành chịu
    return '(Không đọc được nội dung email)';
}

/**
 * Lấy danh sách file đính kèm trong 1 message.
 * Trả về mảng: [
 *   ['filename' => ..., 'mimeType' => ..., 'size' => ..., 'attachmentId' => ..., 'messageId' => ...],
 *   ...
 * ]
 */
function gmail_extract_attachments($message): array
{
    $result    = [];
    $messageId = $message->getId();
    $payload   = $message->getPayload();

    if (!$payload) {
        return $result;
    }

    $stack = [$payload];

    while (!empty($stack)) {
        /** @var Google_Service_Gmail_MessagePart $part */
        $part = array_pop($stack);

        // Nếu part này có filename => có khả năng là attachment
        $filename = $part->getFilename();
        $body     = $part->getBody();

        if ($filename && $body) {
            $attachmentId = $body->getAttachmentId();
            if ($attachmentId) {
                $result[] = [
                    'filename'     => $filename,
                    'mimeType'     => $part->getMimeType(),
                    'size'         => $body->getSize(),
                    'attachmentId' => $attachmentId,
                    'messageId'    => $messageId,
                ];
            }
        }

        // Duyệt tiếp các parts con
        $subParts = $part->getParts();
        if ($subParts) {
            foreach ($subParts as $sp) {
                $stack[] = $sp;
            }
        }
    }

    return $result;
}
