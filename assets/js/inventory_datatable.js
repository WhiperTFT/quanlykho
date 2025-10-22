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
      { title: 'ID' },
      { title: 'Sản phẩm' },
      { title: 'ĐVT' },
      { title: 'Danh mục' },
      { title: 'On hand', className: 'text-end' },
      { title: 'Allocated', className: 'text-end' },
      { title: 'ATP', className: 'text-end' },
      { title: 'Hành động', orderable: false, searchable: false, className: 'text-end' }
    ],
    order: [[1,'asc']],
    pageLength: 25,
    responsive: true,
    stateSave: true
  });

  $('#filterCategory').on('change', ()=> dt.ajax.reload());

  // Mở modal sổ chi tiết
  $table.on('click', '.btn-ledger', function(){
    const pid = $(this).data('product-id');
    const pname = $(this).data('product-name');
    $('#ledgerModalLabel').text('Sổ chi tiết: ' + pname + ' (#' + pid + ')');
    $('#ledgerModal').modal('show');
    loadLedger(pid);
  });

  function loadLedger(productId){
    const $ledger = $('#ledger-table');
    if ( $.fn.dataTable.isDataTable($ledger) ) {
      $ledger.DataTable().destroy();
      $ledger.find('tbody').empty();
    }
    $ledger.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: 'process/inventory_ledger_serverside.php',
        type: 'POST',
        data: function(d){
          d.product_id = productId;
          d.as_of = $('#asOfDate').val(); // optional
        }
      },
      columns: [
        { title: 'Ngày' },
        { title: 'Loại' },
        { title: 'Số chứng từ' },
        { title: 'Nhập (+)', className: 'text-end' },
        { title: 'Xuất (−)', className: 'text-end' },
        { title: 'Tồn lũy kế', className: 'text-end' },
      ],
      order: [[0,'asc']],
      pageLength: 50
    });
  }

  // Lọc ngày trong sổ chi tiết
  $('#asOfDate').on('change', function(){
    const $ledger = $('#ledger-table');
    if ($.fn.dataTable.isDataTable($ledger)) {
      $ledger.DataTable().ajax.reload();
    }
  });
});
