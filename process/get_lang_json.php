<?php
// File: process/get_lang_json.php
require_once __DIR__ . '/../includes/init.php';

$lang_code = $_GET['lang'] ?? 'vi';
if (!in_array($lang_code, ['en', 'vi'])) $lang_code = 'vi';

$lang_file = __DIR__ . '/../lang/' . $lang_code . '.php';
if (file_exists($lang_file)) {
    require $lang_file;
    header('Content-Type: application/json');
    echo json_encode($lang);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Language file not found']);
}
