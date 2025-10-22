// assets/js/script.js
console.log("Custom script loaded.");

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

// --- Các hàm JS dùng chung khác sẽ được thêm vào đây ---