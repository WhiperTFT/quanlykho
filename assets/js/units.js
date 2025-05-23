// File: assets/js/units.js
// Cần jQuery và Bootstrap JS đã được load

$(document).ready(function() {
    const unitModalElement = document.getElementById('unitModal');
    const unitModal = unitModalElement ? new bootstrap.Modal(unitModalElement) : null;
    const form = $('#unitForm');
    const saveButton = $('#saveUnitBtn');
    const tableBody = $('#unitsTableBody');

    if (!unitModal) {
        console.error("Unit modal element not found.");
        // Có thể dừng script nếu modal là cần thiết
        // return;
    }

    // --- Helper Functions ---
    function showUnitMessage(message, type = 'success') {
        alert(type.toUpperCase() + ": " + message); // Tạm dùng alert
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
    });

    // Mở Modal để Sửa (Delegated event)
    tableBody.on('click', '.btn-edit-unit', function() {
        if (!unitModal) return;
        const unitId = $(this).data('id');
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
                } else {
                    showUnitMessage(response.message || LANG['error_fetching_data'] || 'Error fetching unit data', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                 showUnitMessage(LANG['server_error'] || 'Server error', 'error');
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

        if (name.length < 1) {
            return;
        }

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
                    } else {
                        // Không cần làm gì nếu không trùng
                    }
                },
                error: function() {
                     console.error("Error checking unit duplicate name.");
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

        // Kiểm tra lỗi validation client-side trước khi gửi
        if (form.find('.is-invalid').length > 0) {
             showUnitMessage(LANG['fix_errors_before_saving'] || 'Please fix the errors before saving.', 'warning');
             return;
        }

        saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + (LANG['saving'] || 'Saving...'));

        $.ajax({
            url: 'process/units_handler.php',
            type: 'POST',
            data: form.serialize() + '&action=' + action, // Gửi dữ liệu form + action
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    unitModal.hide();
                    showUnitMessage(response.message || LANG['save_success'] || 'Saved successfully!');
                    // Cập nhật bảng bằng JS thay vì reload sẽ tốt hơn
                    // updateUnitsTable(action, response.data);
                } else {
                    if (response.errors) {
                        handleUnitValidationErrors(response.errors);
                    } else {
                        // Lỗi chung khác
                        showUnitMessage(response.message || LANG['save_error'] || 'Error saving data', 'error');
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showUnitMessage(LANG['server_error'] || 'Server error', 'error');
            },
            complete: function() {
                 saveButton.prop('disabled', false).html(action === 'edit' ? (LANG['update'] || 'Update') : (LANG['save'] || 'Save'));
            }
        });
    });

    // Xóa Đơn vị (Delegated event)
    tableBody.on('click', '.btn-delete-unit', function() {
        const button = $(this);
        const unitId = button.data('id');
        const unitName = button.data('name');

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
                        showUnitMessage(response.message || LANG['delete_success'] || 'Deleted successfully!');
                        // Xóa hàng khỏi bảng bằng JS
                        button.closest('tr').fadeOut(300, function() { $(this).remove(); });
                    } else {
                        // Hiển thị lỗi cụ thể (ví dụ: đang được sử dụng)
                        showUnitMessage(response.message || LANG['delete_error'] || 'Error deleting data', 'error');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                    showUnitMessage(LANG['server_error'] || 'Server error', 'error');
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

}); // End document ready
