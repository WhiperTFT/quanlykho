<?php
// File: process/print_center_api.php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

// ==== CẤU HÌNH CÔNG CỤ IN (Windows) ====
$CONFIG = [
    // Đường dẫn thực tế trên máy chủ (SỬA LẠI CHO PHÙ HỢP)
    'SUMATRA' => 'C:\\Program Files\\SumatraPDF\\SumatraPDF.exe',
    'SOFFICE' => 'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
    'ALLOWED_EXT' => ['pdf','png','jpg','jpeg','bmp','gif','tif','tiff','webp','doc','docx','xls','xlsx','csv'],
    'ENABLE_OFFICE_CONVERT' => true,
    // Cookie lưu máy in ưa thích
    'PREF_COOKIE' => 'preferred_printer',
    'PREF_COOKIE_DAYS' => 180
];

// ==== Thư mục tạm theo session ====
$sid = session_id(); // giả định init.php đã session_start();
if (!$sid) {
    session_start();
    $sid = session_id();
}
$TMP_ROOT = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$SESSION_DIR = $TMP_ROOT . '/print/tmp/' . $sid;
ensure_dir($SESSION_DIR);

// ==== Lấy action (JSON -> POST -> GET) ====
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = null;
$JSON_BODY = null;

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $maybeJson = json_decode($raw, true);
    if (is_array($maybeJson) && isset($maybeJson['action'])) {
        $action = $maybeJson['action'];
        $JSON_BODY = $maybeJson;
    }
    if ($action === null && !empty($_POST['action'])) {
        $action = $_POST['action'];
    }
}
if ($action === null) {
    $action = $_GET['action'] ?? null;
}

// ==== Router ====
try {
    switch ($action) {
        case 'upload':                echo json_encode(handle_upload($CONFIG, $SESSION_DIR, $sid)); break;
        case 'enqueue_print':         echo json_encode(handle_print($CONFIG, $SESSION_DIR, $JSON_BODY)); break;
        case 'delete':                echo json_encode(handle_delete($SESSION_DIR)); break;
        case 'list_printers':         echo json_encode(list_printers($CONFIG)); break;
        case 'list_queue':            echo json_encode(list_queue($_GET['printer'] ?? '')); break;
        case 'set_preferred_printer': echo json_encode(api_set_preferred_printer($CONFIG, $JSON_BODY)); break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error', 'detail' => $e->getMessage()]);
}

// ==== FUNCTIONS ====

// ---------- Upload ----------
function handle_upload($CFG, $SESSION_DIR, $sid) {
    if (empty($_FILES['files'])) {
        return ['success' => false, 'message' => 'Không nhận được file tải lên.'];
    }
    $out = [];
    $files = reformat_files_array($_FILES['files']);

    foreach ($files as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $out[] = ['error' => 'Upload lỗi: ' . $f['error']];
            continue;
        }
        $name = $f['name'];
        $size = (int)$f['size'];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $CFG['ALLOWED_EXT'], true)) {
            $out[] = ['error' => "File không hỗ trợ: .$ext", 'name' => $name];
            continue;
        }
        $id  = bin2hex(random_bytes(6));
        $safe = preg_replace('/[^\w\-.]+/u', '_', $name);
        $dest = $SESSION_DIR . DIRECTORY_SEPARATOR . $id . '__' . $safe;

        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            $out[] = ['error' => 'Không thể lưu file.', 'name' => $name];
            continue;
        }
        $previewable = 'none';
        $type = mime_content_type($dest) ?: '';
        if ($ext === 'pdf') $previewable = 'pdf';
        if (in_array($ext, ['png','jpg','jpeg','bmp','gif','tif','tiff','webp'], true)) $previewable = 'image';

        $out[] = [
            'id' => $id,
            'name' => $name,
            'size' => $size,
            'type' => $type,
            'ext'  => $ext,
            'serverPath' => relative_url_from_uploads($dest),
            'previewable' => $previewable
        ];
    }
    return ['success' => true, 'data' => $out];
}

