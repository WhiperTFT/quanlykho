// File: assets/js/units.js
// Cần jQuery và Bootstrap JS đã được load

$(document).ready(function() {
    const unitModalElement = document.getElementById('unitModal');
    const unitModal = unitModalElement ? new bootstrap.Modal(unitModalElement) : null;
    const form = $('#unitForm');
    const saveButton = $('#saveUnitBtn');
    const tableBody = $('#unitsTableBody');

    // ==== Logger helper (an toàn nếu sendUserLog chưa có) ====
    function log(action, description = '', level = 'info') {
        try {
            if (typeof window.sendUserLog === 'function') {
                window.sendUserLog(action, description, level);
            }
        } catch (_) { /* bỏ qua */ }
    }

    if (!unitModal) {
        console.error("Unit modal element not found.");
        log('units_modal_missing', 'Không tìm thấy phần tử modal #unitModal', 'warn');
        // có thể return nếu modal là bắt buộc
        // return;
    }

    // --- Helper Functions ---
    function showUnitMessage(message, type = 'success') {
        alert((type || 'INFO').toUpperCase() + ": " + message); // Tạm dùng alert
        if (type === 'success') {
            location.reload(); // Reload để cập nhật bảng
        }
    }

    function resetUnitForm() {
        if (form.length) {
            form[0].reset();
            form.find('input[type="hidden"]').val('');
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.invalid-feedback').text('');
            $('#unitModalLabel').text(LANG['add_unit'] || 'Add Unit');
            saveButton.text(LANG['save'] || 'Save').prop('disabled', false);
        }
    }

    function handleUnitValidationErrors(errors) {
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').text('');

        if (typeof errors === 'object' && errors !== null) {
            $.each(errors, function(fieldName, messages) {
                const input = form.find(`[name="${fieldName}"]`);
                if (input.length) {
                    input.addClass('is-invalid');
                    input.closest('.mb-3').find('.invalid-feedback').text(messages[0]);
                }
            });
        }
    }

    // --- Event Handlers ---

    // Mở Modal để Thêm mới
    $('.btn-add-unit').on('click', function() {
        if (!unitModal) return;
        resetUnitForm();
        unitModal.show();
        log('unit_modal_open_add', 'Mở modal THÊM Đơn vị tính', 'info');
    });

    // Mở Modal để Sửa (Delegated event)
    tableBody.on('click', '.btn-edit-unit', function() {
        if (!unitModal) return;

        const unitId = $(this).data('id');
        const unitName = $(this).data('name') || '';
        log('unit_edit_click', `Click SỬA đơn vị (id=${unitId}) — "${unitName}"`, 'info');

        resetUnitForm();
        $('#unitModalLabel').text(LANG['edit_unit'] || 'Edit Unit');
        saveButton.text(LANG['update'] || 'Update');
        $('#unitId').val(unitId); // Set ID cho form

        // AJAX lấy thông tin đơn vị
        $.ajax({
            url: 'process/units_handler.php',
            type: 'GET',
            data: { action: 'get', id: unitId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    $('#unitName').val(response.data.name);
                    $('#unitDescription').val(response.data.description || ''); // Xử lý null description
                    unitModal.show();
                    log('unit_modal_open_edit', `Mở modal SỬA đơn vị (id=${unitId}) — "${response.data.name||''}"`, 'info');
                } else {
                    const msg = response.message || LANG['error_fetching_data'] || 'Error fetching unit data';
                    showUnitMessage(msg, 'error');
                    log('unit_get_failed', `Không lấy được dữ liệu đơn vị (id=${unitId}). Lý do: ${msg}`, 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showUnitMessage(LANG['server_error'] || 'Server error', 'error');
                log('unit_get_failed', `Lỗi JS/mạng khi lấy đơn vị (id=${unitId}): ${textStatus} - ${errorThrown}`, 'error');
            }
        });
    });

    // Kiểm tra trùng tên Đơn vị khi nhập liệu (debounced)
    let debounceTimerUnit;
    $('#unitName').on('input', function() {
        clearTimeout(debounceTimerUnit);
        const input = $(this);
        const name = input.val().trim();
        const unitId = $('#unitId').val(); // ID hiện tại nếu là edit
        const errorDiv = input.closest('.mb-3').find('.invalid-feedback');

        // Xóa lỗi validation cũ của Bootstrap ngay lập tức
        input.removeClass('is-invalid');
        errorDiv.text('');

        if (name.length < 1) return;

        debounceTimerUnit = setTimeout(function() {
            $.ajax({
                url: 'process/units_handler.php',
                type: 'GET',
                data: {
                    action: 'check_duplicate',
                    name: name,
                    current_id: unitId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.exists) {
                        input.addClass('is-invalid'); // Đánh dấu lỗi
                        errorDiv.text(LANG['unit_name_exists'] || 'Unit name already exists.');
                        log('units_duplicate_check_hit', `Tên đơn vị bị trùng: "${name}"${unitId?` (khi sửa id=${unitId})`:''}`, 'warn');
                    }
                },
                error: function(xhr, s, e) {
                    console.error("Error checking unit duplicate name.", s, e);
                    log('units_duplicate_check_error', `Lỗi kiểm tra trùng tên: "${name}" — ${s||''} ${e||''}`, 'error');
                }
            });
        }, 500); // Chờ 500ms
    });

    // Submit Form (Thêm/Sửa)
    form.on('submit', function(e) {
        e.preventDefault();
        if (!unitModal) return;

        const unitId = $('#unitId').val();
        const action = unitId ? 'edit' : 'add';
        const unitName = ($('#unitName').val() || '').trim();

        // Kiểm tra lỗi validation client-side trước khi gửi
        if (form.find('.is-invalid').length > 0) {
            const msg = LANG['fix_errors_before_saving'] || 'Please fix the errors before saving.';
            showUnitMessage(msg, 'warning');
            log('unit_save_blocked_validation', `Form có lỗi, không gửi. Hành động: ${action}, tên="${unitName}"`, 'warn');
            return;
        }

        // Log attempt
        log(unitId ? 'unit_update_attempt' : 'unit_create_attempt',
            unitId
                ? `Thử CẬP NHẬT đơn vị (id=${unitId}) — tên="${unitName}"`
                : `Thử TẠO đơn vị — tên="${unitName}"`,
            'info'
        );

        saveButton.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' +
            (LANG['saving'] || 'Saving...')
        );

        $.ajax({
            url: 'process/units_handler.php',
            type: 'POST',
            data: form.serialize() + '&action=' + action, // Gửi dữ liệu form + action
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    unitModal.hide();
                    showUnitMessage(response.message || (LANG['save_success'] || 'Saved successfully!'));
                    log('unit_save_success',
                        `Lưu đơn vị thành công${response.id?` (id=${response.id})`:''} — tên="${unitName}"`,
                        'info'
                    );
                    // TODO: cập nhật bảng bằng JS (nếu muốn), hiện tại reload trong showUnitMessage()
                } else {
                    if (response.errors) {
                        handleUnitValidationErrors(response.errors);
                        log('unit_save_failed',
                            `Lưu đơn vị thất bại (validation). Tên="${unitName}". Errors=${JSON.stringify(response.errors)}`,
                            'error'
                        );
                    } else {
                        const msg = response.message || (LANG['save_error'] || 'Error saving data');
                        showUnitMessage(msg, 'error');
                        log('unit_save_failed',
                            `Lưu đơn vị thất bại (server). Tên="${unitName}". Lý do: ${msg}`,
                            'error'
                        );
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showUnitMessage(LANG['server_error'] || 'Server error', 'error');
                log('unit_save_failed',
                    `Lỗi JS/mạng khi lưu đơn vị. action=${action}, tên="${unitName}": ${textStatus} - ${errorThrown}`,
                    'error'
                );
            },
            complete: function() {
                saveButton.prop('disabled', false).html(action === 'edit'
                    ? (LANG['update'] || 'Update')
                    : (LANG['save'] || 'Save'));
            }
        });
    });

    // Xóa Đơn vị (Delegated event)
    tableBody.on('click', '.btn-delete-unit', function() {
        const button = $(this);
        const unitId = button.data('id');
        const unitName = button.data('name') || '';

        log('unit_delete_click', `Click XÓA đơn vị (id=${unitId}) — "${unitName}"`, 'warn');

        const confirmMessage = (LANG['confirm_delete_unit'] || 'Are you sure you want to delete the unit "%s"?').replace('%s', unitName);

        if (confirm(confirmMessage)) {
            button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

            $.ajax({
                url: 'process/units_handler.php',
                type: 'POST',
                data: { action: 'delete', id: unitId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showUnitMessage(response.message || (LANG['delete_success'] || 'Deleted successfully!'));
                        // Xóa hàng khỏi bảng bằng JS
                        button.closest('tr').fadeOut(300, function() { $(this).remove(); });
                        log('unit_delete_success', `Xóa thành công đơn vị (id=${unitId}) — "${unitName}"`, 'info');
                    } else {
                        const msg = response.message || (LANG['delete_error'] || 'Error deleting data');
                        showUnitMessage(msg, 'error');
                        log('unit_delete_failed', `Xóa đơn vị thất bại (id=${unitId}) — "${unitName}". Lý do: ${msg}`, 'error');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                    showUnitMessage(LANG['server_error'] || 'Server error', 'error');
                    log('unit_delete_failed', `Lỗi JS/mạng khi xóa đơn vị (id=${unitId}) — "${unitName}": ${textStatus} - ${errorThrown}`, 'error');
                },
                complete: function() {
                    // Khôi phục nút nếu không bị xóa khỏi DOM
                    if (button.closest('tr').length) {
                        button.prop('disabled', false).html('<i class="bi bi-trash"></i>');
                    }
                }
            });
        }
    });

    console.log("units.js loaded.");
    log('units_js_loaded', 'File assets/js/units.js đã load', 'info');
}); // End document ready
