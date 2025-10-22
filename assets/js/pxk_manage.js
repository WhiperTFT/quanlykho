// File: assets/js/pxk_manage.js
// Yêu cầu: jQuery, Bootstrap 5, SweetAlert2 (Swal), flatpickr đã có sẵn từ header/footer

// ===== Helper =====
function parseInputNumber(s){ if(!s) return 0; return parseFloat(String(s).replace(/\./g,'').replace(/,/g,'.'))||0; }
function todayDMY(){const d=new Date();return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear();}
function dmyToYmd(d){const m=/^(\d{2})\/(\d{2})\/(\d{4})$/.exec(d||'');return m?`${m[3]}-${m[2]}-${m[1]}`:'';}
function ymdToDmy(d){const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(d||'');return m?`${m[3]}/${m[2]}/${m[1]}`:'';}
function escapeHtml(s){return (s||'').replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);}
function escapeAttr(s){return (s||'').replace(/"/g,'&quot;');}
function hasSwal(){ return (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function'); }
function swalInfo(title, text='', icon='success'){ if(hasSwal()) Swal.fire(title, text, icon); else alert(title + (text?('\\n'+text):'')); }
function swalError(title, text=''){ if(hasSwal()) Swal.fire(title, text, 'error'); else alert((title||'Lỗi') + (text?('\\n'+text):'')); }
function swalConfirm(opts){
  if(hasSwal()){
    return Swal.fire(Object.assign({
      icon:'warning', showCancelButton:true, confirmButtonText:'Xác nhận', cancelButtonText:'Hủy'
    }, opts));
  } else {
    const ok = confirm(opts && opts.title ? opts.title : 'Xác nhận?'); 
    return Promise.resolve({ isConfirmed: ok });
  }
}
function showLoadingToast(msg){
  if(!hasSwal()) { return { close: ()=>{} }; }
  Swal.fire({
    toast:true, position:'top-end', icon:'info', title: msg || 'Đang xử lý...',
    showConfirmButton:false, allowOutsideClick:false, allowEscapeKey:false,
    didOpen: () => { Swal.showLoading(); }
  });
  return { close: ()=> { try{ Swal.close(); }catch(_){}} };
}
function debounce(fn, ms) {
  let t = null;
  return function(...args){
    clearTimeout(t);
    t = setTimeout(()=>fn.apply(this, args), ms);
  };
}

// === Cấu hình in & logging ===
const PRINT_API_URL = 'process/print_api.php';
const DEFAULT_COPIES = 1;

// Khóa/mở nút trong lúc gửi lệnh
function lockButton(btn, locking = true, textWhenLock = 'Đang xử lý...') {
  if (!btn) return;
  if (locking) {
    btn.dataset._orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> ${textWhenLock}`;
  } else {
    btn.disabled = false;
    if (btn.dataset._orig) btn.innerHTML = btn.dataset._orig;
  }
}

// enqueue in theo đường dẫn web
async function enqueuePrintJobByPath(fileWebPath, copies = DEFAULT_COPIES, printer = '') {
  const resp = await fetch(`${PRINT_API_URL}?action=enqueue_path`, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ path: fileWebPath, copies, printer })
  });
  return resp.json();
}
async function fetchPrintStatus(jobId) {
  const resp = await fetch(`${PRINT_API_URL}?action=status&job_id=${encodeURIComponent(jobId)}`);
  return resp.json();
}
function pollPrintJob(jobId, onDone, onFail, timeoutMs=60000, intervalMs=2000) {
  const start = Date.now();
  const t = setInterval(async () => {
    try {
      const j = await fetchPrintStatus(jobId);
      if (j && j.success && j.job) {
        if (j.job.status === 'done')   { clearInterval(t); onDone && onDone(j.job); }
        else if (j.job.status === 'failed') { clearInterval(t); onFail && onFail(j.job); }
      }
    } catch(_) {}
    if (Date.now() - start > timeoutMs) {
      clearInterval(t);
      onFail && onFail({ status:'timeout', error_message:'Hết thời gian chờ' });
    }
  }, intervalMs);
}

// === Gửi log thao tác ===
async function sendUserLog(action, description='', level='info') {
  try {
    await fetch('process/log_api.php?action=log', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action, description, level })
    });
  } catch(_) {}
}

// --- STATE dùng chung cho list ---
const state = {
  kw: '',        // từ khóa lọc
  page: 1,       // trang hiện tại
  per_page: 10,  // số dòng mỗi trang (10/25/50/100/0=All)
  total: 0       // tổng bản ghi từ API trả về
};


// ====== INIT ======
document.addEventListener('DOMContentLoaded', () => {
  // Lấy các control trong toolbar
  const kwInput     = document.getElementById('filter-keyword');   // ô tìm kiếm
  const clearBtn    = document.getElementById('btn-clear-search'); // nút X xoá nhanh
  const searchWrap  = document.querySelector('.search-group');     // div bọc search (để show/hide nút X)
  const pageSizeSel = document.getElementById('pageSizeSelect');   // select "Show entries"
  const totalEl     = document.getElementById('totalCount');       // badge tổng
   window.state = window.state || {};
  if (typeof state.kw === 'undefined') state.kw = '';
  if (typeof state.page === 'undefined') state.page = 1;
  if (typeof state.per_page === 'undefined') state.per_page = 10;
  if (typeof state.total === 'undefined') state.total = 0;

  // Hiển thị/ẩn nút X theo giá trị ô tìm kiếm
  function refreshSearchUI() {
    if (!kwInput || !searchWrap) return;
    const v = (kwInput.value || '').trim();
    searchWrap.classList.toggle('has-value', v.length > 0);
  }

  // Debounce tiện dụng (gõ 1 lúc mới bắn load)
  function debounce(fn, ms=250) {
    let t = null;
    return function(...args){ clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), ms); };
  }
  const debouncedLoad = debounce(()=>loadList(), 250);

  // Khởi tạo giao diện ban đầu
  refreshSearchUI();
  if (pageSizeSel) {
    pageSizeSel.value = String(state.per_page);
  }
  if (totalEl) {
    totalEl.textContent = String(state.total || 0);
  }

  // Sự kiện gõ trong ô tìm kiếm
  if (kwInput) {
    kwInput.addEventListener('input', () => {
      state.kw = (kwInput.value || '').trim();
      state.page = 1;
      refreshSearchUI();
      if (state.kw === '') loadList(); else debouncedLoad();
    });
    kwInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        state.kw = (kwInput.value || '').trim();
        state.page = 1;
        loadList();
      }
    });
  }

  // Nút X (xoá nhanh tìm kiếm)
  if (clearBtn && kwInput) {
    clearBtn.addEventListener('click', () => {
      kwInput.value = '';
      state.kw = '';
      state.page = 1;
      refreshSearchUI();
      loadList();
      kwInput.focus();
    });
  }

  // Đổi "Show entries"
  if (pageSizeSel) {
    pageSizeSel.addEventListener('change', () => {
      const v = parseInt(pageSizeSel.value, 10);
      state.per_page = isNaN(v) ? 10 : v;
      state.page = 1;
      loadList();
    });
  }

  // Datepicker
  const dp = document.getElementById('pxk_date_display');
  if (window.flatpickr) { flatpickr(dp, {dateFormat:'d/m/Y', defaultDate:new Date()}); }
  else { dp.value = todayDMY(); }
  document.getElementById('pxk_date').value = dmyToYmd(dp.value);
  dp.addEventListener('change', ()=> document.getElementById('pxk_date').value = dmyToYmd(dp.value));

  // Buttons
  document.getElementById('btn-new').addEventListener('click', showFormNew);
  document.getElementById('btn-cancel').addEventListener('click', hideForm);
  document.getElementById('btn-reload').addEventListener('click', ()=> { state.page=1; loadList(); });
  document.getElementById('btn-add-item').addEventListener('click', addItemRow);
  document.getElementById('btn-save').addEventListener('click', async ()=>{
    const ok = await savePXK(false);
    if (ok) swalInfo('Đã lưu PXK!', '', 'success');
  });
  document.getElementById('btn-save-export').addEventListener('click', async ()=>{
    const toast = showLoadingToast('Đang lưu và xuất PDF, vui lòng chờ...');
    const ok = await savePXK(true);
    toast.close();
    if (ok) swalInfo('Đã lưu và xuất PDF!', '', 'success');
  });
  document.getElementById('btn-generate-number').addEventListener('click', async ()=>{
    const done = await generateNumber();
    if (done) swalInfo('Đã tạo số PXK tự động!', '', 'success');
  });

  // Filter + Show entries
  const $kw = document.getElementById('filter-keyword');
  const debounced = debounce(()=>{
    state.kw = ($kw.value||'').trim();
    state.page = 1;
    loadList();
  }, 250);
  $kw.addEventListener('input', ()=>{
    if (($kw.value||'').trim()==='') { state.kw=''; state.page=1; loadList(); }
    else debounced();
  });
  $kw.addEventListener('keydown', (e)=>{
    if (e.key==='Enter'){ e.preventDefault(); state.kw = ($kw.value||'').trim(); state.page=1; loadList(); }
  });

  const sel = document.getElementById('pageSizeSelect');
  sel.value = String(state.per_page);
  sel.addEventListener('change', ()=>{
    state.per_page = parseInt(sel.value,10);
    state.page = 1;
    loadList();
  });

  // Nút chẩn đoán in
  document.getElementById('btn-print-diag')?.addEventListener('click', onPrintDiagClick);

  // Delegation nút in
  document.addEventListener('click', onPrintButtonClick);

  // Autocomplete ĐỐI TÁC (4 trường)
  setupPartnerAutocomplete();

  // Autocomplete SẢN PHẨM (jQuery)
  setupProductAutocomplete();

  // Load danh sách đầu tiên
  loadList();
});
// ====== Print enqueue helpers (frontend) ======

/**
 * Gửi lệnh in 1 file PDF theo đường dẫn web (ví dụ: "pdf/pxk/PXK2808202501.pdf")
 * Ưu tiên API mới: process/pxk_api.php?action=enqueue_print
 * Trả về: { success, job_id, spawned, message }
 */
async function enqueuePrintJobByPath(fileWebPath, copies = DEFAULT_COPIES, printerName = '') {
  if (!fileWebPath) {
    return { success: false, message: 'Thiếu fileWebPath' };
  }

  // --- API mới (khuyên dùng) ---
  try {
    const res = await fetch('process/pxk_api.php?action=enqueue_print', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        pdf_web_path: fileWebPath,
        copies: copies,
        printer_name: printerName
      })
    }).then(r => r.json());

    if (res && res.success) {
      // res.spawned === true: đã đá worker thành công.
      // res.spawned === false: đã tạo job nhưng chưa spawn được (xem logs/print_spawn.log)
      return res;
    }

    // Nếu API mới trả lỗi, ta sẽ thử API cũ (fallback) ở dưới.
    console.warn('enqueue_print via pxk_api FAILED, fallback to print_api...', res);
  } catch (e) {
    console.warn('enqueue_print via pxk_api exception, fallback to print_api...', e);
  }

  // --- Fallback API cũ (nếu bạn còn process/print_api.php hỗ trợ theo path) ---
  try {
    const res2 = await fetch('process/print_api.php?action=enqueue_by_path', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        file_web_path: fileWebPath,
        copies: copies,
        printer_name: printerName
      })
    }).then(r => r.json());

    return res2 || { success: false, message: 'enqueue_by_path (legacy) không phản hồi.' };
  } catch (e2) {
    return { success: false, message: 'Không gọi được enqueue API (mới & fallback).' };
  }
}

// ====== Print diag / print button ======
async function onPrintDiagClick(){
  try{
    const j = await fetch('process/print_api.php?action=diag').then(r=>r.json());
    if(!j || !j.success){ swalError('Không đọc được chẩn đoán'); return; }
    const hb = j.heartbeat;
    const stats = j.stats || {};
    const latest = (j.latest || []).map(x=>
      `#${x.id} [${x.status}] ${x.file_web_path}`
      + (x.error_message?`\n  Lý do: ${x.error_message}`:'')
      + (x.finished_at?`\n  Kết thúc: ${x.finished_at}`:'')
    ).join('\n');

    const msg = [
      hb && hb.last_seen
        ? `Watcher: OK (lần cuối hoạt động: ${hb.last_seen}, máy: ${hb.host||'?'})`
        : 'Watcher: KHÔNG HOẠT ĐỘNG hoặc chưa từng chạy.',
      `Hàng đợi 2 ngày gần nhất: pending=${stats.pending||0}, printing=${stats.printing||0}, done=${stats.done||0}, failed=${stats.failed||0}`,
      latest ? `Các lệnh gần nhất:\n${latest}` : 'Chưa có lệnh in gần đây.'
    ].join('\n\n');

    swalInfo('Chẩn đoán in', msg, 'info');
    await sendUserLog('print_diag_view', 'Người dùng xem chẩn đoán in', 'info');
  }catch(_){
    swalError('Không đọc được chẩn đoán');
  }
}

async function onPrintButtonClick(ev){
  const btn = ev.target.closest('.btn-print-server');
  if (!btn) return;

  if (btn.dataset.busy === '1') return;
  btn.dataset.busy = '1';

  const fileWebPath = btn.getAttribute('data-path') || '';
  if (!fileWebPath) {
    swalError('Thiếu đường dẫn PDF để in');
    btn.dataset.busy = '0';
    return;
  }

  try {
    lockButton(btn, true, 'Đang gửi lệnh in...');
    const r = await enqueuePrintJobByPath(fileWebPath, DEFAULT_COPIES);

    if (!r || !r.success) {
      lockButton(btn, false);
      swalError('Không gửi được lệnh in', r && r.message ? r.message : '');
      return;
    }

    const jobId = r.job_id;
    swalInfo('Đã gửi lệnh in', `Mã lệnh: #${jobId}\nTệp: ${fileWebPath}\nĐang chờ máy in xử lý...`, 'success');
    await sendUserLog('pxk_print_enqueue', `Đã gửi lệnh in #${jobId}. Tệp: ${fileWebPath}`, 'info');

    // Theo dõi kết quả (90s)
    pollPrintJob(
      jobId,
      async (job) => {
        lockButton(btn, false);
        swalInfo(
          'In thành công',
          `Tệp: ${fileWebPath}\nTrạng thái: Hoàn tất${job.finished_at ? `\nKết thúc: ${job.finished_at}` : ''}`,
          'success'
        );
        await sendUserLog('pxk_print_done', `In thành công #${jobId}. Tệp: ${fileWebPath}`, 'info');
      },
      async (job) => {
        lockButton(btn, false);

        if (job && job.status === 'timeout') {
          let hint = 'Không nhận được tín hiệu từ watcher. Có thể watcher chưa chạy trên máy in.';
          try {
            const dg = await fetch('process/print_api.php?action=diag').then(r=>r.json());
            if (dg && dg.success && dg.heartbeat && dg.heartbeat.last_seen) {
              hint = `Watcher lần cuối hoạt động: ${dg.heartbeat.last_seen} (máy: ${dg.heartbeat.host||'?'})`;
            }
          } catch(_) {}

          const human = `Hết thời gian chờ.\nTệp: ${fileWebPath}\nGợi ý: ${hint}\n- Kiểm tra watch_print_queue.php có đang chạy?\n- Kiểm tra đường dẫn PDF còn tồn tại?`;
          swalError('Không in được', human);
          await sendUserLog('pxk_print_timeout', human, 'warn');
        } else {
          const em = job && job.error_message ? job.error_message : 'Không rõ nguyên nhân.';
          swalError('In thất bại', `Tệp: ${fileWebPath}\nLý do: ${em}`);
          await sendUserLog('pxk_print_failed', `In thất bại #${jobId}. Tệp: ${fileWebPath}. Lý do: ${em}`, 'error');
        }
      },
      90000,
      2000
    );

  } catch (e) {
    lockButton(btn, false);
    const em = e && e.message ? e.message : 'Lỗi không xác định.';
    swalError('Lỗi gửi lệnh in', `Tệp: ${fileWebPath}\nLý do: ${em}`);
    await sendUserLog('pxk_print_enqueue_error', `Lỗi gửi lệnh in. Tệp: ${fileWebPath}. Lý do: ${em}`, 'error');
  } finally {
    btn.dataset.busy = '0';
  }
}

// ====== Build PDF Href ======
function buildPdfHref(relPath) {
  try { return new URL(relPath, document.baseURI).href; } catch(e) { return relPath; }
}

// ====== LIST + FILTER + PAGINATION ======
async function loadList() {
  try {
    const params = new URLSearchParams();
    params.set('action', 'list');
    params.set('kw', state.kw || '');                // luôn gửi kw
    params.set('page', String(state.page || 1));
    params.set('per_page', String(state.per_page || 10));

    const resp = await fetch('process/pxk_api.php?' + params.toString());
    const data = await resp.json();

    // Cập nhật state.total + badge tổng
    state.total = parseInt(data?.total ?? 0, 10) || 0;
    const totalEl = document.getElementById('totalCount');
    if (totalEl) totalEl.textContent = String(state.total);

    // --- render bảng ---
    const tb = document.getElementById('pxkTableBody');
    tb.innerHTML = '';

    if (data && data.success && Array.isArray(data.rows)) {
      for (const row of data.rows) {
        const pdfBtns = row.pdf_web_path
          ? `<div class="d-flex flex-wrap gap-1">
               <a href="${escapeAttr(buildPdfHref(row.pdf_web_path))}" target="_blank"
                  class="btn btn-sm btn-outline-secondary">
                 <i class="bi bi-file-earmark-pdf"></i> Mở PDF
               </a>
               <button type="button" class="btn btn-sm btn-primary btn-print-server"
                       data-path="${escapeAttr(row.pdf_web_path)}">
                 <i class="bi bi-printer"></i> Gửi lệnh In
               </button>
             </div>`
          : '<span class="text-muted">Chưa có</span>';

        tb.insertAdjacentHTML('beforeend', `
          <tr data-id="${row.id}">
            <td>${row.id}</td>
            <td>${escapeHtml(row.pxk_number||'')}</td>
            <td>${escapeHtml(row.pxk_date_display||'')}</td>
            <td>${escapeHtml(row.partner_name||'-')}</td>
            <td>${pdfBtns}</td>
            <td class="text-center">
              <button class="btn btn-sm btn-outline-primary btn-edit">Sửa</button>
              <button class="btn btn-sm btn-outline-danger btn-del">Xóa</button>
            </td>
          </tr>
        `);
      }
      tb.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', e => {
    const id = e.target.closest('tr').dataset.id;
    editPXK(id);
    window.PXK && PXK.waitAndScroll();
  });
});
      tb.querySelectorAll('.btn-del').forEach(btn=>{
        btn.addEventListener('click', async e=>{
          const id = e.target.closest('tr').dataset.id;
          const rs = await swalConfirm({
            title:'Bạn có chắc muốn xóa PXK này?',
            text:'Dữ liệu sẽ không thể phục hồi!',
            confirmButtonText:'Xóa',
            cancelButtonText:'Hủy'
          });
          if (!rs.isConfirmed) return;
          const r = await fetch('process/pxk_api.php?action=delete', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({id})
          });
          const j = await r.json();
          if (j && j.success) { loadList(); swalInfo('Đã xóa PXK!', '', 'success'); }
          else swalError('Xóa thất bại', j && j.message ? j.message : '');
        });
      });
    }
  } catch (err) {
    console.error('loadList error', err);
  }
}
function setBtnLoading($btn, isLoading, loadingText) {
  if (isLoading) {
    if ($btn.data('loading') === '1') return; // đã loading
    $btn.data('loading', '1');
    $btn.data('orig-html', $btn.html());
    // Nếu dùng Bootstrap 4/5 có spinner-border:
    $btn.prop('disabled', true).html(
      '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
      (loadingText || 'Đang tải...')
    );
  } else {
    $btn.prop('disabled', false);
    const html = $btn.data('orig-html');
    if (html != null) $btn.html(html);
    $btn.data('loading', '0');
  }
}

