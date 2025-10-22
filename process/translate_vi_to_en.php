<?php
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$text = trim($data['text'] ?? '');

if (!$text) {
    echo json_encode(['translated' => '']);
    exit;
}

$lang_vi = [];
$lang_en = [];

// Load ngôn ngữ
require_once __DIR__ . '/../lang/vi.php';
$lang_vi = $lang;

require_once __DIR__ . '/../lang/en.php';
$lang_en = $lang;

// Tìm key tương ứng với text tiếng Việt
$matched_key = array_search($text, $lang_vi);

if ($matched_key && isset($lang_en[$matched_key])) {
    $translated = $lang_en[$matched_key];
} else {
    // Nếu không tìm được: fallback = chữ thường, viết hoa chữ cái đầu
    $translated = ucfirst(strtolower($text));
}

echo json_encode(['translated' => $translated]);
