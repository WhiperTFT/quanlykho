<?php
// dashboard.php — fixed & aligned (2025-11-04)
// Meaning update: sales_orders là CHI PHÍ mua vào → dùng thuật ngữ "Giá trị đặt hàng" thay vì "Doanh thu".

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
require_login();

$page_title = $lang['dashboard'] ?? 'Dashboard';

// ---------------------------
// DB helpers
// ---------------------------
function db_scalar(PDO $pdo, string $sql, array $params = [], $default = 0) {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? $default : ($v ?? $default);
  } catch (Throwable $e) {
    return $default;
  }
}
function db_rows(PDO $pdo, string $sql, array $params = [], $default = []) {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    return $rows ?: $default;
  } catch (Throwable $e) {
    return $default;
  }
}

$today = new DateTime('today');
$startMonth = (new DateTime('first day of this month'))->setTime(0,0,0);
$endMonth   = (new DateTime('last day of this month'))->setTime(23,59,59);

// ---------------------------
// KPIs (đồng bộ ngữ nghĩa)
// ---------------------------
$kpi = [
  // Đơn hàng chưa giao = chưa có ngày giao
  'orders_without_delivery_date' => db_scalar(
    $pdo,
    "SELECT COUNT(*) FROM sales_orders WHERE expected_delivery_date IS NULL"
  ),
  // Báo giá chờ (logic nhẹ)
  'pending_quotes'    => db_scalar($pdo, "SELECT COUNT(*) FROM sales_quotes WHERE status IN ('draft','sent')"),
  // Sắp hết hàng: chưa có tồn kho → 0
  'low_stock'         => 0,
  // Đơn hôm nay (đếm bản ghi)
  'orders_today'      => db_scalar($pdo, "SELECT COUNT(*) FROM sales_orders WHERE DATE(order_date)=:d", [':d'=>$today->format('Y-m-d')]),
  // Đơn trong tháng (đếm bản ghi)
  'orders_this_month' => db_scalar($pdo, "SELECT COUNT(*) FROM sales_orders WHERE order_date BETWEEN :s AND :e", [':s'=>$startMonth->format('Y-m-d H:i:s'), ':e'=>$endMonth->format('Y-m-d H:i:s')]),
  // Chi phí mua vào (từ sales_orders.grand_total)
  'spending_today'    => db_scalar($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM sales_orders WHERE DATE(order_date)=:d", [':d'=>$today->format('Y-m-d')], 0.0),
  'spending_month'    => db_scalar($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM sales_orders WHERE order_date BETWEEN :s AND :e", [':s'=>$startMonth->format('Y-m-d H:i:s'), ':e'=>$endMonth->format('Y-m-d H:i:s')], 0.0),
  // Đơn giao hôm nay (số lượng)
  'deliveries_today'  => db_scalar($pdo, "SELECT COUNT(*) FROM sales_orders WHERE DATE(expected_delivery_date)=:d", [':d'=>$today->format('Y-m-d')]),
];

// ---------------------------
// Charts
// ---------------------------
// A) Giá trị đặt hàng 12 tháng (sum grand_total theo order_date)
$chartMonthly = ['labels'=>[], 'values'=>[]];
try {
  $rows = db_rows($pdo, "
    SELECT DATE_FORMAT(order_date, '%Y-%m') AS ym, COALESCE(SUM(grand_total),0) AS amt
    FROM sales_orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
  ");
  $map = [];
  foreach ($rows as $r) { $map[$r['ym']] = (float)$r['amt']; }
  $period = new DatePeriod((new DateTime('first day of -11 months'))->setTime(0,0), new DateInterval('P1M'), 12);
  foreach ($period as $dt) {
    $ym = $dt->format('Y-m');
    $chartMonthly['labels'][] = $dt->format('m/Y');
    $chartMonthly['values'][] = isset($map[$ym]) ? (float)$map[$ym] : 0.0;
  }
} catch (Throwable $e) { /* fallback rỗng */ }

// B) Cơ cấu giao hàng (không phụ thuộc status draft)
$chartStatus = [ 'labels' => ['Chưa có ngày giao','Có lịch - chờ giao','Quá hạn giao','Đã huỷ'], 'values' => [0,0,0,0] ];
try {
  $todayStr = $today->format('Y-m-d');
  $rows = db_rows($pdo, "
    SELECT
      SUM(CASE WHEN expected_delivery_date IS NULL AND (status <> 'cancelled' OR status IS NULL) THEN 1 ELSE 0 END) AS chua_co_ngay,
      SUM(CASE WHEN expected_delivery_date IS NOT NULL AND (status <> 'cancelled' OR status IS NULL) AND expected_delivery_date >= :d THEN 1 ELSE 0 END) AS co_lich_cho_giao,
      SUM(CASE WHEN expected_delivery_date IS NOT NULL AND (status <> 'cancelled' OR status IS NULL) AND expected_delivery_date <  :d THEN 1 ELSE 0 END) AS qua_han,
      SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS da_huy
    FROM sales_orders
  ", [':d'=>$todayStr]);
  if (!empty($rows)) {
    $r = $rows[0];
    $chartStatus['values'] = [ (int)($r['chua_co_ngay']??0), (int)($r['co_lich_cho_giao']??0), (int)($r['qua_han']??0), (int)($r['da_huy']??0) ];
  }
} catch (Throwable $e) { /* bỏ qua */ }

// C) Top tài xế 30 ngày (dựa expected_delivery_date)
$topDrivers = [];
try {
  $topDrivers = db_rows($pdo, "
    SELECT d.ten AS driver_name, COUNT(*) AS total_deliveries
    FROM sales_orders so
    JOIN drivers d ON d.id = so.driver_id
    WHERE so.expected_delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY d.id, d.ten
    ORDER BY total_deliveries DESC
    LIMIT 5
  ");
} catch (Throwable $e) { $topDrivers = []; }

// Logs: user_logs + users
$logs = db_rows($pdo, "
  SELECT ul.*, u.username
  FROM user_logs ul
  LEFT JOIN users u ON u.id = ul.user_id
  ORDER BY ul.created_at DESC
  LIMIT 20
", [], []);

$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$role     = htmlspecialchars($_SESSION['role'] ?? 'Unknown');
?>

<style>
.kpi-card{ border:0; border-radius:1rem; box-shadow:0 6px 18px rgba(0,0,0,.06); }
.kpi-value{ font-size: clamp(1.4rem, 2.5vw, 2.1rem); font-weight:800; }
.kpi-icon{ width:44px;height:44px;display:grid;place-items:center;border-radius:.75rem;background:var(--bs-light); }
.table-compact td, .table-compact th{ padding:.5rem .75rem; }
.badge-soft{ background: rgba(13,110,253,.12); color:#0d6efd; }
.badge-soft-warning{ background: rgba(255,193,7,.18); color:#b58100; }
.badge-soft-danger{ background: rgba(220,53,69,.12); color:#b02a37; }
</style>

<div class="d-flex justify-content-between align-items-center pt-2 pb-2 mb-3 border-bottom">
  <h1 class="h4 m-0"><?php echo $lang['dashboard_title'] ?? 'Tổng quan hệ thống'; ?></h1>
  <div class="text-muted small">Xin chào, <strong><?php echo $username; ?></strong> · Vai trò: <strong><?php echo $role; ?></strong></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="card kpi-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small">Đơn hàng chưa giao</div>
          <div class="kpi-value"><?php echo (int)$kpi['orders_without_delivery_date']; ?></div>
        </div>
        <div class="kpi-icon"><i class="bi bi-inboxes"></i></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="card kpi-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small"><?php echo $lang['pending_quotes'] ?? 'Báo giá chờ duyệt'; ?></div>
          <div class="kpi-value"><?php echo (int)$kpi['pending_quotes']; ?></div>
        </div>
        <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="card kpi-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small"><?php echo $lang['stock_level'] ?? 'Sản phẩm sắp hết'; ?></div>
          <div class="kpi-value"><?php echo (int)$kpi['low_stock']; ?></div>
        </div>
        <div class="kpi-icon" title="Cần bảng tồn kho để tính chính xác"><i class="bi bi-archive"></i></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="card kpi-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small">Đơn giao hôm nay</div>
          <div class="kpi-value"><?php echo (int)$kpi['deliveries_today']; ?></div>
        </div>
        <div class="kpi-icon"><i class="bi bi-calendar2-check"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-lg-8">
    <div class="card h-100 shadow-sm">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div><i class="bi bi-graph-up-arrow me-2"></i>Giá trị đặt hàng 12 tháng</div>
        <span class="badge badge-soft"><?php echo $startMonth->format('m/Y'); ?> · <?php echo $endMonth->format('m/Y'); ?></span>
      </div>
      <div class="card-body">
        <canvas id="chartMonthlySpending" height="120"></canvas>
      </div>
      <div class="card-footer bg-white small text-muted">
        Hôm nay chi: <strong><?php echo number_format((float)$kpi['spending_today'], 0, ',', '.'); ?>₫</strong> · Tháng này chi: <strong><?php echo number_format((float)$kpi['spending_month'], 0, ',', '.'); ?>₫</strong>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card h-100 shadow-sm">
      <div class="card-header bg-white"><i class="bi bi-truck-flatbed me-2"></i>Cơ cấu giao hàng</div>
      <div class="card-body">
        <div id="orderStructureHolder"><canvas id="chartOrderStructure" height="220"></canvas></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-lg-5">
    <div class="card h-100 shadow-sm">
      <div class="card-header bg-white"><i class="bi bi-trophy me-2"></i>Top tài xế (30 ngày)</div>
      <div class="card-body">
        <?php if (empty($topDrivers)): ?>
          <div class="text-muted small">Chưa có dữ liệu.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-borderless table-compact align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Tài xế</th>
                  <th class="text-end">Số chuyến</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($topDrivers as $row): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['driver_name'] ?? 'N/A'); ?></td>
                    <td class="text-end"><span class="badge bg-primary-subtle text-primary fw-semibold"><?php echo (int)$row['total_deliveries']; ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card h-100 shadow-sm">
      <div class="card-header bg-white"><i class="bi bi-activity me-2"></i><?php echo $lang['recent_activity'] ?? 'Hoạt động gần đây'; ?></div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="tblRecent" class="table table-hover table-striped table-compact w-100">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Người dùng</th>
                <th>Hành động</th>
                <th>IP LAN</th>
                <th>IP WAN</th>
                <th>Loại mạng</th>
                <th>Mức</th>
                <th>Thời gian</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr><td colspan="8"><em>Không có hoạt động gần đây.</em></td></tr>
              <?php else: ?>
                <?php foreach ($logs as $i => $log): ?>
                  <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($log['action'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($log['ip_lan'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($log['ip_wan'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($log['network_type'] ?? ''); ?></td>
                    <td>
                      <?php $level = $log['level'] ?? 'info';
                        $badge = ($level==='error') ? 'badge-soft-danger' : (($level==='warn') ? 'badge-soft-warning' : 'badge-soft'); ?>
                      <span class="badge <?php echo $badge; ?> text-uppercase"><?php echo htmlspecialchars($level); ?></span>
                    </td>
                    <td><?php echo isset($log['created_at']) ? date('d/m/Y H:i:s', strtotime($log['created_at'])) : ''; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function(){
  const monthlyLabels = <?php echo json_encode($chartMonthly['labels'], JSON_UNESCAPED_UNICODE); ?>;
  const monthlyValues = <?php echo json_encode($chartMonthly['values'], JSON_UNESCAPED_UNICODE); ?>;
  const statusLabels  = <?php echo json_encode($chartStatus['labels'], JSON_UNESCAPED_UNICODE); ?>;
  const statusValues  = <?php echo json_encode($chartStatus['values'], JSON_UNESCAPED_UNICODE); ?>;

  // Line: Giá trị đặt hàng 12 tháng
  const ctx1 = document.getElementById('chartMonthlySpending');
  if (ctx1 && monthlyLabels && monthlyLabels.length) {
    new Chart(ctx1, {
      type: 'line',
      data: { labels: monthlyLabels, datasets: [{ label: 'Giá trị đặt hàng', data: monthlyValues, fill: true, tension: .35, borderWidth: 2, pointRadius: 2 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
  }

  // Doughnut: Cơ cấu giao hàng (fallback khi không có dữ liệu)
  const holder = document.getElementById('orderStructureHolder');
  const ctx2 = document.getElementById('chartOrderStructure');
  const total = (statusValues||[]).reduce((a,b)=>a + Number(b||0), 0);
  if (ctx2 && total > 0) {
    new Chart(ctx2, { type: 'doughnut', data: { labels: statusLabels, datasets: [{ data: statusValues }] }, options: { responsive: true, maintainAspectRatio: false } });
  } else if (holder) {
    holder.innerHTML = '<div class="text-muted small">Chưa có dữ liệu.</div>';
  }

  // DataTables (nếu đã load sẵn)
  if (window.jQuery && $.fn.DataTable) {
    $('#tblRecent').DataTable({ pageLength: 10, order: [[7,'desc']], language: { url: 'assets/datatables/i18n/vi.json' } });
  }
})();
</script>
