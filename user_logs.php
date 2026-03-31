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
        <button id="runCleanup" class="btn btn-warning btn-sm"><i class="bi bi-magic me-1"></i> Dọn Log</button>
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
    <div class="content-card-header">
        <span><i class="bi bi-journal-text me-2 text-primary"></i>Chi tiết nhật ký</span>
    </div>
    <div class="content-card-body-flush">
        <div class="table-responsive">
            <table id="auditTable" class="table table-hover table-custom mb-0 w-100" style="font-size:0.9rem;">
                <thead class="table-light">
                    <tr>
                        <th width="15%">Thời gian</th>
                        <th width="15%">Người thực hiện</th>
                        <th width="10%">Phân hệ</th>
                        <th width="10%">Hành động</th>
                        <th width="40%">Chi tiết nội dung cập nhật</th>
                        <th width="10%" class="text-center">Dữ liệu (JSON)</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
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
            { data: 'description', orderable: false, render: function(d,t,r) {
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

    $('#runCleanup').click(function() {
        if(confirm("Tự động dọn rác sẽ xoá vĩnh viễn toàn bộ dấu vết hệ thống CŨ HƠN 30 NGÀY! \nXin lưu ý hành động này áp dụng vĩnh viễn!\n\nTiến hành không?")) {
            $.get('cleanup_logs.php', function(r) {
                let data = JSON.parse(r);
                if(data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert("System Exception: " + data.error);
                }
            });
        }
    });
});

window.viewJsonTrace = function(jsonString) {
    try {
        let obj = JSON.parse(jsonString);
        $('#jsonOutput').text(JSON.stringify(obj, null, 4));
        new bootstrap.Modal(document.getElementById('jsonModal')).show();
    } catch(e) { alert("Corrupted Audit Trail Bytes."); }
};
</script>
