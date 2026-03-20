<?php
// file.php - Secure File Access Proxy
// Acts as an authenticator tunnel for files physically stored in /uploads/
require_once __DIR__ . '/includes/init.php'; 
// Note: As init.php encapsulates auth_check, non-logged users are strictly bounced inherently.

$path = $_GET['path'] ?? '';
if (empty($path)) {
    http_response_code(404);
    exit("File reference missing");
}

$base_dir = realpath(__DIR__ . '/uploads');
$requested_file = realpath($base_dir . '/' . $path);

// Prevent Path Traversal Security Vulnerabilities
if ($requested_file === false || strpos($requested_file, $base_dir) !== 0 || !file_exists($requested_file) || is_dir($requested_file)) {
    http_response_code(404);
    exit("File not found or access denied");
}

$contentType = mime_content_type($requested_file);
$contentLength = filesize($requested_file);

// Buffer management for robust file streaming without exhausting physical memory
header("Content-Type: " . ($contentType ?: 'application/octet-stream'));
header("Content-Length: " . $contentLength);
header("Cache-Control: private, max-age=86400");
header("X-Content-Type-Options: nosniff"); // Security harden against MIME sniffing overrides
readfile($requested_file);
exit;
?>
