<?php
// /quanlykho/get_token.php
require __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName("Gmail OAuth App");
$client->setScopes([
  'https://www.googleapis.com/auth/gmail.readonly',
  'https://www.googleapis.com/auth/gmail.send',
  'https://www.googleapis.com/auth/gmail.modify',
]);
$client->setAuthConfig(__DIR__ . '/config/client_secret.json');
$client->setAccessType('offline');
$client->setIncludeGrantedScopes(true);
$client->setPrompt('consent select_account');

// Tự chọn redirect theo môi trường (phải khớp Authorized redirect URIs)
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
  $client->setRedirectUri('http://localhost/quanlykho/callback.php');
} else {
  $client->setRedirectUri('https://quanlykho.ddns.net/quanlykho/callback.php');
}

// Cảnh báo nếu sắp ghi đè token cũ (nếu bạn truy cập file này trực tiếp)
$tokenPath = __DIR__ . '/config/token.json';
if (is_file($tokenPath)) {
  // Không echo HTML để khỏi cản redirect; nếu muốn, log ra file thay vì echo.
  // echo "⚠️ Warning: token.json đã tồn tại và có thể bị ghi đè.";
}

$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;
