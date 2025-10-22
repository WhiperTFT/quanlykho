<?php
// File: pxk_manager.php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
require_login();

$page_title = $lang['pxk_manager'] ?? 'Quản lý Phiếu Xuất Kho';
?>
<div class="container-fluid py-3">

  <!-- Header + New -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><?= htmlspecialchars($page_title) ?></h4>
    <div>
      <button id="btn-new" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> Thêm PXK
      </button>
      <button id="btnSelectPrinter" class="btn btn-outline-secondary btn-sm">
  <i class="bi bi-gear"></i> Chọn máy in...
</button>
<button id="btnPrintCleanup" class="btn btn-outline-danger btn-sm">
  <i class="bi bi-broom"></i> Dọn hàng đợi
</button>

    </div>
  </div>

  <!-- Card danh sách -->
  <div class="card shadow-sm">
    <div class="card-header py-2">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <h6 class="mb-0">Danh sách PXK</h6>
          <span class="badge bg-primary-subtle text-primary border" id="totalCount">0</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <!-- chừa trống toolbar phải -->
        </div>
      </div>

      <!-- TOOLBAR -->
      <div class="mt-2 toolbar d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <label for="pageSizeSelect" class="form-label mb-0 small text-muted">Hiển thị</label>
          <select id="pageSizeSelect" class="form-select form-select-sm" style="width:auto;">
            <option value="10">10 dòng</option>
            <option value="25">25 dòng</option>
            <option value="50">50 dòng</option>
            <option value="100">100 dòng</option>
            <option value="0">Tất cả</option>
          </select>

          <div class="search-group position-relative">
            <i class="bi bi-search"></i>
            <input type="text" id="filter-keyword" class="form-control form-control-sm ps-5" placeholder="Tìm số PXK / bên nhận...">
            <button type="button" class="btn btn-sm btn-link text-muted px-2 clear-search" id="btn-clear-search" aria-label="Xóa tìm kiếm" title="Xóa tìm kiếm">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button id="btn-reload" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-repeat"></i> Tải lại
          </button>
          <button id="btn-print-diag" class="btn btn-sm btn-outline-info">
            <i class="bi bi-activity"></i> Chẩn đoán in
          </button>
        </div>
      </div>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table id="pxkTable" class="table table-hover table-bordered table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:60px;">ID</th>
              <th style="width:160px;">Số PXK</th>
              <th style="width:120px;">Ngày</th>
              <th>Tên đơn vị nhận</th>
              <th style="width:120px;">Tệp PDF</th>
              <th style="width:160px;" class="text-center">Hành động</th>
            </tr>
          </thead>
          <tbody id="pxkTableBody"></tbody>
        </table>
      </div>
      <div id="pxkPagination" class="d-flex justify-content-between align-items-center"></div>
    </div>
  </div>

  <!-- FORM PXK (ẩn/hiện do JS điều khiển) -->
  <style>
    /* khi cuộn tới form, chừa khoảng để không bị navbar đè */
    #pxkFormCard { scroll-margin-top: 88px; }
  </style>
  <div class="card shadow-sm mt-3" id="pxkFormCard" style="display:none;">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span id="formTitle">Thêm Phiếu Xuất Kho</span>
      <div class="d-flex gap-2">
        <button id="btn-generate-number" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-magic"></i> Tạo số PXK tự động
        </button>
        <button id="btn-cancel" class="btn btn-sm btn-outline-secondary">Hủy</button>
      </div>
    </div>
    <div class="card-body">
      <form id="pxkForm">
        <input type="hidden" id="pxk_id" name="id" value="">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Số PXK</label>
            <input type="text" class="form-control form-control-sm" id="pxk_number" name="pxk_number" placeholder="PXKddmmyyyyNN" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Ngày xuất</label>
            <input type="text" class="form-control form-control-sm datepicker" id="pxk_date_display" placeholder="dd/mm/yyyy" required>
            <input type="hidden" id="pxk_date" name="pxk_date" value="">
          </div>
          <div class="col-md-6">
            <label class="form-label">Ghi chú</label>
            <input type="text" class="form-control form-control-sm" id="notes" name="notes" placeholder="Ghi chú...">
          </div>

          <div class="col-md-6 position-relative">
            <label class="form-label">Tên đơn vị nhận hàng</label>
            <input type="text" class="form-control form-control-sm" id="partner_name" name="partner_name" placeholder="Nhập tên đơn vị nhận..." autocomplete="off" required>
            <div id="partner_ac_box" class="ac-box" style="display:none; position:absolute; z-index:9999; background:#fff; border:1px solid #ccc; max-height:240px; overflow-y:auto;"></div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Địa chỉ</label>
            <input type="text" class="form-control form-control-sm" id="partner_address" name="partner_address" placeholder="Địa chỉ...">
          </div>

          <div class="col-md-3">
            <label class="form-label">Người liên hệ</label>
            <input type="text" class="form-control form-control-sm" id="partner_contact_person" name="partner_contact_person" placeholder="Họ tên người liên hệ...">
          </div>

          <div class="col-md-3">
            <label class="form-label">Số điện thoại</label>
            <input type="text" class="form-control form-control-sm" id="partner_phone" name="partner_phone" placeholder="Số điện thoại...">
          </div>
        </div>

        <hr>

        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0">Hàng hóa</h6>
          <button type="button" id="btn-add-item" class="btn btn-sm btn-success">
            <i class="bi bi-plus-lg"></i> Thêm dòng
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle" id="itemsTable">
            <thead class="table-light">
              <tr>
                <th style="width:50px;" class="text-center">STT</th>
                <th style="width:180px;">Danh mục</th>
                <th>Tên sản phẩm</th>
                <th style="width:110px;">Đơn vị</th>
                <th style="width:140px;" class="text-end">Số lượng</th>
                <th style="width:160px;">Ghi chú</th>
                <th style="width:80px;" class="text-center">Xóa</th>
              </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
          </table>
        </div>

        <div class="d-flex gap-2">
          <button type="button" id="btn-save" class="btn btn-primary">
            <i class="bi bi-save"></i> Lưu
          </button>
          <button type="button" id="btn-save-export" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-pdf"></i> Lưu & Xuất PDF
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Logic chính ở file riêng -->
<script src="assets/js/pxk_manage.js?v=1.0.1"></script>