// ---------- Print ----------
function handle_print($CFG, $SESSION_DIR, $JSON_BODY) {
    $body = $JSON_BODY ?? (json_decode(file_get_contents('php://input'), true) ?: []);
    $printer = trim((string)($body['printer'] ?? '')); // có thể rỗng → tự chọn
    $copies  = max(1, (int)($body['copies'] ?? 1));
    $pageRanges = trim((string)($body['pageRanges'] ?? ''));
    $duplex  = $body['duplex'] ?? 'off';          // off|long|short
    $orient  = $body['orientation'] ?? 'auto';    // auto|portrait|landscape
    $ids     = $body['fileIds'] ?? [];

    if (empty($ids)) {
        return ['success' => false, 'message' => 'Thiếu danh sách file.'];
    }

    // --- Lấy danh sách máy in hiện tại
    $lp = core_list_printers();
    if ($lp['success'] !== true) {
        return ['success' => false, 'message' => 'Không thể đọc danh sách máy in (PowerShell).'];
    }
    $printers = $lp['data'];
    $names = array_map(fn($p) => $p['name'], $printers);

    // --- Quyết định máy in nếu thiếu
    if ($printer === '') {
        $preferred = get_cookie_printer($CFG);
        if ($preferred && in_array($preferred, $names, true)) {
            $printer = $preferred;
        } else {
            $def = get_default_printer_name($printers);
            if ($def) {
                $printer = $def;
            } else {
                return ['success' => false, 'message' => 'Không xác định được máy in mặc định.'];
            }
        }
    } else {
        // Nếu FE truyền tên máy in không tồn tại → báo lỗi
        if (!in_array($printer, $names, true)) {
            return ['success' => false, 'message' => 'Máy in không tồn tại: ' . $printer];
        }
    }

    // --- Gom file mục tiêu
    $allFiles = scan_dir_files($SESSION_DIR);
    $targets = [];
    foreach ($ids as $id) {
        $match = null;
        foreach ($allFiles as $p) {
            if (str_starts_with(basename($p), $id . '__')) { $match = $p; break; }
        }
        if ($match) $targets[] = ['id' => $id, 'path' => $match];
    }
    if (!$targets) return ['success' => false, 'message' => 'Không tìm thấy file để in.'];

    $successes = [];
    $fails = [];

    foreach ($targets as $t) {
        $path = $t['path'];
        $id   = $t['id'];
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $toPrint = $path;
        $converted = null;

        // Convert Office -> PDF vào cùng thư mục session
        if (in_array($ext, ['doc','docx','xls','xlsx','csv'], true)) {
            if (!$CFG['ENABLE_OFFICE_CONVERT']) {
                $fails[] = ['id' => $id, 'file' => basename($path), 'error' => 'Chưa bật chuyển đổi Office -> PDF'];
                continue;
            }
            $converted = convert_to_pdf($CFG['SOFFICE'], $path, $SESSION_DIR);
            if (!$converted) {
                $fails[] = ['id' => $id, 'file' => basename($path), 'error' => 'Chuyển đổi sang PDF thất bại.'];
                continue;
            }
            $toPrint = $converted;
        }

        // In bằng SumatraPDF
        $ok = sumatra_print($CFG['SUMATRA'], $toPrint, $printer, $copies, $pageRanges, $duplex, $orient);

        if ($ok) {
            @unlink($path);
            if ($converted && is_file($converted)) @unlink($converted);
            $successes[] = ['id' => $id, 'file' => basename($path)];
        } else {
            $fails[] = ['id' => $id, 'file' => basename($path), 'error' => 'Lệnh in trả về lỗi (kiểm tra Sumatra/driver/quyền).'];
        }
    }

    // Nếu có ít nhất 1 job in thành công → lưu lại máy in đã dùng làm preferred
    if (count($successes) > 0) {
        set_cookie_printer($CFG, $printer);
    }

    cleanup_empty_dir($SESSION_DIR);

    return ['success' => true, 'data' => ['printerUsed' => $printer, 'successes' => $successes, 'fails' => $fails]];
}

// ---------- Xoá file tạm ----------
function handle_delete($SESSION_DIR) {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
    $ids  = $body['fileIds'] ?? [];
    if (!$ids) return ['success' => true, 'data' => ['deleted' => 0]];

    $allFiles = scan_dir_files($SESSION_DIR);
    $deleted = 0;
    foreach ($ids as $id) {
        foreach ($allFiles as $p) {
            if (str_starts_with(basename($p), $id . '__')) {
                if (@unlink($p)) $deleted++;
                // xóa cả PDF convert cùng base
                $base = pathinfo($p, PATHINFO_FILENAME);
                $maybePdf = $SESSION_DIR . DIRECTORY_SEPARATOR . $base . '.pdf';
                if (is_file($maybePdf)) @unlink($maybePdf);
            }
        }
    }
    cleanup_empty_dir($SESSION_DIR);
    return ['success' => true, 'data' => ['deleted' => $deleted]];
}

