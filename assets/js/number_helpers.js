// assets/js/number_helpers.js
// ===========================================================
// Helpers chuẩn hóa & định dạng số theo chuẩn VN (.
// là nghìn, , là thập phân), kèm heuristic xử lý trường hợp chỉ có dấu .
// ===========================================================

(function () {

  // Cờ cưỡng bức VN: nếu true, mọi trường hợp "chỉ có ." sẽ coi là NGHÌN
  // Bật qua: window.NUMBER_HELPERS_FORCE_VN = true;
  const FORCE_VN = !!window.NUMBER_HELPERS_FORCE_VN;

  function parseVNNumber(str) {
    if (str == null) return 0;
    let s = String(str).trim();

    // Loại bỏ ký tự không hợp lệ (giữ số, dấu . , và -)
    s = s.replace(/[^\d.,-]/g, '');
    if (s === '' || s === ',' || s === '.' || s === '-' || s === '-,' || s === '-.') return 0;

    const hasComma = s.includes(',');
    const hasDot   = s.includes('.');

    if (hasComma && hasDot) {
      // Cả , và . tồn tại → ký tự xuất hiện cuối là DẤU THẬP PHÂN
      const lastComma = s.lastIndexOf(',');
      const lastDot   = s.lastIndexOf('.');
      if (lastComma > lastDot) {
        // , là thập phân → bỏ mọi . (nghìn), đổi , -> .
        s = s.replace(/\./g, '').replace(',', '.');
      } else {
        // . là thập phân → bỏ mọi , (nghìn)
        s = s.replace(/,/g, '');
      }
    } else if (hasComma && !hasDot) {
      // Chỉ có , → coi là thập phân VN
      s = s.replace(',', '.');
    } else if (!hasComma && hasDot) {
      // Chỉ có .
      if (FORCE_VN) {
        // Cưỡng bức VN: luôn coi . là nghìn
        s = s.replace(/\./g, '');
      } else {
        const parts = s.split('.');
        if (parts.length > 2) {
          // Nhiều dấu . → nghìn
          s = s.replace(/\./g, '');
        } else {
          // 1 dấu .
          const frac = parts[1] || '';
          if (frac.length === 3 && /^\d{3}$/.test(frac)) {
            // Đúng 3 chữ số sau dấu . → khả năng cao là NGHÌN
            s = s.replace(/\./g, '');
          } else {
            // Không phải 3 chữ số (1,2,4,5...) → coi như THẬP PHÂN (12.5, 0.25)
            // Giữ nguyên
          }
        }
      }
    } else {
      // Chỉ số thuần (vd "25000") → để nguyên
    }

    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
  }

  function formatVNNumber(x, maxFractionDigits = 6) {
    // Nếu là số nguyên, không bắt buộc 0 thập phân
    const isInt = Number.isFinite(x) && Math.floor(x) === x;
    try {
      return new Intl.NumberFormat('vi-VN', {
        minimumFractionDigits: isInt ? 0 : 0,
        maximumFractionDigits: maxFractionDigits
      }).format(x);
    } catch (e) {
      // Fallback đơn giản nếu thiếu Intl
      const num = Number(x);
      if (!Number.isFinite(num)) return '';
      const fixed = num.toString();
      const parts = fixed.split('.');
      let intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      if (parts.length > 1 && parts[1]) return intPart + ',' + parts[1];
      return intPart;
    }
  }

  function recalcRow(tr) {
    const qtyInput   = tr.querySelector('.quantity');
    const priceInput = tr.querySelector('.unit-price');
    const totalInput = tr.querySelector('.line-total');
    if (!qtyInput || !priceInput || !totalInput) return;

    const qty   = parseVNNumber(qtyInput.value);
    const price = parseVNNumber(priceInput.value);
    const total = qty * price;

    totalInput.value = Number.isFinite(total) ? formatVNNumber(total) : '';
    totalInput.dataset.raw = String(Number.isFinite(total) ? total : 0);
  }

  // Tránh vòng lặp sự kiện khi format
  let _formatting = false;

  function formatFieldOnBlur(inputEl) {
    if (!inputEl) return;
    if (_formatting) return;
    _formatting = true;
    try {
      const n = parseVNNumber(inputEl.value);
      inputEl.value = Number.isFinite(n) ? formatVNNumber(n) : '';
      inputEl.dataset.raw = String(Number.isFinite(n) ? n : 0);

      const tr = inputEl.closest('tr');
      if (tr) recalcRow(tr);
    } finally {
      _formatting = false;
    }
  }

  function bindNumberHelpers(tbodySelector, formSelector) {
    const tbody = document.querySelector(tbodySelector);
    if (!tbody) return;

    // Khi gõ: chỉ tính lại (không format nghìn ngay để tránh giật con trỏ)
    tbody.addEventListener('input', function (e) {
      const target = e.target;
      if (target && (target.classList.contains('quantity') || target.classList.contains('unit-price'))) {
        const tr = target.closest('tr');
        if (tr) recalcRow(tr);
      }
    });

    // Khi blur: format đúng VN
    tbody.addEventListener('blur', function (e) {
      const target = e.target;
      if (target && (target.classList.contains('quantity') || target.classList.contains('unit-price'))) {
        formatFieldOnBlur(target);
      }
    }, true);

    // Quan sát dòng mới thêm → format ban đầu nếu có giá trị
    const observer = new MutationObserver((muts) => {
      muts.forEach(m => {
        m.addedNodes.forEach(node => {
          if (node.nodeType === 1 && node.matches('tr')) {
            const q = node.querySelector('.quantity');
            const p = node.querySelector('.unit-price');
            if (q && q.value) formatFieldOnBlur(q);
            if (p && p.value) formatFieldOnBlur(p);
            recalcRow(node);
          }
        });
      });
    });
    observer.observe(tbody, { childList: true });

    // Trước khi submit: ép về "dạng máy"
    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (!form) return;
      // Hỗ trợ nhiều form selector, ví dụ: "#order-form, #quote-form"
      const matchForm = formSelector
        ? form.matches(formSelector) || form.querySelector(formSelector)
        : true;
      if (!matchForm) return;

      const fields = form.querySelectorAll('.quantity, .unit-price');
      fields.forEach(inp => {
        const raw = parseVNNumber(inp.value);
        inp.value = Number.isFinite(raw) ? String(raw) : '';
      });
    }, true);
  }

  // Xuất API
  window.NumberHelpers = {
    parseVNNumber,
    formatVNNumber,
    formatFieldOnBlur,
    recalcRow,
    bindNumberHelpers
  };

  // Auto-detect: tự bind cho các trang có #item-details-body
  document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('#item-details-body')) {
      // Cho phép nhiều form id: order, quote, driver...
      const forms = '#order-form, #quote-form, #driver-form';
      bindNumberHelpers('#item-details-body', forms);
    }
  });

})();
