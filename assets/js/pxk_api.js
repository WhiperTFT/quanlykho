// File: assets/js/pxk_api.js
// Gói các lời gọi API (PXK + Print) qua fetch; expose ra window.PXKAPI

(function () {
  const PXK_BASE   = 'process/pxk_api.php';
  const PRINT_BASE = 'process/print_api.php';

  // --- helpers chung ---
  async function _jsonGet(base, paramsObj) {
    const usp = new URLSearchParams(paramsObj || {});
    const res = await fetch(`${base}?${usp.toString()}`, { method: 'GET', cache: 'no-store' });
    return res.json();
  }
  async function _jsonPost(base, action, bodyObj) {
    const res = await fetch(`${base}?action=${encodeURIComponent(action)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(bodyObj || {})
    });
    return res.json();
  }
  async function _formPost(base, formObj) {
    const form = new FormData();
    for (const k in (formObj || {})) {
      if (Object.hasOwn(formObj, k) && formObj[k] !== undefined) {
        form.append(k, formObj[k]);
      }
    }
    const res = await fetch(base, { method: 'POST', body: form });
    return res.json();
  }

  // --- PXK endpoints ---
  async function list({ kw = '', page = 1, per_page = 10 } = {}) {
    const p = { action: 'list', page, per_page };
    if (kw) p.kw = kw;
    return _jsonGet(PXK_BASE, p);
  }

  async function get(id) {
    return _jsonGet(PXK_BASE, { action: 'get', id });
  }

  async function save(payload) {
    return _jsonPost(PXK_BASE, 'save', payload);
  }

  async function del(id) {
    return _jsonPost(PXK_BASE, 'delete', { id });
  }

  async function generateNumber() {
    // POST đơn giản không body
    const res = await fetch(`${PXK_BASE}?action=generate_number`, { method: 'POST' });
    return res.json();
  }

  async function productSuggest(term) {
    return _jsonGet(PXK_BASE, { action: 'product_suggest', term });
  }

  async function partnerSearch(kw) {
    return _jsonGet(PXK_BASE, { action: 'partner_search', kw });
  }

  /**
   * Export PDF
   * @param {{id:number, auto_print?:number, printer_name?:string}} args
   * - auto_print = 1: export xong tự enqueue in (nếu bạn áp dụng Cách A trong pxk_api.php)
   * - auto_print = 0 (mặc định): chỉ xuất PDF, trả về đường dẫn web + (nếu có) đường dẫn tuyệt đối
   */
  async function exportPdf(args) {
    const { id, auto_print = 0, printer_name = '' } = args || {};
    return _jsonPost(PXK_BASE, 'export_pdf', { id, auto_print, printer_name });
  }

  // --- PRINT endpoints ---
  async function diagPrint() {
    return _jsonGet(PRINT_BASE, { action: 'diag' });
  }

  /**
   * Enqueue in (Cách B): gọi print_api.php
   * @param {{doc_type?:string, ref_id?:number, pdf_path:string, printer_name?:string}} args
   */
  async function enqueuePrint({ doc_type = 'pxk', ref_id = 0, pdf_path, printer_name = '' }) {
    return _formPost(`${PRINT_BASE}?action=enqueue_print`, {
      action: 'enqueue_print',
      doc_type, ref_id, pdf_path, printer_name
    });
  }

  // Expose
  window.PXKAPI = {
    // PXK
    list, get, save, del,
    generateNumber, productSuggest, partnerSearch,
    exportPdf,

    // Print
    diagPrint, enqueuePrint
  };
})();
