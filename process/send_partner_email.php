<?php
require_once __DIR__ . '/vendor/autoload.php'; // Đảm bảo autoload của Composer được gọi

// --- Phần Giả định ---
// 1. Bạn đã có biến $gmailService là một instance của Google_Service_Gmail đã được xác thực OAuth 2.0
//    (Việc lấy $gmailService liên quan đến luồng OAuth 2.0, client_secret.json, refresh token,...)
// 2. Dữ liệu được gửi từ form bằng phương thức POST

// --- Lấy dữ liệu từ Form ---
$recipientTo = isset($_POST['email']) ? trim($_POST['email']) : null;
$ccEmailsInput = isset($_POST['cc_emails']) ? trim($_POST['cc_emails']) : '';
$subject = "Chủ đề email của bạn"; // Lấy từ form hoặc đặt cố định
$messageBody = "Nội dung email của bạn..."; // Lấy từ form hoặc tạo động
$senderEmail = 'me'; // Hoặc địa chỉ email cụ thể của tài khoản đã xác thực

// --- Xử lý Email CC ---
$ccRecipients = [];
if (!empty($ccEmailsInput)) {
    $potentialEmails = explode(',', $ccEmailsInput); // Tách bằng dấu phẩy
    foreach ($potentialEmails as $email) {
        $trimmedEmail = trim($email);
        if (filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL)) { // Kiểm tra định dạng email hợp lệ
            $ccRecipients[] = $trimmedEmail;
        }
    }
}

// --- Kiểm tra email người nhận chính ---
if (!$recipientTo || !filter_var($recipientTo, FILTER_VALIDATE_EMAIL)) {
    // Xử lý lỗi: Email người nhận chính không hợp lệ hoặc bị thiếu
    die('Email người nhận chính không hợp lệ.');
}

// --- Tạo nội dung Email theo chuẩn RFC 2822 ---
$emailMessage = "From: " . $senderEmail . "\r\n";
$emailMessage .= "To: " . $recipientTo . "\r\n";
if (!empty($ccRecipients)) {
    $emailMessage .= "Cc: " . implode(', ', $ccRecipients) . "\r\n"; // Thêm header Cc nếu có
}
$emailMessage .= "Subject: =?utf-8?B?" . base64_encode($subject) . "?=\r\n"; // Encode subject UTF-8
$emailMessage .= "MIME-Version: 1.0\r\n";
$emailMessage .= "Content-Type: text/html; charset=utf-8\r\n"; // Hoặc text/plain
$emailMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
$emailMessage .= base64_encode($messageBody); // Encode nội dung email

// --- Tạo đối tượng Message của Gmail API ---
$message = new Google_Service_Gmail_Message();
// Mã hóa URL-safe base64 cho toàn bộ nội dung email
$message->setRaw(strtr(base64_encode($emailMessage), '+/', '-_'));

// --- Gửi Email ---
try {
    $sentMessage = $gmailService->users_messages->send($senderEmail, $message);
    echo 'Email đã được gửi thành công. Message ID: ' . $sentMessage->getId();
    // Xử lý thành công (ví dụ: redirect, hiển thị thông báo)

} catch (Exception $e) {
    echo 'Đã xảy ra lỗi khi gửi email: ' . $e->getMessage();
    // Xử lý lỗi (ví dụ: log lỗi, hiển thị thông báo lỗi)
}

?>