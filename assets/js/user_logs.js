/* assets/js/user_logs.js
   Khởi tạo bảng user_logs: phân trang 50/100/200/500, toolbar tìm kiếm,
   chip lọc hành động, nút Mật độ, tooltip, modal xem mô tả đầy đủ.
*/
(function () {
  // ==== Đợi đủ dependency (jQuery + DataTables + Bootstrap) rồi mới chạy ====
  function depsReady() {
    return (
      window.jQuery &&
      typeof window.jQuery.fn !== "undefined" &&
      typeof window.jQuery.fn.DataTable !== "undefined" &&
      window.bootstrap &&
      typeof window.bootstrap.Tooltip === "function"
    );
  }
  function waitForDeps(cb, retries = 40, interval = 100) {
    if (depsReady()) return cb();
    if (retries <= 0) return console.error("[user_logs] Không tìm thấy jQuery/DataTables/Bootstrap.");
    setTimeout(() => waitForDeps(cb, retries - 1, interval), interval);
  }

  waitForDeps(initUserLogs);

  function initUserLogs() {
  const $ = window.jQuery;

  const $table = $('#logsTable');
  if (!$table.length) return;

  const dt = $table.DataTable({
    dom: 'rtp', // không có search box mặc định
    pageLength: 50,
    lengthChange: false,
    order: [[0, 'desc']],
    autoWidth: false,
    scrollY: '60vh',
    scrollCollapse: true,
    paging: true,
    language: {
      zeroRecords: 'Không tìm thấy dữ liệu',
      info: 'Hiển thị _START_ đến _END_ của _TOTAL_ dòng',
      infoEmpty: 'Không có dữ liệu',
      infoFiltered: '(lọc từ _MAX_ dòng)',
      paginate: { first: 'Đầu', last: 'Cuối', next: 'Tiếp', previous: 'Trước' }
    },
    columnDefs: [
      { targets: [0, 4], className: 'text-nowrap' },
      { targets: 2, createdCell: function (td) { td.style.whiteSpace = 'nowrap'; } }
    ],
    drawCallback: function () {
      const api = this.api();
      const info = api.page.info();
      const infoText = (info.recordsDisplay === 0)
        ? 'Không có dữ liệu'
        : 'Hiển thị ' + (info.start + 1) + '–' + info.end + ' / ' + info.recordsDisplay + ' dòng';
      $('#dtInfo').text(infoText);

      const $pager = $(api.table().container()).find('.dataTables_paginate');
      $('#dtPager').empty().append($pager);
    },
    initComplete: function () {
      initTooltips();
    }
  });

  // Search toàn bảng
  $('#globalSearch').on('input', function () {
    dt.search(this.value).draw();
  });

  // Số dòng / trang
  $('#rowsPerPage').val('50').on('change', function () {
    dt.page.len(parseInt(this.value, 10) || 50).draw();
  });

  // Tooltip
  function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      new bootstrap.Tooltip(el, { trigger: 'hover' });
    });
  }
  $table.on('draw.dt', initTooltips);

  // Mật độ (dense)
  $('#densityToggle').on('click', function () {
    $table.toggleClass('dense');
    $(this).toggleClass('active');
  });

  // Modal mô tả đầy đủ
  $('#logsTable tbody').on('click', 'td.col-desc', function () {
    var text = $(this).text().trim();
    $('#descModalBody').text(text);
    new bootstrap.Modal(document.getElementById('descModal')).show();
  });

  // Reset lọc
  $('#resetFilters').on('click', function () {
    $('#globalSearch').val('');
    dt.search('').columns().search('').draw();
  });

  }})();
  