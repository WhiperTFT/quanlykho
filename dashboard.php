<?php
// dashboard.php - Modern Redesign
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
require_login();

$page_title = $lang['dashboard'] ?? 'Business Dashboard';

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
// KPIs / Summary Cards
// ---------------------------
$kpi = [
  'orders_this_month' => db_scalar($pdo, "SELECT COUNT(*) FROM sales_orders WHERE order_date BETWEEN :s AND :e", [':s'=>$startMonth->format('Y-m-d H:i:s'), ':e'=>$endMonth->format('Y-m-d H:i:s')]),
  'spending_month'    => db_scalar($pdo, "SELECT COALESCE(SUM(grand_total),0) FROM sales_orders WHERE order_date BETWEEN :s AND :e", [':s'=>$startMonth->format('Y-m-d H:i:s'), ':e'=>$endMonth->format('Y-m-d H:i:s')], 0.0),
  'pending_quotes'    => db_scalar($pdo, "SELECT COUNT(*) FROM sales_quotes WHERE status IN ('draft','sent')"),
  'deliveries_today'  => db_scalar($pdo, "SELECT COUNT(*) FROM sales_orders WHERE DATE(expected_delivery_date)=:d", [':d'=>$today->format('Y-m-d')]),
];

// ---------------------------
// Charts Data
// ---------------------------
// A) Value 12 Months
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
} catch (Throwable $e) {}

// B) Order Delivery Status Structure
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
} catch (Throwable $e) {}

// ---------------------------
// Recent Activities
// ---------------------------
$recentOrders = db_rows($pdo, "SELECT * FROM sales_orders ORDER BY order_date DESC LIMIT 5");
// For quotes, fallback to created_at if quote_date doesn't exist. Usually there's an id.
$recentQuotes = db_rows($pdo, "SELECT * FROM sales_quotes ORDER BY id DESC LIMIT 5"); 

$recentTrips = db_rows($pdo, "
    SELECT so.id, d.ten as driver_name, so.expected_delivery_date, so.status
    FROM sales_orders so
    JOIN drivers d ON d.id = so.driver_id
    ORDER BY so.expected_delivery_date DESC
    LIMIT 5
");

$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$role     = htmlspecialchars($_SESSION['role'] ?? 'Unknown');

?>

<style>
:root {
  --primary-color: #4361ee;
  --success-color: #2ec4b6;
  --warning-color: #ff9f1c;
  --info-color: #4cc9f0;
  --danger-color: #e71d36;
  --bg-light: #f8f9fa;
}

body {
  background-color: var(--bg-light);
}

/* Kpi Cards */
.kpi-card {
  border-radius: 16px;
  border: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  overflow: hidden;
  background: #fff;
}
.kpi-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(0,0,0,0.06) !important;
}
.kpi-icon-box {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
}
.icon-primary { background: rgba(67, 97, 238, 0.1); color: var(--primary-color); }
.icon-success { background: rgba(46, 196, 182, 0.1); color: var(--success-color); }
.icon-warning { background: rgba(255, 159, 28, 0.1); color: var(--warning-color); }
.icon-info { background: rgba(76, 201, 240, 0.1); color: var(--info-color); }

.kpi-title {
  font-weight: 600;
  color: #6c757d;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.kpi-value {
  font-weight: 800;
  font-size: 1.8rem;
  color: #212529;
  margin-bottom: 0;
}

/* Sections */
.section-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #343a40;
  border-bottom: 2px solid #e9ecef;
  padding-bottom: 12px;
  margin-bottom: 20px;
}

/* Content Cards */
.content-card {
  border-radius: 16px;
  border: 1px solid rgba(0,0,0,0.05);
  background: #fff;
}
.content-card-header {
  background: transparent;
  border-bottom: 1px solid rgba(0,0,0,0.05);
  padding: 1rem 1.25rem;
  font-weight: 600;
  font-size: 1.05rem;
}

/* Tables */
.table-custom th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: #495057;
  border-bottom: 2px solid #e9ecef !important;
  text-transform: uppercase;
  font-size: 0.8rem;
  letter-spacing: 0.5px;
}
.table-custom td {
  vertical-align: middle;
  font-size: 0.95rem;
  color: #495057;
}

/* Action Buttons */
.btn-quick-action {
  border-radius: 10px;
  padding: 12px;
  font-weight: 600;
  text-align: left;
  transition: all 0.2s;
}
.btn-quick-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.btn-quick-action i {
  font-size: 1.2rem;
  vertical-align: middle;
  margin-right: 8px;
}

