// assets/js/drivers.js
$(document).ready(function() {
    const driverModalElement = document.getElementById('driverModal');
    const driverModal = new bootstrap.Modal(driverModalElement);
    const driverForm = $('#driverForm');
    const modalTitle = $('#driverModalLabel');
    const modalErrorMessage = $('#modal-error-message');
    const loadingRow = $('#loading-row');

    // --- Các biến ngôn ngữ (nên được truyền từ PHP nếu có thể, ví dụ qua data attributes hoặc một object global) ---
    // Đây là các giá trị mặc định, bạn nên thay thế bằng $lang['...'] từ PHP nếu cần đa ngôn ngữ động trong JS
    const lang = {
        add_driver: 'Thêm Tài xế',
        edit_driver: 'Sửa thông tin Tài xế',
        loading_data: 'Đang tải dữ liệu...',
        no_data_found: 'Không tìm thấy dữ liệu tài xế.',
        confirm_delete_driver: 'Bạn có chắc chắn muốn xóa tài xế này?',
        error_occurred: 'Đã có lỗi xảy ra. Vui lòng thử lại.',
        // Thêm các thông báo khác nếu cần
        driver_name_label: 'Tên Tài xế',
        cccd_label: 'CCCD',
        issue_date_label: 'Ngày cấp',
        issue_place_label: 'Nơi cấp',
        phone_label: 'SĐT',
        license_plates_label: 'Biển số xe',
        notes_label: 'Ghi chú',
        action_label: 'Hành động'
    };

    // --- Hàm tải danh sách tài xế ---
    function loadDrivers(searchTerm = '') {
        loadingRow.html(`<td colspan="9" class="text-center">${lang.loading_data}</td>`).show();
        $('#driver-table tbody').find("tr:not(#loading-row)").remove(); // Xóa các dòng cũ, trừ dòng loading

        $.ajax({
            url: 'process/drivers_controller.php', // Đảm bảo đường dẫn này đúng
            type: 'GET',
            data: {
                action: 'list',
                search: searchTerm
            },
            dataType: 'json',
            success: function(response) {
                loadingRow.hide();
                const drivers = response.data;
                let rows = '';
                if (drivers && drivers.length > 0) {
                    drivers.forEach(function(driver, index) {
                        rows += `
    <tr>
        <td>${index + 1}</td>
        <td>${escapeHtml(driver.ten)}</td>
        <td>${escapeHtml(driver.cccd)}</td>
        <td>${escapeHtml(driver.ngay_cap || '')}</td>
        <td>${escapeHtml(driver.noi_cap || '')}</td>
        <td>${escapeHtml(driver.sdt)}</td>
        <td>${escapeHtml(driver.bien_so_xe || '')}</td>
        <td>${escapeHtml(driver.ghi_chu || '')}</td>
        <td class="action-links">
            <button class="btn btn-sm btn-info editDriverBtn" data-id="${driver.id}" title="Sửa"><i class="bi bi-pencil-square"></i></button>
            <button class="btn btn-sm btn-danger deleteDriverBtn" data-id="${driver.id}" title="Xóa"><i class="bi bi-trash"></i></button>
        </td>
        <td>
            <button class="btn btn-outline-primary btn-sm btn-view-card" data-id="${driver.id}" title="Xem danh thiếp">
                <i class="bi bi-person-vcard"></i>
            </button>
        </td>
    </tr>
`;
                    });
                } else {
                    rows = `<tr><td colspan="9" class="text-center">${lang.no_data_found}</td></tr>`;
                }
                $('#driver-table tbody').append(rows);
            },
            error: function(xhr, status, error) {
                loadingRow.hide();
                $('#driver-table tbody').append(`<tr><td colspan="9" class="text-center text-danger">Lỗi tải dữ liệu: ${error}</td></tr>`);
                console.error("Error loading drivers:", status, error, xhr.responseText);
            }
        });
    }

    // --- Hàm thoát HTML để tránh XSS ---
    function escapeHtml(text) {
        if (text === null || typeof text === 'undefined') {
            return '';
        }
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // --- Hiển thị thông báo chung (ví dụ: sau khi thêm/sửa/xóa) ---
    function showGlobalMessage(message, type = 'success') {
        // Bạn có thể tạo một khu vực hiển thị thông báo ở đâu đó trên trang `driver.php`
        // Ví dụ: <div id="global-message-container" class="mt-3"></div>
        const messageContainer = $('#global-message-container');
        if (messageContainer.length === 0) {
            // Nếu chưa có container, tạo một cái tạm thời ở trên cùng
            $('h1.h2').after(`<div id="global-message-container" class="mt-3"></div>`);
             $('#global-message-container').html(`<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`);
        } else {
             messageContainer.html(`<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`);
        }
         // Tự động ẩn sau 5 giây
        setTimeout(() => {
            $('#global-message-container .alert').alert('close');
        }, 5000);
    }


    // --- Sự kiện khi nhấn nút "Thêm Tài xế" ---
    $('#addDriverBtn').on('click', function() {
        driverForm[0].reset(); // Reset form
        $('#driver_id').val(''); // Xóa ID (quan trọng cho việc phân biệt thêm mới và cập nhật)
        modalTitle.text(lang.add_driver);
        modalErrorMessage.addClass('d-none').text('');
        driverModal.show();
    });

    // --- Sự kiện khi submit form (Thêm/Sửa) ---
    driverForm.on('submit', function(e) {
        e.preventDefault();
        modalErrorMessage.addClass('d-none').text('');

        const driverId = $('#driver_id').val();
        const action = driverId ? 'update' : 'add';
        let url = 'process/drivers_controller.php?action=' + action;
        if (driverId) {
            url += '&id=' + driverId;
        }

        // Lấy dữ liệu từ form
        // Cách 1: FormData (nếu có file upload, tiện hơn)
        // const formData = new FormData(this);
        // Cách 2: serializeArray hoặc object (cho dữ liệu text đơn giản)
        const formDataObject = {};
        $(this).serializeArray().forEach(item => {
            formDataObject[item.name] = item.value;
        });

        // Client-side validation (cơ bản)
        if (!formDataObject.ten || !formDataObject.cccd ) {
            modalErrorMessage.text('Tên tài xế và CCCD là bắt buộc.').removeClass('d-none');
            return;
        }


        $.ajax({
            url: url,
            type: 'POST',
            data: formDataObject, // Hoặc formData nếu dùng FormData
            // processData: false, // Cần nếu dùng FormData
            // contentType: false, // Cần nếu dùng FormData
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    driverModal.hide();
                    showGlobalMessage(response.message || (action === 'add' ? 'Thêm tài xế thành công!' : 'Cập nhật tài xế thành công!'), 'success');
                    loadDrivers($('#driver-search').val()); // Tải lại danh sách
                } else {
                    modalErrorMessage.text(response.message || lang.error_occurred).removeClass('d-none');
                }
            },
            error: function(xhr, status, error) {
                modalErrorMessage.text(`Lỗi AJAX: ${error}. Chi tiết: ${xhr.responseText}`).removeClass('d-none');
                console.error("Form submission error:", status, error, xhr.responseText);
            }
        });
    });

    // --- Sự kiện khi nhấn nút "Sửa" trên dòng của bảng ---
    $('#driver-table').on('click', '.editDriverBtn', function() {
        const driverId = $(this).data('id');
        modalErrorMessage.addClass('d-none').text('');

        $.ajax({
            url: 'process/drivers_controller.php',
            type: 'GET',
            data: {
                action: 'get',
                id: driverId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const driver = response.data;
                    $('#driver_id').val(driver.id);
                    $('#driver_name').val(driver.ten);
                    $('#driver_cccd').val(driver.cccd);
                    $('#driver_ngay_cap').val(driver.ngay_cap);
                    $('#driver_noi_cap').val(driver.noi_cap);
                    $('#driver_sdt').val(driver.sdt);
                    $('#driver_bien_so_xe').val(driver.bien_so_xe);
                    $('#driver_ghi_chu').val(driver.ghi_chu);

                    modalTitle.text(lang.edit_driver);
                    driverModal.show();
                } else {
                     showGlobalMessage(response.message || 'Không tìm thấy thông tin tài xế.', 'danger');
                }
            },
            error: function(xhr, status, error) {
                 showGlobalMessage(`Lỗi khi tải dữ liệu tài xế: ${error}`, 'danger');
                console.error("Error fetching driver data for edit:", status, error, xhr.responseText);
            }
        });
    });

    // --- Sự kiện khi nhấn nút "Xóa" trên dòng của bảng ---
    $('#driver-table').on('click', '.deleteDriverBtn', function() {
        const driverId = $(this).data('id');

        if (confirm(lang.confirm_delete_driver)) {
            $.ajax({
                url: 'process/drivers_controller.php?action=delete&id=' + driverId,
                type: 'POST', // Hoặc GET tùy theo thiết kế API của bạn, POST an toàn hơn cho xóa
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showGlobalMessage(response.message || 'Xóa tài xế thành công!', 'success');
                        loadDrivers($('#driver-search').val()); // Tải lại danh sách
                    } else {
                        showGlobalMessage(response.message || lang.error_occurred, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    showGlobalMessage(`Lỗi khi xóa tài xế: ${error}`, 'danger');
                    console.error("Error deleting driver:", status, error, xhr.responseText);
                }
            });
        }
    });

    // --- Sự kiện tìm kiếm/lọc ---
    let searchTimeout;
    $('#driver-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        const searchTerm = $(this).val();
        searchTimeout = setTimeout(function() {
            loadDrivers(searchTerm);
        }, 300); // Đợi 300ms sau khi người dùng ngừng gõ rồi mới tìm kiếm
    });
    $(document).on('click', '.btn-view-card', function () {
    const driverId = $(this).data('id');
    if (!driverId) return;

    $.ajax({
        url: 'process/get_driver_card.php',
        method: 'POST',
        data: { id: driverId },
        success: function (res) {
            $('#driverCardContent').html(res);
            const modal = new bootstrap.Modal(document.getElementById('driverCardModal'));
            modal.show();
        },
        error: function () {
            $('#driverCardContent').html('<div class="text-danger">Không thể tải danh thiếp.</div>');
        }
    });
});
    $(document).on('click', '.btn-remove-field', function (e) {
        e.preventDefault();
        $(this).closest('p').fadeOut(200, function () {
            $(this).remove();
        });
    });

    // --- Tải danh sách tài xế khi trang được mở ---
    loadDrivers();
});