$(function(){
  // Chọn máy in
  $('#btnSelectPrinter').on('click', async function(){
  const $btn = $(this);
  setBtnLoading($btn, true, 'Đang tải máy in...');
  try{
    const data = await fetch('process/pxk_api.php?action=printers_list').then(r=>r.json());
    if(!data || !data.success) { Swal.fire('Lỗi', 'Không đọc được danh sách máy in', 'error'); return; }
    const list = data.printers || [];
    const appDef = data.app_default || '';
    const winDef = data.windows_default || '';

    let html = '<div class="mb-2 small text-muted">Máy in mặc định của Windows: <b>'
               + (winDef || '(không xác định)') + '</b></div>';
    html += '<select id="printerSelect" class="form-select">';
    html += '<option value="">(Dùng Windows Default)</option>';
    for (const p of list) {
      const sel = (p.name === appDef) ? ' selected' : '';
      html += `<option value="${p.name.replaceAll('"','&quot;')}"${sel}>${p.name} ${p.default?'(Windows Default)':''}</option>`;
    }
    html += '</select>';

    const sel = await Swal.fire({
      title: 'Chọn máy in mặc định (Ứng dụng)',
      html: html,
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: 'Lưu',
      cancelButtonText: 'Hủy',
      didOpen: () => { document.getElementById('printerSelect')?.focus(); }
    });

    if (!sel.isConfirmed) return;
    const name = document.getElementById('printerSelect').value || '';

    const saveRes = await fetch('process/pxk_api.php?action=printer_save', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ name })
    }).then(r=>r.json());

    if (saveRes && saveRes.success) {
      Swal.fire('Đã lưu', `Máy in mặc định (app): ${name || '(Windows Default)'}`, 'success');
    } else {
      Swal.fire('Lỗi', saveRes && saveRes.message ? saveRes.message : 'Không lưu được.', 'error');
    }
  } catch(e){
    console.error(e);
    Swal.fire('Lỗi', 'Không đọc/ghi được cấu hình máy in.', 'error');
  } finally {
    setBtnLoading($btn, false);
  }
});


  // Dọn hàng đợi
  $('#btnPrintCleanup').on('click', async function(){
    const q = await Swal.fire({
      title: 'Dọn hàng đợi in',
      html: `
        <div class="mb-2">Đánh <b>failed</b> các job kẹt:</div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">printing quá (phút)</label>
            <input id="pc_minutes" type="number" class="form-control" value="10" min="1">
          </div>
          <div class="col-6">
            <label class="form-label">pending quá (phút)</label>
            <input id="pd_minutes" type="number" class="form-control" value="120" min="1">
          </div>
        </div>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Thực hiện',
      cancelButtonText: 'Hủy'
    });
    if (!q.isConfirmed) return;

    const older = parseInt(document.getElementById('pc_minutes').value || '10', 10);
    const pold  = parseInt(document.getElementById('pd_minutes').value || '120', 10);

    try{
      const res = await fetch('process/pxk_api.php?action=print_cleanup', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ older_minutes: older, pending_older_minutes: pold })
      }).then(r=>r.json());
      if (res && res.success) {
        Swal.fire('Đã dọn', `printing → failed: ${res.printing_failed || 0}<br>pending → failed: ${res.pending_failed || 0}`, 'success');
      } else {
        Swal.fire('Lỗi', res && res.message ? res.message : 'Không dọn được.', 'error');
      }
    }catch(e){
      console.error(e);
      Swal.fire('Lỗi', 'Không gọi được API dọn hàng đợi.', 'error');
    }
  });
});


function renderPagingInfo(){
  const info = document.getElementById('pagingInfo');
  const total = state.total || 0;
  const pp = parseInt(state.per_page,10);
  if (total===0){ info.textContent = 'Hiển thị 0–0 trong tổng 0 bản ghi'; return; }
  if (pp===0){ info.textContent = `Hiển thị 1–${total} trong tổng ${total} bản ghi`; return; }

  const start = ((state.page-1)*pp) + 1;
  const end = Math.min(state.page*pp, total);
  info.textContent = `Hiển thị ${start}–${end} trong tổng ${total} bản ghi`;
}

function renderPaging(){
  const ul = document.getElementById('pager');
  ul.innerHTML = '';

  const total = state.total || 0;
  const pp = parseInt(state.per_page,10);
  if (pp===0 || total<=pp){ return; } // không cần phân trang

  const totalPages = Math.max(1, Math.ceil(total/pp));
  const cur = Math.min(Math.max(1, state.page), totalPages);

  function li(label, page, disabled=false, active=false){
    const li = document.createElement('li');
    li.className = 'page-item' + (disabled?' disabled':'') + (active?' active':'');
    const a = document.createElement('a');
    a.className = 'page-link';
    a.href = '#';
    a.textContent = label;
    a.addEventListener('click', (e)=> {
      e.preventDefault();
      if (disabled || active) return;
      state.page = page;
      loadList();
    });
    li.appendChild(a);
    return li;
  }

  ul.appendChild(li('« First', 1, cur===1));
  ul.appendChild(li('‹ Prev', Math.max(1, cur-1), cur===1));

  // range pages
  const windowSize = 5;
  let start = Math.max(1, cur - Math.floor(windowSize/2));
  let end = Math.min(totalPages, start + windowSize - 1);
  if (end - start + 1 < windowSize) {
    start = Math.max(1, end - windowSize + 1);
  }

  for (let p = start; p <= end; p++){
    ul.appendChild(li(String(p), p, false, p===cur));
  }

  ul.appendChild(li('Next ›', Math.min(totalPages, cur+1), cur===totalPages));
  ul.appendChild(li('Last »', totalPages, cur===totalPages));
}

async function onDeleteRow(e){
  const id = e.target.closest('tr').dataset.id;
  const rs = await swalConfirm({
    title:'Bạn có chắc muốn xóa PXK này?',
    text:'Dữ liệu sẽ không thể phục hồi!',
    confirmButtonText:'Xóa',
    cancelButtonText:'Hủy'
  });
  if (!rs.isConfirmed) return;
  try {
    const r = await fetch('process/pxk_api.php?action=delete', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({id})
    });
    const j = await r.json();
    if (j && j.success) { loadList(); swalInfo('Đã xóa PXK!', '', 'success'); }
    else swalError('Xóa thất bại', j && j.message ? j.message : '');
  } catch (err) {
    swalError('Xóa thất bại', err && err.message ? err.message : '');
  }
}

// ====== FORM ======
function showFormNew() {
  editingId = null;
  document.getElementById('formTitle').textContent = 'Thêm Phiếu Xuất Kho';
  document.getElementById('pxkForm').reset();
  const dp = document.getElementById('pxk_date_display');
  dp.value = todayDMY();
  document.getElementById('pxk_date').value = dmyToYmd(dp.value);
  document.getElementById('itemsBody').innerHTML = '';
  addItemRow();
  document.getElementById('pxkFormCard').style.display = '';
}
function hideForm(){ document.getElementById('pxkFormCard').style.display='none'; }

function itemRowTemplate(index) {
  return `
  <tr>
    <td class="text-center stt">${index}</td>
    <td>
      <input type="text" class="form-control form-control-sm category-display bg-light" readonly tabindex="-1">
      <input type="hidden" class="category-snapshot">
    </td>
    <td>
      <input type="text" class="form-control form-control-sm product-autocomplete" placeholder="Nhập tên sản phẩm..." required autocomplete="off">
      <input type="hidden" class="product-id">
      <div class="invalid-feedback"></div>
    </td>
    <td>
      <input type="text" class="form-control form-control-sm unit-display bg-light text-center" readonly tabindex="-1">
      <input type="hidden" class="unit-snapshot">
    </td>
    <td>
      <input type="text" class="form-control form-control-sm text-end quantity" placeholder="0" value="0" inputmode="decimal">
      <div class="invalid-feedback"></div>
    </td>
    <td>
      <input type="text" class="form-control form-control-sm item-note" placeholder="Ghi chú...">
    </td>
    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash"></i></button></td>
  </tr>`;
}
function renumberRows(){ [...document.querySelectorAll('#itemsBody tr')].forEach((tr,i)=> tr.querySelector('.stt').textContent=i+1 ); }
function addItemRow() {
  const body = document.getElementById('itemsBody');
  body.insertAdjacentHTML('beforeend', itemRowTemplate(body.children.length+1));
  const tr = body.lastElementChild;
  tr.querySelector('.btn-remove').addEventListener('click', ()=>{ tr.remove(); renumberRows(); });
  tr.querySelector('.quantity').addEventListener('input', e=>{
    e.target.value = e.target.value.replace(/[^0-9.,-]/g, '');
  });
}

async function editPXK(id) {
  try {
    const r = await fetch('process/pxk_api.php?action=get&id='+encodeURIComponent(id));
    const j = await r.json();
    if (!j || !j.success || !j.row) { swalError('Không tải được PXK', j && j.message ? j.message : ''); return; }
    const row = j.row;
    editingId = row.id;
    document.getElementById('formTitle').textContent = 'Sửa Phiếu Xuất Kho #' + row.id;
    document.getElementById('pxkFormCard').style.display = '';

    document.getElementById('pxk_id').value = row.id;
    document.getElementById('pxk_number').value = row.pxk_number || '';
    document.getElementById('pxk_date_display').value = row.pxk_date_display || todayDMY();
    document.getElementById('pxk_date').value = row.pxk_date || '';
    document.getElementById('notes').value = row.notes || '';
    document.getElementById('partner_name').value = row.partner_name || '';
    document.getElementById('partner_address').value = row.partner_address || '';
    document.getElementById('partner_contact_person').value = (row.partner_contact_person || row.contact_person || '');
    document.getElementById('partner_phone').value = (row.partner_phone || row.phone || '');

    const body = document.getElementById('itemsBody');
    body.innerHTML = '';
    const items = Array.isArray(row.items) ? row.items : [];
    if (items.length === 0) addItemRow();
    items.forEach(it=>{
      addItemRow();
      const tr = body.lastElementChild;
      tr.querySelector('.product-id').value = it.product_id || '';
      tr.querySelector('.product-autocomplete').value = it.product_name || '';
      tr.querySelector('.category-display').value = it.category || '';
      tr.querySelector('.category-snapshot').value = it.category || '';
      tr.querySelector('.unit-display').value = it.unit || '';
      tr.querySelector('.unit-snapshot').value = it.unit || '';
      tr.querySelector('.quantity').value = it.quantity ?? '0';
      tr.querySelector('.item-note').value = it.note || '';
    });
  } catch (err) {
    swalError('Lỗi tải PXK', err && err.message ? err.message : '');
  }
}

function collectFormData() {
  const id = document.getElementById('pxk_id').value || null;
  const pxk_number = document.getElementById('pxk_number').value.trim();
  const pxk_date_display = document.getElementById('pxk_date_display').value.trim();
  const pxk_date = dmyToYmd(pxk_date_display);
  const notes = document.getElementById('notes').value.trim();
  const partner_name = document.getElementById('partner_name').value.trim();
  const partner_address = document.getElementById('partner_address').value.trim();
  const partner_contact_person = document.getElementById('partner_contact_person').value.trim();
  const partner_phone = document.getElementById('partner_phone').value.trim();

  const items = [];
  document.querySelectorAll('#itemsBody tr').forEach(tr=>{
    const product_id = tr.querySelector('.product-id').value.trim();
    const product_name = tr.querySelector('.product-autocomplete').value.trim();
    const category = tr.querySelector('.category-snapshot').value || tr.querySelector('.category-display').value;
    const unit = tr.querySelector('.unit-snapshot').value || tr.querySelector('.unit-display').value;
    const quantity = tr.querySelector('.quantity').value.trim();
    const note = tr.querySelector('.item-note').value.trim();
    if (product_name) items.push({product_id, product_name, category, unit, quantity, note});
  });
  return { id, pxk_number, pxk_date, notes, partner_name, partner_address, partner_contact_person, partner_phone, items };
}

async function savePXK(exportPdf=false) {
  const payload = collectFormData();
  if (!payload.pxk_number) { swalError('Thiếu thông tin', 'Vui lòng nhập Số PXK'); return false; }
  if (!payload.pxk_date) { swalError('Thiếu thông tin', 'Vui lòng chọn Ngày xuất'); return false; }
  if (!payload.partner_name) { swalError('Thiếu thông tin', 'Vui lòng nhập Tên đơn vị nhận'); return false; }
  if (!payload.items || payload.items.length===0) { swalError('Thiếu hàng hóa', 'Vui lòng nhập ít nhất 1 dòng hàng'); return false; }

  try {
    const r = await fetch('process/pxk_api.php?action=save', {
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
    });
    const j = await r.json();
    if (!j || !j.success) { swalError('Lưu PXK thất bại', (j && j.message) ? j.message : ''); return false; }

    if (exportPdf) {
      try {
        const r2 = await fetch('process/pxk_api.php?action=export_pdf', {
          method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ id: j.id })
        });
        const j2 = await r2.json();
        if (j2 && j2.success && j2.pdf_web_path) {
          window.open(buildPdfHref(j2.pdf_web_path), '_blank');
        } else {
          swalError('Xuất PDF thất bại', (j2 && j2.message) ? j2.message : '');
        }
      } catch (err2) {
        swalError('Xuất PDF thất bại', err2 && err2.message ? err2.message : '');
      }
    }

    hideForm();
    loadList();
    return true;
  } catch (err) {
    swalError('Lỗi lưu PXK', err && err.message ? err.message : '');
    return false;
  }
}

async function generateNumber() {
  const btn = document.getElementById('btn-generate-number');
  btn.disabled = true;
  const original = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang tạo...';
  try {
    const r = await fetch('process/pxk_api.php?action=generate_number', { method:'POST' });
    const text = await r.text();
    let j=null; 
    try { j = JSON.parse(text); } catch(e) { console.error('parse JSON failed', e, text); }
    if (!j || !j.success) throw new Error((j && j.message) || 'Không tạo được số PXK.');
    document.getElementById('pxk_number').value = j.pxk_number || '';
    return true;
  } catch (e) {
    swalError('Không tạo được số PXK', e && e.message ? e.message : '');
    console.error('generate_number failed', e);
    return false;
  } finally {
    btn.disabled = false;
    btn.innerHTML = original;
  }
}

// ====== Autocomplete ĐỐI TÁC 4 trường ======
function setupPartnerAutocomplete(){
  const fldName    = document.getElementById('partner_name');
  const fldAddr    = document.getElementById('partner_address');
  const fldContact = document.getElementById('partner_contact_person');
  const fldPhone   = document.getElementById('partner_phone');

  function ensureBox(inputEl) {
    let box = inputEl.nextElementSibling;
    if (!box || !box.classList.contains('ac-box')) {
      box = document.createElement('div');
      box.className = 'ac-box';
      inputEl.parentElement.style.position = 'relative';
      inputEl.insertAdjacentElement('afterend', box);
    }
    return box;
  }
  function renderList(box, list) {
    if (!Array.isArray(list) || list.length === 0) { box.style.display = 'none'; box.innerHTML = ''; return; }
    box.innerHTML = list.slice(0, 20).map(p => {
      const name    = escapeHtml(p.name || '');
      const address = escapeHtml(p.address || '');
      const contact = escapeHtml(p.contact_person || '');
      const phone   = escapeHtml(p.phone || '');
      const meta = [contact, phone, address].filter(Boolean).join(' — ');
      return `
        <div class="ac-item"
             data-id="${p.id||''}"
             data-name="${name}"
             data-address="${address}"
             data-contact="${contact}"
             data-phone="${phone}">
          ${name}${meta ? ` <small class="text-muted">— ${meta}</small>` : ''}
        </div>
      `;
    }).join('');
    box.style.minWidth = box.previousElementSibling.offsetWidth + 'px';
    box.style.display = 'block';
  }
  async function queryPartners(keyword) {
    if (!keyword) return [];
    try {
      const r = await fetch('process/pxk_api.php?action=partner_search&kw=' + encodeURIComponent(keyword));
      const j = await r.json();
      if (j && j.success && Array.isArray(j.rows)) return j.rows;
    } catch (e) { console.error('partner_search failed', e); }
    return [];
  }
  function pickPartnerFromItem(itemEl) {
    const name    = itemEl.dataset.name    || '';
    const address = itemEl.dataset.address || '';
    const contact = itemEl.dataset.contact || '';
    const phone   = itemEl.dataset.phone   || '';
    fldName.value    = name;
    fldAddr.value    = address;
    fldContact.value = contact;
    fldPhone.value   = phone;
    [fldName, fldAddr, fldContact, fldPhone].forEach(hideBox);
  }
  function hideBox(inputEl) {
    const b = inputEl && inputEl.nextElementSibling;
    if (b && b.classList.contains('ac-box')) { b.style.display = 'none'; }
  }
  function attachPickHandler(box) {
    if (box._boundPick) return;
    box._boundPick = true;
    box.addEventListener('mousedown', (e) => {
      const it = e.target.closest('.ac-item');
      if (!it) return;
      pickPartnerFromItem(it);
    });
  }
  function installPartnerAC(inputEl) {
    const box = ensureBox(inputEl);
    attachPickHandler(box);
    let timer = null;
    function doSuggest() {
      const kw = (inputEl.value || '').trim();
      if (!kw) { hideBox(inputEl); return; }
      queryPartners(kw).then(list => renderList(box, list));
    }
    inputEl.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(doSuggest, 200); });
    inputEl.addEventListener('focus', () => { clearTimeout(timer); timer = setTimeout(doSuggest, 0); });
    inputEl.addEventListener('blur', () => { setTimeout(() => hideBox(inputEl), 150); });
    inputEl.addEventListener('keydown', async (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const kw = (inputEl.value || '').trim();
        if (!kw) return;
        const list = await queryPartners(kw);
        if (Array.isArray(list) && list.length > 0) {
          const p = list[0];
          fldName.value    = p.name || kw;
          fldAddr.value    = p.address || '';
          fldContact.value = p.contact_person || '';
          fldPhone.value   = p.phone || '';
          hideBox(inputEl);
        }
      }
    });
  }
  [fldName, fldAddr, fldContact, fldPhone].forEach(installPartnerAC);
}

// ====== Autocomplete SẢN PHẨM (dùng jQuery) ======
function setupProductAutocomplete(){
  (function($) {
    let acTimer = null;

    $(document).on('input focus', '.product-autocomplete', function() {
      const $inp = $(this);
      clearTimeout(acTimer);
      acTimer = setTimeout(()=> runSuggest($inp), 150);
    });

    function ensureBox($inp){
      let $box = $inp.siblings('.ac-box');
      if ($box.length === 0) {
        $inp.parent().css('position','relative');
        $box = $('<div class="ac-box"></div>').css({
          position:'absolute', zIndex:9999, background:'#fff', border:'1px solid #ccc',
          minWidth: $inp.outerWidth(), maxHeight:'240px', overflowY:'auto'
        }).insertAfter($inp);
      }
      return $box;
    }

    function runSuggest($inp){
      const term = ($inp.val() || '').trim();
      const $box = ensureBox($inp);
      if (!term) { $box.hide(); return; }

      $.getJSON('process/pxk_api.php', { action:'product_suggest', term })
        .done(function(list){
          if (!Array.isArray(list) || list.length === 0) { $box.hide(); return; }
          const html = list.map(function(it){
            const name = it.name || '';
            const unit = it.unit_name ? ' ['+it.unit_name+']' : '';
            const cat  = it.category_name ? ' — '+it.category_name : '';
            return '<div class="ac-item" data-id="'+(it.id||'')+'" data-name="'+(it.name||'')+'" data-unit="'+(it.unit_name||'')+'" data-cat="'+(it.category_name||'')+'" style="padding:6px 8px; cursor:pointer;">'
                   + name + cat + unit + '</div>';
          }).join('');
          $box.html(html).show();
        })
        .fail(function(){ $box.hide(); });
    }

    // mousedown để nhận trước blur
    $(document).on('mousedown', '.ac-box .ac-item', function(){
      const $it  = $(this);
      const $box = $it.closest('.ac-box');
      const $inp = $box.siblings('.product-autocomplete');
      const $row = $inp.closest('tr');

      const name = $it.data('name') || '';
      const unit = $it.data('unit') || '';
      const cat  = $it.data('cat')  || '';

      $inp.val(name);
      $row.find('.unit-display').val(unit);
      $row.find('.unit-snapshot').val(unit);
      $row.find('.category-display').val(cat);
      $row.find('.category-snapshot').val(cat);
      $row.find('.product-id').val($it.data('id') || '');

      $box.hide();
    });

    $(document).on('blur', '.product-autocomplete', function(){
      const $box = $(this).siblings('.ac-box');
      setTimeout(()=> $box.hide(), 150);
    });

  })(jQuery);
}