<!-- Helpers cuộn mượt & binding nút Thêm -->
<script>
(() => {
  // Tính vị trí cuộn có bù header cố định (navbar)
  function smoothScrollToEl(el, offset = 88) {
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const targetY = rect.top + window.pageYOffset - offset;
    window.scrollTo({ top: targetY, behavior: 'smooth' });
  }

  // Đợi đến khi card form hiển thị (display != none) rồi mới cuộn
  function waitUntilVisible(el, callback, timeout = 3000) {
    const start = performance.now();
    const tick = () => {
      const visible = el && el.offsetParent !== null && getComputedStyle(el).display !== 'none';
      if (visible) {
        callback();
      } else if (performance.now() - start < timeout) {
        requestAnimationFrame(tick);
      } else {
        // hết thời gian, vẫn cuộn thử
        callback();
      }
    };
    tick();
  }

  // Expose để gọi từ pxk_manage.js (sau editPXK)
  window.PXK = window.PXK || {};
  window.PXK.waitAndScroll = function() {
    const card = document.getElementById('pxkFormCard');
    if (!card) return;
    waitUntilVisible(card, () => {
      smoothScrollToEl(card);
      const firstInput = document.getElementById('pxk_number');
      if (firstInput) {
        try { firstInput.focus({ preventScroll: true }); } catch (e) { firstInput.focus(); }
      }
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    const btnNew = document.getElementById('btn-new');
    if (btnNew) {
      btnNew.addEventListener('click', () => {
        // nếu form đang ẩn, để JS chính show nó; sau đó đợi-visible rồi cuộn
        const card = document.getElementById('pxkFormCard');
        if (card && card.style.display === 'none') {
          // nhiều project show form trong pxk_manage.js; ở đây chỉ lo phần cuộn
        }
        window.PXK.waitAndScroll();
      });
    }
  });
})();
(() => {
  // 1) Bật smooth default cho trình duyệt hỗ trợ (không ảnh hưởng polyfill)
  if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
    try {
      document.documentElement.style.scrollBehavior = 'smooth';
    } catch (e) {}
  }

  // 2) Tìm container cuộn gần nhất
  function getScrollContainer(el) {
    let node = el.parentElement;
    while (node && node !== document.body) {
      const style = getComputedStyle(node);
      const canScrollY =
        /(auto|scroll)/.test(style.overflowY) && node.scrollHeight > node.clientHeight;
      if (canScrollY) return node;
      node = node.parentElement;
    }
    return document.scrollingElement || document.documentElement;
  }

  // 3) rAF polyfill mượt trong mọi trường hợp
  function rafScroll(container, to, duration = 400) {
    const start = container.scrollTop;
    const change = to - start;
    const startTime = performance.now();
    const easeInOut = t => (t < 0.5 ? 2*t*t : -1+(4-2*t)*t); // easeInOutQuad

    function tick(now) {
      const elapsed = now - startTime;
      const t = Math.min(1, elapsed / duration);
      container.scrollTop = start + change * easeInOut(t);
      if (t < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  // 4) Cuộn tới el, tự phát hiện container & offset để tránh navbar che
  function smoothScrollToEl(el, offset = 88, duration = 400) {
    if (!el) return;
    const container = getScrollContainer(el);

    // Tính toạ độ mục tiêu trong hệ quy chiếu của container
    const elRect = el.getBoundingClientRect();
    const containerRect = container.getBoundingClientRect();
    const currentScrollTop = container.scrollTop;
    const deltaTop = elRect.top - containerRect.top;
    const target = currentScrollTop + deltaTop - offset;

    // Thử native smooth nếu có; nếu không dùng rAF
    const supportsNative =
      'scrollBehavior' in document.documentElement.style &&
      window.matchMedia('(prefers-reduced-motion: no-preference)').matches;

    if (container === document.documentElement || container === document.body || container === document.scrollingElement) {
      if (supportsNative) {
        window.scrollTo({ top: target, behavior: 'smooth' });
      } else {
        rafScroll(document.scrollingElement || document.documentElement, target, duration);
      }
    } else {
      if (supportsNative && typeof container.scrollTo === 'function') {
        container.scrollTo({ top: target, behavior: 'smooth' });
      } else {
        rafScroll(container, target, duration);
      }
    }
  }

  // 5) Đợi form hiện thật sự rồi mới cuộn (tránh “nhảy”)
  function waitUntilVisible(el, callback, timeout = 3000) {
    const start = performance.now();
    const check = () => {
      const shown = el && el.offsetParent !== null && getComputedStyle(el).display !== 'none';
      if (shown) callback();
      else if (performance.now() - start < timeout) requestAnimationFrame(check);
      else callback(); // hết thời gian, vẫn thử cuộn
    };
    check();
  }

  // 6) Expose để dùng ở pxk_manage.js cho nút Sửa
  window.PXK = window.PXK || {};
  window.PXK.waitAndScroll = function() {
    const card = document.getElementById('pxkFormCard');
    if (!card) return;
    waitUntilVisible(card, () => {
      smoothScrollToEl(card, 88, 450);
      const firstInput = document.getElementById('pxk_number');
      if (firstInput) {
        try { firstInput.focus({ preventScroll: true }); } catch (e) { firstInput.focus(); }
      }
    });
  };

  // 7) Nút Thêm PXK → gọi cuộn mượt
  document.addEventListener('DOMContentLoaded', () => {
    const btnNew = document.getElementById('btn-new');
    if (btnNew) {
      btnNew.addEventListener('click', () => {
        // Form có thể được show bởi js khác; mình chỉ lo phần cuộn
        window.PXK.waitAndScroll();
      });
    }
  });
})();
</script>
