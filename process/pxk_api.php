<?php
declare(strict_types=1);
header("Expires: 0");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false); // fix cho IE
header("Pragma: no-cache");
/* ==== BẮT LỖI CHUẨN JSON & HEADERS (bỏ qua deprecated) ==== */
ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(__DIR__ . '/../logs')) { @mkdir(__DIR__ . '/../logs', 0775, true); }
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

header('Content-Type: application/json; charset=utf-8');

/* Quan trọng: loại bỏ E_DEPRECATED & E_USER_DEPRECATED để khỏi ngã 500 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

set_error_handler(function($no, $str, $file, $line){
    if ($no === E_DEPRECATED || $no === E_USER_DEPRECATED) {
        error_log("DEPRECATED: $str in $file:$line");
        return true; // swallow
    }
    error_log("PHP[$no] $str in $file:$line");
    if (ob_get_length()) { ob_clean(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error','detail'=>"[$no] $str"], JSON_UNESCAPED_UNICODE);
    exit;
});

set_exception_handler(function($ex){
    error_log("EXC ".get_class($ex).": ".$ex->getMessage()." @ ".$ex->getFile().":".$ex->getLine());
    if (ob_get_length()) { ob_clean(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server exception','detail'=>$ex->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

/* ==== INIT & HELPERS ==== */
require_once __DIR__ . '/../includes/init.php'; // có $pdo, session, auth...
if (!isset($pdo)) { if (ob_get_length()) ob_clean(); http_response_code(500); echo json_encode(['success'=>false,'message'=>'DB not ready']); exit; }
if (!function_exists('require_login')) { function require_login(){} }
require_login();

