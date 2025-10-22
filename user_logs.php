<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/init.php';
require_login();

// Rút gọn User Agent
function parseUserAgent($userAgent) {
    $browser = 'Unknown'; $os = 'Unknown';
    if (stripos($userAgent, 'Windows NT 10.0') !== false) $os = 'Windows 10';
    elseif (stripos($userAgent, 'Windows NT 6.1') !== false) $os = 'Windows 7';
    elseif (stripos($userAgent, 'Mac OS X') !== false) $os = 'macOS';
    elseif (stripos($userAgent, 'Linux') !== false) $os = 'Linux';

    if (preg_match('/Edg\/|Edge\//i', $userAgent)) $browser = 'Edge';
    elseif (preg_match('/Chrome/i', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/Safari/i', $userAgent) && stripos($userAgent, 'Chrome') === false) $browser = 'Safari';

    return "$browser / $os";
}

// Xóa log thủ công
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logs'])) {
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    if ($start_date && $end_date && strtotime($start_date) <= strtotime($end_date)) {
        $end_date_next = date('Y-m-d', strtotime($end_date . ' +1 day'));
        $stmt = $pdo->prepare("DELETE FROM user_logs WHERE created_at >= ? AND created_at < ?");
        $stmt->execute([$start_date . ' 00:00:00', $end_date_next . ' 00:00:00']);
        $success = "Đã xóa log thành công.";
    } else {
        $error = "Vui lòng chọn ngày bắt đầu và ngày kết thúc hợp lệ.";
    }
}

// Thiết lập auto delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_auto_delete'])) {
    $days = (int)($_POST['auto_delete_days'] ?? 0);
    if ($days > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES ('auto_delete_days', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$days]);
            $success = "Đã thiết lập tự động xóa sau $days ngày.";
        } catch (PDOException $e) {
            $error = ($e->getCode() == '42S02')
                ? "Bảng 'settings' không tồn tại. Vui lòng tạo bảng trước."
                : "Lỗi: " . $e->getMessage();
        }
    } else {
        $error = "Vui lòng nhập số ngày hợp lệ.";
    }
}

// Lấy setting hiện tại
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_delete_days'");
    $stmt->execute();
    $auto_delete_days = $stmt->fetchColumn() ?: 'Chưa thiết lập';
} catch (PDOException $e) {
    $auto_delete_days = ($e->getCode() == '42S02') ? 'Chưa thiết lập' : 'Lỗi';
}

