// assets/js/inventory.js
$(function () {
  const dt = $('#inv-table').DataTable({
    processing: true,
    serverSide: true,
    searching: true,
    lengthMenu: [[10,25,50,100],[10,25,50,100]],
      ajax: {
    url: 'process/inventory_serverside.php',
    type: 'POST',
    data: function (d) { d.so_status = $('#status-filter').val() || ''; },
    error: function (xhr) {
      // Hiện lỗi server (nếu có JSON) thay vì chỉ “Ajax error”
      let msg = 'AJAX error';
      try {
        const j = JSON.parse(xhr.responseText);
        if (j && (j.error || j.message)) msg = j.error || j.message;
      } catch (_) {
        msg = xhr.responseText || msg;
      }
      alert('Lỗi tải tồn kho:\n' + msg);
    }
  
    },
    columns: [
  { data: 'category',      defaultContent: '', width: '140px', title: 'Danh mục' },
  { data: 'product_name',  defaultContent: '', title: 'Tên sản phẩm' },
  { data: 'unit',          defaultContent: '', width: '80px',  title: 'ĐVT' },
  { data: 'qty_purchased', className: 'text-end', render: renderNumber,   title: 'SL mua (SO)' },
  { data: 'qty_committed', className: 'text-end', render: renderNumber,   title: 'SL đã chốt bán' },
  { data: 'qty_available', className: 'text-end fw-semibold', render: renderNumber, title: 'Tồn khả dụng' },
  { data: 'cost_purchased',    className: 'text-end', render: renderCurrency, title: 'Tổng tiền mua' },
  { data: 'revenue_committed', className: 'text-end', render: renderCurrency, title: 'Tổng tiền bán (accepted)' },
  {
    data: null, orderable: false, searchable: false, width: '90px', title: 'Chi tiết',
    render: function (row) {
      return `<button class="btn btn-sm btn-outline-primary btn-movement"
                data-pkey="${escapeHtml(row.pkey)}"
                data-pname="${escapeHtml(row.product_name || '')}">
                Xem chi tiết
              </button>`;
    }
  }
],
order: [[1, 'asc']],
footerCallback: function (row, data) {
  const api = this.api();
  const sum = (idx) => api.column(idx, {page:'current'}).data()
                    .reduce((a,b)=> a + (parseFloat(b)||0), 0);
  // index mới: SL mua=3, chốt bán=4, tồn=5, tiền mua=6, tiền bán=7
  $(api.column(3).footer()).html(formatNumber(sum(3)));
  $(api.column(4).footer()).html(formatNumber(sum(4)));
  $(api.column(5).footer()).html(formatNumber(sum(5)));
  $(api.column(6).footer()).html(formatCurrency(sum(6)));
  $(api.column(7).footer()).html(formatCurrency(sum(7)));
}
  });

  $('#inv-search').on('keyup change', function(){ dt.search(this.value).draw(); });
  $('#status-filter').on('change', function(){ dt.ajax.reload(null, false); });

  $('#btn-export').on('click', function(){
    exportCurrentPageToCSV(dt, 'inventory_export');
  });

  // Modal chi tiết
  $(document).on('click', '.btn-movement', function(){
    const pkey = $(this).data('pkey');
    const pname = $(this).data('pname');
    $('#mv-product-title').text(pname || '');
    $('#mv-purchases-body').empty();
    $('#mv-sales-body').empty();

    $.getJSON('process/inventory_movements.php', { pkey }, function(resp){
      if (!resp || !resp.ok) {
        alert(resp && resp.error ? resp.error : 'Lỗi tải dữ liệu');
        return;
      }
      const pRows = resp.purchases || [];
      const sRows = resp.sales || [];
      for (const r of pRows) {
        $('#mv-purchases-body').append(`
          <tr>
            <td>${escapeHtml(r.order_date || '')}</td>
            <td>${escapeHtml(r.supplier_name || '')}</td>
            <td class="text-end">${formatNumber(r.quantity)}</td>
            <td class="text-end">${formatCurrency(r.unit_price)}</td>
            <td class="text-end">${formatCurrency(r.line_total)}</td>
            <td>${escapeHtml(r.order_number || '')}</td>
          </tr>
        `);
      }
      for (const r of sRows) {
        $('#mv-sales-body').append(`
          <tr>
            <td>${escapeHtml(r.quote_date || '')}</td>
            <td>${escapeHtml(r.customer_name || '')}</td>
            <td class="text-end">${formatNumber(r.quantity)}</td>
            <td class="text-end">${formatCurrency(r.unit_price)}</td>
            <td class="text-end">${formatCurrency(r.line_total)}</td>
            <td>${escapeHtml(r.quote_number || '')}</td>
          </tr>
        `);
      }
      const modal = new bootstrap.Modal(document.getElementById('movementModal'));
      modal.show();
    });
  });

  // ---- helpers ----
  function renderNumber(v){ return formatNumber(v); }
  function renderCurrency(v){ return formatCurrency(v); }
  function formatNumber(v){
    const n = parseFloat(v||0);
    return n.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    // Nếu bạn muốn 0 hoặc 2 lẻ tùy theo đơn vị, có thể chỉnh tại đây.
  }
  function formatCurrency(v){
    const n = parseFloat(v||0);
    return n.toLocaleString(undefined, {style:'currency', currency:'VND', currencyDisplay:'code'}).replace('VND','₫');
  }
  function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function exportCurrentPageToCSV(dt, filename){
    const headers = $('#inv-table thead th').map(function(){ return $(this).text().trim(); }).get();
    const data = dt.rows({page:'current'}).data().toArray().map(r => ([
      r.product_id ?? '',
      r.product_name ?? '',
      r.category ?? '',
      r.unit ?? '',
      r.qty_purchased ?? 0,
      r.qty_committed ?? 0,
      r.qty_available ?? 0,
      r.cost_purchased ?? 0,
      r.revenue_committed ?? 0
    ]));

    let csv = headers.join(',') + '\n';
    data.forEach(row => {
      csv += row.map(x => `"${String(x).replace(/"/g,'""')}"`).join(',') + '\n';
    });

    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.csv';
    a.click();
    URL.revokeObjectURL(url);
  }
});