function jexit(bool $ok, array $payload=[], int $code=200) {
    if (ob_get_length()) { ob_clean(); }
    http_response_code($code);
    echo json_encode(array_merge(['success'=>$ok], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}
function body_json(): array {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function to_dmy_text(?string $ymd): string {
    $ts = $ymd ? strtotime($ymd) : time();
    return 'Ngày '.date('d',$ts).' tháng '.date('m',$ts).' năm '.date('Y',$ts);
}

/* company settings */
function get_company(PDO $pdo): array {
    $company = [
        'name_vi'     => '',
        'address_vi'  => '',
        'phone'       => '',
        'tax_id'      => '',
        'website'     => '',
        'email'       => '',
        'logo_path'   => '',
    ];
    try {
        $rs = $pdo->query("SELECT name_vi, address_vi, phone, tax_id, website, email, logo_path FROM company_info ORDER BY id ASC LIMIT 1");
        if ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
            $company = array_merge($company, $row);
        }
    } catch (Throwable $e) {}
    return $company;
}

// Chuyển logo thành src hợp lệ (ưu tiên file -> base64, fallback URL)
function resolve_logo_src(string $path): string {
    $p = trim($path);
    if ($p === '') return '';
    if (preg_match('~^https?://~i', $p)) return $p;

    $candidates = [];
    $webroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if ($webroot) {
        $candidates[] = ($p[0] === '/') ? ($webroot.$p) : ($webroot.'/'.$p);
    }
    $proj = realpath(__DIR__.'/..');
    if ($proj) {
        $candidates[] = ($p[0] === '/') ? ($proj.$p) : ($proj.'/'.$p);
    }

    foreach ($candidates as $abs) {
        if (is_file($abs) && is_readable($abs)) {
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            $mime = match($ext){
                'jpg','jpeg' => 'image/jpeg',
                'png'       => 'image/png',
                'gif'       => 'image/gif',
                'svg'       => 'image/svg+xml',
                default     => 'application/octet-stream',
            };
            $data = base64_encode(@file_get_contents($abs));
            if ($data) return "data:$mime;base64,$data";
        }
    }
    return $p;
}

/** Sinh số PXK duy nhất: PXKddmmyyyyNN */
function next_pxk_number(PDO $pdo, string $pxk_date): string {
    $ts = strtotime($pxk_date ?: date('Y-m-d'));
    $ymd = date('Y-m-d', $ts);
    $prefix = 'PXK' . date('dmY', $ts);

    $sql = "INSERT INTO pxk_number_seq(ymd, last_seq)
            VALUES(?, 1)
            ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1)";
    $st  = $pdo->prepare($sql);
    $st->execute([$ymd]);
    $seq = (int)$pdo->lastInsertId();

    $num = str_pad((string)$seq, 2, '0', STR_PAD_LEFT);
    return $prefix . $num;
}

// === PRINT WORKER CONFIG & HELPERS (spawn tiến trình ẩn) ===
$__PRINT_PHP_EXE = 'C:\\xampp\\php\\php.exe';
$__PRINT_SCRIPT  = 'C:\\xampp\\htdocs\\quanlykho\\cli\\process_print_job.php';
$__PRINT_WORKDIR = 'C:\\xampp\\htdocs\\quanlykho';
$__POWERSHELL_EXE= 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';

function print_spawn_log($msg) {
    $f = __DIR__ . '/../logs/print_spawn.log';
    @file_put_contents($f, '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL, FILE_APPEND);
}
function cli_spawn_allowed(): array {
    $df = strtolower((string)ini_get('disable_functions'));
    $disabled = array_filter(array_map('trim', explode(',', $df)));
    return [
        'exec'       => !in_array('exec', $disabled, true),
        'shell_exec' => !in_array('shell_exec', $disabled, true),
        'popen'      => !in_array('popen', $disabled, true),
        'proc_open'  => !in_array('proc_open', $disabled, true),
    ];
}
/**
 * Đá 1 job xử lý nền: trả true nếu spawn thành công.
 */
function kickJobDetached(int $jobId, string $PHP_EXE, string $SCRIPT, string $WORKDIR, string $PS_EXE): bool {
    $allow = cli_spawn_allowed();
    print_spawn_log("Kick job {$jobId} | allow=" . json_encode($allow));

    // Ưu tiên PowerShell Start-Process (ổn định dưới Apache service)
    if ($allow['exec']) {
    // Nội dung lệnh chạy bên trong -Command (dùng nháy kép cho path có khoảng trắng)
    $psInner = 'Start-Process -FilePath "' . $PHP_EXE . '" '
             . '-ArgumentList @("' . $SCRIPT . '","--id=' . $jobId . '") '
             . '-WorkingDirectory "' . $WORKDIR . '" '
             . '-WindowStyle Hidden -NoNewWindow';

    // Bọc toàn khối -Command bằng escapeshellarg để exec không vỡ nháy
    $cmd = '"' . $PS_EXE . '" -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden '
         . '-Command ' . escapeshellarg($psInner) . ' >NUL 2>&1';

    @exec($cmd, $o, $r);
    print_spawn_log("Method PS Start-Process rc=$r cmd=".$cmd);
    if ($r === 0) return true;
}

    // Fallback: cmd start /MIN qua proc_open
    if ($allow['proc_open']) {
        $phpCmd = '"'.$PHP_EXE.'" "'.$SCRIPT.'" --id='.$jobId;
        $cmd = 'cmd /c start "" /MIN '.$phpCmd.' >NUL 2>&1';
        $desc = [0=>['pipe','r'],1=>['file','NUL','w'],2=>['file','NUL','w']];
        $proc = @proc_open($cmd, $desc, $pipes, $WORKDIR);
        $ok = is_resource($proc);
        if ($ok) @proc_close($proc);
        print_spawn_log("Method cmd start result=".($ok?'ok':'fail')." cmd=".$cmd);
        if ($ok) return true;
    }

    // Fallback: popen
    if ($allow['popen']) {
        $phpCmd = '"'.$PHP_EXE.'" "'.$SCRIPT.'" --id='.$jobId;
        $cmd = 'start "" /MIN '.$phpCmd.' >NUL 2>&1';
        $h = @popen($cmd, 'r');
        $ok = is_resource($h);
        if ($ok) @pclose($h);
        print_spawn_log("Method popen result=".($ok?'ok':'fail')." cmd=".$cmd);
        if ($ok) return true;
    }

    // Cuối cùng: exec trực tiếp (blocking ngắn)
    if ($allow['exec']) {
        $phpCmd = '"'.$PHP_EXE.'" "'.$SCRIPT.'" --id='.$jobId.' >NUL 2>&1 &';
        @exec($phpCmd, $o, $r);
        print_spawn_log("Method exec direct rc=$r cmd=".$phpCmd);
        if ($r === 0) return true;
    }

    // COM (nếu có)
    if (class_exists('COM')) {
        try {
            $W = new COM("WScript.Shell");
            $rc = $W->Run('"'.$PHP_EXE.'" "'.$SCRIPT.'" --id='.$jobId, 0, false);
            print_spawn_log("Method COM rc=".$rc);
            if ((int)$rc === 0) return true;
        } catch (Throwable $e) {
            print_spawn_log("Method COM EXC=".$e->getMessage());
        }
    }

    print_spawn_log("All spawn methods failed for job {$jobId}");
    return false;
}
function get_setting(PDO $pdo, string $key, ?string $default=null): ?string {
    try {
        $st = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key`=? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false) ? $default : (string)$v;
    } catch (Throwable $e) { return $default; }
}
function set_setting(PDO $pdo, string $key, string $value): bool {
    $st = $pdo->prepare("INSERT INTO app_settings(`key`,`value`) VALUES(?,?)
                         ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    return $st->execute([$key,$value]);
}

/* ==== ROUTER ==== */
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$__debug = isset($_GET['debug']) && $_GET['debug'] === '1';

switch ($action) {

    /* ---- Danh sách (lọc + phân trang, chống HY093) ---- */
    case 'list': {
        $kw       = trim($_GET['kw'] ?? '');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $per_page = (int)($_GET['per_page'] ?? 10); // 0 = All

        $conds  = [];
        $params = [];

        if ($kw !== '') {
            $kwMulti = preg_replace('/\s+/', '%', $kw);
            $like = '%'.$kwMulti.'%';

            if (ctype_digit($kw)) {
                $conds[]  = "id = ?";
                $params[] = (int)$kw;
            }

            $conds[]  = "(pxk_number LIKE ? OR partner_name LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = '';
        if (!empty($conds)) $whereSql = ' WHERE ' . implode(' AND ', $conds);

        // total
        $sqlCount = "SELECT COUNT(*) FROM pxk_slips" . $whereSql;
        $stc = $pdo->prepare($sqlCount);
        $stc->execute($params);
        $total = (int)$stc->fetchColumn();

        // paging
        $limit  = ($per_page > 0) ? max(1, $per_page) : 0;
        $offset = ($per_page > 0) ? max(0, ($page - 1) * $per_page) : 0;

        $sql = "SELECT id, pxk_number,
                       DATE_FORMAT(pxk_date,'%d/%m/%Y') AS pxk_date_display,
                       partner_name,
                       pdf_path AS pdf_web_path
                FROM pxk_slips" . $whereSql . " ORDER BY id DESC";

        if ($limit > 0) $sql .= " LIMIT {$limit} OFFSET {$offset}";
        else $sql .= " LIMIT 2000";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        jexit(true, ['rows'=>$rows, 'total'=>$total]);
    }

    /* ---- Lấy 1 phiếu ---- */
    case 'get': {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) jexit(false, ['message'=>'Thiếu id'], 400);

        $st = $pdo->prepare("SELECT * FROM pxk_slips WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) jexit(false, ['message'=>'Không tìm thấy PXK'], 404);

        $items = [];
        if (!empty($row['items_json'])) {
            $tmp = json_decode($row['items_json'], true);
            if (is_array($tmp)) $items = $tmp;
        }
        $row['pxk_date_display'] = $row['pxk_date'] ? date('d/m/Y', strtotime($row['pxk_date'])) : '';
        $row['items'] = $items;
        jexit(true, ['row'=>$row]);
    }

    /* ---- Xóa ---- */
    case 'delete': {
        $j = body_json();
        $id = (int)($j['id'] ?? 0);
        if ($id <= 0) jexit(false, ['message'=>'Thiếu id'], 400);

        $st = $pdo->prepare("DELETE FROM pxk_slips WHERE id=?");
        $st->execute([$id]);
        jexit(true);
    }

    /* ---- Tạo số tự động (đơn giản) ---- */
    case 'generate_number': {
        $today = date('Y-m-d');
        $prefix = 'PXK'.date('dmY');
        $st = $pdo->prepare("SELECT COUNT(*) FROM pxk_slips WHERE pxk_date = ?");
        $st->execute([$today]);
        $count = (int)$st->fetchColumn();
        $num = str_pad((string)($count+1), 2, '0', STR_PAD_LEFT);
        jexit(true, ['pxk_number' => $prefix.$num]);
    }

    /* ---- Lưu (insert/update + tránh trùng số) ---- */
    case 'save': {
        $j = body_json();

        $id              = (int)($j['id'] ?? 0);
        $pxk_number      = trim((string)($j['pxk_number'] ?? ''));
        $pxk_date        = trim((string)($j['pxk_date'] ?? ''));
        $notes           = trim((string)($j['notes'] ?? ''));
        $partner_name    = trim((string)($j['partner_name'] ?? ''));
        $partner_address = trim((string)($j['partner_address'] ?? ''));
        $contact_person  = trim((string)($j['contact_person'] ?? ($j['contact_name'] ?? '')));
        $partner_phone   = trim((string)($j['partner_phone']  ?? ($j['phone'] ?? '')));
        $items           = is_array($j['items'] ?? null) ? $j['items'] : [];

        if ($pxk_date==='' || $partner_name==='' || count($items)===0) {
            jexit(false, ['message'=>'Thiếu dữ liệu bắt buộc'], 400);
        }
        $items_json = json_encode($items, JSON_UNESCAPED_UNICODE);

        if ($id <= 0 && $pxk_number === '') {
            $pxk_number = next_pxk_number($pdo, $pxk_date);
        }

        if ($id > 0) {
            if ($pxk_number !== '') {
                $st = $pdo->prepare("SELECT COUNT(*) FROM pxk_slips WHERE pxk_number = ? AND id <> ?");
                $st->execute([$pxk_number, $id]);
                if ((int)$st->fetchColumn() > 0) {
                    jexit(false, ['message'=>'Số phiếu đã tồn tại, vui lòng đổi số khác hoặc để trống để hệ thống tự phát'], 409);
                }
            } else {
                $pxk_number = next_pxk_number($pdo, $pxk_date);
            }

            $sql = "UPDATE pxk_slips
                    SET pxk_number=?, pxk_date=?, partner_name=?, partner_address=?, partner_phone=?, contact_person=?, notes=?, items_json=?, updated_at=NOW()
                    WHERE id=?";
            $st = $pdo->prepare($sql);
            $st->execute([
                $pxk_number, $pxk_date, $partner_name, $partner_address, $partner_phone,
                $contact_person, $notes, $items_json, $id
            ]);
            jexit(true, ['id'=>$id, 'pxk_number'=>$pxk_number]);
        }

        $maxRetry = 3;
        for ($try=1; $try <= $maxRetry; $try++) {
            try {
                $sql = "INSERT INTO pxk_slips(pxk_number, pxk_date, partner_name, partner_address, partner_phone, contact_person, notes, items_json, total_amount, created_at)
                        VALUES(?,?,?,?,?,?,?,?,?,NOW())";
                $st = $pdo->prepare($sql);
                $st->execute([
                    $pxk_number, $pxk_date, $partner_name, $partner_address, $partner_phone,
                    $contact_person, $notes, $items_json, 0.00
                ]);
                $newId = (int)$pdo->lastInsertId();
                jexit(true, ['id'=>$newId, 'pxk_number'=>$pxk_number]);
            } catch (PDOException $e) {
                if ($e->errorInfo[0] === '23000' && (strpos($e->getMessage(), '1062') !== false || (int)($e->errorInfo[1] ?? 0) === 1062)) {
                    $pxk_number = next_pxk_number($pdo, $pxk_date);
                    if ($try === $maxRetry) {
                        jexit(false, ['message'=>'Không thể phát số phiếu (trùng lặp liên tiếp). Thử lại sau.'], 500);
                    }
                } else {
                    throw $e;
                }
            }
        }
    }

    /* ---- Gợi ý sản phẩm ---- */
    case 'product_suggest': {
        $term = trim($_GET['term'] ?? '');
        if ($term === '') { if (ob_get_length()) ob_clean(); echo json_encode([]); exit; }

        $sql = "
            SELECT p.id, p.name, u.name AS unit_name, c.name AS category_name
            FROM products p
            LEFT JOIN units u      ON u.id = p.unit_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.name LIKE :kw
            ORDER BY p.name ASC
            LIMIT 20
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':kw'=>"%{$term}%"]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (ob_get_length()) ob_clean();
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ---- Gợi ý đối tác ---- */
    case 'partner_search': {
        try {
            $kw = trim($_GET['kw'] ?? '');
            if ($kw === '') { jexit(true, ['rows' => []]); }

            $like = '%'.$kw.'%';
            $sql = "
                SELECT id, name, address, phone, contact_person
                FROM partners
                WHERE name LIKE :kw1
                   OR address LIKE :kw2
                   OR phone LIKE :kw3
                   OR contact_person LIKE :kw4
                ORDER BY name ASC
                LIMIT 50
            ";
            $params = [
                ':kw1'=>$like, ':kw2'=>$like, ':kw3'=>$like, ':kw4'=>$like
            ];
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            jexit(true, ['rows' => $rows]);

        } catch (Throwable $e) {
            error_log("[partner_search] ".$e->getMessage());
            if ($__debug) jexit(false, ['message'=>'DEBUG: '.$e->getMessage()], 500);
            jexit(false, ['message' => 'Lỗi truy vấn đối tác'], 500);
        }
    }

    /* ---- Lấy đối tác by name ---- */
    case 'partner_get_by_name': {
        $name = trim($_GET['name'] ?? '');
        if ($name === '') jexit(false, ['message'=>'Thiếu name'], 400);
        $st = $pdo->prepare("SELECT id, name, address FROM partners WHERE name = ? LIMIT 1");
        $st->execute([$name]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) jexit(false, ['message'=>'Không tìm thấy đối tác'], 404);
        jexit(true, ['row'=>$row]);
    }
    /* ---- Danh sách máy in (Windows) ---- */
case 'printers_list': {
    $printers = [];
    $winDefault = '';
    $appDefault = trim((string)get_setting($pdo, 'printer_default', ''));

    // Ưu tiên PowerShell (ra JSON đẹp)
    $ps = $__POWERSHELL_EXE ?? 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
    $cmd = '"'.$ps.'" -NoProfile -ExecutionPolicy Bypass -Command ' .
           escapeshellarg('Get-Printer | Select-Object Name,DriverName,Default | ConvertTo-Json -Compress');

    @exec($cmd.' 2>&1', $out, $rc);
    if ($rc === 0) {
        $json = implode("\n", $out);
        $arr  = json_decode($json, true);
        if (is_array($arr)) {
            // PS trả về object nếu chỉ có 1 máy in
            if (isset($arr['Name'])) $arr = [$arr];
            foreach ($arr as $p) {
                $printers[] = [
                    'name'    => (string)($p['Name'] ?? ''),
                    'driver'  => (string)($p['DriverName'] ?? ''),
                    'default' => (bool)($p['Default'] ?? false),
                ];
            }
            foreach ($printers as $p) if ($p['default']) { $winDefault = $p['name']; break; }
        }
    }

    // Fallback WMIC nếu PowerShell không được
    if (!$printers) {
        @exec('wmic printer get Name,Default,DriverName /format:csv 2>&1', $out2, $rc2);
        if ($rc2 === 0 && $out2) {
            foreach ($out2 as $line) {
                if (preg_match('/^[^,]*,(.+),(.+),(.+)$/', trim($line), $m)) {
                    $name = trim($m[1]); $def = trim($m[2]); $drv = trim($m[3]);
                    if ($name && $name!=='Name') {
                        $isDef = stripos($def,'TRUE')!==false || $def==='TRUE' || $def==='True';
                        $printers[] = ['name'=>$name, 'driver'=>$drv, 'default'=>$isDef];
                        if ($isDef && !$winDefault) $winDefault = $name;
                    }
                }
            }
        }
    }

    jexit(true, ['printers'=>$printers, 'windows_default'=>$winDefault, 'app_default'=>$appDefault]);
}
/* ---- Lưu máy in mặc định của ứng dụng ---- */
case 'printer_save': {
    $j = body_json();
    $name = trim((string)($j['name'] ?? '')); // '' = xoá -> dùng Windows Default
    $ok = set_setting($pdo, 'printer_default', $name);
    jexit($ok, ['saved'=>$ok, 'app_default'=>$name]);
}
/* ---- Dọn hàng đợi: fail job kẹt ---- */
case 'print_cleanup': {
    $j = body_json();
    $printingMinutes = max(1, (int)($j['older_minutes'] ?? 10));     // printing quá X phút
    $pendingMinutes  = max(1, (int)($j['pending_older_minutes'] ?? 120)); // pending quá Y phút

    // Tạo message ở PHP để khỏi lặp placeholder
    $msgPrinting = 'Dọn hàng đợi: printing quá ' . $printingMinutes . ' phút @ ' . date('Y-m-d H:i:s');
    $msgPending  = 'Dọn hàng đợi: pending quá '  . $pendingMinutes  . ' phút @ ' . date('Y-m-d H:i:s');

    // printing quá hạn
    $sql1 = "
        UPDATE print_jobs
        SET status='failed',
            error_message=?,
            finished_at=NOW()
        WHERE status='printing'
          AND started_at IS NOT NULL
          AND TIMESTAMPDIFF(MINUTE, started_at, NOW()) > ?
    ";
    $st1 = $pdo->prepare($sql1);
    $st1->execute([$msgPrinting, $printingMinutes]);
    $a1 = $st1->rowCount();

    // pending quá hạn
    $sql2 = "
        UPDATE print_jobs
        SET status='failed',
            error_message=?,
            finished_at=NOW()
        WHERE status='pending'
          AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > ?
    ";
    $st2 = $pdo->prepare($sql2);
    $st2->execute([$msgPending, $pendingMinutes]);
    $a2 = $st2->rowCount();

    jexit(true, ['printing_failed'=>$a1, 'pending_failed'=>$a2]);
}

/* ---- Hủy một job ---- */
case 'print_cancel_job': {
    $j = body_json();
    $id = (int)($j['id'] ?? ($_GET['id'] ?? 0));
    if ($id<=0) jexit(false, ['message'=>'Thiếu id'], 400);

    $st = $pdo->prepare("
        UPDATE print_jobs
        SET status='failed', error_message=CONCAT('Hủy thủ công @ ', NOW()), finished_at=NOW()
        WHERE id=? AND status IN ('pending','printing')
    ");
    $st->execute([$id]);
    jexit(true, ['affected'=>$st->rowCount()]);
}

/* ---- Tạo lệnh in PDF & đá worker ---- */
case 'enqueue_print': {
    // Input: { "pdf_web_path": "pdf/pxk/PXKddmmyyyyNN.pdf", "copies": 1, "printer_name": "" }
    $j = body_json();
    $pdfWebPath  = trim((string)($j['pdf_web_path'] ?? ''));
    $copies      = max(1, (int)($j['copies'] ?? 1));
    $printerName = trim((string)($j['printer_name'] ?? ''));

    if ($pdfWebPath === '') jexit(false, ['message'=>'Thiếu pdf_web_path'], 400);

    try {
        $st = $pdo->prepare("INSERT INTO print_jobs (doc_type, doc_id, file_web_path, copies, printer_name, status, created_at)
                             VALUES ('file', NULL, ?, ?, ?, 'pending', NOW())");
        $st->execute([$pdfWebPath, $copies, ($printerName!==''?$printerName:null)]);
        $jobId = (int)$pdo->lastInsertId();

        // Đá worker NGAY tại đây
        $spawned = kickJobDetached($jobId, $__PRINT_PHP_EXE, $__PRINT_SCRIPT, $__PRINT_WORKDIR, $__POWERSHELL_EXE);

        jexit(true, [
            'job_id'   => $jobId,
            'spawned'  => $spawned,           // để UI biết có đá được không
            'message'  => $spawned ? 'Đã tạo lệnh in và khởi chạy xử lý nền.' :
                                     'Đã tạo lệnh in, nhưng chưa khởi chạy được tiến trình xử lý. Vui lòng kiểm tra logs/print_spawn.log'
        ]);
    } catch (Throwable $e) {
        jexit(false, ['message'=>'Không tạo được lệnh in', 'detail'=>$e->getMessage()], 500);
    }
}

    /* ---- Xuất PDF (robust + debug) ---- */
case 'export_pdf': {
    // ==== Tăng tài nguyên, chuẩn bị debug ====
    $debug = (($_GET['debug'] ?? $_POST['debug'] ?? '') === '1');
    @ini_set('display_errors', $debug ? '1' : '0');
    @ini_set('log_errors', '1');
    @ini_set('memory_limit', '512M');   // tăng cho dompdf
    @set_time_limit(120);

    // 1) Autoload Dompdf
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) jexit(false, ['message'=>'Thiếu Dompdf (vendor/autoload.php). Cài: composer require dompdf/dompdf']);
    require_once $autoload;
    if (!class_exists(\Dompdf\Dompdf::class)) jexit(false, ['message'=>'Dompdf chưa sẵn sàng'], 500);

    // 2) Input
    $j = body_json();
    $id = (int)($j['id'] ?? 0);
    if ($id <= 0) jexit(false, ['message'=>'Thiếu id'], 400);

    try {
        // 3) Lấy dữ liệu phiếu
        $st = $pdo->prepare("SELECT * FROM pxk_slips WHERE id=? LIMIT 1");
        $st->execute([$id]);
        $pxk = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pxk) jexit(false, ['message'=>'Không tìm thấy PXK'], 404);

        $items = [];
        if (!empty($pxk['items_json'])) {
            $tmp = json_decode($pxk['items_json'], true);
            if (is_array($tmp)) $items = $tmp;
        }

        // 4) Company & helper
        if (!function_exists('get_company')) {
            function get_company(PDO $pdo): array {
                $company = ['name_vi'=>'','address_vi'=>'','phone'=>'','tax_id'=>'','website'=>'','email'=>'','logo_path'=>''];
                try {
                    $rs = $pdo->query("SELECT name_vi,address_vi,phone,tax_id,website,email,logo_path FROM company_info ORDER BY id ASC LIMIT 1");
                    if ($row = $rs->fetch(PDO::FETCH_ASSOC)) $company = array_merge($company, $row);
                } catch (Throwable $e) {}
                return $company;
            }
        }
        if (!function_exists('resolve_logo_src')) {
            function resolve_logo_src(string $path): string {
                $p = trim($path);
                if ($p === '') return '';
                if (preg_match('~^https?://~i', $p)) return $p;
                $candidates = [];
                $webroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
                if ($webroot) $candidates[] = ($p[0] === '/') ? ($webroot.$p) : ($webroot.'/'.$p);
                $proj = realpath(__DIR__.'/..');
                if ($proj)   $candidates[] = ($p[0] === '/') ? ($proj.$p) : ($proj.'/'.$p);
                foreach ($candidates as $abs) {
                    if (is_file($abs) && is_readable($abs)) {
                        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                        $mime = match($ext){ 'jpg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','svg'=>'image/svg+xml', default=>'application/octet-stream' };
                        $data = base64_encode(@file_get_contents($abs));
                        if ($data) return "data:$mime;base64,$data";
                    }
                }
                return $p;
            }
        }
        if (!function_exists('to_dmy_text')) {
            function to_dmy_text(?string $ymd): string {
                $ts = $ymd ? strtotime($ymd) : time();
                return 'Ngày '.date('d',$ts).' tháng '.date('m',$ts).' năm '.date('Y',$ts);
            }
        }
        if (!function_exists('h')) {
            function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
        }

        $c = get_company($pdo);

        // 5) Bổ sung contact/phone nếu thiếu
        $contact_person = trim((string)($pxk['contact_person'] ?? ($pxk['contact_name'] ?? '')));
        $partner_phone  = trim((string)($pxk['partner_phone']  ?? ($pxk['phone'] ?? '')));
        if ($contact_person === '' || $partner_phone === '') {
            try {
                $stP = $pdo->prepare("SELECT phone, contact_person FROM partners WHERE name = ? LIMIT 1");
                $stP->execute([trim((string)($pxk['partner_name'] ?? ''))]);
                if ($rowP = $stP->fetch(PDO::FETCH_ASSOC)) {
                    if ($contact_person === '' && !empty($rowP['contact_person'])) $contact_person = $rowP['contact_person'];
                    if ($partner_phone === '' && !empty($rowP['phone']))          $partner_phone  = $rowP['phone'];
                }
            } catch (Throwable $e) {}
        }

        // 6) Meta
        $logoSrc    = resolve_logo_src((string)$c['logo_path']);
        $pxk_number = $pxk['pxk_number'] ?? ('PXK-'.$id);
        $date_text  = to_dmy_text($pxk['pxk_date'] ?? date('Y-m-d'));

        // 7) Mật độ
        $rowCount = is_array($items) ? count($items) : 0;
        if     ($rowCount <= 7)  $density = 'normal';
        elseif ($rowCount <= 11) $density = 'compact';
        elseif ($rowCount <= 15) $density = 'tight';
        else                     $density = 'ultra';

        // 8) Nạp CSS từ style-pdf.css
        $cssFile = __DIR__ . '/../assets/css/style-pdf.css';
        $css = '';
        if (is_file($cssFile) && is_readable($cssFile)) {
            $css = file_get_contents($cssFile) ?: '';
        } else {
            // fallback tối giản
            $css = "@page{size:A4 portrait;margin:9mm}.pdf-export{font-family:'DejaVu Sans',Arial,Helvetica,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}table.items th,table.items td{border:1px solid #333;padding:4px}.title{text-align:center;font-weight:bold}";
        }

        // 9) Render HTML
        ob_start();
        ?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Phiếu xuất kho <?= h($pxk_number) ?></title>
<style><?= $css ?></style>
</head>
<body class="pdf-export density-<?= h($density) ?>">
<div class="a4-container">
  <div class="mid-cutline"></div>
  <?php $renderSlip = function() use ($c, $logoSrc, $pxk, $items, $pxk_number, $date_text, $contact_person, $partner_phone) { ?>
  <table style="width: 100%; border-collapse: collapse; vertical-align: top;">
    <tr>
      <td style="width: 80px; vertical-align: top;">
        <?php if ($logoSrc): ?>
          <div class="logo-wrap">
            <img src="<?= h($logoSrc) ?>" alt="Logo" style="max-width:70px; max-height:70px; object-fit:contain;">
          </div>
        <?php endif; ?>
      </td>
      <td style="vertical-align: top; padding-left: 10px;">
        <h1 class="company-name"><?= h($c['name_vi'] ?: '') ?></h1>
        <p class="company-info"><b>Địa chỉ:</b> <?= h($c['address_vi'] ?: '') ?></p>
        <p class="company-info">
          <?php $a=[]; if(!empty($c['phone'])) $a[]='<b>ĐT:</b> '.h($c['phone']); if(!empty($c['tax_id'])) $a[]='<b>MST:</b> '.h($c['tax_id']); echo implode(' | ',$a); ?>
        </p>
        <p class="company-info">
          <?php $b=[]; if(!empty($c['website'])) $b[]='<b>Web:</b> '.h($c['website']); if(!empty($c['email'])) $b[]='<b>Email:</b> '.h($c['email']); echo implode(' | ',$b); ?>
        </p>
      </td>
    </tr>
  </table>
  <div class="divider"></div>
  <h2 class="title">PHIẾU XUẤT KHO</h2>
  <div class="meta">
    <p class="line"><?= h($date_text) ?></p>
    <p class="line"><strong>Số:</strong> <?= h($pxk_number) ?></p>
  </div>

  <table class="info-rows" style="width: 100%;">
    <tr><td style="width: 140px;"><b>Đơn vị nhận hàng:</b></td><td><?= h($pxk['partner_name'] ?? '') ?></td></tr>
    <tr><td><b>Người liên hệ:</b></td><td><?= h($contact_person) ?></td></tr>
    <tr><td><b>Số điện thoại:</b></td><td><?= h($partner_phone) ?></td></tr>
    <tr><td><b>Địa chỉ:</b></td><td><?= h($pxk['partner_address'] ?? '') ?></td></tr>
    <tr><td><b>Ghi chú:</b></td><td><?= h($pxk['notes'] ?? '') ?></td></tr>
  </table>

  <table class="items">
    <thead>
      <tr>
        <th style="width:20px">STT</th>
        <th style="width:120px">Danh mục</th>
        <th>Tên sản phẩm</th>
        <th style="width:40px">Đơn vị</th>
        <th style="width:50px">Số lượng</th>
        <th style="width:170px">Ghi chú</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($items): $i=1; foreach ($items as $it): ?>
        <tr>
          <td class="center"><?= $i++ ?></td>
          <td><?= h($it['category'] ?? '') ?></td>
          <td><?= h($it['product_name'] ?? '') ?></td>
          <td class="center"><?= h($it['unit'] ?? '') ?></td>
          <td class="num"><?= h((string)($it['quantity'] ?? '')) ?></td>
          <td><?= h($it['note'] ?? '') ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="6" class="center">Không có dữ liệu hàng hoá.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <table class="signatures" style="width: 100%;">
    <tr>
      <td style="width: 33.33%;" class="title">Người lập phiếu</td>
      <td style="width: 33.33%;" class="title">Người nhận hàng</td>
      <td style="width: 33.33%;" class="title">Thủ kho</td>
    </tr>
    <tr>
      <td class="note">(Ký, họ tên)</td>
      <td class="note">(Ký, họ tên)</td>
      <td class="note">(Ký, họ tên)</td>
    </tr>
    <tr>
      <td class="space"></td>
      <td class="space"></td>
      <td class="space"></td>
    </tr>
  </table>
  <?php }; ?>
  <section class="slip-a5 slip-top"><?php $renderSlip(); ?></section>
  <section class="slip-a5 slip-bottom"><?php $renderSlip(); ?></section>
</div>
</body>
</html>
        <?php
        $html = ob_get_clean();

        // 10) Dompdf options
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('defaultMediaType', 'print');

        // tempDir & chroot (giúp tránh lỗi quyền/thư mục tạm)
        $tempDir = __DIR__ . '/../storage/tmp';
        if (!is_dir($tempDir)) { @mkdir($tempDir, 0775, true); }
        $options->set('tempDir', realpath($tempDir) ?: $tempDir);
        $options->set('chroot', realpath(__DIR__ . '/..')); // giới hạn truy cập trong project

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');

        try {
            $dompdf->render();
        } catch (Throwable $e) {
            if ($debug) jexit(false, ['message'=>'Render PDF lỗi', 'detail'=>$e->getMessage()], 500);
            jexit(false, ['message'=>'Render PDF lỗi'], 500);
        }

        // 11) Ghi file
        $outDir = __DIR__ . '/../pdf/pxk';
        if (!is_dir($outDir)) { @mkdir($outDir, 0775, true); }
        if (!is_writable($outDir)) {
            if ($debug) jexit(false, ['message'=>'Thư mục không ghi được', 'dir'=>$outDir], 500);
            jexit(false, ['message'=>'Không ghi được file PDF'], 500);
        }

        $fileName = preg_replace('/[^a-zA-Z0-9-_\.]/', '', ($pxk['pxk_number'] ?? ('PXK-'.$id))).'.pdf';
        $absPath  = $outDir . '/' . $fileName;

        try {
            file_put_contents($absPath, $dompdf->output());
        } catch (Throwable $e) {
            if ($debug) jexit(false, ['message'=>'Ghi file PDF lỗi', 'detail'=>$e->getMessage()], 500);
            jexit(false, ['message'=>'Ghi file PDF lỗi'], 500);
        }

        // 12) Cập nhật DB + trả về
        $webPath = 'pdf/pxk/'.$fileName;
        $st = $pdo->prepare("UPDATE pxk_slips SET pdf_path=? WHERE id=?");
        $st->execute([$webPath, $id]);

        jexit(true, ['pdf_web_path'=>$webPath]);

    } catch (Throwable $e) {
        if ($debug) jexit(false, ['message'=>'Server exception (export_pdf)', 'detail'=>$e->getMessage()], 500);
        jexit(false, ['message'=>'Server exception (export_pdf)'], 500);
    }
}


    default:
        jexit(false, ['message'=>'Unknown action'], 404);
}
