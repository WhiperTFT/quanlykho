// cleaned: console logs optimized, debug system applied
// assets/js/script.js
devLog("Custom script loaded.");

document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete'); // Add class="btn-delete" to your delete buttons/links

    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            // Lấy thông điệp xác nhận từ thuộc tính data-confirm hoặc dùng mặc định
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to delete this item?'; // TODO: Use $lang variable here if possible via JS vars

            if (!confirm(message)) {
                event.preventDefault(); // Ngăn chặn hành động mặc định (ví dụ: đi đến link href)
            }
        });
    });

function escapeHtml(unsafe) {
    if (unsafe === null || typeof unsafe === 'undefined') return '';
    return String(unsafe)
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

function sanitizeFilename(filename) {
    if (filename === null || typeof filename === 'undefined') return '';
    return String(filename).replace(/[^a-z0-9_.-]/gi, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '');
}

});

// --- Các hàm JS dùng chung khác ---

/**
 * Cookies Helpers for robust device identification
 */
function setCookie(name, value, days) {
    let expires = "";
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
}

function getCookie(name) {
    const nameEQ = name + "=";
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

/**
 * Fingerprint thiết bị để định danh máy đang sử dụng
 * Kết hợp LocalStorage và Cookie để đảm bảo PHP luôn nhận được ID qua mọi loại request
 */
function getDeviceId() {
    // Ưu tiên lấy từ LocalStorage
    let id = localStorage.getItem('device_id');
    
    // Nếu không có, thử lấy từ Cookie (phòng trường hợp trình duyệt xóa LS nhưng còn Cookie)
    if (!id) {
        id = getCookie('device_id');
    }

    // Nếu vẫn không có, tạo mới hoàn toàn
    if (!id) {
        id = 'DEV-' + Math.random().toString(36).substr(2, 8).toUpperCase();
    }
    
    // Đồng bộ ngược lại cả 2 nơi để đảm bảo tính nhất quán
    localStorage.setItem('device_id', id);
    setCookie('device_id', id, 365); // Hết hạn sau 1 năm
    
    return id;
}

// Khởi chạy ngay để Cookie được set sớm nhất có thể
getDeviceId();

// Tự động đính kèm device_id vào tất cả yêu cầu AJAX qua jQuery
if (typeof jQuery !== 'undefined') {
    $(document).ajaxSend(function(event, jqXHR, ajaxOptions) {
        const deviceId = getDeviceId();
        
        // Nếu là FormData (upload file), ta không can thiệp trực tiếp bằng string concat
        if (ajaxOptions.data instanceof FormData) {
            if (!ajaxOptions.data.has('device_id')) {
                ajaxOptions.data.append('device_id', deviceId);
            }
            return;
        }

        // Xử lý request JSON hoặc String
        if (typeof ajaxOptions.data === 'string') {
            try {
                // Kiểm tra xem có phải JSON không
                let json = JSON.parse(ajaxOptions.data);
                if (typeof json === 'object' && json !== null) {
                    json.device_id = deviceId;
                    ajaxOptions.data = JSON.stringify(json);
                }
            } catch (e) {
                // Không phải JSON, xử lý như query string
                if (ajaxOptions.data.indexOf('device_id=') === -1) {
                    ajaxOptions.data += (ajaxOptions.data ? '&' : '') + 'device_id=' + encodeURIComponent(deviceId);
                }
            }
        } else if (typeof ajaxOptions.data === 'object' && ajaxOptions.data !== null) {
            ajaxOptions.data.device_id = deviceId;
        } else if (!ajaxOptions.data) {
            ajaxOptions.data = 'device_id=' + encodeURIComponent(deviceId);
        }
    });

    // Tự động chèn device_id vào tất cả các Form HTML chuẩn khi submit
    $(document).on('submit', 'form', function() {
        const $form = $(this);
        const deviceId = getDeviceId();
        
        // Nếu form đã có device_id thì cập nhật, nếu chưa thì chèn mới
        let $input = $form.find('input[name="device_id"]');
        if ($input.length === 0) {
            $('<input>').attr({
                type: 'hidden',
                name: 'device_id',
                value: deviceId
            }).appendTo($form);
        } else {
            $input.val(deviceId);
        }
    });
}