.alert-modern {
  border-radius: 12px;
  border: none;
  border-left: 5px solid;
}
.alert-modern.alert-warning { border-left-color: var(--warning-color); }
.alert-modern.alert-danger { border-left-color: var(--danger-color); }
</style>

<div class="d-flex justify-content-between align-items-center pt-2 pb-3 mb-4 border-bottom">
  <div>
    <h1 class="h3 mb-1 fw-bold"><?= $lang['dashboard_title'] ?? 'Business Dashboard' ?></h1>
    <p class="text-muted mb-0 small">Welcome back, <strong><?= $username ?></strong> (<?= $role ?>) • <?= $today->format('l, F j, Y') ?></p>
  </div>
</div>

<!-- Alerts / Notifications -->
<div class="row mb-3">
  <div class="col-12">
    <?php if ($chartStatus['values'][2] > 0): ?>
      <div class="alert alert-danger alert-modern shadow-sm py-2 px-3 d-flex align-items-center mb-2">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-3 text-danger"></i>
        <div>
          <strong>Cảnh báo:</strong> Có <?= $chartStatus['values'][2] ?> đơn hàng đang <strong>quá hạn giao</strong>.
        </div>
      </div>
    <?php endif; ?>
    <?php if ($kpi['pending_quotes'] > 0): ?>
      <div class="alert alert-warning alert-modern shadow-sm py-2 px-3 d-flex align-items-center mb-3">
        <i class="bi bi-hourglass-split fs-5 me-3 text-warning"></i>
        <div>
          <strong>Thông báo:</strong> Có <?= $kpi['pending_quotes'] ?> báo giá đang <strong>chờ duyệt/xử lý</strong>.
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Summary Cards (KPIs) -->
<div class="row g-4 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card kpi-card shadow-sm h-100 p-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="kpi-title">Đơn hàng (Tháng này)</div>
        <div class="kpi-icon-box icon-primary"><i class="bi bi-basket3"></i></div>
      </div>
      <h3 class="kpi-value text-primary"><?= number_format($kpi['orders_this_month']) ?></h3>
      <div class="mt-2 text-muted small"><i class="bi bi-calendar2-range me-1"></i>Từ đầu tháng</div>
    </div>
  </div>
  
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card kpi-card shadow-sm h-100 p-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="kpi-title">Chi Phí (Tháng này)</div>
        <div class="kpi-icon-box icon-success"><i class="bi bi-currency-dollar"></i></div>
      </div>
      <h3 class="kpi-value text-success"><?= number_format((float)$kpi['spending_month'], 0, ',', '.') ?>₫</h3>
      <div class="mt-2 text-muted small"><i class="bi bi-graph-up-arrow me-1"></i>Từ đầu tháng</div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card kpi-card shadow-sm h-100 p-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="kpi-title">Báo giá chờ duyệt</div>
        <div class="kpi-icon-box icon-warning"><i class="bi bi-file-earmark-text"></i></div>
      </div>
      <h3 class="kpi-value text-warning"><?= number_format($kpi['pending_quotes']) ?></h3>
      <div class="mt-2 text-muted small"><i class="bi bi-clock-history me-1"></i>Draft / Sent</div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card kpi-card shadow-sm h-100 p-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="kpi-title">Giao hàng (Hôm nay)</div>
        <div class="kpi-icon-box icon-info"><i class="bi bi-truck"></i></div>
      </div>
      <h3 class="kpi-value text-info"><?= number_format($kpi['deliveries_today']) ?></h3>
      <div class="mt-2 text-muted small"><i class="bi bi-calendar-check me-1"></i>Cần giao trong ngày</div>
    </div>
  </div>
</div>

<!-- Main Dashboard Body -->
<div class="row g-4 mb-4">
  <!-- Charts Column -->
  <div class="col-12 col-lg-8">
    <div class="card content-card shadow-sm h-100">
      <div class="card-header content-card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Chi phí 12 tháng gần nhất</span>
        <button class="btn btn-sm btn-light border"><i class="bi bi-download"></i></button>
      </div>
      <div class="card-body p-4">
        <div style="height: 300px;">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Order Status Column -->
  <div class="col-12 col-lg-4">
    <div class="card content-card shadow-sm h-100">
      <div class="card-header content-card-header">
        <i class="bi bi-pie-chart-fill me-2 text-success"></i>Trạng thái giao hàng
      </div>
      <div class="card-body p-4 d-flex justify-content-center align-items-center">
        <div style="height: 250px; width: 100%;">
          <canvas id="statusChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bottom Section -->
