<?php
// partners.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/init.php';
require_login();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= $lang['manage_partners'] ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#partnerModal" id="addPartnerBtn">
            <i class="bi bi-plus-lg"></i> <?= $lang['add_partner'] ?>
        </button>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <input type="text" id="partner-search" class="form-control" placeholder="<?= $lang['filter_partners'] ?>">
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover" id="partner-table">
        <thead class="table-dark"> <?php /* Hoặc table-light, hoặc không có class nào để dùng kiểu mặc định */ ?>
            <tr>
                <th>#</th>
                <th><?= $lang['partner_name'] ?></th>
                <th><?= $lang['partner_type'] ?></th>
                <th><?= $lang['tax_id'] ?></th>
                <th><?= $lang['phone'] ?></th>
                <th><?= $lang['email'] ?></th>
                <th><?= $lang['email_cc'] ?></th> <?php /* ← CỘT MỚI ĐƯỢC THÊM */ ?>
                <th><?= $lang['contact_person'] ?></th>
                <th><?= $lang['action'] ?></th>
            </tr>
        </thead>
        <tbody>
            <?php /* Dòng hiển thị "Loading..." và "Không có dữ liệu" sẽ được JavaScript quản lý */ ?>
            <tr>
                <td colspan="9" class="text-center" id="loading-row">Đang tải...</td> <?php /* Cập nhật colspan thành 9 */ ?>
            </tr>
        </tbody>
    </table>
</div>

<div class="modal fade" id="partnerModal" tabindex="-1" aria-labelledby="partnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="partnerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="partnerModalLabel"><?= htmlspecialchars($lang['add_partner'] ?? 'Thêm Đối tác') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="partner_id" name="partner_id">
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <?php // Ví dụ: Tên đối tác và loại đối tác vẫn bắt buộc ?>
                            <label for="partner_name" class="form-label required"><?= htmlspecialchars($lang['partner_name'] ?? 'Tên Đối tác') ?></label>
                            <input type="text" class="form-control" id="partner_name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="partner_type" class="form-label required"><?= htmlspecialchars($lang['partner_type'] ?? 'Loại') ?></label>
                            <select class="form-select" id="partner_type" name="type" required>
                                <option value="" selected disabled><?= htmlspecialchars($lang['select_type'] ?? 'Chọn loại') ?></option>
                                <option value="customer"><?= htmlspecialchars($lang['customer'] ?? 'Khách hàng') ?></option>
                                <option value="supplier"><?= htmlspecialchars($lang['supplier'] ?? 'Nhà cung cấp') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="partner_tax_id" class="form-label"><?= htmlspecialchars($lang['tax_id'] ?? 'Mã số thuế') ?></label>
                        <input type="text" class="form-control" id="partner_tax_id" name="tax_id">
                        <small id="tax_id_warning" class="text-danger d-none"><?= htmlspecialchars($lang['tax_id_exists'] ?? 'Mã số thuế đã tồn tại.') ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="partner_address" class="form-label"><?= htmlspecialchars($lang['address'] ?? 'Địa chỉ') ?></label>
                        <textarea class="form-control" id="partner_address" name="address" rows="2"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label for="partner_phone" class="form-label"><?= htmlspecialchars($lang['phone'] ?? 'Điện thoại') ?></label>
                            <input type="tel" class="form-control" id="partner_phone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php // Email không còn bắt buộc ?>
                            <label for="partner_email" class="form-label"><?= htmlspecialchars($lang['email'] ?? 'Email') ?> (<?= htmlspecialchars($lang['recipient_to'] ?? 'Gửi chính') ?>)</label>
                            <input type="email" class="form-control" id="partner_email" name="email"> <?php /* XÓA thuộc tính 'required' */ ?>
                            <small id="email_error" class="text-danger d-none"><?= htmlspecialchars($lang['invalid_email'] ?? 'Email không hợp lệ.') ?></small>
                            <small class="form-text text-muted"><?= htmlspecialchars($lang['emails_instruction'] ?? 'Phân tách nhiều email bằng dấu phẩy.') ?></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <?php // Email CC không còn bắt buộc ?>
                        <label for="partner_cc_emails" class="form-label"><?= htmlspecialchars($lang['email_cc'] ?? 'Email CC') ?> (<?= htmlspecialchars($lang['recipient_cc'] ?? 'Người nhận CC') ?>)</label>
                        <input type="text" class="form-control" id="partner_cc_emails" name="cc_emails" placeholder="<?= htmlspecialchars($lang['enter_cc_emails_placeholder'] ?? 'VD: cc1@example.com, cc2@example.com') ?>"> <?php /* XÓA thuộc tính 'required' */ ?>
                        <small id="cc_emails_error" class="text-danger d-none"><?= htmlspecialchars($lang['invalid_cc_emails'] ?? 'Một hoặc nhiều email CC không hợp lệ.') ?></small>
                        <small class="form-text text-muted"><?= htmlspecialchars($lang['cc_emails_instruction'] ?? 'Các email này sẽ được CC trong các trao đổi.') ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="partner_contact_person" class="form-label"><?= htmlspecialchars($lang['contact_person'] ?? 'Người liên hệ') ?></label>
                        <input type="text" class="form-control" id="partner_contact_person" name="contact_person">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($lang['cancel'] ?? 'Hủy') ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= htmlspecialchars($lang['save'] ?? 'Lưu') ?></button>
                </div>
            </form>
            <div id="modal-error-message" class="alert alert-danger mt-3 d-none" role="alert"></div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
<script>
// ==== Logger dùng chung cho trang Đối tác (partners) ====

