<?php
// File: process/print_api.php
declare(strict_types=1);

ob_start();
ini_set('display_errors','0');
ini_set('log_errors','1');
if (!is_dir(__DIR__ . '/../logs')) { @mkdir(__DIR__ . '/../logs', 0775, true); }
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php'; // $pdo, session, require_login(), ...
if (!isset($pdo)) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'DB not ready']); exit; }
if (!function_exists('require_login')) { function require_login(){} }
require_login();

function jexit($ok, array $payload = [], int $code = 200): never {
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
  if ($action === 'enqueue_pxk') {
    $id      = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $copies  = max(1, (int)($_GET['copies'] ?? $_POST['copies'] ?? 1));
    $printer = trim((string)($_GET['printer'] ?? $_POST['printer'] ?? ''));

    if ($id <= 0) jexit(false, ['message'=>'Thiếu id'], 400);

    $st = $pdo->prepare("SELECT pdf_path FROM pxk_slips WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $pdf_path = (string)$st->fetchColumn();

    if ($pdf_path === '') jexit(false, ['message'=>'Chưa có đường dẫn PDF cho PXK'], 404);

    $ins = $pdo->prepare("INSERT INTO print_jobs(doc_type, doc_id, file_web_path, copies, printer_name, status, created_at)
                          VALUES('pxk', ?, ?, ?, ?, 'pending', NOW())");
    $ins->execute([$id, $pdf_path, $copies, ($printer !== '' ? $printer : null)]);

    jexit(true, [
      'job_id'        => (int)$pdo->lastInsertId(),
      'file_web_path' => $pdf_path
    ]);
  }

  if ($action === 'enqueue_path') {
    $j       = array_merge($_POST, $_GET, body_json());
    $path    = trim((string)($j['path'] ?? ''));
    $copies  = max(1, (int)($j['copies'] ?? 1));
    $printer = trim((string)($j['printer'] ?? ''));

    if ($path === '' || !preg_match('~\.pdf$~i', $path)) {
      jexit(false, ['message'=>'Thiếu hoặc sai file_web_path (phải là PDF)'], 400);
    }
    $ins = $pdo->prepare("INSERT INTO print_jobs(doc_type, doc_id, file_web_path, copies, printer_name, status, created_at)
                          VALUES('file', NULL, ?, ?, ?, 'pending', NOW())");
    $ins->execute([$path, $copies, ($printer !== '' ? $printer : null)]);

    jexit(true, [
      'job_id'        => (int)$pdo->lastInsertId(),
      'file_web_path' => $path
    ]);
  }

  if ($action === 'status') {
    $job_id = (int)($_GET['job_id'] ?? 0);
    if ($job_id <= 0) jexit(false, ['message'=>'Thiếu job_id'], 400);
    $st = $pdo->prepare("SELECT id, status, error_message, created_at, started_at, finished_at FROM print_jobs WHERE id=?");
    $st->execute([$job_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) jexit(false, ['message'=>'Không tìm thấy job'], 404);
    jexit(true, ['job'=>$row]);
  }

  if ($action === 'diag') {
    // 1) heartbeat
    $st = $pdo->query("SELECT last_seen, host, info FROM print_worker_status WHERE id=1");
    $hb = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    // 2) queue stats (2 ngày gần nhất)
    $q = $pdo->query("
      SELECT status, COUNT(*) cnt
      FROM print_jobs
      WHERE created_at >= NOW() - INTERVAL 2 DAY
      GROUP BY status
    ");
    $stats = $q->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // 3) latest jobs (không giới hạn thời gian)
    $q2 = $pdo->query("SELECT id, file_web_path, printer_name, copies, status, error_message, created_at, started_at, finished_at
                       FROM print_jobs ORDER BY id DESC LIMIT 10");
    $latest = $q2->fetchAll(PDO::FETCH_ASSOC) ?: [];

    jexit(true, [
      'heartbeat' => $hb,
      'stats'     => $stats,
      'latest'    => $latest,
      'hint'      => ($hb ? null : 'Watcher chưa chạy. Hãy mở CLI: php -f cli/watch_print_queue.php')
    ]);
  }

  jexit(false, ['message'=>'Unknown action'], 400);

} catch (Throwable $e) {
  error_log("[print_api] ".$e->getMessage());
  jexit(false, ['message'=>'Server error'], 500);
}
