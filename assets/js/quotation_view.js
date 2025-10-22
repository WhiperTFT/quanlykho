// File: quotation_view.js
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            if (!id) return;

            const modalContainer = document.getElementById('viewModal');
            if (!modalContainer) {
                console.error("❌ Modal phần tử không tồn tại trong DOM. Đảm bảo đã include 'quotation_view_modal.php' trước khi load JS.");
                return;
            }

            fetch('ajax_get_quotation.php?id=' + id)
                .then(res => res.json())
                .then(data => {
                    const titleElem = document.getElementById("viewModalLabel");
                    const bodyElem = document.getElementById("viewModalBody");

                    if (!titleElem || !bodyElem) {
                        console.error("Modal phần tử không tồn tại!");
                        return;
                    }

                    titleElem.textContent = data.title || 'Chi tiết báo giá';

                    let html = `
                        <p><strong>Đối tác:</strong> ${data.partner_name || ''}</p>
                        <p><strong>Ngày báo giá:</strong> ${data.quotation_date}</p>
                        <p><strong>Ghi chú:</strong> ${data.notes || 'Không có'}</p>
                        <table class="table table-bordered mt-3">
                            <thead>
                                <tr><th>Số lượng</th><th>Giá</th></tr>
                            </thead>
                            <tbody>`;

                    data.details.forEach(item => {
                        let unit = item.unit || 'cái';
                        let qty = formatNumber(item.qty) + ' ' + unit;
                        let price = formatNumber(item.price) + ' đ'; // hoặc '$'
                        html += `
                            <tr>
                                <td>${qty}</td>
                                <td>${price}</td>
                            </tr>`;
                    });

                    html += `</tbody></table>`;
                    bodyElem.innerHTML = html;

                    const modal = new bootstrap.Modal(modalContainer);
                    modal.show();
                })
                .catch(err => {
                    console.error("Lỗi lấy dữ liệu báo giá:", err);
                    Swal.fire("Lỗi", "Không thể tải chi tiết báo giá.", "error");
                });
        });
    });
});

// ✅ Hàm định dạng số có dấu ngăn cách hàng nghìn
function formatNumber(n) {
    return new Intl.NumberFormat('vi-VN').format(n);
}
