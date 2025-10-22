// File: assets/js/print_center.js (v1.1.2, no jQuery)
(function () {
  "use strict";

  const api = "process/print_center_api.php";

  /** ---------------- UI helpers ---------------- **/
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  function toast(message, type) {
    const area = qs("#toastArea");
    if (!area) return alert(message);

    const id = "t" + String(Math.random()).slice(2);
    const cls =
      type === "error" ? "text-bg-danger" :
      type === "warn"  ? "text-bg-warning" :
      type === "ok"    ? "text-bg-success" : "text-bg-primary";

    const wrapper = document.createElement("div");
    wrapper.className = `toast ${cls}`;
    wrapper.id = id;
    wrapper.setAttribute("role", "alert");
    wrapper.setAttribute("aria-live", "assertive");
    wrapper.setAttribute("aria-atomic", "true");
    wrapper.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    area.appendChild(wrapper);

    if (window.bootstrap && bootstrap.Toast) {
      const t = new bootstrap.Toast(wrapper, { delay: 3500 });
      t.show();
      wrapper.addEventListener("hidden.bs.toast", () => wrapper.remove());
    } else {
      setTimeout(() => wrapper.remove(), 3500);
    }
  }

  function humanSize(bytes) {
    if (bytes === 0) return "0 B";
    const k = 1024, units = ["B","KB","MB","GB","TB"];
    const i = Math.floor(Math.log(bytes)/Math.log(k));
    return (bytes/Math.pow(k,i)).toFixed(2) + " " + units[i];
  }

  /** ---------------- State ---------------- **/
  let files = [];        // [{id, name, size, type, serverPath, previewable, ext}]
  let selectedId = null;
  let printers = [];     // [{name, isDefault}]

  /** ---------------- DOM refs ---------------- **/
  const dropzone   = qs("#dropzone");
  const fileInput  = qs("#fileInput");
  const fileListEl = qs("#fileList");
  const viewer     = qs("#viewer");
  const fileCount  = qs("#fileCount");
  const printerSel = qs("#printerSelect");
  const defBadge   = qs("#defaultPrinterBadge");

  /** ---------------- Init ---------------- **/
  document.addEventListener("DOMContentLoaded", () => {
    bindDnD();
    bindButtons();
 });

  /** ---------------- Bindings ---------------- **/
  function bindDnD() {
    if (!dropzone || !fileInput) return;

    // 1) Dropzone click để mở file dialog
    dropzone.addEventListener("click", () => {
      // reset trước khi mở để chọn lại cùng file vẫn nhận change
      fileInput.value = "";
      fileInput.click();
    });

    // 2) Nút "Chọn file" nằm bên trong dropzone:
    //    Phải chặn nổi bọt + default để không kích hoạt dropzone.click() lần 2
    const btnBrowse = qs("#btn-browse");
    if (btnBrowse) {
      btnBrowse.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation(); // NGĂN bubble lên dropzone
        fileInput.value = ""; // reset trước
        fileInput.click();
      });
    }

    ["dragenter","dragover"].forEach(evt => {
      dropzone.addEventListener(evt, e => {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.add("dragover");
      });
    });
    ["dragleave","drop"].forEach(evt => {
      dropzone.addEventListener(evt, e => {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.remove("dragover");
      });
    });
    dropzone.addEventListener("drop", e => handleFiles(e.dataTransfer.files));

    // Khi chọn file xong
    fileInput.addEventListener("change", e => {
      handleFiles(e.target.files);
      // reset sau khi xử lý, để lần chọn tiếp theo (kể cả cùng file) vẫn kích hoạt change
      e.target.value = "";
    });
  }

  function bindButtons() {
    qs("#btn-clear-all")?.addEventListener("click", deleteAll);
    qs("#btn-print-selected")?.addEventListener("click", doPrint);
    qs("#btn-refresh-printers")?.addEventListener("click", () => window.Printers?.reload());
    qs("#btn-refresh-queue")?.addEventListener("click", loadQueue);
    qs("#toggleSelectAll")?.addEventListener("change", e => {
      qsa('input[type="checkbox"]', fileListEl).forEach(cb => { cb.checked = e.target.checked; });
    });
    qs("#btn-open-queue")?.addEventListener("click", loadQueue);
  }

  /** ---------------- Core ---------------- **/
  function handleFiles(list) {
    if (!list || !list.length) return;
    const fd = new FormData();
    for (const f of list) fd.append("files[]", f);
    fd.append("action", "upload");

    toast("Đang tải lên…", "info");
    fetch(api, { method: "POST", body: fd })
      .then(r => r.json())
      .then(res => {
        if (!res.success) { toast(res.message || "Tải lên thất bại", "error"); return; }
        for (const it of res.data || []) files.push(it);
        renderList();
        toast(`Đã thêm ${res.data.length} file.`, "ok");
      })
      .catch(() => toast("Lỗi kết nối khi tải lên.", "error"));
  }

  function renderList() {
    if (fileCount) fileCount.textContent = String(files.length);
    if (!files.length) {
      fileListEl.innerHTML = `<div class="text-center text-muted py-4">Chưa có file nào.</div>`;
      viewer.innerHTML = `<div class="h-100 d-flex align-items-center justify-content-center text-muted">Chọn 1 file để xem trước…</div>`;
      return;
    }
    fileListEl.innerHTML = files.map(it => {
      const icon = pickIcon(it);
      return `
        <div class="file-card p-2 mb-2">
          <div class="d-flex align-items-center gap-3">
            ${renderThumb(it, icon)}
            <div class="flex-grow-1">
              <div class="d-flex align-items-center justify-content-between">
                <div class="fw-semibold text-truncate" title="${it.name}">${it.name}</div>
                <div class="text-muted small ms-2">${humanSize(it.size)}</div>
              </div>
              <div class="text-muted small">${it.type || it.ext.toUpperCase()}</div>
              <div class="mt-1 d-flex align-items-center gap-2">
                <div class="form-check">
                  <input class="form-check-input file-select" type="checkbox" data-id="${it.id}">
                  <label class="form-check-label small">Chọn in</label>
                </div>
                <button class="btn btn-sm btn-outline-secondary btn-preview" data-id="${it.id}" title="Xem trước">
                  <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger btn-remove" data-id="${it.id}" title="Xóa file tạm này">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            </div>
          </div>
        </div>`;
    }).join("");

    // bind buttons
    qsa(".btn-remove", fileListEl).forEach(b => b.addEventListener("click", onRemove));
    qsa(".btn-preview", fileListEl).forEach(b => b.addEventListener("click", onPreview));
    if (selectedId == null && files[0]) { selectedId = files[0].id; }
    renderViewer();
  }

  function renderThumb(it, icon) {
    if (it.previewable === "image") {
      return `<img class="file-thumb" src="${it.serverPath}" alt="">`;
    }
    return `<div class="file-thumb d-flex align-items-center justify-content-center">
      <i class="bi ${icon}" style="font-size: 1.75rem; color:#6c757d"></i>
    </div>`;
  }

  function renderViewer() {
    const it = files.find(x => x.id === selectedId);
    if (!it) {
      viewer.innerHTML = `<div class="h-100 d-flex align-items-center justify-content-center text-muted">Chọn 1 file để xem trước…</div>`;
      return;
    }
    if (it.previewable === "image") {
      viewer.innerHTML = `<div class="h-100 d-flex align-items-center justify-content-center">
        <img src="${it.serverPath}" style="max-width:100%; max-height:100%; object-fit:contain;">
      </div>`;
    } else if (it.previewable === "pdf") {
  viewer.innerHTML = `
    <iframe
      src="${it.serverPath}#view=FitH"
      allow="fullscreen"
      allowfullscreen
      loading="lazy"
      style="width:100%;height:100%;border:0;"
    ></iframe>`;
    } else {
      viewer.innerHTML = `<div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted">
        <div><i class="bi ${pickIcon(it)}" style="font-size:2rem;"></i></div>
        <div class="mt-2">Không xem trước được <strong>.${it.ext}</strong>. Hệ thống sẽ tự chuyển đổi để in.</div>
      </div>`;
    }
  }

  function onRemove(e) {
    const id = e.currentTarget.getAttribute("data-id");
    deleteFiles([id], () => {
      files = files.filter(x => x.id !== id);
      if (selectedId === id) selectedId = null;
      renderList();
    });
  }

  function deleteAll() {
    if (!files.length) { toast("Không có file để xóa.", "warn"); return; }
    const allIds = files.map(f => f.id);
    deleteFiles(allIds, () => {
      files = []; selectedId = null; renderList();
      toast("Đã xóa toàn bộ file tạm.", "ok");
    });
  }

  function deleteFiles(ids, onDone) {
    fetch(api, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "delete", fileIds: ids })
    })
    .then(r => r.json())
    .then(res => {
      if (!res.success) { toast(res.message || "Xóa file tạm thất bại.", "error"); return; }
      if (onDone) onDone();
    })
    .catch(() => toast("Lỗi kết nối khi xóa file tạm.", "error"));
  }

  function onPreview(e) {
    selectedId = e.currentTarget.getAttribute("data-id");
    renderViewer();
  }

  function pickIcon(it) {
    const ext = (it.ext || "").toLowerCase();
    if (["doc","docx"].includes(ext)) return "bi-file-earmark-word";
    if (["xls","xlsx","csv"].includes(ext)) return "bi-file-earmark-excel";
    if (ext === "pdf") return "bi-file-earmark-pdf";
    if (["png","jpg","jpeg","bmp","gif","tif","tiff","webp"].includes(ext)) return "bi-file-earmark-image";
    return "bi-file-earmark";
  }

  function loadPrintersBasic() {
  if (printerSel) {
    printerSel.disabled = true;
    printerSel.innerHTML = '<option>Đang nạp máy in…</option>';
  }
  if (defBadge) defBadge.textContent = '';

  const esc = (s) => String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  fetch(api + "?action=list_printers", { cache: "no-store", credentials: "same-origin" })
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        toast(res.message || "Không đọc được danh sách máy in", "error");
        if (printerSel) printerSel.innerHTML = '<option value="">(Lỗi đọc máy in)</option>';
        return;
      }

      const { data, preferredPrinter, defaultPrinter, autoSelected } = res;
      printers = Array.isArray(data) ? data : [];

      if (!printers.length) {
        if (printerSel) {
          printerSel.innerHTML = '<option value="">(Không có máy in nào)</option>';
        }
        if (defBadge) defBadge.textContent = '';
        return;
      }

      // Render option có nhãn hiện đại
      const opts = printers.map(p => {
        const tags = [
          p.isDefault ? 'Mặc định' : null,
          (preferredPrinter && preferredPrinter === p.name) ? 'Ưa thích' : null
        ].filter(Boolean);
        const label = p.name + (tags.length ? ` — ${tags.join(' · ')}` : '');
        return `<option value="${esc(p.name)}">${esc(label)}</option>`;
      }).join("");

      if (printerSel) {
        printerSel.innerHTML = opts;
        // Ưu tiên: preferred → default → phần tử đầu
        const want = autoSelected || defaultPrinter || printers[0]?.name || '';
        if (want) printerSel.value = want;
      }

      // Badge: chip trạng thái
      if (defBadge) {
        const sel = printerSel ? printerSel.value : '';
        const chips = [];
        if (sel) chips.push(`<span class="badge rounded-pill text-bg-secondary me-1"><i class="bi bi-printer me-1"></i>${esc(sel)}</span>`);
        if (preferredPrinter && sel === preferredPrinter) chips.push(`<span class="badge rounded-pill text-bg-success me-1"><i class="bi bi-star-fill me-1"></i>Ưa thích</span>`);
        if (defaultPrinter && sel === defaultPrinter) chips.push(`<span class="badge rounded-pill text-bg-info"><i class="bi bi-check2-circle me-1"></i>Mặc định Windows</span>`);
        if (defaultPrinter && preferredPrinter && sel === preferredPrinter && defaultPrinter !== preferredPrinter) {
          chips.push(`<span class="badge rounded-pill text-bg-light text-dark ms-2"><i class="bi bi-info-circle me-1"></i>Mặc định: ${esc(defaultPrinter)}</span>`);
        }
        defBadge.innerHTML = chips.join('');
        defBadge.classList.add('printer-badge');
      }

      // Phát sự kiện cho phần khác (nếu cần)
      window.dispatchEvent(new CustomEvent('printer:list-loaded', {
        detail: {
          printers,
          selected: printerSel ? printerSel.value : '',
          preferred: preferredPrinter || '',
          defaultPrinter: defaultPrinter || ''
        }
      }));
    })
    .catch(() => {
      toast("Lỗi khi đọc máy in", "error");
      if (printerSel) printerSel.innerHTML = '<option value="">(Lỗi đọc máy in)</option>';
    })
    .finally(() => {
      if (printerSel) printerSel.disabled = false;
    });
}

  function doPrint() {
    const selected = qsa(".file-select:checked", fileListEl).map(cb => cb.getAttribute("data-id"));
    if (!selected.length) { toast("Hãy chọn ít nhất 1 file để in.", "warn"); return; }

    const printer = printerSel ? printerSel.value : "";
    if (!printer) { toast("Chưa chọn máy in.", "warn"); return; }

    const copies = parseInt(qs("#copies")?.value || "1", 10);
    const pageRanges = (qs("#pageRanges")?.value || "").trim();
    const duplex = qs("#duplex")?.value || "off";
    const orientation = qs("#orientation")?.value || "auto";

    const payload = {
      action: "enqueue_print",
      printer,
      copies: isFinite(copies) ? copies : 1,
      pageRanges,
      duplex,           // off|long|short
      orientation,      // auto|portrait|landscape
      fileIds: selected
    };

    toast("Đang gửi lệnh in…", "info");
    fetch(api, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) })
      .then(r => r.json())
      .then(res => {
        if (!res.success) { toast(res.message || "Gửi lệnh in thất bại.", "error"); return; }
        const okList = res.data?.successes || [];
        const failList = res.data?.fails || [];
        const ok = okList.length, fail = failList.length;

        // Loại bỏ khỏi giao diện các file đã in thành công (server đã xóa file tạm)
        if (ok) {
          const okIds = new Set(okList.map(it => it.id));
          files = files.filter(f => !okIds.has(f.id));
          if (okIds.has(selectedId)) selectedId = null;
          renderList();
        }

        const type = ok && !fail ? "ok" : (ok ? "warn" : "error");
        toast(`Đã gửi lệnh in: ${ok} thành công, ${fail} lỗi.`, type);
        if (fail) console.table(failList);
      })
      .catch(() => toast("Lỗi kết nối khi gửi lệnh in.", "error"));
  }

  function loadQueue() {
    const printer = printerSel ? printerSel.value : "";
    const wrap = qs("#queueTableWrapper");
    if (!wrap) return;
    if (!printer) {
      wrap.innerHTML = `<div class="text-center text-muted py-4">Chưa chọn máy in.</div>`;
      return;
    }
    wrap.innerHTML = `<div class="text-center text-muted py-4">Đang nạp…</div>`;
    fetch(api + "?action=list_queue&printer=" + encodeURIComponent(printer))
      .then(r => r.json())
      .then(res => {
        if (!res.success) {
          wrap.innerHTML = `<div class="text-center text-danger py-4">${res.message || "Không đọc được hàng chờ."}</div>`;
          return;
        }
        const rows = (res.data || []).map((j, idx) => `
          <tr>
            <td>${idx+1}</td>
            <td>${j.JobId}</td>
            <td>${j.Document || ""}</td>
            <td>${j.Owner || ""}</td>
            <td>${j.PagesPrinted ?? ""}/${j.TotalPages ?? ""}</td>
            <td>${j.SubmittedTime || ""}</td>
            <td>${j.JobStatus || ""}</td>
          </tr>`).join("");
        const html = `
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead class="table-light">
                <tr>
                  <th>#</th><th>JobID</th><th>Tài liệu</th><th>Người gửi</th>
                  <th>Trang</th><th>Gửi lúc</th><th>Trạng thái</th>
                </tr>
              </thead>
              <tbody>${rows || `<tr><td colspan="7" class="text-center text-muted">Hàng chờ trống.</td></tr>`}</tbody>
            </table>
          </div>`;
        wrap.innerHTML = html;
      })
      .catch(() => { wrap.innerHTML = `<div class="text-center text-danger py-4">Lỗi khi nạp hàng chờ.</div>`; });
  }

})();
// ==== Printer dropdown auto-select & remember (v1.1.1) ======================
(function () {
  const API = 'process/print_center_api.php';

  const el = {
    select: document.getElementById('printerSelect'),
    badge:  document.getElementById('defaultPrinterBadge'),
    btnReload: document.getElementById('btn-refresh-printers')
  };
  if (!el.select) return;

  // ---------- Minimal UI helpers ----------
  const setBusy = (yes) => {
    if (yes) {
      el.select.disabled = true;
      el.select.innerHTML = '<option>Đang nạp máy in…</option>';
      if (el.btnReload) {
        el.btnReload.disabled = true;
        el.btnReload.dataset._oldHtml = el.btnReload.innerHTML;
        el.btnReload.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang nạp';
      }
    } else {
      el.select.disabled = false;
      if (el.btnReload) {
        el.btnReload.disabled = false;
        if (el.btnReload.dataset._oldHtml) el.btnReload.innerHTML = el.btnReload.dataset._oldHtml;
      }
    }
  };
  const toast = (msg, type='primary') => {
    const area = document.getElementById('toastArea');
    if (!area || !window.bootstrap) { console.log(msg); return; }
    const id = 't' + Date.now();
    area.insertAdjacentHTML('beforeend', `
      <div id="${id}" class="toast text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">${msg}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    `);
    new bootstrap.Toast(document.getElementById(id), { delay: 2400 }).show();
  };
  const esc = (s) => String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  // ---------- Badge (hiện đại, rõ ràng) ----------
  function renderBadge({selected, preferred, def}) {
    if (!el.badge) return;
    if (!selected) { el.badge.textContent = ''; return; }

    let chips = [];
    chips.push(`<span class="badge rounded-pill text-bg-secondary me-1"><i class="bi bi-printer me-1"></i>${esc(selected)}</span>`);
    if (preferred && preferred === selected) chips.push(`<span class="badge rounded-pill text-bg-success me-1"><i class="bi bi-star-fill me-1"></i>Ưa thích</span>`);
    if (def && def === selected)           chips.push(`<span class="badge rounded-pill text-bg-info"><i class="bi bi-check2-circle me-1"></i>Mặc định Windows</span>`);
    if (def && preferred && selected === preferred && def !== preferred) {
      // đang chọn ưa thích, nhưng mặc định Win là máy khác → show thêm info
      chips.push(`<span class="badge rounded-pill text-bg-light text-dark ms-2"><i class="bi bi-info-circle me-1"></i>Mặc định: ${esc(def)}</span>`);
    }
    el.badge.innerHTML = chips.join('');
    el.badge.classList.add('printer-badge');
  }

  // ---------- Core load ----------
  async function load() {
    try {
      setBusy(true);
      const res = await fetch(`${API}?action=list_printers`, { cache: 'no-store', credentials: 'same-origin' });
      const j = await res.json();
      if (!j.success) throw new Error(j.message || 'Không đọc được máy in');

      const { data: printers, preferredPrinter, defaultPrinter, autoSelected } = j;

      // Build options — gắn nhãn đẹp ngay trong option
      el.select.innerHTML = (printers || []).map(p => {
        const tags = [
          p.isDefault ? 'Mặc định' : null,
          (preferredPrinter && preferredPrinter === p.name) ? 'Ưa thích' : null
        ].filter(Boolean);
        const label = p.name + (tags.length ? ` — ${tags.join(' · ')}` : '');
        return `<option value="${esc(p.name)}">${esc(label)}</option>`;
      }).join('') || '<option value="">(Không có máy in nào)</option>';

      // Chọn đúng theo server (cookie → default)
      if (printers?.length) {
        const want = autoSelected || defaultPrinter || printers[0].name;
        el.select.value = want;               // <<< GIỮ NGUYÊN khi reload
      }

      renderBadge({ selected: el.select.value || '', preferred: preferredPrinter || '', def: defaultPrinter || '' });

      // Phát sự kiện cho phần khác nếu cần
      window.dispatchEvent(new CustomEvent('printer:list-loaded', {
        detail: { printers, selected: el.select.value, preferred: preferredPrinter, defaultPrinter }
      }));
    } catch (e) {
      console.error(e);
      el.select.innerHTML = '<option value="">(Lỗi đọc máy in)</option>';
      renderBadge({ selected: '', preferred: '', def: '' });
      toast('Lỗi khi nạp danh sách máy in.', 'danger');
    } finally {
      setBusy(false);
    }
  }

  // ---------- Change = lưu ưa thích ngay ----------
  async function onChange() {
    const name = el.select.value || '';
    try {
      const r = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'set_preferred_printer', printer: name })
      });
      const j = await r.json();
      if (!j.success) throw new Error(j.message || 'Không thể lưu máy in ưa thích');
      renderBadge({ selected: name, preferred: name, def: null });
      toast('Đã lưu máy in ưa thích.', 'success');
      window.dispatchEvent(new CustomEvent('printer:changed', { detail: { printer: name } }));
    } catch (e) {
      console.error(e);
      toast('Không thể lưu máy in ưa thích.', 'danger');
    }
  }

  // ---------- Wire up ----------
  el.select.addEventListener('change', onChange);
  el.btnReload?.addEventListener('click', load);

  // ---------- Public API ----------
  window.Printers = {
    reload: load,
    getSelected: () => el.select.value || ''
  };

  // First load (sau khi DOM sẵn sàng)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();
