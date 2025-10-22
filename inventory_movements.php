<?php
// inventory_movements.php — Sổ chi tiết phát sinh kho theo sản phẩm
// Yêu cầu: đã có các VIEW: v_inventory_movements (đã hợp nhất IN/OUT)
// Layout của bạn cần đã nạp sẵn jQuery + DataTables + Bootstrap

require_once __DIR__ . '/includes/init.php'; // $pdo (PDO)
require_once __DIR__ . '/includes/header.php';
$products = [];
try {
  $stmt = $pdo->query("SELECT id, name FROM products ORDER BY name");
  $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $products = [];
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Sổ chi tiết kho</title>
  <style>
    #ledger-table td, #ledger-table th { white-space: nowrap; }
    .toolbar-ledger .form-select, .toolbar-ledger .form-control { min-width: 220px; }
  </style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Sổ chi tiết kho</h5>
  </div>
  <div class="card-body">
    <div class="toolbar-ledger d-flex flex-wrap align-items-end gap-2 mb-3">
      <div>
        <label class="form-label mb-1">Sản phẩm</label>
        <select id="ledgerProduct" class="form-select form-select-sm">
          <option value="">— Chọn sản phẩm —</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (#<?= (int)$p['id'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label mb-1">Tới ngày</label>
        <input id="ledgerAsOf" type="date" class="form-control form-control-sm" />
      </div>
      <div>
        <button id="btnLoadLedger" class="btn btn-sm btn-primary">
          <i class="bi bi-search"></i> Xem sổ
        </button>
      </div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="badge bg-secondary" id="badgeBeginBal">Đầu kỳ: 0</span>
        <span class="badge bg-info text-dark" id="badgeIn">Nhập: 0</span>
        <span class="badge bg-warning text-dark" id="badgeOut">Xuất: 0</span>
        <span class="badge bg-success" id="badgeEndBal">Cuối kỳ: 0</span>
      </div>
    </div>

    <table id="ledger-table" class="table table-striped table-bordered w-100">
      <thead class="table-light">
      <tr>
        <th>Ngày</th>
        <th>Loại</th>
        <th>Số chứng từ</th>
        <th class="text-end">Nhập (+)</th>
        <th class="text-end">Xuất (−)</th>
        <th class="text-end">Tồn lũy kế</th>
      </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
$(function(){
  let dt;

  function initDT(){
    const $tbl = $('#ledger-table');
    if ($.fn.dataTable.isDataTable($tbl)) {
      dt.destroy();
      $tbl.find('tbody').empty();
    }
    dt = $tbl.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: 'process/inventory_ledger_serverside.php',
        type: 'POST',
        data: function(d){
          d.product_id = $('#ledgerProduct').val() || '';
          d.as_of      = $('#ledgerAsOf').val() || '';
        }
      },
      columns: [
        { data: 0, title: 'Ngày' },
        { data: 1, title: 'Loại' },
        { data: 2, title: 'Số chứng từ' },
        { data: 3, title: 'Nhập (+)', className: 'text-end',
          render: v => Number(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) },
        { data: 4, title: 'Xuất (−)', className: 'text-end',
          render: v => Number(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) },
        { data: 5, title: 'Tồn lũy kế', className: 'text-end fw-semibold',
          render: v => Number(v||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) }
      ],
      order: [[0, 'asc']],
      pageLength: 50,
      responsive: true,
      drawCallback: function(settings){
        const sum = (settings.json && settings.json.summary) || {begin:0, in:0, out:0, end:0};
        $('#badgeBeginBal').text('Đầu kỳ: ' + Number(sum.begin||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}));
        $('#badgeIn').text('Nhập: ' + Number(sum.in||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}));
        $('#badgeOut').text('Xuất: ' + Number(sum.out||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}));
        $('#badgeEndBal').text('Cuối kỳ: ' + Number(sum.end||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}));
      }
    });
  }

  $('#btnLoadLedger').on('click', function(){
    const pid = $('#ledgerProduct').val();
    if (!pid) { alert('Vui lòng chọn sản phẩm.'); return; }
    initDT();
  });

  // Enter để load nhanh
  $('#ledgerProduct, #ledgerAsOf').on('keypress', function(e){
    if (e.which === 13) $('#btnLoadLedger').click();
  });
});
</script>
</body>
</html>
<?php require_once __DIR__ . '/includes/footer.php'; ?>