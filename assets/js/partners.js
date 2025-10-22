// File: assets/js/partners.js

document.addEventListener('DOMContentLoaded', function() {
    const partnerTableBody = document.querySelector('#partner-table tbody');
    const loadingRow = document.getElementById('loading-row');
    const partnerModal = new bootstrap.Modal(document.getElementById('partnerModal'));
    const partnerForm = document.getElementById('partnerForm');
    const partnerModalLabel = document.getElementById('partnerModalLabel');
    const partnerIdInput = document.getElementById('partner_id');
    const addPartnerBtn = document.getElementById('addPartnerBtn');
    const partnerSearchInput = document.getElementById('partner-search');
    const taxIdInput = document.getElementById('partner_tax_id');
    const taxIdWarning = document.getElementById('tax_id_warning');
    const modalErrorMessage = document.getElementById('modal-error-message');

    // ==== Logger ====
    function sendUserLog(action, description = '', level = 'info') {
        try {
            return fetch('process/log_api.php?action=log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, description, level })
            }).catch(() => {});
        } catch (_) {}
    }

    document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-clone-opposite');
  if (!btn) return;

  // Lấy id ưu tiên từ nút; nếu thiếu, lấy từ <tr data-partner-id="...">
  let id = btn.getAttribute('data-id');
  if (!id) {
    const tr = btn.closest('tr');
    if (tr && tr.dataset.partnerId) id = tr.dataset.partnerId;
  }

  if (!id || !/^\d+$/.test(String(id))) {
    // Log nhẹ để bạn debug nhanh
    console.warn('clone_opposite_type: thiếu/invalid id', { id, btn });
    if (window.Swal) {
      await Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không xác định được ID đối tác nguồn.' });
    } else {
      alert('Không xác định được ID đối tác nguồn.');
    }
    return;
  }

  const name = btn.getAttribute('data-name') || '';
  const type = btn.getAttribute('data-type') || '';

  // Xác nhận
  if (window.Swal) {
    const res = await Swal.fire({
      icon: 'question',
      title: 'Nhân bản sang loại còn lại?',
      text: name ? `Sẽ nhân bản: ${name} (${type || 'n/a'})` : 'Sẽ tạo bản ghi mới KH ⇄ NCC, sao chép toàn bộ thông tin.',
      showCancelButton: true,
      confirmButtonText: 'Thực hiện',
      cancelButtonText: 'Hủy'
    });
    if (!res.isConfirmed) return;
  } else if (!confirm('Nhân bản sang loại còn lại?')) {
    return;
  }

  try {
    const fd = new FormData();
    fd.append('id', id); // <-- Quan trọng

    const resp = await fetch('process/partners_process.php?action=clone_opposite_type', {
      method: 'POST',
      body: fd
    });
    const data = await resp.json();

    if (data.success) {
      if (window.Swal) {
        await Swal.fire({ icon: 'success', title: 'Thành công', text: data.message || 'Đã nhân bản.' });
      } else {
        alert(data.message || 'Đã nhân bản.');
      }
      // Tùy bạn: reload danh sách hoặc gọi loadPartners()
      // loadPartners();
      location.reload();
    } else {
      if (window.Swal) {
        Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message || 'Không thể nhân bản.' });
      } else {
        alert(data.message || 'Không thể nhân bản.');
      }
    }
  } catch (err) {
    if (window.Swal) {
      Swal.fire({ icon: 'error', title: 'Lỗi mạng', text: 'Không thể kết nối máy chủ.' });
    } else {
      alert('Lỗi mạng');
    }
  }
});



    // --- Ngôn ngữ từ PHP ---
    const lang = {
        edit_partner: partnerModalLabel.getAttribute('data-lang-edit') || 'Edit Partner',
        add_partner: partnerModalLabel.getAttribute('data-lang-add') || 'Add New Partner',
        confirm_delete: partnerModalLabel.getAttribute('data-lang-confirm-delete') || 'Are you sure you want to delete this partner?',
        error_processing: partnerModalLabel.getAttribute('data-lang-error') || 'Error processing request.',
        tax_id_exists: taxIdWarning.textContent || 'Tax ID already exists!',
        invalid_email: document.getElementById('email_error')?.textContent || 'Invalid email address.',
        invalid_cc_emails: document.getElementById('cc_emails_error')?.textContent || 'One or more CC email addresses are invalid.'
    };

    // --- Hàm kiểm tra định dạng email ---
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // --- Hàm hiển thị thông báo lỗi trong modal ---
    function showModalError(message) {
        modalErrorMessage.textContent = message;
        modalErrorMessage.classList.remove('d-none');
    }
    function hideModalError() {
        modalErrorMessage.classList.add('d-none');
    }
    // helper ở đầu file (đặt ngoài loadPartners)
    function renderTypeBadge(type) {
    const t = String(type || '').toLowerCase();
    if (t === 'customer' || t === 'kh' || t === 'khách hàng')
        return '<span class="badge bg-primary">KH</span>';
    if (t === 'supplier' || t === 'ncc' || t === 'nhà cung cấp')
        return '<span class="badge bg-warning text-dark">NCC</span>';
    return `<span class="badge bg-secondary">${(type||'').toString().toUpperCase()}</span>`;
    }

    function rowTypeClass(type) {
    const t = String(type || '').toLowerCase();
    if (t === 'customer' || t === 'kh' || t === 'khách hàng') return 'customer';
    if (t === 'supplier' || t === 'ncc' || t === 'nhà cung cấp') return 'supplier';
    return '';
    }


    // --- Hàm tải danh sách đối tác ---
    async function loadPartners() {
        if (loadingRow) loadingRow.classList.remove('d-none');
        partnerTableBody.innerHTML = '';
        if (loadingRow) partnerTableBody.appendChild(loadingRow);

        try {
            const response = await fetch('process/partners_process.php?action=list');
            if (!response.ok) throw new Error(`Lỗi HTTP! Trạng thái: ${response.status}`);
            const result = await response.json();

            if (loadingRow) loadingRow.classList.add('d-none');
            partnerTableBody.innerHTML = '';

            if (result.success && result.data) {
                sendUserLog('partners_list_loaded', `Tải danh sách đối tác: ${result.data.length} dòng`, 'info');

                if (result.data.length === 0) {
                    partnerTableBody.innerHTML = `<tr><td colspan="9" class="text-center">${lang.no_data_available || 'Không có dữ liệu.'}</td></tr>`;
                } else {
                    result.data.forEach((partner, index) => {
                        const partnerTypeDisplay = partner.type === 'customer' ? (lang.customer || 'Khách hàng') : (lang.supplier || 'Nhà cung cấp');
                        const row = `
                        <tr data-partner-id="${partner.id}" class="partner-row ${rowTypeClass(partner.type)}"
                            data-search-term="${(partner.name + ' ' + (partner.tax_id || '') + ' ' + (partner.phone || '') + ' ' + (partner.email || '') + ' ' + (partner.cc_emails || '') + ' ' + (partner.contact_person || '')).toLowerCase()}">
                            <td>${index + 1}</td>
                            <td>${partner.name || ''}</td>
                            <td>${renderTypeBadge(partner.type)}</td>
                            <td>${partner.tax_id || ''}</td>
                            <td>${partner.phone || ''}</td>
                            <td>${partner.email || ''}</td>
                            <td>${partner.cc_emails || ''}</td>
                            <td>${partner.contact_person || ''}</td>
                            <td class="text-center">
                            <!-- nút clone (đã làm gọn ở mục 2) -->
                            <button class="btn btn-icon btn-outline-primary btn-sm rounded-pill btn-clone-opposite"
                                    data-id="${partner.id}" data-name="${partner.name}" data-type="${partner.type}"
                                    title="Nhân bản KH ⇄ NCC" data-bs-toggle="tooltip" aria-label="Nhân bản KH ⇄ NCC">
                                <i class="bi bi-shuffle"></i>
                            </button>

                            <button class="btn btn-sm btn-warning btn-edit me-1" data-id="${partner.id}" title="Sửa">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-delete"
                                    data-id="${partner.id}"
                                    data-name="${partner.name}"
                                    data-type-display="${partner.type}"
                                    title="Xóa">
                                <i class="bi bi-trash"></i>
                            </button>
                            </td>
                        </tr>`;
                        partnerTableBody.insertAdjacentHTML('beforeend', row);
                    });
                }
            } else {
                partnerTableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">${result.message || lang.error_processing}</td></tr>`;
                sendUserLog('partners_list_failed', `Tải danh sách thất bại: ${result.message || 'không rõ'}`, 'error');
            }
        } catch (error) {
            if (loadingRow) loadingRow.classList.add('d-none');
            partnerTableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Lỗi tải dữ liệu: ${error.message}</td></tr>`;
            sendUserLog('partners_list_failed', `Lỗi tải danh sách: ${error.message}`, 'error');
        }
    }
    // Sau partnerTableBody.insertAdjacentHTML(...); hoặc cuối loadPartners()
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(el => { try { new bootstrap.Tooltip(el); } catch(_) {} });


    // --- Kiểm tra trùng MST ---
    async function checkTaxIdDuplicate() {
        const taxId = taxIdInput.value.trim();
        const partnerId = partnerIdInput.value;
        if (taxId === '') {
            taxIdWarning.classList.add('d-none');
            return;
        }
        try {
            let url = `process/partners_process.php?action=check_duplicate&tax_id=${encodeURIComponent(taxId)}`;
            if (partnerId) url += `&partner_id=${partnerId}`;
            const response = await fetch(url);
            const result = await response.json();

            if (result.success && result.exists) {
            // Hiện cảnh báo text, nhưng KHÔNG thêm is-invalid để không cản trở người dùng
            taxIdWarning.classList.remove('d-none');
            taxIdInput.classList.remove('is-invalid'); // <- đảm bảo không bị đỏ
            } else {
            taxIdWarning.classList.add('d-none');
            taxIdInput.classList.remove('is-invalid');
            }

        } catch (error) {
            console.error('Error checking tax ID:', error);
        }
    }

    // --- Load partners khi trang tải xong
    loadPartners();

    // --- Mở modal thêm mới
    addPartnerBtn.addEventListener('click', () => {
        partnerForm.reset();
        partnerIdInput.value = '';
        partnerModalLabel.textContent = lang.add_partner;
        taxIdWarning.classList.add('d-none');
        taxIdInput.classList.remove('is-invalid');
        hideModalError();
        document.getElementById('email_error').classList.add('d-none');
        document.getElementById('cc_emails_error').classList.add('d-none');
        partnerModal.show();
        sendUserLog('partner_modal_open_add', 'Mở modal THÊM đối tác', 'info');
    });

    taxIdInput.addEventListener('blur', checkTaxIdDuplicate);

    // --- Submit form (Add/Edit)
    partnerForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        hideModalError();
        const emailErrorEl = document.getElementById('email_error');
        const ccEmailsErrorEl = document.getElementById('cc_emails_error');
        if (emailErrorEl) { emailErrorEl.classList.add('d-none'); emailErrorEl.textContent = ''; }
        if (ccEmailsErrorEl) { ccEmailsErrorEl.classList.add('d-none'); ccEmailsErrorEl.textContent = ''; }

        let formIsValid = true;

        // Validate email
        const emailInput = document.getElementById('partner_email');
        const email = emailInput.value.trim();
        if (email && !isValidEmail(email)) {
            emailErrorEl.textContent = lang.invalid_email;
            emailErrorEl.classList.remove('d-none');
            formIsValid = false;
        }

        // Validate cc_emails
        const ccEmailsInput = document.getElementById('partner_cc_emails');
        const ccEmails = ccEmailsInput.value.trim();
        if (ccEmails) {
            const ccEmailArray = ccEmails.split(',').map(e => e.trim()).filter(e => e);
            for (let ccEmail of ccEmailArray) {
                if (!isValidEmail(ccEmail)) {
                    ccEmailsErrorEl.textContent = lang.invalid_cc_emails;
                    ccEmailsErrorEl.classList.remove('d-none');
                    formIsValid = false;
                    break;
                }
            }
        }

        if (taxIdInput.value.trim() !== '') {
            await checkTaxIdDuplicate();
            if (taxIdInput.classList.contains('is-invalid')) {
                showModalError(lang.tax_id_exists);
                formIsValid = false;
            }
        }

        if (!formIsValid) return;

        const formData = new FormData(partnerForm);
        const data = Object.fromEntries(formData.entries());
        if (partnerIdInput.value) data.partner_id = partnerIdInput.value;

        const isEdit = !!partnerIdInput.value;
        const logAction = isEdit ? 'partner_update_attempt' : 'partner_create_attempt';
        sendUserLog(logAction, `Người dùng gửi form ${isEdit?'CẬP NHẬT':'TẠO'} đối tác: ${JSON.stringify(data)}`, 'info');

        try {
            const response = await fetch('process/partners_process.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                partnerModal.hide();
                loadPartners();
                alert(result.message || 'Lưu thành công!');
                sendUserLog('partner_save_success', `Lưu đối tác thành công (id=${result.id||''})`, 'info');
            } else {
                showModalError(result.message || lang.error_processing);
                sendUserLog('partner_save_failed', `Lưu đối tác thất bại: ${result.message||'không rõ'}`, 'error');
            }
        } catch (error) {
            console.error('Error saving partner:', error);
            showModalError(lang.error_processing + ': ' + error.message);
            sendUserLog('partner_save_failed', `Lỗi JS/mạng khi lưu đối tác: ${error.message}`, 'error');
        }
    });

    // --- Edit/Delete
    partnerTableBody.addEventListener('click', async function(event) {
        const target = event.target;
        const editButton = target.closest('.btn-edit');
        const deleteButton = target.closest('.btn-delete');

        // Edit
        if (editButton) {
            const id = editButton.getAttribute('data-id');
            sendUserLog('partner_edit_click', `Click SỬA đối tác (id=${id})`, 'info');
            try {
                const response = await fetch(`process/partners_process.php?action=get_partner&id=${id}`);
                const result = await response.json();
                if (result.success && result.data) {
                    const partner = result.data;
                    partnerIdInput.value = partner.id || '';
                    document.getElementById('partner_name').value = partner.name || '';
                    document.getElementById('partner_type').value = partner.type || '';
                    document.getElementById('partner_tax_id').value = partner.tax_id || '';
                    document.getElementById('partner_address').value = partner.address || '';
                    document.getElementById('partner_phone').value = partner.phone || '';
                    document.getElementById('partner_email').value = partner.email || '';
                    document.getElementById('partner_cc_emails').value = partner.cc_emails || '';
                    document.getElementById('partner_contact_person').value = partner.contact_person || '';

                    partnerModalLabel.textContent = lang.edit_partner;
                    partnerModal.show();
                    sendUserLog('partner_modal_open_edit', `Mở modal SỬA đối tác (id=${partner.id})`, 'info');
                } else {
                    alert(result.message || lang.error_processing);
                    sendUserLog('partner_get_failed', `Không lấy được đối tác (id=${id})`, 'error');
                }
            } catch (error) {
                console.error('Lỗi lấy thông tin đối tác để sửa:', error);
                alert(lang.error_processing);
                sendUserLog('partner_get_failed', `Lỗi JS/mạng khi lấy đối tác (id=${id}): ${error.message}`, 'error');
            }
        }

        // Delete
        if (deleteButton) {
            const id = deleteButton.getAttribute('data-id');
            const name = deleteButton.getAttribute('data-name');
            sendUserLog('partner_delete_click', `Click XÓA đối tác (id=${id}) — ${name}`, 'warn');
            if (confirm(lang.confirm_delete)) {
                try {
                    const response = await fetch('process/partners_process.php?action=delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: `id=${id}`
                    });
                    const result = await response.json();
                    if (result.success) {
                        deleteButton.closest('tr')?.remove();
                        alert(result.message);
                        sendUserLog('partner_delete_success', `Xóa thành công đối tác (id=${id}) — ${name}`, 'info');
                    } else {
                        alert(result.message || lang.error_processing);
                        sendUserLog('partner_delete_failed', `Xóa đối tác thất bại (id=${id}): ${result.message||'không rõ'}`, 'error');
                    }
                } catch (error) {
                    console.error('Error deleting partner:', error);
                    alert(lang.error_processing);
                    sendUserLog('partner_delete_failed', `Lỗi JS/mạng khi xóa đối tác (id=${id}): ${error.message}`, 'error');
                }
            }
        }
    });

    // --- Live Filter ---
    let __searchTimer = null;
    partnerSearchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase().trim();
        clearTimeout(__searchTimer);
        __searchTimer = setTimeout(() => {
            if (searchTerm) sendUserLog('partners_search', `Từ khóa: "${searchTerm}"`, 'info');
        }, 600);

        const rows = partnerTableBody.querySelectorAll('tr[data-partner-id]');
        rows.forEach(row => {
            const rowText = row.getAttribute('data-search-term') || '';
            row.style.display = rowText.includes(searchTerm) ? '' : 'none';
        });
    });
});
// 