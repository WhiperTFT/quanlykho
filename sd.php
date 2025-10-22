<?php
// sd.php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
require_login();

if (!is_logged_in()) {
    header("Location: " . PROJECT_BASE_URL . "login.php?message=login_required");
    exit();
}

$shutdown_msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ===== HỦY LỆNH =====
    if (isset($_POST['cancel_shutdown'])) {
        $cmd = "shutdown -a";
        exec($cmd, $output, $status);

        if ($status === 0) {
            $shutdown_msg = "✅ Đã hủy thành công các lệnh tắt máy/khởi động đã hẹn.";
        } else {
            $shutdown_msg = "ℹ️ Không có lệnh tắt máy/khởi động nào đang chờ để hủy, hoặc đã có lỗi xảy ra.";
        }
    } 
    // ===== THỰC HIỆN LỆNH =====
    else {
        $type = $_POST['shutdown_type'] ?? 'now';
        $action = $_POST['action'] ?? 'shutdown'; // shutdown, restart, sleep
        $delay = 0;

        // Nếu có hẹn giờ (delay hoặc at_time)
        if ($type === 'delay') {
            $minutes = intval($_POST['minutes'] ?? 0);
            $seconds = intval($_POST['seconds'] ?? 0);
            $delay = $minutes * 60 + $seconds;
        } elseif ($type === 'at_time') {
            $hour = intval($_POST['hour'] ?? -1);
            $minute = intval($_POST['minute'] ?? -1);

            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                $now = time();
                $target_time_today = strtotime("today $hour:$minute");
                $target_time = ($target_time_today < $now) ? strtotime("tomorrow $hour:$minute") : $target_time_today;
                $delay = $target_time - $now;
            } else {
                $shutdown_msg = "❌ Thời gian nhập vào không hợp lệ.";
            }
        }

        if (is_null($shutdown_msg)) {
            // Sleep không hỗ trợ hẹn giờ qua shutdown -t, xử lý riêng
            if ($action === 'sleep') {
                if ($delay > 0) {
                    // Nếu có delay thì dùng timeout + rundll32
                    $cmd = "timeout /t $delay /nobreak && rundll32.exe powrprof.dll,SetSuspendState 0,1,0";
                } else {
                    // Ngủ ngay
                    $cmd = "rundll32.exe powrprof.dll,SetSuspendState 0,1,0";
                }
            } else {
                // Các lệnh shutdown / restart như cũ
                $cmd = ($action === 'restart')
                    ? "shutdown -r -t $delay"
                    : "shutdown -s -t $delay";
            }

            exec($cmd, $output, $status);

            if ($status === 0) {
                if ($action === 'sleep') {
                    $shutdown_msg = ($delay > 0)
                        ? "💤 Đã hẹn đưa máy vào chế độ ngủ sau $delay giây."
                        : "💤 Máy sẽ vào chế độ ngủ ngay lập tức.";
                } else {
                    if ($delay > 0) {
                        $shutdown_msg = "✅ Lệnh " . ($action === 'restart' ? "khởi động lại" : "tắt máy") . " đã được hẹn thành công.";
                    } else {
                        $shutdown_msg = "✅ Lệnh " . ($action === 'restart' ? "khởi động lại" : "tắt máy") . " ngay lập tức đã được gửi.";
                    }
                }
            } else {
                $shutdown_msg = "❌ Không thể gửi lệnh ($action). Mã lỗi: $status";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tắt máy / Khởi động lại / Sleep từ xa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    
    <?php if ($shutdown_msg): ?>
        <div class="alert alert-info"><?= $shutdown_msg ?></div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm">
        <h2 class="mb-4">Hẹn giờ Tắt máy / Khởi động lại / Sleep (Admin)</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Hành động:</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="action" id="action_shutdown" value="shutdown" checked>
                    <label class="form-check-label" for="action_shutdown">Tắt máy</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="action" id="action_restart" value="restart">
                    <label class="form-check-label" for="action_restart">Khởi động lại</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="action" id="action_sleep" value="sleep">
                    <label class="form-check-label" for="action_sleep">Ngủ (Sleep)</label>
                </div>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="radio" name="shutdown_type" id="shutdown_now" value="now" checked>
                <label class="form-check-label" for="shutdown_now">Thực hiện ngay</label>
            </div>
    
            <div class="form-check mt-2">
                <input class="form-check-input" type="radio" name="shutdown_type" id="shutdown_delay" value="delay">
                <label class="form-check-label" for="shutdown_delay">Thực hiện sau (đếm ngược)</label>
            </div>
            
            <div class="form-check mt-2">
                <input class="form-check-input" type="radio" name="shutdown_type" id="shutdown_at_time" value="at_time">
                <label class="form-check-label" for="shutdown_at_time">Thực hiện vào lúc (giờ cụ thể)</label>
            </div>
    
            <div id="delay_input" class="row mt-3 g-3" style="display: none;">
                <div class="col-md-6">
                    <label for="minutes" class="form-label">Phút:</label>
                    <input type="number" name="minutes" id="minutes" class="form-control" min="0" value="0">
                </div>
                <div class="col-md-6">
                    <label for="seconds" class="form-label">Giây:</label>
                    <input type="number" name="seconds" id="seconds" class="form-control" min="0" value="0">
                </div>
            </div>
    
            <div id="at_time_input" class="row mt-3 g-3" style="display: none;">
                <div class="col-md-6">
                    <label for="hour" class="form-label">Giờ:</label>
                    <input type="number" name="hour" id="hour" class="form-control" min="0" max="23" placeholder="0-23">
                </div>
                <div class="col-md-6">
                    <label for="minute" class="form-label">Phút:</label>
                    <input type="number" name="minute" id="minute" class="form-control" min="0" max="59" placeholder="0-59">
                </div>
            </div>
    
            <button type="submit" class="btn btn-danger mt-4 w-100">Thực hiện</button>
        </form>
    </div>

    <div class="card p-4 shadow-sm mt-4">
        <h2 class="mb-3">Hủy lệnh</h2>
        <form method="POST">
            <button type="submit" name="cancel_shutdown" class="btn btn-info w-100">Hủy tất cả lệnh tắt máy/khởi động</button>
        </form>
    </div>

</div>

<script>
    const radios = document.querySelectorAll('input[name="shutdown_type"]');
    const delayInput = document.getElementById('delay_input');
    const atTimeInput = document.getElementById('at_time_input');

    function toggleInputs() {
        const selectedValue = document.querySelector('input[name="shutdown_type"]:checked').value;
        delayInput.style.display = (selectedValue === 'delay') ? 'flex' : 'none';
        atTimeInput.style.display = (selectedValue === 'at_time') ? 'flex' : 'none';
    }

    radios.forEach(radio => radio.addEventListener('change', toggleInputs));
    toggleInputs();
</script>
</body>
</html>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
