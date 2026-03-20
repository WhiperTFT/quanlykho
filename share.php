<?php
// share.php - Publicly Accessible Secure File Endpoint
// Parses signed tokens to serve files without login requirements globally
require_once __DIR__ . '/includes/init.php'; 
// Note: init.php allows /share.php through execution whitelist natively

$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(403);
    exit('Invalid or missing share link.');
}

$parts = explode('.', $token);
if (count($parts) !== 2) {
    http_response_code(403);
    exit('Malformed sharing token.');
}

$payload_b64 = $parts[0];
$hash = $parts[1];

$payload_json = base64_decode($payload_b64);
if (!$payload_json) {
    http_response_code(403);
    exit('Invalid payload decoding.');
}

$expected_hash = hash_hmac('sha256', $payload_json, SHARE_SECRET);
if (!hash_equals($expected_hash, $hash)) {
    http_response_code(403);
    exit('Token signature validation failed. Access permanently denied.');
}

$payload = json_decode($payload_json, true);
if (!$payload || !isset($payload['exp']) || !isset($payload['path'])) {
    http_response_code(403);
    exit('Corrupt token structure format.');
}

if (time() > $payload['exp']) {
    http_response_code(403);
    exit('This temporary share link has expired (Lifetime limit 3 Days).');
}

$path = $payload['path'];

if (strpos($path, 'uploads/') !== false) {
    $path = substr($path, strpos($path, 'uploads/'));
}

$base_dir = realpath(__DIR__);
$requested_file = realpath($base_dir . '/' . $path);

if ($requested_file === false || strpos($requested_file, realpath(__DIR__ . '/uploads')) !== 0 || !file_exists($requested_file) || is_dir($requested_file)) {
    http_response_code(404);
    exit("Source file not found or explicit access denied");
}

$mime_type = mime_content_type($requested_file);

header("Content-Type: " . ($mime_type ?: 'application/octet-stream'));
header("Content-Length: " . filesize($requested_file));
header("Cache-Control: public, max-age=86400"); // Cache safely up to 1 day given exact expiry nature

$extension = strtolower(pathinfo($requested_file, PATHINFO_EXTENSION));
if (in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    header("Content-Disposition: inline; filename=\"" . basename($requested_file) . "\"");
} else {
    header("Content-Disposition: attachment; filename=\"" . basename($requested_file) . "\"");
}

readfile($requested_file);
exit;
