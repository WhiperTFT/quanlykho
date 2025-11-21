<?php
require_once __DIR__ . '/includes/init.php';
require_login();

require_once __DIR__ . '/includes/gmail_client.php';

$mid      = $_GET['mid']      ?? '';
$aid      = $_GET['aid']      ?? '';
$filename = $_GET['filename'] ?? 'attachment';
$mimeType = $_GET['mime']     ?? 'application/octet-stream';

// Nếu có ?download=1 thì ép tải về, ngược lại sẽ hiển thị inline
$forceDownload = !empty($_GET['download']);

if ($mid === '' || $aid === '') {
    http_response_code(400);
    echo 'Missing mid or aid.';
    exit;
}

try {
    $service = get_gmail_service();

    // Lấy body của attachment
    $attachment = $service->users_messages_attachments->get('me', $mid, $aid);
    $data       = gmail_decode_body($attachment->getData());

    if ($data === '') {
        http_response_code(404);
        echo 'Attachment not found or empty.';
        exit;
    }

    header('Content-Type: ' . $mimeType);

    if ($forceDownload) {
        // Ép trình duyệt tải file xuống
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    } else {
        // Cho phép trình duyệt hiển thị trực tiếp (PDF, hình, text...)
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
    }

    header('Content-Length: ' . strlen($data));

    echo $data;
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