// Lấy logs
$stmt = $pdo->prepare("
    SELECT l.*, u.username
    FROM user_logs l
    JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lịch sử hoạt động người dùng</title>
<!-- CSS đã đưa vào assets/css/style.css theo phần 2 -->
</head>
<body>
<div class="container mt-5">
  <div class="card shadow-sm rounded-4 p-4 mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h2 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Lịch sử hoạt động người dùng</h2>
      <div class="d-flex align-items-center gap-2">
        <button id="densityToggle" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-aspect-ratio"></i> Mật độ
        </button>
        <button id="resetFilters" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-x-circle"></i> Xóa lọc
        </button>
      </div>
    </div>

    <?php if (isset($success)): ?>
      <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Thiết lập auto delete -->
    <div class="row g-3 mt-3">
      <div class="col-lg-6">
        <div class="card border-0 bg-light p-3 rounded-3">
          <h5 class="mb-3"><i class="bi bi-clock me-2"></i>Tự động xóa log</h5>
          <form method="POST" class="d-flex align-items-end gap-3 flex-wrap">
            <div>
              <label for="auto_delete_days" class="form-label mb-1">Sẽ tự động xóa sau (ngày):</label>
              <input type="number" min="1" name="auto_delete_days" id="auto_delete_days" class="form-control"
                     value="<?= htmlspecialchars(is_numeric($auto_delete_days) ? $auto_delete_days : '') ?>"
                     placeholder="Số ngày">
            </div>
            <button type="submit" name="set_auto_delete" class="btn btn-primary">Lưu</button>
          </form>
          <small class="text-muted">Hiện tại: <?= htmlspecialchars($auto_delete_days) ?><?= is_numeric($auto_delete_days) ? ' ngày' : '' ?></small>
        </div>
      </div>

      <!-- Xóa thủ công -->
      <div class="col-lg-6">
        <div class="card border-0 bg-light p-3 rounded-3">
          <h5 class="mb-3"><i class="bi bi-trash me-2"></i>Xóa log thủ công</h5>
          <form method="POST" class="d-flex align-items-end gap-3 flex-wrap">
            <div>
              <label for="start_date" class="form-label mb-1">Từ ngày:</label>
              <input type="date" name="start_date" id="start_date" class="form-control" required>
            </div>
            <div>
              <label for="end_date" class="form-label mb-1">Đến ngày:</label>
              <input type="date" name="end_date" id="end_date" class="form-control" required>
            </div>
            <button type="submit" name="delete_logs" class="btn btn-danger"
                    onclick="return confirm('!!! Bạn có chắc chắn muốn xóa log trong khoảng thời gian này ??? LOG BỊ XÓA SẼ KHÔNG THỂ KHÔI PHỤC LẠI !!!');">
              Xóa
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Thanh công cụ bảng -->
    <div class="logs-toolbar mt-4">
  <div class="row g-3 align-items-center">
    <div class="col-md-6">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input id="globalSearch" type="text" class="form-control" placeholder="Tìm kiếm nhanh (mọi cột)">
      </div>
    </div>
    <div class="col-md-3">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-list-ol"></i></span>
        <select id="rowsPerPage" class="form-select">
          <option value="50">50 dòng / trang</option>
          <option value="100">100 dòng / trang</option>
          <option value="200">200 dòng / trang</option>
          <option value="500">500 dòng / trang</option>
        </select>
      </div>
    </div>



    <!-- Bảng logs -->
    <div class="log-table-wrapper mt-3">
      <table id="logsTable" class="table table-hover table-bordered table-sm log-table">
        <thead>
          <tr>
            <th class="col-time">Thời gian</th>
            <th class="col-user">Người dùng</th>
            <th class="col-action">Hành động</th>
            <th class="col-desc">Mô tả</th>
            <th class="col-ip">IP</th>
            <th class="col-ua">User Agent</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <?php
              $uaShort = parseUserAgent($log['user_agent'] ?? '');
              $descFull = (string)($log['description'] ?? '');
            ?>
            <tr>
              <td class="col-time text-nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
              <td class="col-user"><?= htmlspecialchars($log['username']) ?></td>
              <td class="col-action"><code><?= htmlspecialchars($log['action']) ?></code></td>
              <td class="col-desc">
                <div class="truncate-2" data-bs-toggle="tooltip" title="<?= htmlspecialchars($descFull) ?>">
                  <?= htmlspecialchars($descFull) ?>
                </div>
              </td>
              <td class="col-ip text-nowrap"><?= htmlspecialchars($log['ip_address']) ?></td>
              <td class="col-ua">
                <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($uaShort) ?>">
                  <?= htmlspecialchars($uaShort) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Phần chân DataTables: info + phân trang -->
    <div class="dt-bottom row align-items-center mt-2">
      <div class="col-md-6">
        <div id="dtInfo" class="text-muted small"></div>
      </div>
      <div class="col-md-6">
        <div id="dtPager" class="d-flex justify-content-end"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal xem đầy đủ mô tả -->
<div class="modal fade" id="descModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content rounded-4">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-card-text me-2"></i>Mô tả chi tiết</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body">
        <pre id="descModalBody" class="mb-0"></pre>
      </div>
    </div>
  </div>
</div>


</body>
</html>
<body class="user-logs"></body>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/user_logs.js?v=1.0.0" defer></script>