<?php
declare(strict_types=1);
ob_start();
ini_set('display_errors','0');
ini_set('log_errors','1');
if (!is_dir(__DIR__ . '/../logs')) { @mkdir(__DIR__ . '/../logs', 0775, true); }
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php'; // $pdo, session, require_login()
if (!isset($pdo)) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'DB not ready']); exit; }
if (!function_exists('require_login')) { function require_login(){} }
require_login();

function jexit($ok, $payload=[], $code=200){
  if (ob_get_length()) ob_clean();
  http_response_code($code);
  echo json_encode(array_merge(['success'=>$ok], $payload), JSON_UNESCAPED_UNICODE);
  exit;
}
function body_json(): array {
  $raw = file_get_contents('php://input') ?: '';
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
  if ($action === 'log') {
    $j = array_merge($_POST, $_GET, body_json());
    $act = trim((string)($j['action'] ?? ''));
    $desc= trim((string)($j['description'] ?? ''));
    if ($act === '') jexit(false, ['message'=>'Thiếu action'], 400);

    $userId = $_SESSION['user_id'] ?? null;
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Ghi log bằng hàm chuẩn để đảm bảo có device_id từ Cookie/Session
    write_user_log($act, 'system', $desc, $j, $level);

    jexit(true);
  }

  jexit(false, ['message'=>'Unknown action'], 400);

} catch (Throwable $e) {
  error_log("[log_api] ".$e->getMessage());
  jexit(false, ['message'=>'Server error'], 500);
}
