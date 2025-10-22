<?php
require 'vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName("My Email App");
$client->setScopes(Google_Service_Gmail::MAIL_GOOGLE_COM);
$client->setAuthConfig('config/client_secret.json');
$client->setAccessType('offline'); // Quan trọng để lấy refresh_token
$client->setPrompt('consent');     // BẮT BUỘC để Google cấp refresh_token mỗi lần
$client->setRedirectUri('https://quanlykho.ddns.net/quanlykho/callback.php');

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit;
