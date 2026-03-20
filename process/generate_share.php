<?php
// process/generate_share.php
// Generates secure temporary tokens for file sharing
require_once __DIR__ . '/../includes/init.php';

$files = $_POST['files'] ?? [];
if (empty($files) || !is_array($files)) {
    echo json_encode(['success' => false, 'message' => 'No files provided.']);
    exit;
}

$links = [];
$now = time();
$expire = $now + (3 * 24 * 3600); // 3 days validity

foreach ($files as $file_path) {
    // Extract raw path robustly avoiding base domains if sent via absolute strings
    $path = trim(parse_url($file_path, PHP_URL_PATH), '/');
    if (strpos($path, 'quanlykho/') === 0) {
        $path = substr($path, 10);
    }
    
    $payload = json_encode(['path' => $path, 'exp' => $expire]);
    $hash = hash_hmac('sha256', $payload, SHARE_SECRET);
    $token = base64_encode($payload) . '.' . $hash;

    // Construct full absolute URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $fullBaseUrl = $protocol . $host . rtrim(PROJECT_BASE_URL, '/') . '/';
    
    $links[] = $fullBaseUrl . 'share.php?token=' . urlencode($token);
}

echo json_encode(['success' => true, 'links' => $links]);
exit;