// ---------- Danh sách máy in (kèm preferred & autoSelected) ----------
function list_printers($CFG) {
    $lp = core_list_printers();
    if ($lp['success'] !== true) return $lp;

    $printers = $lp['data'];
    $preferred = get_cookie_printer($CFG);
    $defaultName = get_default_printer_name($printers);

    // Nếu preferred không còn tồn tại → bỏ qua
    $names = array_map(fn($p) => $p['name'], $printers);
    if ($preferred && !in_array($preferred, $names, true)) {
        $preferred = '';
    }

    // Quy tắc auto-selected: preferred > default
    $autoSelected = $preferred ?: $defaultName;

    return [
        'success' => true,
        'data' => $printers,
        'preferredPrinter' => $preferred,
        'defaultPrinter' => $defaultName,
        'autoSelected' => $autoSelected
    ];
}

// ---------- Đọc hàng chờ ----------
function list_queue($printer) {
    $printer = trim((string)$printer);
    if ($printer === '') return ['success' => false, 'message' => 'Chưa chọn máy in.'];
    $ps = <<<PS
Get-PrintJob -PrinterName "#{PRN}" | Select-Object Id,Document,UserName,PagesPrinted,TotalPages,TimeSubmitted,JobStatus |
ForEach-Object {
  [PSCustomObject]@{
    JobId = $_.Id
    Document = $_.Document
    Owner = $_.UserName
    PagesPrinted = $_.PagesPrinted
    TotalPages = $_.TotalPages
    SubmittedTime = $_.TimeSubmitted
    JobStatus = $_.JobStatus
  }
} | ConvertTo-Json
PS;
    $ps = str_replace('#{PRN}', addslashes($printer), $ps);
    $cmd = 'powershell -NoProfile -Command ' . escapeshellarg($ps);
    exec($cmd, $out, $code);
    if ($code !== 0) {
        return ['success' => false, 'message' => 'Không thể đọc hàng chờ.'];
    }
    $json = json_decode(implode("\n", $out), true);
    if (!$json) $json = [];
    if (isset($json['JobId'])) $json = [$json];
    return ['success' => true, 'data' => $json];
}

// ---------- Đặt preferred printer qua API ----------
function api_set_preferred_printer($CFG, $JSON_BODY) {
    $body = $JSON_BODY ?? (json_decode(file_get_contents('php://input'), true) ?: []);
    $name = trim((string)($body['printer'] ?? ''));
    if ($name === '') {
        // Cho phép xoá
        set_cookie_printer($CFG, '');
        return ['success' => true, 'message' => 'Đã xoá máy in ưa thích.'];
    }

    // Xác thực tên có tồn tại
    $lp = core_list_printers();
    if ($lp['success'] !== true) return ['success' => false, 'message' => 'Không thể đọc danh sách máy in.'];
    $printers = $lp['data'];
    $names = array_map(fn($p) => $p['name'], $printers);
    if (!in_array($name, $names, true)) {
        return ['success' => false, 'message' => 'Máy in không tồn tại: ' . $name];
    }

    set_cookie_printer($CFG, $name);
    return ['success' => true, 'message' => 'Đã lưu máy in ưa thích.', 'preferredPrinter' => $name];
}

// ==== Utilities ====

// --- Core gọi PowerShell để lấy DS máy in
function core_list_printers() {
    // Windows PowerShell: Get-Printer
    // Dùng ConvertTo-Json để luôn về JSON chuẩn
    $cmd = 'powershell -NoProfile -Command "Get-Printer | Select-Object Name,Default | ConvertTo-Json"';
    exec($cmd, $out, $code);
    if ($code !== 0) {
        return ['success' => false, 'message' => 'Không thể đọc danh sách máy in. Kiểm tra PowerShell quyền thực thi.'];
    }
    $json = json_decode(implode("\n", $out), true);
    $arr = [];
    if (is_array($json)) {
        // Khi chỉ có 1 phần tử, PowerShell trả object thay vì array
        $list = isset($json['Name']) ? [$json] : $json;
        foreach ($list as $p) {
            $arr[] = ['name' => $p['Name'] ?? '', 'isDefault' => (bool)($p['Default'] ?? false)];
        }
    }
    return ['success' => true, 'data' => $arr];
}

