<?php
// File: cli/watch_print_queue.php (chạy bằng PHP CLI trên máy server)
// php -d detect_unicode=0 cli/watch_print_queue.php

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../includes/init.php'; // dùng $pdo

// === CẤU HÌNH ===
$PROJECT_DIR      = realpath(__DIR__ . '/..'); // thư mục gốc dự án
$SUMATRA_EXE      = 'C:\\Program Files\\SumatraPDF\\SumatraPDF.exe'; // ĐỔI nếu path khác
$DEFAULT_PRINTER  = ''; // Khuyến nghị: điền sẵn 'Brother HL-L2320D series' hoặc để trống để tự lấy từ Windows default
$SLEEP_SECONDS    = 3;  // nghỉ giữa các lần quét

// ==== UTILS ====
function log_stderr(string $msg): void {
    file_put_contents('php://stderr', "[watch_print] $msg\n");
}
function heartbeat(PDO $pdo, string $info=''): void {
    $host = gethostname() ?: '';
    $st = $pdo->prepare("UPDATE print_worker_status SET last_seen=NOW(), host=?, info=? WHERE id=1");
    $st->execute([$host, $info]);
}
function web_to_abs(string $webPath, string $projectDir): string {
    // Chấp nhận cả '/quanlykho/pdf/...', 'quanlykho/pdf/...', hoặc 'pdf/...'
    $p = str_replace(['\\','/'], '/', $webPath);
    $p = preg_replace('~^https?://[^/]+/~i', '/', $p); // nếu lỡ truyền absolute URL -> cắt domain
    $p = ltrim($p, '/');

    // nếu path bắt đầu bằng 'quanlykho/', bỏ prefix để nối đúng vào $PROJECT_DIR
    if (stripos($p, 'quanlykho/') === 0) {
        $p = substr($p, strlen('quanlykho/'));
    }
    // Kết quả: $PROJECT_DIR . '/pdf/...'
    return $projectDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);
}
function get_windows_default_printer(): string {
    // Lấy Default Printer từ Windows (wmic)
    // Trả '' nếu không tìm thấy hoặc lệnh không hỗ trợ
    @exec('wmic printer where "Default=true" get Name /value 2>&1', $out, $ret);
    if ($ret !== 0 || empty($out)) return '';
    $joined = implode("\n", $out);
    if (preg_match('/Name=(.+)/i', $joined, $m)) {
        return trim($m[1]);
    }
    return '';
}
function print_pdf_sumatra(string $exe, string $absFile, string $printerName='', int $copies=1): array {
    if (!is_file($absFile)) {
        return [false, "Không tìm thấy file: $absFile"];
    }
    $copies = max(1, $copies);

    $cmd = [];
    $cmd[] = '"'.$exe.'"';
    $cmd[] = '-silent';
    if ($printerName !== '') {
        $cmd[] = '-print-to';
        $cmd[] = '"'.$printerName.'"';
    } else {
        $cmd[] = '-print-to-default';
    }
    $cmd[] = '-print-settings';
    $cmd[] = '"copies='.$copies.'"';
    $cmd[] = '"'.$absFile.'"';

    $full = implode(' ', $cmd);
    exec($full . ' 2>&1', $out, $ret);

    $outText = implode("\n", $out);
    return [$ret === 0, $outText !== '' ? $outText : 'OK'];
}

echo "[watch_print] Start at ".date('Y-m-d H:i:s')."\n";

