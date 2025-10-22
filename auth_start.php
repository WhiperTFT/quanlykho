<?php
// /quanlykho/auth_start.php
require __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName("My Email App");
$client->setScopes(Google_Service_Gmail::MAIL_GOOGLE_COM); // Toàn quyền Gmail
$client->setAuthConfig(__DIR__ . '/config/client_secret.json');
$client->setAccessType('offline');
$client->setIncludeGrantedScopes(true);
$client->setPrompt('consent select_account');

// Tự chọn redirect theo môi trường
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
    $client->setRedirectUri('http://localhost/quanlykho/callback.php');
} else {
    $client->setRedirectUri('https://quanlykho.ddns.net/quanlykho/callback.php');
}

// (Tuỳ chọn) chống CSRF bằng state:
// $_SESSION['oauth2state'] = bin2hex(random_bytes(16));
// $client->setState($_SESSION['oauth2state']);

$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;
