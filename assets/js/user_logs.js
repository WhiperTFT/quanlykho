/* assets/js/user_logs.js
   Khởi tạo bảng user_logs với phân trang tùy biến (không di chuyển DOM nội bộ của DataTables),
   toolbar tìm kiếm, chọn số dòng/trang, tooltip, modal xem mô tả đầy đủ, nút Mật độ.
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
      // KHÔNG hiển thị length/info/paginate mặc định để tránh xung đột
      dom: 't',
      paging: true,
      searching: true,    // cho phép dt.search(), nhưng không hiển thị ô tìm mặc định vì dom: 't'
      info: false,
      lengthChange: false,
      pageLength: 50,
      order: [[0, 'desc']],
      autoWidth: false,
      scrollY: '60vh',
      scrollCollapse: true,
      language: {
        zeroRecords: 'Không tìm thấy dữ liệu',
        paginate: { first: 'Đầu', last: 'Cuối', next: 'Tiếp', previous: 'Trước' },
      },
      columnDefs: [
        { targets: [0, 4], className: 'text-nowrap' },
        { targets: 2, createdCell: function (td) { td.style.whiteSpace = 'nowrap'; } }
      ],
      drawCallback: function () {
        const api = this.api();
        updateInfo(api);
        renderPager(api);
        initTooltips(); // re-init tooltip sau mỗi lần draw
      },
      initComplete: function () {
        initTooltips();
        // Ẩn hoàn toàn length/info/paginate nếu bị ép bởi default global (phòng hờ)
        const $wrap = $table.closest('.dataTables_wrapper');
        $wrap.find('.dataTables_length, .dataTables_info, .dataTables_paginate').hide();
      }
    });

    // ====== Toolbar ======
    // Tìm kiếm toàn bảng
    $('#globalSearch').on('input', function () {
      dt.search(this.value).draw();
    });

    // Số dòng / trang (select bên ngoài)
    $('#rowsPerPage').val('50').on('change', function () {
      const n = parseInt(this.value, 10);
      dt.page.len(Number.isFinite(n) ? n : 50).draw(false);
    });

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

    // ====== Helpers ======
    function initTooltips() {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        // Tránh tạo tooltip trùng lặp
        if (!el._tooltip) {
          el._tooltip = new bootstrap.Tooltip(el, { trigger: 'hover' });
        }
      });
    }

    function updateInfo(api) {
      const info = api.page.info();
      const text = (info.recordsDisplay === 0)
        ? 'Không có dữ liệu'
        : `Hiển thị ${info.start + 1}–${info.end} / ${info.recordsDisplay} dòng`;
      document.getElementById('dtInfo').textContent = text;
    }

    function renderPager(api) {
      const info = api.page.info();
      const $pager = $('#dtPager').empty();

      if (info.pages <= 1) return; // không cần pager

      // Prev
      const prev = $('<button type="button" class="btn btn-sm btn-outline-secondary me-1">Trước</button>')
        .prop('disabled', info.page === 0)
        .on('click', () => api.page('previous').draw('page'));
      $pager.append(prev);

      // Tạo dải số trang (tối đa 7 nút)
      const maxButtons = 7;
      let start = Math.max(0, info.page - Math.floor(maxButtons / 2));
      let end = Math.min(info.pages - 1, start + maxButtons - 1);
      if (end - start + 1 < maxButtons) {
        start = Math.max(0, end - maxButtons + 1);
      }

      if (start > 0) {
        const firstBtn = $('<button type="button" class="btn btn-sm btn-outline-secondary me-1">1</button>')
          .on('click', () => api.page(0).draw('page'));
        $pager.append(firstBtn);
        if (start > 1) $pager.append('<span class="px-1">…</span>');
      }

      for (let i = start; i <= end; i++) {
        const btn = $('<button type="button" class="btn btn-sm me-1"></button>')
          .text(i + 1)
          .toggleClass('btn-primary', i === info.page)
          .toggleClass('btn-outline-secondary', i !== info.page)
          .on('click', () => api.page(i).draw('page'));
        $pager.append(btn);
      }

      if (end < info.pages - 1) {
        if (end < info.pages - 2) $pager.append('<span class="px-1">…</span>');
        const lastBtn = $('<button type="button" class="btn btn-sm btn-outline-secondary ms-0">')
          .text(info.pages)
          .on('click', () => api.page(info.pages - 1).draw('page'));
        $pager.append(lastBtn);
      }

      // Next
      const next = $('<button type="button" class="btn btn-sm btn-outline-secondary ms-2">Tiếp</button>')
        .prop('disabled', info.page >= info.pages - 1)
        .on('click', () => api.page('next').draw('page'));
      $pager.append(next);
    }
  }
})();
