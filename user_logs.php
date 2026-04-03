<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
require_login();

// Build stats cards dynamically
$today = date('Y-m-d');
$stats = [
    'total_today' => 0,
    'creations' => 0,
    'updates' => 0,
    'deletions' => 0,
    'top_user' => 'N/A',
    'top_module' => 'N/A'
];
try {
    $stats['total_today'] = $pdo->query("SELECT COUNT(*) FROM user_logs WHERE DATE(created_at) = '$today'")->fetchColumn();
    $stats['creations'] = $pdo->query("SELECT COUNT(*) FROM user_logs WHERE action = 'CREATE'")->fetchColumn();
    $stats['updates'] = $pdo->query("SELECT COUNT(*) FROM user_logs WHERE action = 'UPDATE'")->fetchColumn();
    $stats['deletions'] = $pdo->query("SELECT COUNT(*) FROM user_logs WHERE action = 'DELETE'")->fetchColumn();

    $tu = $pdo->query("SELECT u.username, COUNT(*) as c FROM user_logs l JOIN users u ON l.user_id = u.id GROUP BY l.user_id ORDER BY c DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if($tu) $stats['top_user'] = $tu['username'];

    $tm = $pdo->query("SELECT module, COUNT(*) as c FROM user_logs GROUP BY module ORDER BY c DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if($tm) $stats['top_module'] = $tm['module'];
} catch (Exception $e) {}

// Lấy cấu hình dọn dẹp tự động
$settings_file = __DIR__ . '/storage/logs/settings.json';
$auto_cleanup_days = 30; // Mặc định
if (file_exists($settings_file)) {
    $config_json = @file_get_contents($settings_file);
    if ($config_json) {
        $config_data = json_decode($config_json, true);
        $auto_cleanup_days = isset($config_data['auto_cleanup_days']) ? (int)$config_data['auto_cleanup_days'] : 30;
    }
}

// Extract unique users & modules for selectors
$usersList = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
$modulesList = $pdo->query("SELECT DISTINCT module FROM user_logs WHERE module IS NOT NULL AND module != ''")->fetchAll(PDO::FETCH_COLUMN);

// Handle Manual Arbitrary Bulk Deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logs'])) {
    if (is_admin()) {
        $sd = $_POST['start_date'] ?? '';
        $ed = $_POST['end_date'] ?? '';
        if ($sd && $ed && strtotime($sd) <= strtotime($ed)) {
            $end_date_next = date('Y-m-d', strtotime($ed . ' +1 day'));
            $pdo->prepare("DELETE FROM user_logs WHERE created_at >= ? AND created_at < ?")->execute([$sd . ' 00:00:00', $end_date_next . ' 00:00:00']);
            $success = "Xóa rác thành công.";
            write_user_log('DELETE', 'system', "Đã quét dọn file Log thủ công.", [], 'danger');
        }
    } else {
        $error = "Bạn không có quyền quản trị để thao tác dữ liệu Log.";
    }
}
?>

<div class="container-fluid mt-4 mb-5 user-logs">
    <div class="page-header">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Nhật ký Hệ thống</h1>
        <p class="text-muted mb-0 small">Lịch sử hoạt động của người dùng trong hệ thống</p>
    </div>
    <div class="page-header-actions">
        <button onclick="window.location.reload();" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i> Làm mới</button>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupModal"><i class="bi bi-gear-wide-connected me-1"></i> Tùy chọn dọn dẹp</button>
    </div>
</div>

    <!-- SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 text-center bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1 opacity-75">Tương tác hôm nay</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($stats['total_today']) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-light">
                <div class="card-body d-flex border-bottom border-success">
                    <div class="me-auto text-success fw-bold"><i class="bi bi-plus-circle"></i> THÊM MỚI</div>
                    <h5 class="fw-bold mb-0"><?= number_format($stats['creations']) ?></h5>
                </div>
                <div class="card-body d-flex py-2 border-bottom border-info">
                    <div class="me-auto text-info fw-bold"><i class="bi bi-pencil-square"></i> CẬP NHẬT</div>
                    <h5 class="fw-bold mb-0"><?= number_format($stats['updates']) ?></h5>
                </div>
                <div class="card-body d-flex pt-2 text-danger">
                    <div class="me-auto fw-bold"><i class="bi bi-trash"></i> XÓA BỎ</div>
                    <h5 class="fw-bold mb-0"><?= number_format($stats['deletions']) ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 text-center">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted mb-2">Nhân viên năng nổ nhất</h6>
                    <span class="badge bg-secondary p-2 px-3 fw-bold fs-6"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($stats['top_user']) ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 text-center">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted mb-2">Phân hệ dùng nhiều nhất</h6>
                    <span class="badge bg-dark p-2 px-3 fw-bold fs-6"><i class="bi bi-cpu-fill"></i> <?= htmlspecialchars(strtoupper($stats['top_module'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($success)): ?>
      <div class="alert alert-success alert-dismissible shadow-sm fade show" role="alert"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <div class="alert alert-danger alert-dismissible shadow-sm fade show" role="alert"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="filter-card">
    <form id="filterForm" class="row g-2 align-items-center">
        <div class="col-md-2">
            <input type="date" id="f_date_start" class="form-control form-control-sm" placeholder="Từ khoảng">
        </div>
        <div class="col-md-2">
            <input type="date" id="f_date_end" class="form-control form-control-sm" placeholder="Tới khoảng">
        </div>
        <div class="col-md-2">
            <select id="f_user" class="form-select form-select-sm">
                <option value="">-- Mọi người --</option>
                <?php foreach($usersList as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select id="f_module" class="form-select form-select-sm">
                <option value="">-- Mọi Phân Hệ --</option>
                <?php foreach($modulesList as $m): ?>
                <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars(strtoupper($m)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select id="f_action" class="form-select form-select-sm">
                <option value="">-- Mọi Hành Động --</option>
                <option value="CREATE">Phân loại: THÊM MỚI</option>
                <option value="UPDATE">Phân loại: CẬP NHẬT</option>
                <option value="DELETE">Phân loại: XÓA Bỏ</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm w-100 btn-primary"><i class="bi bi-filter"></i> Lọc dữ liệu</button>
        </div>
    </form>
</div>

    <!-- MAIN TABLE -->
    <div class="content-card shadow-sm">
    <div class="content-card-header d-flex align-items-center">
        <span><i class="bi bi-journal-text me-2 text-primary"></i>Chi tiết nhật ký</span>
    </div>
    <div class="content-card-body-flush table-responsive-custom">
        <div class="table-responsive">
            <table id="auditTable" class="table table-hover table-custom mb-0 w-100" style="font-size:0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th width="14%">Thời gian</th>
                        <th width="14%">Người thực hiện</th>
                        <th width="12%">Thiết bị (ID)</th>
                        <th width="10%">Phân hệ</th>
                        <th width="10%">Hành động</th>
                        <th width="32%">Nội dung</th>
                        <th width="8%" class="text-center">JSON</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Modal Dọn dẹp Log -->
<div class="modal fade" id="cleanupModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-bottom-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-trash3 text-danger me-2"></i>Cấu hình dọn dẹp hệ thống</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i> Dữ liệu log giúp tra soát khi có sự cố. Chúng tôi khuyên bạn nên giữ ít nhất 30 ngày.
        </div>

        <section class="mb-4">
            <h6 class="fw-bold mb-3 small text-uppercase text-primary"><i class="bi bi-gear-fill me-1"></i> Cấu hình tự động dọn dẹp</h6>
            <div class="row g-2 align-items-center">
                <div class="col-8">
                    <select id="autoCleanupDays" class="form-select form-select-sm">
                        <option value="0" <?= $auto_cleanup_days === 0 ? 'selected' : '' ?>>Không tự động dọn dẹp</option>
                        <option value="7" <?= $auto_cleanup_days === 7 ? 'selected' : '' ?>>Tự động xóa log > 7 ngày</option>
                        <option value="30" <?= $auto_cleanup_days === 30 ? 'selected' : '' ?>>Tự động xóa log > 30 ngày (Khuyên dùng)</option>
                        <option value="90" <?= $auto_cleanup_days === 90 ? 'selected' : '' ?>>Tự động xóa log > 90 ngày</option>
                        <option value="180" <?= $auto_cleanup_days === 180 ? 'selected' : '' ?>>Tự động xóa log > 6 tháng</option>
                    </select>
                </div>
                <div class="col-4">
                    <button type="button" class="btn btn-sm btn-primary w-100" onclick="saveAutoCleanupSettings()">Lưu cấu hình</button>
                </div>
            </div>
        </section>

        <section>
            <h6 class="fw-bold mb-3 small text-uppercase text-secondary"><i class="bi bi-lightning-charge-fill me-1"></i> Thao tác dọn dẹp tức thì</h6>
            <div class="list-group list-group-flush rounded-3 border">
            <button type="button" class="list-group-item list-group-item-action d-flex align-items-center py-3" onclick="triggerCleanup(7)">
                <div class="bg-light rounded-circle p-2 me-3"><i class="bi bi-calendar-event text-primary"></i></div>
                <div>
                    <h6 class="mb-0 fw-bold">Xóa log cũ hơn 7 ngày</h6>
                    <small class="text-muted">Giữ lại dữ liệu tuần gần nhất</small>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </button>
            <button type="button" class="list-group-item list-group-item-action d-flex align-items-center py-3" onclick="triggerCleanup(30)">
                <div class="bg-light rounded-circle p-2 me-3"><i class="bi bi-calendar-month text-success"></i></div>
                <div>
                    <h6 class="mb-0 fw-bold">Xóa log cũ hơn 30 ngày</h6>
                    <small class="text-muted">Giữ lại dữ liệu tháng gần nhất (Khuyên dùng)</small>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </button>
            <button type="button" class="list-group-item list-group-item-action d-flex align-items-center py-3" onclick="triggerCleanup(90)">
                <div class="bg-light rounded-circle p-2 me-3"><i class="bi bi-calendar3 text-warning"></i></div>
                <div>
                    <h6 class="mb-0 fw-bold">Xóa log cũ hơn 90 ngày</h6>
                    <small class="text-muted">Giữ lại dữ liệu quý gần nhất</small>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </button>
        </div>

        <hr class="my-4">
        
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="triggerCleanup('all')">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Xóa TOÀN BỘ nhật ký
            </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal xem Data JSON -->
<div class="modal fade" id="jsonModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-braces text-primary me-2"></i>Dữ liệu Hệ thống đính kèm (JSON)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="bg-dark text-success p-3 rounded-3 overflow-auto" id="jsonOutput" style="max-height:500px; font-family: monospace; font-size:14px; white-space: pre-wrap;"></div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- DATATABLES REQUIREMENTS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    let table = $('#auditTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: 'process/fetch_logs.php',
            type: 'POST',
            data: function(d) {
                d.f_user = $('#f_user').val();
                d.f_module = $('#f_module').val();
                d.f_action = $('#f_action').val();
                d.f_date_start = $('#f_date_start').val();
                d.f_date_end = $('#f_date_end').val();
            }
        },
        columns: [
            { data: 'created_at_fmt', orderable: true },
            { data: 'username', orderable: false, render: function(d,t,r) {
                return `<strong><i class="bi bi-person-circle text-muted"></i> ${d}</strong><br><small class="text-secondary">${r.ip_address}</small>`;
            }},
            { data: 'device_id', orderable: true, render: function(d) {
                if (!d || d === 'unknown') return `<span class="text-muted small">unknown</span>`;
                return `<code class="text-primary small" style="font-weight:600;">${d}</code>`;
            }},
            { data: 'module', orderable: true, render: function(d) {
                return `<span class="badge text-bg-light border text-uppercase">${d}</span>`;
            }},
            { data: 'action', orderable: true, render: function(d) {
                let badge = 'bg-secondary';
                if(d.includes('CREATE')) badge = 'bg-success';
                else if(d.includes('UPDATE')) badge = 'bg-info text-dark';
                else if(d.includes('DELETE')) badge = 'bg-danger';
                return `<span class="badge ${badge} p-2 px-3 fw-bold w-100">${d}</span>`;
            }},
            { data: 'description', orderable: false, className: 'log-description-cell', render: function(d,t,r) {
                let ic = r.log_type === 'danger' ? '<i class="bi bi-exclamation-octagon-fill text-danger me-1"></i>' : '';
                return ic + d;
            }},
            { data: 'data', orderable: false, className: 'text-center', render: function(d) {
                if(!d || (typeof d === 'object' && Object.keys(d).length === 0)) return '<span class="text-muted fst-italic">No Data</span>';
                let stringified = JSON.stringify(d).replace(/"/g, '&quot;');
                return `<button class="btn btn-sm btn-outline-dark" onclick="viewJsonTrace('${stringified}')"><i class="bi bi-eye"></i> View</button>`;
            }}
        ],
        order: [[0, 'desc']],
        pageLength: 50,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json" // Vietnamese translation
        }
    });

    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    window.triggerCleanup = function(days) {
        let msg = days === 'all' 
            ? "Bạn có chắc chắn muốn XÓA TOÀN BỘ nhật ký hệ thống? Hành động này không thể hoàn tác!" 
            : `Xác nhận dọn dẹp các bản ghi cũ hơn ${days} ngày?`;
            
        if(confirm(msg)) {
            $.get('cleanup_logs.php', { days: days }, function(r) {
                try {
                    let data = typeof r === 'object' ? r : JSON.parse(r);
                    if(data.success) {
                        alert("Thành công: " + data.message);
                        window.location.reload();
                    } else {
                        alert("Lỗi: " + data.error);
                    }
                } catch(e) { 
                    console.error(r);
                    alert("Phản hồi hệ thống không hợp lệ."); 
                }
            });
        }
    };

    window.saveAutoCleanupSettings = function() {
        const days = $('#autoCleanupDays').val();
        $.post('process/save_log_settings.php', { auto_days: days }, function(r) {
            if(r.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Đã lưu cấu hình',
                    text: r.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Lỗi', r.error || 'Không thể lưu cấu hình', 'error');
            }
        }, 'json');
    };
});

window.viewJsonTrace = function(jsonString) {
    try {
        let obj = JSON.parse(jsonString);
        $('#jsonOutput').text(JSON.stringify(obj, null, 4));
        new bootstrap.Modal(document.getElementById('jsonModal')).show();
    } catch(e) { alert("Corrupted Audit Trail Bytes."); }
};
</script>
