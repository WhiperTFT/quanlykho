<?php
// inventory.php — Báo cáo Tồn kho (On hand) + Allocated + ATP
// Yêu cầu: đã tạo các VIEW: v_stock_on_hand, v_inventory_allocated, v_inventory_atp
// Lưu ý: trang layout của bạn phải đang nạp sẵn jQuery + DataTables + Bootstrap + Bootstrap Icons

require_once __DIR__ . '/includes/init.php'; // $pdo (PDO)
require_once __DIR__ . '/includes/header.php';
$categories = [];
try {
  $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
  $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $categories = [];
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Tồn kho</title>
  <style>
    /* tinh chỉnh nhỏ cho bảng */
    #inventory-table td, #inventory-table th { white-space: nowrap; }
    .stat-badges .badge { font-weight: 500; }
  </style>
</head>
<body>
<div class="card">
  <div class="card-header d-flex flex-wrap align-items-center gap-2">
    <h5 class="mb-0">Tồn kho</h5>
    <div class="ms-auto d-flex align-items-center gap-2">
      <select id="filterCategory" class="form-select form-select-sm" style="max-width: 260px">
        <option value="">— Tất cả danh mục —</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="stat-badges d-none d-sm-flex align-items-center gap-1">
        <span class="badge bg-secondary" id="badgeOnHand">On hand: 0</span>
        <span class="badge bg-warning text-dark" id="badgeAllocated">Allocated: 0</span>
        <span class="badge bg-success" id="badgeATP">ATP: 0</span>
      </div>
    </div>
  </div>

  <div class="card-body">
    <table id="inventory-table" class="table table-striped table-bordered w-100">
      <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Sản phẩm</th>
        <th>ĐVT</th>
        <th>Danh mục</th>
        <th class="text-end">On hand</th>
        <th class="text-end">Allocated</th>
        <th class="text-end">ATP</th>
      </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>
<div class="card">
  <div class="card-header d-flex align-items-center gap-2">
    <h5 class="mb-0">Tồn kho</h5>
    <select id="filterCategory" class="form-select form-select-sm" style="max-width:240px">
      <option value="">-- Tất cả danh mục --</option>
      <!-- render server-side các option categories nếu cần -->
    </select>
  </div>
  <div class="card-body">
    <table id="inventory-table" class="table table-striped table-bordered w-100"></table>
  </div>
</div>

<!-- Modal sổ chi tiết -->
<div class="modal fade" id="ledgerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="ledgerModalLabel" class="modal-title">Sổ chi tiết</h5>
        <input id="asOfDate" type="date" class="form-control form-control-sm ms-3" style="max-width: 200px;">
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <table id="ledger-table" class="table table-sm table-hover w-100"></table>
      </div>
    </div>
  </div>
</div>

<script>
// Khởi tạo DataTable
$(function(){
  const $table = $('#inventory-table');
  const dt = $table.DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: 'process/inventory_serverside.php',
      type: 'POST',
      data: function(d){
        d.category_id = $('#filterCategory').val() || '';
      }
    },
    columns: [
      { data: 0, title: 'ID' },
      { data: 1, title: 'Sản phẩm' },
      { data: 2, title: 'ĐVT' },
      { data: 3, title: 'Danh mục' },
      { data: 4, title: 'On hand', className: 'text-end',
        render: data => Number(data).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) },
      { data: 5, title: 'Allocated', className: 'text-end',
        render: data => Number(data).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) },
      { data: 6, title: 'ATP', className: 'text-end',
        render: function(data){
          const n = Number(data);
          const txt = n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
          // tô màu theo âm/dương
          return n < 0 ? `<span class="text-danger fw-semibold">${txt}</span>` : `<span class="text-success fw-semibold">${txt}</span>`;
        }
      }
    ],
    order: [[1,'asc']],
    pageLength: 25,
    stateSave: true,
    responsive: true,
    drawCallback: function(settings){
      // cập nhật badges tổng hợp (server trả về qua json.summary)
      const json = settings.json || {};
      const sum = json.summary || {on_hand:0, allocated:0, atp:0};
      $('#badgeOnHand').text('On hand: ' + Number(sum.on_hand||0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}));
      $('#badgeAllocated').text('Allocated: ' + Number(sum.allocated||0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}));
      $('#badgeATP').text('ATP: ' + Number(sum.atp||0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}));
    }
  });

  $('#filterCategory').on('change', ()=> dt.ajax.reload());
});
</script>
</body>
</html>
<?php require_once __DIR__ . '/includes/footer.php'; ?>