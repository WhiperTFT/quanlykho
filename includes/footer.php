<?php
// File: includes/footer.php
?>
</main> 
<footer class="footer mt-auto py-3 bg-light"> 
    <div class="container text-center">
        <span class="text-muted">© <?= date('Y') ?> <?= htmlspecialchars($lang['appName'] ?? 'Inventory App', ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</span>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="<?= PROJECT_BASE_URL ?>assets/js/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
<script src="assets/js/upload-enhance.js"></script>

<!-- Custom navbar script -->
<script src="<?= PROJECT_BASE_URL ?>assets/js/navbar.js?v=<?= filemtime(__DIR__ . '/../assets/js/navbar.js') ?>"></script>
<script src="assets/js/number_helpers.js"></script>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<script>window.PROJECT_BASE_URL = "<?= rtrim(PROJECT_BASE_URL, '/').'/'; ?>";</script>
<script src="<?= PROJECT_BASE_URL ?>assets/js/auth_guard.js"></script>
<script>
// 3.1 Gắn endpoint nếu thiếu
document.addEventListener('DOMContentLoaded', function () {
  try {
    if (typeof AJAX_URL === 'object' && AJAX_URL !== null) {
      if (!('product_history' in AJAX_URL)) {
        AJAX_URL.product_history = PROJECT_BASE_URL + 'process/product_history_api.php';
        console.log('[boot] attached AJAX_URL.product_history =', AJAX_URL.product_history);
      }
    } else {
      window.AJAX_URL = { product_history: PROJECT_BASE_URL + 'process/product_history_api.php' };
      console.warn('[boot] created AJAX_URL with product_history');
    }
  } catch (e) { console.error('[boot] patch AJAX_URL failed:', e); }
});

// 3.2 Toast + utils (một bản duy nhất)
(function ensureMiniToastHost() {
  if (!document.getElementById('mini-toast-host')) {
    const host = document.createElement('div');
    host.id = 'mini-toast-host';
    host.style.cssText = 'position:fixed;top:12px;right:12px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
    document.body.appendChild(host);
  }
})();
window.showMiniToast = window.showMiniToast || function(message, variant='info', ttlMs=5000){
  const host = document.getElementById('mini-toast-host');
  const box = document.createElement('div');
  box.style.cssText = 'min-width:280px;max-width:420px;padding:10px 12px;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.18);color:#111827;font-size:14px;line-height:1.4;transition:opacity .2s ease;border:1px solid';
  if (variant==='error'){ box.style.background='#fdecea'; box.style.borderColor='#f5c6cb'; }
  else if (variant==='success'){ box.style.background='#e8f5e9'; box.style.borderColor='#c8e6c9'; }
  else { box.style.background='#eef4ff'; box.style.borderColor='#c7d2fe'; }
  box.innerHTML = message;
  host.appendChild(box);
  setTimeout(()=>{ box.style.opacity='0'; setTimeout(()=> host.removeChild(box),220); }, ttlMs);
};
if (typeof window.diffDays !== 'function') window.diffDays = (d1,d2)=>Math.round((new Date(d2+'T00:00:00')-new Date(d1+'T00:00:00'))/86400000);
if (typeof window.todayYMD !== 'function') window.todayYMD = ()=>{const d=new Date(),m=String(d.getMonth()+1).padStart(2,'0'),dd=String(d.getDate()).padStart(2,'0');return `${d.getFullYear()}-${m}-${dd}`;};

// 3.3 Helper gọi API (GỬI source theo trang)
function fetchLastOrderInfo(productId, formEl) {
  const partnerId =
    formEl?.querySelector('[name="partner_id"]')?.value ||
    formEl?.querySelector('[name="supplier_id"]')?.value ||
    formEl?.querySelector('[name="customer_id"]')?.value || '';
  const source = (window.APP_CONTEXT && window.APP_CONTEXT.type === 'quote') ? 'quote' : 'order';
  return $.ajax({
    url: (window.AJAX_URL && window.AJAX_URL.product_history) ? window.AJAX_URL.product_history : (window.PROJECT_BASE_URL + 'process/product_history_api.php'),
    dataType: "json",
    method: "GET",
    data: { action:'latest_price', product_id:productId, partner_id:partnerId, source }
  });
}

// 3.4 Hook delegated: mọi .product-autocomplete
$(document).on('autocompleteselect', '.product-autocomplete', function (e, ui) {
  const productId = ui?.item?.id;
  const productName = ui?.item?.value || ui?.item?.label || '';
  const isQuote = (window.APP_CONTEXT && window.APP_CONTEXT.type === 'quote');
  if (!productId) { showMiniToast('Không xác định được ID sản phẩm để tra lịch sử.', 'error', 4500); return; }

  fetchLastOrderInfo(productId, this.closest('form')).done(function(res){
    if (res && res.success) {
      if (res.data && (res.data.last_date || res.data.unit_price!==null)) {
        const lastDate = res.data.last_date || '';
        const lastPrice = Number(res.data.unit_price || 0);
        const currency = res.data.currency || 'VND';
        const days = lastDate ? diffDays(lastDate, todayYMD()) : null;
        const formatted = lastPrice.toLocaleString('vi-VN');
        const title   = isQuote ? 'Giá báo gần nhất'  : 'Giá gần nhất';
        const dayName = isQuote ? 'Ngày báo gần nhất' : 'Ngày gần nhất';
        const dayText = (days===null)?'không rõ ngày' : (days===0?'hôm nay':(days>0?`${days} ngày trước`:`${Math.abs(days)} ngày tới`));
        showMiniToast(`<div style="font-weight:600;margin-bottom:2px;">${productName}</div><div>${title}: <b>${formatted} ${currency}</b></div><div>${dayName}: <b>${lastDate||'-'}</b> (${dayText})</div>`, 'info', 7000);
      } else {
        showMiniToast(isQuote?'Chưa có lịch sử báo giá cho sản phẩm này.':'Chưa có lịch sử đặt hàng cho sản phẩm này.', 'info', 4000);
      }
    } else {
      showMiniToast(res?.message || 'Không lấy được lịch sử sản phẩm.', 'error', 4500);
    }
  }).fail(function(xhr){
    console.warn('[product_history] AJAX fail:', xhr?.status, xhr?.responseText);
    showMiniToast('Lỗi mạng khi lấy lịch sử sản phẩm.', 'error', 4500);
  });
});
</script>
