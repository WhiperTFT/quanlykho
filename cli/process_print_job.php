<?php
// File: cli/process_print_job.php
declare(strict_types=1);
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../includes/init.php'; // có $pdo

// ============ CONFIG ============
$PROJECT_DIR     = realpath(__DIR__ . '/..');
$SUMATRA_EXE     = 'C:\\Program Files\\SumatraPDF\\SumatraPDF.exe';
$LOG_FILE        = __DIR__ . '/../logs/print_worker.log';
$DEFAULT_PRINTER = get_app_default_printer($pdo); // '' = dùng Windows Default

// ============ LOG & SAFETY ============
$CURRENT_JOB_ID = 0;
function wrklog(string $s) {
    global $LOG_FILE;
    @file_put_contents($LOG_FILE, '['.date('Y-m-d H:i:s').'] '.$s.PHP_EOL, FILE_APPEND);
}
register_shutdown_function(function () {
    global $pdo, $CURRENT_JOB_ID;
    if ($CURRENT_JOB_ID > 0) {
        $err = error_get_last();
        if ($err) {
            $msg = 'Worker died: '.$err['message'].' @ '.$err['file'].':'.$err['line'];
            try {
                $st = $pdo->prepare("UPDATE print_jobs
                    SET status='failed', error_message=?, finished_at=NOW()
                  WHERE id=? AND status='printing'");
                $st->execute([substr($msg,0,2000), $CURRENT_JOB_ID]);
                wrklog("FATAL -> mark failed job #{$CURRENT_JOB_ID}: ".$msg);
            } catch (\Throwable $e) {
                wrklog("FATAL but cannot update DB: ".$e->getMessage());
            }
        }
    }
});
function get_app_default_printer(PDO $pdo): string {
    try {
        $st = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key`='printer_default' LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        return $v!==false ? trim((string)$v) : '';
    } catch (\Throwable $e) { return ''; }
}

// ============ HELPERS ============
function getArg(string $name): ?string {
    foreach ($GLOBALS['argv'] ?? [] as $a) {
        if (strpos($a, "--{$name}=") === 0) return substr($a, strlen($name) + 3);
    }
    return null;
}
function web_to_abs(string $webPath, string $projectDir): string {
    $p = str_replace(['\\','/'], '/', $webPath);
    $p = preg_replace('~^https?://[^/]+/~i', '/', $p);
    $p = ltrim($p, '/');
    if (stripos($p, 'quanlykho/') === 0) $p = substr($p, strlen('quanlykho/'));
    return $projectDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);
}
function get_windows_default_printer(): string {
    @exec('wmic printer where "Default=true" get Name /value 2>&1', $out, $ret);
    if ($ret !== 0 || empty($out)) return '';
    $joined = implode("\n", $out);
    if (preg_match('/Name=(.+)/i', $joined, $m)) return trim($m[1]);
    return '';
}
function clip($s, $max=2000){ return (strlen($s) > $max) ? substr($s,0,$max) : $s; }

function print_pdf_sumatra(string $exe, string $absFile, string $printerName='', int $copies=1): array {
    if (!is_file($absFile)) return [false, "Không tìm thấy file: $absFile"];
    $copies = max(1, $copies);

    $cmd = [];
    $cmd[] = '"'.$exe.'"';
    $cmd[] = '-silent';
    $cmd[] = '-exit-when-done'; // Quan trọng để không treo
    if ($printerName !== '') {
        $cmd[] = '-print-to';        $cmd[] = '"'.$printerName.'"';
    } else {
        $cmd[] = '-print-to-default';
    }
    $cmd[] = '-print-settings';     $cmd[] = '"copies='.$copies.'"';
    $cmd[] = '"'.$absFile.'"';

    $full = implode(' ', $cmd).' 2>&1';
    wrklog('CMD: '.$full);
    $out = [];
    $ret = 0;
    exec($full, $out, $ret);
    $outText = implode("\n", $out);
    wrklog("RET={$ret} OUT=".($outText!==''?$outText:'<empty>'));
    return [$ret === 0, $outText !== '' ? $outText : 'OK'];
}

// ============ MAIN ============
try {
    $jobIdArg = getArg('id');
    $jobId    = $jobIdArg !== null ? (int)$jobIdArg : 0;
    if ($jobId <= 0) { wrklog('No --id provided, exit'); exit(0); }

    // Claim job
    $job = null;
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT * FROM print_jobs WHERE id=:id LIMIT 1 FOR UPDATE");
        $st->execute([':id'=>$jobId]);
        $job = $st->fetch(PDO::FETCH_ASSOC);
        if (!$job) { $pdo->commit(); wrklog("Job #{$jobId} not found"); exit(0); }

        if ($job['status'] === 'pending') {
            $upd = $pdo->prepare("UPDATE print_jobs
                SET status='printing', started_at=NOW()
              WHERE id=:id AND status='pending'");
            $upd->execute([':id'=>$job['id']]);
        }
        $pdo->commit();
    } catch (\Throwable $tx) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Fallback không khóa
        $st = $pdo->prepare("SELECT * FROM print_jobs WHERE id=? LIMIT 1");
        $st->execute([$jobId]);
        $job = $st->fetch(PDO::FETCH_ASSOC);
        if ($job && $job['status']==='pending') {
            $upd = $pdo->prepare("UPDATE print_jobs
               SET status='printing', started_at=NOW()
             WHERE id=? AND status='pending'");
            $upd->execute([$job['id']]);
        }
    }

    if (!$job) { wrklog("Job #{$jobId} disappeared"); exit(0); }
    if ($job['status'] !== 'printing' && $job['status'] !== 'pending') { wrklog("Job #{$jobId} already {$job['status']}"); exit(0); }

    $CURRENT_JOB_ID = (int)$job['id'];
    wrklog("CLAIMED job #{$CURRENT_JOB_ID}");

    // Check Sumatra
    if (!file_exists($SUMATRA_EXE)) {
        $human = "Thiếu SumatraPDF: $SUMATRA_EXE";
        $st = $pdo->prepare("UPDATE print_jobs SET status='failed', error_message=?, finished_at=NOW() WHERE id=?");
        $st->execute([$human, $job['id']]);
        wrklog("FAIL #{$job['id']}: ".$human);
        exit(1);
    }

    // Resolve file
    $webPath = (string)$job['file_web_path'];
    $absFile = web_to_abs($webPath, $PROJECT_DIR);
    if (!is_file($absFile)) {
        $human = "Không tìm thấy file để in: $absFile (từ web path: $webPath)";
        $st = $pdo->prepare("UPDATE print_jobs SET status='failed', error_message=?, finished_at=NOW() WHERE id=?");
        $st->execute([$human, $job['id']]);
        wrklog("FAIL #{$job['id']}: ".$human);
        exit(1);
    }

    // Printer
    $printerFromJob = trim((string)($job['printer_name'] ?? ''));
    $printer = $printerFromJob !== '' ? $printerFromJob : $DEFAULT_PRINTER;
    if ($printer === '') {
        $auto = get_windows_default_printer();
        wrklog("Windows default printer: ".($auto ?: '<none>'));
        if ($auto === '') {
            $human = "Không xác định được máy in (job không chỉ định, DEFAULT_PRINTER trống, Windows không có default).";
            $st = $pdo->prepare("UPDATE print_jobs SET status='failed', error_message=?, finished_at=NOW() WHERE id=?");
            $st->execute([$human, $job['id']]);
            wrklog("FAIL #{$job['id']}: ".$human);
            exit(1);
        }
        $printer = $auto;
    }
    wrklog("PRINT file={$absFile} printer={$printer} copies=".max(1,(int)$job['copies']));

    // Print
    $copies = max(1, (int)$job['copies']);
    [$ok, $msg] = print_pdf_sumatra($SUMATRA_EXE, $absFile, $printer, $copies);

    $human = $ok
        ? "Đã in: $webPath → {$printer} (bản: $copies)"
        : ("In lỗi: $webPath → {$printer} (bản: $copies). Chi tiết: ".$msg);

    $st = $pdo->prepare("UPDATE print_jobs SET status=?, error_message=?, finished_at=NOW() WHERE id=?");
    $st->execute([$ok ? 'done' : 'failed', $ok ? null : clip($human, 2000), $job['id']]);

    wrklog(($ok?'DONE':'FAILED')." #{$job['id']}: ".$human);
    exit($ok ? 0 : 1);

} catch (\Throwable $e) {
    wrklog("EXC: ".$e->getMessage());
    // shutdown handler sẽ mark failed nếu cần
    exit(1);
}