<div class="row g-4 mb-5">
  <!-- Recent Activities Tables -->
  <div class="col-12 col-xl-9">
    <div class="card content-card shadow-sm mb-4">
      <div class="card-header content-card-header bg-white d-flex align-items-center justify-content-between">
        <span><i class="bi bi-receipt me-2 text-info"></i>Đơn Hàng Gần Đây</span>
        <a href="sales_orders.php" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-custom mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Mã Đơn</th>
                <th>Ngày Đặt</th>
                <th>Trạng Thái</th>
                <th class="text-end pe-4">Tổng Cộng</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentOrders)): ?>
                <tr><td colspan="4" class="text-center py-4 text-muted">Không có đơn hàng nào</td></tr>
              <?php else: ?>
                <?php foreach ($recentOrders as $ro): ?>
                  <?php 
                     $st = $ro['status'] ?? 'pending';
                     $badgeCls = 'bg-secondary';
                     if (in_array($st, ['completed', 'delivered'])) $badgeCls = 'bg-success';
                     elseif (in_array($st, ['processing', 'sent'])) $badgeCls = 'bg-primary';
                     elseif ($st === 'cancelled') $badgeCls = 'bg-danger';
                     elseif ($st === 'draft') $badgeCls = 'bg-warning text-dark';
                  ?>
                  <tr>
                    <td class="ps-4 fw-semibold text-primary">#<?= htmlspecialchars($ro['order_number'] ?? $ro['id'] ?? 'N/A') ?></td>
                    <td><?= !empty($ro['order_date']) ? date('d/m/Y', strtotime($ro['order_date'])) : '---' ?></td>
                    <td><span class="badge badge-status <?= $badgeCls ?>"><?= htmlspecialchars(ucfirst($st)) ?></span></td>
                    <td class="text-end pe-4 fw-bold"><?= number_format((float)($ro['grand_total'] ?? 0), 0, ',', '.') ?>₫</td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card content-card shadow-sm h-100">
                <div class="card-header content-card-header bg-white">
                    <i class="bi bi-file-text me-2 text-warning"></i>Báo Giá Gần Đây
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Mã Báo Giá</th>
                                <th>Ngày Báo</th>
                                <th>Trạng Thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentQuotes)): ?>
                                <tr><td colspan="3" class="text-center py-3 text-muted">Không có dữ liệu</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentQuotes as $rq): ?>
                                    <?php 
                                        $st = $rq['status'] ?? 'draft';
                                        $badgeCls = 'bg-secondary';
                                        if ($st === 'accepted') $badgeCls = 'bg-success';
                                        elseif ($st === 'sent') $badgeCls = 'bg-primary';
                                        elseif ($st === 'rejected') $badgeCls = 'bg-danger';
                                        elseif ($st === 'draft') $badgeCls = 'bg-warning text-dark';
                                        
                                        $dateField = !empty($rq['quote_date']) ? $rq['quote_date'] : ($rq['created_at'] ?? '');
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-semibold">#<?= htmlspecialchars($rq['quote_number'] ?? $rq['id'] ?? 'N/A') ?></td>
                                        <td><?= !empty($dateField) ? date('d/m/Y', strtotime($dateField)) : '---' ?></td>
                                        <td><span class="badge badge-status <?= $badgeCls ?>"><?= htmlspecialchars(ucfirst($st)) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card content-card shadow-sm h-100">
                <div class="card-header content-card-header bg-white">
                    <i class="bi bi-truck-flatbed me-2 text-info"></i>Các chuyến giao gần nhất
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Mã Đơn</th>
                                <th>Tài Xế</th>
                                <th>Ngày Giao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTrips)): ?>
                                <tr><td colspan="3" class="text-center py-3 text-muted">Không có dữ liệu</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentTrips as $rt): ?>
                                    <tr>
                                        <td class="ps-3 fw-semibold text-primary">#<?= htmlspecialchars($rt['id'] ?? 'N/A') ?></td>
                                        <td><i class="bi bi-person-circle me-1 text-secondary"></i><?= htmlspecialchars($rt['driver_name'] ?? 'N/A') ?></td>
                                        <td><?= !empty($rt['expected_delivery_date']) ? date('d/m/Y', strtotime($rt['expected_delivery_date'])) : '<span class="text-muted fst-italic">Chưa xếp lịch</span>' ?></td>
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
  </div>

  <!-- Quick Actions Panel -->
  <div class="col-12 col-xl-3">
    <div class="card content-card shadow-sm h-100 bg-white">
      <div class="card-header content-card-header">
        <i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Thao Tác Nhanh
      </div>
      <div class="card-body p-4">
        <div class="d-grid gap-3">
          <a href="sales_orders.php" class="btn btn-light btn-quick-action border shadow-sm text-primary">
            <i class="bi bi-plus-circle-fill"></i> Tạo Đơn Hàng Mới
          </a>
          <a href="sales_quotes.php" class="btn btn-light btn-quick-action border shadow-sm text-success">
            <i class="bi bi-file-earmark-plus-fill"></i> Tạo Báo Giá
          </a>
          <a href="partners.php" class="btn btn-light btn-quick-action border shadow-sm text-info">
            <i class="bi bi-person-plus-fill"></i> Thêm Đối Tác
          </a>
          <a href="driver_trips.php" class="btn btn-light btn-quick-action border shadow-sm text-dark">
            <i class="bi bi-truck"></i> Bảng kê giao hàng
          </a>
        </div>
        
        <hr class="my-4 text-muted">
        
        <h6 class="text-muted text-uppercase small fw-bold mb-3">Thông Tin Hệ Thống</h6>
        <div class="d-flex align-items-center justify-content-between mb-2 small">
            <span>Phiên bản:</span> <span class="badge bg-secondary">v2.4.1</span>
        </div>
        <div class="d-flex align-items-center justify-content-between small">
            <span>Sao lưu cuối:</span> <span class="text-muted">Hôm nay, 08:00</span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Chart.js inclusion -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // Prepare PHP data for JS variables
  const monthlyLabels = <?= json_encode($chartMonthly['labels'], JSON_UNESCAPED_UNICODE) ?>;
  const monthlyValues = <?= json_encode($chartMonthly['values'], JSON_UNESCAPED_UNICODE) ?>;
  
  const statusLabels  = <?= json_encode($chartStatus['labels'], JSON_UNESCAPED_UNICODE) ?>;
  const statusValues  = <?= json_encode($chartStatus['values'], JSON_UNESCAPED_UNICODE) ?>;

  // Revenue Chart (Bar)
  const revCtx = document.getElementById('revenueChart');
  if (revCtx && monthlyLabels.length > 0) {
    new Chart(revCtx, {
      type: 'bar',
      data: {
        labels: monthlyLabels,
        datasets: [{
          label: 'Chi phí (VNĐ)',
          data: monthlyValues,
          backgroundColor: 'rgba(67, 97, 238, 0.7)',
          borderColor: '#4361ee',
          borderWidth: 1,
          borderRadius: 4,
          hoverBackgroundColor: '#4361ee'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(context) {
                let label = context.dataset.label || '';
                if (label) { label += ': '; }
                if (context.parsed.y !== null) {
                  label += new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(context.parsed.y);
                }
                return label;
              }
            }
          }
        },
        scales: {
          y: { 
            beginAtZero: true,
            grid: { borderDash: [2, 4], color: '#f0f0f0' },
            ticks: {
                callback: function(value) {
                    if (value >= 1000000) { return (value / 1000000).toLocaleString('vi-VN') + 'M'; }
                    return value.toLocaleString('vi-VN');
                }
            }
          },
          x: {
              grid: { display: false }
          }
        }
      }
    });
  }

  // Status Chart (Doughnut)
  const statusCtx = document.getElementById('statusChart');
  const totalStatus = statusValues.reduce((a, b) => a + Number(b), 0);
  
  if (statusCtx && totalStatus > 0) {
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: statusLabels,
        datasets: [{
          data: statusValues,
          backgroundColor: [
            '#6c757d', // Chưa có ngày
            '#4cc9f0', // Có lịch
            '#e71d36', // Quá hạn
            '#ced4da'  // Đã huỷ
          ],
          borderWidth: 2,
          borderColor: '#fff',
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 15,
                font: { size: 11 }
            }
          }
        }
      }
    });
  } else if (statusCtx) {
    statusCtx.parentElement.innerHTML = '<div class="text-center text-muted h-100 d-flex align-items-center justify-content-center"><p class="mb-0"><i>Không có dữ liệu giao hàng <br>hoặc mọi thứ đã hoàn tất.</i></p></div>';
  }
});
</script>