function get_default_printer_name(array $printers) {
    foreach ($printers as $p) {
        if (!empty($p['isDefault'])) return $p['name'];
    }
    return $printers[0]['name'] ?? '';
}

function ensure_dir($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
}
function cleanup_empty_dir($dir) {
    if (!is_dir($dir)) return;
    $scan = array_diff(scandir($dir), ['.','..']);
    if (empty($scan)) @rmdir($dir);
}
function reformat_files_array($file_post) {
    $files = [];
    if (!is_array($file_post['name'])) return $files;
    $count = count($file_post['name']);
    for ($i=0; $i<$count; $i++) {
        $files[] = [
            'name' => $file_post['name'][$i],
            'type' => $file_post['type'][$i],
            'tmp_name' => $file_post['tmp_name'][$i],
            'error' => $file_post['error'][$i],
            'size' => $file_post['size'][$i]
        ];
    }
    return $files;
}
function scan_dir_files($dir) {
    if (!is_dir($dir)) return [];
    $res = [];
    $items = scandir($dir);
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_file($p)) $res[] = $p;
    }
    return $res;
}
function convert_to_pdf($soffice, $src, $outDir) {
    if (!is_file($soffice)) return null;
    ensure_dir($outDir);
    $cmd = '"' . $soffice . '" --headless --convert-to pdf --outdir ' . escapeshellarg($outDir) . ' ' . escapeshellarg($src);
    exec($cmd, $out, $code);
    $base = pathinfo($src, PATHINFO_FILENAME);
    $pdf  = $outDir . DIRECTORY_SEPARATOR . $base . '.pdf';
    return (is_file($pdf) ? $pdf : null);
}
function sumatra_print($sumatra, $file, $printer, $copies, $pageRanges, $duplex, $orient) {
    if (!is_file($sumatra)) return false;
    $settings = [];
    if ($pageRanges !== '') $settings[] = $pageRanges;
    $settings[] = 'copies=' . max(1, (int)$copies);
    if ($duplex === 'long')  $settings[] = 'duplex';
    if ($duplex === 'short') $settings[] = 'duplexshort';
    if ($orient === 'portrait')  $settings[] = 'portrait';
    if ($orient === 'landscape') $settings[] = 'landscape';
    $settingsStr = implode(',', $settings);

    $cmd = '"' . $sumatra . '" -silent -print-to ' . escapeshellarg($printer) .
           ($settingsStr ? ' -print-settings ' . escapeshellarg($settingsStr) : '') .
           ' ' . escapeshellarg($file);

    exec($cmd, $out, $code);
    return $code === 0;
}
function relative_url_from_uploads($absPath) {
    $uploadsRoot = realpath(__DIR__ . '/../uploads');
    $abs = realpath($absPath);
    if (!$uploadsRoot || !$abs) return '';

    // phần tương đối bên trong /uploads (bắt đầu bằng /print/...)
    $rel = substr($abs, strlen($uploadsRoot));
    $rel = str_replace('\\', '/', $rel);
    $rel = ltrim($rel, '/');

    // Dùng đường dẫn tương đối từ trang gốc dự án (trangin.php)
    // => uploads/print/tmp/<sid>/...
    return 'uploads/' . $rel;
}

// ---------- Preferred printer via cookie ----------
function get_cookie_printer($CFG) {
    $key = $CFG['PREF_COOKIE'];
    return isset($_COOKIE[$key]) ? trim((string)$_COOKIE[$key]) : '';
}
function set_cookie_printer($CFG, $name) {
    $key = $CFG['PREF_COOKIE'];
    $days = (int)$CFG['PREF_COOKIE_DAYS'];
    $expire = time() + ($days * 86400);
    if ($name === '') {
        // Xoá cookie
        setcookie($key, '', time() - 3600, "/");
        $_COOKIE[$key] = '';
    } else {
        // Lưu cookie site-wide
        setcookie($key, $name, $expire, "/");
        $_COOKIE[$key] = $name;
    }
}