function sendUserLog(action, description = '', level = 'info') {
  try {
    return fetch('process/log_api.php?action=log', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, description, level })
    }).catch(()=>{});
  } catch(_) {}
}

// Ghi lại lần vào trang
document.addEventListener('DOMContentLoaded', () => {
  sendUserLog('partners_view', 'Người dùng mở trang Quản lý Đối tác', 'info');
});
</script>

<script src="assets/js/partners.js"></script>
<script>
(function() {
  const form       = document.getElementById('partnerForm');
  const taxInput   = document.getElementById('partner_tax_id');
  const idInput    = document.getElementById('partner_id'); // hidden
  const warnSmall  = document.getElementById('tax_id_warning');

  // ===== 1) Cảnh báo MST trùng nhưng vẫn cho lưu =====
  let lastCheck = { tax: '', result: { exists: false, rows: [] } };

  async function checkTaxId(tax, excludeId) {
    if (!tax) {
      warnSmall?.classList.add('d-none');
      lastCheck = { tax: '', result: { exists: false, rows: [] } };
      return lastCheck.result;
    }
    if (lastCheck.tax === tax) return lastCheck.result;

    const url = new URL('process/partners_process.php', window.location.origin);
    url.searchParams.set('action', 'check_tax_id');
    url.searchParams.set('tax_id', tax);
    if (excludeId) url.searchParams.set('exclude_id', excludeId);

    try {
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
      const data = await res.json();
      lastCheck = { tax, result: data };
      if (data.exists) {
        warnSmall?.classList.remove('d-none');
        warnSmall.textContent = 'Mã số thuế đã tồn tại.';
      } else {
        warnSmall?.classList.add('d-none');
      }
      return data;
    } catch (e) {
      warnSmall?.classList.add('d-none');
      return { exists: false, rows: [] };
    }
  }

  taxInput?.addEventListener('blur', () => {
    checkTaxId(taxInput.value.trim(), idInput?.value || 0);
  });

  form?.addEventListener('submit', async (ev) => {
    const tax = taxInput?.value.trim();
    const excludeId = idInput?.value || 0;
    const data = (lastCheck.tax === tax)
      ? lastCheck.result
      : await checkTaxId(tax, excludeId);

    if (data?.exists) {
      ev.preventDefault();

      const dupList = (data.rows || [])
        .map(r => `• ${r.name} (${r.type === 'customer' ? 'KH' : (r.type === 'supplier' ? 'NCC' : r.type)}) [ID ${r.id}]`)
        .join('\n');

      if (window.Swal) {
        const res = await Swal.fire({
          icon: 'warning',
          title: 'Mã số thuế đã tồn tại',
          text: 'Bạn có muốn thêm nữa không?',
          footer: dupList ? `<pre style="text-align:left;margin:0;white-space:pre-wrap">${dupList}</pre>` : '',
          showCancelButton: true,
          confirmButtonText: 'Vẫn thêm',
          cancelButtonText: 'Hủy'
        });
        if (res.isConfirmed) form.submit();
      } else {
        const ok = confirm(
          'Mã số thuế của đối tác này đã tồn tại, bạn có muốn thêm nữa không?\n\n' +
          (dupList ? `Trùng với:\n${dupList}\n\n` : '')
        );
        if (ok) form.submit();
      }
    }
    // không trùng -> submit bình thường
  });

  // ===== 2) Tô màu KH/NCC trên bảng (bền vững) =====
  function normalizeType(raw) {
    // nhận cả "customer" / "supplier" hoặc "KH" / "NCC"
    const v = String(raw || '').trim().toLowerCase();
    if (v === 'customer' || v === 'kh')  return 'customer';
    if (v === 'supplier' || v === 'ncc') return 'supplier';
    return '';
  }

  function renderTypeBadge(type) {
    if (type === 'customer') return '<span class="badge bg-primary badge-role">KH</span>';
    if (type === 'supplier') return '<span class="badge bg-warning text-dark badge-role">NCC</span>';
    return `<span class="badge bg-secondary badge-role">${(type||'').toUpperCase()}</span>`;
  }

  function paintRowByType(tr, type) {
    tr.classList.add('partner-row');
    tr.classList.remove('customer', 'supplier');
    if (type === 'customer') tr.classList.add('customer');
    if (type === 'supplier') tr.classList.add('supplier');
  }

  // Cột "Loại" là cột thứ 3 (index 2) theo partners.php
  const table = document.getElementById('partner-table');
  const tbody = table?.querySelector('tbody');

  function transformTypeCells(root) {
    if (!root) return;

    root.querySelectorAll('tr').forEach(tr => {
      // bỏ qua hàng thông báo / loading
      if (tr.id === 'loading-row') return;

      const tds = tr.children;
      if (!tds || tds.length < 3) return;

      const typeCell = tds[2];

      // Nếu đã format rồi thì bỏ qua
      if (typeCell.dataset.fmt === '1') return;

      // Lấy text hiện có (nếu đã là badge thì textContent vẫn đọc được KH/NCC)
      let raw = (typeCell.textContent || '').trim();
      const norm = normalizeType(raw);
      if (!norm) return; // không rõ loại thì thôi

      typeCell.innerHTML = renderTypeBadge(norm);
      typeCell.dataset.fmt = '1';
      paintRowByType(tr, norm);
    });
  }

  // Chạy lần đầu
  if (tbody) transformTypeCells(tbody);

  // Theo dõi thay đổi bảng (nạp AJAX) để tự tô lại
  if (tbody && window.MutationObserver) {
    const obs = new MutationObserver(muts => {
      // chỉ xử lý khi có thêm/sửa node
      transformTypeCells(tbody);
    });
    obs.observe(tbody, { childList: true, subtree: true });
  }

})();
</script>
