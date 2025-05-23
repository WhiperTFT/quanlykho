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

    // --- Ngôn ngữ từ PHP ---
    const lang = {
        edit_partner: document.getElementById('partnerModalLabel').getAttribute('data-lang-edit') || 'Edit Partner',
        add_partner: document.getElementById('partnerModalLabel').getAttribute('data-lang-add') || 'Add New Partner',
        confirm_delete: document.getElementById('partnerModalLabel').getAttribute('data-lang-confirm-delete') || 'Are you sure you want to delete this partner?',
        error_processing: document.getElementById('partnerModalLabel').getAttribute('data-lang-error') || 'Error processing request.',
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

    // --- Hàm tải danh sách đối tác ---
    async function loadPartners() {
        const loadingRow = document.getElementById('loading-row'); // Giữ lại nếu bạn vẫn dùng dòng này
        // Hoặc nếu bạn dùng spinner:
        // const loadingSpinnerContainer = document.getElementById('loading-spinner-container');
        // if (loadingSpinnerContainer) loadingSpinnerContainer.classList.remove('d-none');

        if(loadingRow) loadingRow.classList.remove('d-none'); // Hiển thị dòng "Đang tải..."
        partnerTableBody.innerHTML = ''; // Xóa nội dung cũ
        if(loadingRow) partnerTableBody.appendChild(loadingRow); // Thêm lại dòng "Đang tải..." nếu dùng


        try {
            const response = await fetch('process/partners_process.php?action=list');
            if (!response.ok) {
                throw new Error(`Lỗi HTTP! Trạng thái: ${response.status}`);
            }
            const result = await response.json();

            if(loadingRow) loadingRow.classList.add('d-none'); // Ẩn dòng "Đang tải..."
            // if (loadingSpinnerContainer) loadingSpinnerContainer.classList.add('d-none');
            partnerTableBody.innerHTML = ''; // Xóa hoàn toàn tbody trước khi thêm mới

            if (result.success && result.data) {
                if (result.data.length === 0) {
                    // Cập nhật colspan thành 9
                    partnerTableBody.innerHTML = `<tr><td colspan="9" class="text-center">${lang.no_data_available || 'Không có dữ liệu.'}</td></tr>`;
                } else {
                    result.data.forEach((partner, index) => {
                        const partnerTypeDisplay = partner.type === 'customer' ? (lang.customer || 'Khách hàng') : (lang.supplier || 'Nhà cung cấp');
                        const row = `
                            <tr data-partner-id="${partner.id}" data-search-term="${(partner.name + ' ' + (partner.tax_id || '') + ' ' + (partner.phone || '') + ' ' + (partner.email || '') + ' ' + (partner.cc_emails || '') + ' ' + (partner.contact_person || '')).toLowerCase()}">
                                <td>${index + 1}</td>
                                <td>${partner.name || ''}</td>
                                <td>${partnerTypeDisplay}</td>
                                <td>${partner.tax_id || ''}</td>
                                <td>${partner.phone || ''}</td>
                                <td>${partner.email || ''}</td>
                                <td>${partner.cc_emails || ''}</td>  <?php /* ← DỮ LIỆU CỘT MỚI */ ?>
                                <td>${partner.contact_person || ''}</td>
                                <td class="text-center"> <?php /* Thêm class text-center nếu muốn căn giữa các nút */ ?>
                                    <button class="btn btn-sm btn-warning btn-edit me-1" data-id="${partner.id}" title="${lang.text_edit || 'Sửa'}">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-delete"
                                            data-id="${partner.id}"
                                            data-name="${partner.name}"
                                            data-type-display="${partnerTypeDisplay}"
                                            title="${lang.text_delete || 'Xóa'}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        partnerTableBody.insertAdjacentHTML('beforeend', row);
                    });
                }
            } else {
                // Cập nhật colspan thành 9
                partnerTableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">${result.message || lang.error_processing || 'Lỗi xử lý yêu cầu.'}</td></tr>`;
            }
        } catch (error) {
            console.error('Lỗi tải danh sách đối tác:', error);
            if(loadingRow) loadingRow.classList.add('d-none');
            // if (loadingSpinnerContainer) loadingSpinnerContainer.classList.add('d-none');
            partnerTableBody.innerHTML = ''; // Xóa tbody
            // Cập nhật colspan thành 9
            partnerTableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Lỗi tải dữ liệu: ${error.message}</td></tr>`;
        }
    }

    // --- Hàm kiểm tra trùng MST ---
    async function checkTaxIdDuplicate() {
        const taxId = taxIdInput.value.trim();
        const partnerId = partnerIdInput.value;

        if (taxId === '') {
            taxIdWarning.classList.add('d-none');
            return;
        }

        try {
            let url = `process/partners_process.php?action=check_duplicate&tax_id=${encodeURIComponent(taxId)}`;
            if (partnerId) {
                url += `&partner_id=${partnerId}`;
            }
            const response = await fetch(url);
            const result = await response.json();

            if (result.success && result.exists) {
                taxIdWarning.classList.remove('d-none');
                taxIdInput.classList.add('is-invalid');
            } else {
                taxIdWarning.classList.add('d-none');
                taxIdInput.classList.remove('is-invalid');
            }
        } catch (error) {
            console.error('Error checking tax ID:', error);
        }
    }

    // --- Xử lý sự kiện ---

    // Load partners khi trang tải xong
    loadPartners();

    // Mở modal thêm mới
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
    });

    // Kiểm tra trùng MST khi người dùng nhập xong
    taxIdInput.addEventListener('blur', checkTaxIdDuplicate);

    // Xử lý submit form (Add/Edit)
    partnerForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        hideModalError(); // Hàm này của bạn
        const emailErrorEl = document.getElementById('email_error');
        const ccEmailsErrorEl = document.getElementById('cc_emails_error');
if (emailErrorEl) {
    emailErrorEl.classList.add('d-none'); // Thao tác 1: Thêm class
    emailErrorEl.textContent = '';      // Thao tác 2: Gán textContent
}
if (ccEmailsErrorEl) {
    ccEmailsErrorEl.classList.add('d-none'); // Thao tác 1: Thêm class
    ccEmailsErrorEl.textContent = '';      // Thao tác 2: Gán textContent
} // Reset thông báo lỗi

        let formIsValid = true; // Biến cờ để theo dõi tính hợp lệ của form

        // Kiểm tra email (chỉ một email) - KHÔNG BẮT BUỘC, nhưng nếu nhập phải đúng định dạng
        const emailInput = document.getElementById('partner_email');
        const email = emailInput.value.trim();
        // Xóa bỏ kiểm tra bắt buộc:
        // if (!email && emailInput.hasAttribute('required')) { ... }

        if (email && !isValidEmail(email)) { // Chỉ kiểm tra định dạng nếu có nhập email
            if(emailErrorEl) {
                emailErrorEl.textContent = lang.invalid_email || 'Email không hợp lệ.';
                emailErrorEl.classList.remove('d-none');
            }
            formIsValid = false; // Đánh dấu form không hợp lệ
        }

        // Kiểm tra cc_emails (nhiều email) - KHÔNG BẮT BUỘC, nhưng nếu nhập phải đúng định dạng
        const ccEmailsInput = document.getElementById('partner_cc_emails');
        const ccEmails = ccEmailsInput.value.trim();
        // Xóa bỏ kiểm tra bắt buộc:
        // if (!ccEmails && ccEmailsInput.hasAttribute('required')) { ... }

        if (ccEmails) { // Chỉ kiểm tra định dạng nếu có nhập cc_emails
            const ccEmailArray = ccEmails.split(',').map(e => e.trim()).filter(e => e); // Lọc bỏ email rỗng
            for (let ccEmail of ccEmailArray) {
                if (!isValidEmail(ccEmail)) {
                    if(ccEmailsErrorEl) {
                        ccEmailsErrorEl.textContent = lang.invalid_cc_emails || 'Một hoặc nhiều Email CC không hợp lệ.';
                        ccEmailsErrorEl.classList.remove('d-none');
                    }
                    formIsValid = false; // Đánh dấu form không hợp lệ
                    break; // Dừng kiểm tra nếu đã có lỗi
                }
            }
        }
        
        // Kiểm tra trùng MST (nếu có nhập)
        if (taxIdInput && taxIdInput.value.trim() !== '') {
            await checkTaxIdDuplicate(); // Hàm này của bạn
            if (taxIdInput.classList.contains('is-invalid')) {
                showModalError(lang.tax_id_exists || 'Mã số thuế đã tồn tại.');
                formIsValid = false; // Đánh dấu form không hợp lệ
            }
        }

        // Nếu form không hợp lệ ở bất kỳ bước nào ở trên, dừng lại
        if (!formIsValid) {
            return;
        }

        // Nếu form hợp lệ, tiếp tục gửi dữ liệu
        const formData = new FormData(partnerForm);
        const data = Object.fromEntries(formData.entries());
        if (partnerIdInput.value) {
            data.partner_id = partnerIdInput.value;
        }
        // data.email và data.cc_emails sẽ là chuỗi rỗng nếu người dùng không nhập,
        // hoặc là giá trị đã nhập nếu người dùng có nhập.

        try {
            const response = await fetch('process/partners_process.php?action=save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                partnerModal.hide();
                loadPartners();
                alert(result.message || (lang.saving_successfully || 'Lưu thành công!'));
            } else {
                showModalError(result.message || lang.error_processing_request || 'Lỗi xử lý.');
            }
        } catch (error) {
            console.error('Error saving partner:', error);
            showModalError((lang.error_processing_request || 'Lỗi xử lý.') + ': ' + error.message);
        }
    });

    // Xử lý nút Edit và Delete (Event Delegation)
    partnerTableBody.addEventListener('click', async function(event) {
        const target = event.target;
        const editButton = target.closest('.btn-edit');
        const deleteButton = target.closest('.btn-delete');

        // --- Edit ---
        if (editButton) {
            const id = editButton.getAttribute('data-id');
            partnerForm.reset();
            // hideModalError(); // Nếu bạn có hàm này
            // taxIdWarning.classList.add('d-none'); // Nếu có
            // taxIdInput.classList.remove('is-invalid'); // Nếu có

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
                    document.getElementById('partner_cc_emails').value = partner.cc_emails || ''; // ← GÁN GIÁ TRỊ CHO CC EMAILS
                    document.getElementById('partner_contact_person').value = partner.contact_person || '';
                    
                    partnerModalLabel.textContent = lang.edit_partner || 'Sửa Đối tác';
                    partnerModal.show();
                } else {
                    alert(result.message || lang.error_processing || 'Lỗi xử lý yêu cầu.');
                }
            } catch (error) {
                console.error('Lỗi lấy thông tin đối tác để sửa:', error);
                alert(lang.error_processing || 'Lỗi xử lý yêu cầu.');
            }
        }

        // --- Delete ---
        if (deleteButton) {
            const id = deleteButton.getAttribute('data-id');
            if (confirm(lang.confirm_delete)) {
                try {
                    const response = await fetch('process/partners_process.php?action=delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `id=${id}`
                    });
                    const result = await response.json();

                    if (result.success) {
                        const rowToDelete = deleteButton.closest('tr');
                        if (rowToDelete) {
                            rowToDelete.remove();
                        }
                        alert(result.message);
                    } else {
                        alert(result.message || lang.error_processing);
                    }
                } catch (error) {
                    console.error('Error deleting partner:', error);
                    alert(lang.error_processing);
                }
            }
        }
    });

    // --- Live Filter ---
    partnerSearchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const rows = partnerTableBody.querySelectorAll('tr[data-partner-id]');

        rows.forEach(row => {
            const rowText = row.getAttribute('data-search-term') || '';
            if (rowText.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});