while (true) {
    try {
        // 1) Heartbeat mỗi vòng
        heartbeat($pdo, 'watch_print_queue.php running');

        // 2) Lấy 1 job pending (có chống MyISAM/transaction không chặn được)
        $jobId = 0;
        try {
            $pdo->beginTransaction();
            $st = $pdo->query("SELECT id FROM print_jobs WHERE status='pending' ORDER BY id ASC LIMIT 1 FOR UPDATE");
            $jobId = (int)($st->fetchColumn() ?: 0);
            if ($jobId > 0) {
                $st = $pdo->prepare("UPDATE print_jobs SET status='printing', started_at=NOW() WHERE id=? AND status='pending'");
                $st->execute([$jobId]);
            }
            $pdo->commit();
        } catch (Throwable $txe) {
            // Có thể bảng MyISAM không hỗ trợ transaction/for update -> best effort lấy job không khoá
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            log_stderr("Transaction warning: ".$txe->getMessage());
            $st = $pdo->query("SELECT id FROM print_jobs WHERE status='pending' ORDER BY id ASC LIMIT 1");
            $jobId = (int)($st->fetchColumn() ?: 0);
            if ($jobId > 0) {
                $st = $pdo->prepare("UPDATE print_jobs SET status='printing', started_at=NOW() WHERE id=? AND status='pending'");
                $st->execute([$jobId]);
            }
        }

        if ($jobId === 0) { sleep($SLEEP_SECONDS); continue; }

        $st = $pdo->prepare("SELECT * FROM print_jobs WHERE id=? LIMIT 1");
        $st->execute([$jobId]);
        $job = $st->fetch(PDO::FETCH_ASSOC);
        if (!$job) { sleep($SLEEP_SECONDS); continue; }

        $webPath = (string)$job['file_web_path'];
        $absFile = web_to_abs($webPath, $PROJECT_DIR);

        // 3) Kiểm tra Sumatra có sẵn không
        if (!file_exists($SUMATRA_EXE)) {
            $human = "Thiếu trình in PDF (SumatraPDF) tại: $SUMATRA_EXE. Cài từ sumatrapdfreader.org và chỉnh đường dẫn.";
            $upd = $pdo->prepare("UPDATE print_jobs SET status='failed', error_message=?, finished_at=NOW() WHERE id=?");
            $upd->execute([$human, $jobId]);
            log_stderr("Job #$jobId FAILED: $human");
            continue;
        }

        // 4) Resolve printer
        $printerFromJob = trim((string)($job['printer_name'] ?? ''));
        $printer = $printerFromJob !== '' ? $printerFromJob : $DEFAULT_PRINTER;

        if ($printer === '') {
            // Lấy từ Windows default
            $auto = get_windows_default_printer();
            if ($auto === '') {
                $human = "Không xác định được máy in: printer_name trống, DEFAULT_PRINTER trống, Windows không có default.";
                $upd = $pdo->prepare("UPDATE print_jobs SET status='failed', error_message=?, finished_at=NOW() WHERE id=?");
                $upd->execute([$human, $jobId]);
                log_stderr("Job #$jobId FAILED: $human");
                continue;
            }
            $printer = $auto;
        }

        // 5) Kiểm tra file
        if (!is_file($absFile)) {
            $human = "Không tìm thấy file để in: $absFile (từ web path: $webPath). Kiểm tra export PDF hoặc quyền file.";
            $upd = $pdo->prepare("UPDATE print_jobs SET status='failed', error_message=?, finished_at=NOW() WHERE id=?");
            $upd->execute([$human, $jobId]);
            log_stderr("Job #$jobId FAILED: $human");
            continue;
        }

        // 6) In
        $copies  = max(1, (int)$job['copies']);
        [$ok, $msg] = print_pdf_sumatra($SUMATRA_EXE, $absFile, $printer, $copies);

        // 7) Ghi kết quả
        $human = $ok
            ? "Đã in xong: $webPath → máy in {$printer} (bản sao: $copies)"
            : ("In thất bại: $webPath → máy in {$printer} (bản sao: $copies). Chi tiết: ".$msg);

        $upd = $pdo->prepare("UPDATE print_jobs SET status=?, error_message=?, finished_at=NOW() WHERE id=?");
        $upd->execute([$ok ? 'done' : 'failed', $ok ? null : mb_substr($human, 0, 2000), $jobId]);

        echo "[watch_print] Job #{$jobId} ".($ok?'DONE':'FAILED').": $human\n";

    } catch (Throwable $e) {
        // Lỗi vòng lặp -> không làm kẹt job, chỉ nghỉ rồi chạy tiếp
        error_log("[watch_print] ".$e->getMessage());
        sleep($SLEEP_SECONDS);
    }
}
 