<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/init.php';

use Google\Client as Google_Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

$log_base_dir = __DIR__ . '/../logs/';
if (!is_dir($log_base_dir)) {
    mkdir($log_base_dir, 0775, true);
}
$log_file_path = $log_base_dir . 'email_queue_' . date('Ymd') . '.log';

function direct_log($message, $log_path) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_path, "[QUEUE_PROCESSOR][{$timestamp}] {$message}\n", FILE_APPEND);
}

direct_log("[STARTUP] Script process_email_queue.php started. PHP Version: " . PHP_VERSION, $log_file_path);

$pdo = db_connect();
if (!$pdo) {
    direct_log('[ERROR] Database connection failed.', $log_file_path);
    exit(1);
}
direct_log('[DEBUG] Database connected successfully.', $log_file_path);

if (!isset($argv[1]) || !isset($argv[2])) {
    direct_log('Error: Missing log_id or log_type from command line. Args: ' . print_r($argv, true), $log_file_path);
    exit(1);
}

$log_id = (int)$argv[1];
$log_type = trim($argv[2]);
direct_log("Processing log ID: {$log_id} for type: {$log_type}", $log_file_path);

$target_log_table = $log_type === 'quote' ? 'quote_email_logs' : 'email_logs';
if (!in_array($log_type, ['quote', 'order'])) {
    direct_log("[ERROR] Invalid log_type received: {$log_type}. Exiting.", $log_file_path);
    exit(1);
}
direct_log("Target log table determined: {$target_log_table}", $log_file_path);

try {
    $pdo->beginTransaction();
    $sql = "SELECT * FROM {$target_log_table} WHERE id = :id AND status = 'pending' LIMIT 1 FOR UPDATE";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $log_id, PDO::PARAM_INT);
    $stmt->execute();
    $email_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email_record) {
        direct_log("Log ID {$log_id} (type: {$log_type}) not found or already processed.", $log_file_path);
        $pdo->rollBack();
        exit(0);
    }

    direct_log("Found log ID {$log_id} with status: " . $email_record['status'], $log_file_path);

    $update_sql = "UPDATE {$target_log_table} SET status = 'sending', sent_at = NOW(), message = NULL WHERE id = :id";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->bindParam(':id', $log_id, PDO::PARAM_INT);
    $update_stmt->execute();
    $pdo->commit();

    direct_log("Status updated to 'sending' for log ID {$log_id}.", $log_file_path);

    // === GMAIL AUTH CONFIG ===
    $config_path = realpath(__DIR__ . '/../config');
    $clientSecretPath = $config_path . '/client_secret.json';
    $tokenPath = $config_path . '/token.json';

    if (!file_exists($clientSecretPath)) {
        throw new Exception("client_secret.json không tồn tại tại $clientSecretPath");
    }
    if (!file_exists($tokenPath)) {
        throw new Exception("token.json không tồn tại tại $tokenPath");
    }

    $client = new Google_Client();
    $client->setApplicationName("Quản Lý Kho");
    $client->setScopes([Gmail::MAIL_GOOGLE_COM]);
    $client->setAuthConfig($clientSecretPath);
    $client->setAccessType('offline');
    $client->setRedirectUri('http://quanlykho.ddns.net/quanlykho/callback.php');

    $accessToken = json_decode(file_get_contents($tokenPath), true);
    if (!$accessToken || !is_array($accessToken)) {
        throw new InvalidArgumentException("Token JSON không hợp lệ.");
    }

    $client->setAccessToken($accessToken);
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            direct_log("Token đã được làm mới và lưu lại.", $log_file_path);
        } else {
            throw new Exception("Token hết hạn và không có refresh token.");
        }
    }

    $service = new Gmail($client);

    $sender_email = 'saothienvuong80@gmail.com';
    $boundary = uniqid('boundary_');
$subject = $email_record['subject'];
$body_html = $email_record['body'];
$to = $email_record['to_email'];
$cc = $email_record['cc_emails'];

$raw = '';
$raw .= "From: {$sender_email}\r\n";
$raw .= "To: {$to}\r\n";
if (!empty($cc)) {
    $raw .= "Cc: {$cc}\r\n";
}
$raw .= "Subject: =?utf-8?B?" . base64_encode($subject) . "?=\r\n";
$raw .= "MIME-Version: 1.0\r\n";
$raw .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

// --- Phần nội dung HTML ---
$raw .= "--{$boundary}\r\n";
$raw .= "Content-Type: text/html; charset=utf-8\r\n";
$raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
$raw .= chunk_split(base64_encode($body_html)) . "\r\n";

$attachments = json_decode($email_record['attachment_paths'], true);
if (is_array($attachments)) {
    foreach ($attachments as $web_path) {
    // Xử lý đường dẫn lỗi có thêm "/quanlykho/"
    $web_path = str_replace(['/quanlykho/', '\\quanlykho\\'], '', $web_path);

    $web_root = realpath(__DIR__ . '/../'); // C:/xampp/htdocs/quanlykho
    $relative_path = ltrim($web_path, '/\\');
    $file_path = $web_root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative_path);

    direct_log("[DEBUG] Attachment web_path (after clean): $web_path", $log_file_path);
    direct_log("[DEBUG] Resolved file_path: $file_path", $log_file_path);

    if (file_exists($file_path)) {
        $file_data = file_get_contents($file_path);
        $file_name = basename($file_path);
        $file_type = mime_content_type($file_path) ?: 'application/octet-stream';

        $raw .= "--{$boundary}\r\n";
        $raw .= "Content-Type: {$file_type}; name=\"{$file_name}\"\r\n";
        $raw .= "Content-Disposition: attachment; filename=\"{$file_name}\"\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= chunk_split(base64_encode($file_data)) . "\r\n";
    } else {
        direct_log("[WARNING] Attachment not found at resolved path: $file_path", $log_file_path);
    }
}
}

$raw .= "--{$boundary}--";

$encoded_message = base64_encode($raw);
$message = new Message();
$message->setRaw($encoded_message);


    $send_success = false;
    $message_text = null;

    try {
        $service->users_messages->send('me', $message);
        $send_success = true;
        $message_text = 'Email sent successfully.';
        direct_log("Email sent successfully for Log ID: {$log_id}.", $log_file_path);
    } catch (Exception $e) {
        $message_text = "Failed to send email: " . $e->getMessage();
        direct_log("[ERROR] [$log_id]: {$message_text}", $log_file_path);
    }

    $final_status = $send_success ? 'sent' : 'failed';
    $update_sql_final = "UPDATE {$target_log_table} SET status = :status, sent_at = NOW(), message = :message WHERE id = :id";
    $update_stmt_final = $pdo->prepare($update_sql_final);
    $update_stmt_final->bindParam(':status', $final_status);
    $update_stmt_final->bindParam(':message', $message_text);
    $update_stmt_final->bindParam(':id', $log_id, PDO::PARAM_INT);
    $update_stmt_final->execute();

    direct_log("Final status updated to '{$final_status}' for log ID {$log_id}.", $log_file_path);

} catch (Exception $e) {
    direct_log("[FATAL ERROR] Log ID {$log_id}: " . $e->getMessage(), $log_file_path);
    if ($pdo->inTransaction()) $pdo->rollBack();
    exit(1);
}

direct_log("Script finished for Log ID: {$log_id}", $log_file_path);
exit(0);
