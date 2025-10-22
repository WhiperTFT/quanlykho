/* file assets/js/driver_trips.js */
function formatDetails(orderId) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'process/sales_order_handler.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get_details',
                id: orderId
            },
            success: function(response) {
                if (response.success && response.data && response.data.details && response.data.details.length > 0) {
                    let items = response.data.details;
                    let itemsHtml = '<div class="p-2 bg-light border rounded"><table class="table table-sm table-bordered" style="margin: 0;">';
                    itemsHtml += '<thead class="table-secondary"><tr><th>Sản phẩm</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>';
                    
                    items.forEach(item => {
                        const formatCurrency = (num) => (num ? parseFloat(num).toLocaleString('vi-VN', { style: 'currency', currency: 'VND' }) : '0 đ');
                        const formatNumber = (num) => (num ? parseFloat(num).toLocaleString('vi-VN') : '0');
                        const lineTotal = (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);

                        itemsHtml += `
                            <tr>
                                <td>${item.product_name_snapshot || 'N/A'}</td>
                                <td>${formatNumber(item.quantity)}</td>
                                <td>${formatCurrency(item.unit_price)}</td>
                                <td>${formatCurrency(lineTotal)}</td>
                            </tr>`;
                    });

                    itemsHtml += '</tbody></table></div>';
                    resolve(itemsHtml);
                } else {
                    resolve('<div class="p-2 text-center">Không tìm thấy chi tiết sản phẩm cho đơn hàng này.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error("--- AJAX ERROR ---");
                console.error("Lỗi khi lấy chi tiết đơn hàng.");
                console.error("Status:", status);
                console.error("Error:", error);
                console.error("Response from server:", xhr.responseText);
                reject('<div class="p-2 text-danger text-center">Đã xảy ra lỗi khi tải dữ liệu. Vui lòng kiểm tra console (F12).</div>');
            }
        });
    });
}

$(document).ready(function() {
    // Hàm khởi tạo Flatpickr
    function initializeFlatpickr() {
        $('.delivery-date-input').each(function() {
            // Hủy Flatpickr cũ nếu tồn tại
            if (this._flatpickr) {
                this._flatpickr.destroy();
            }
            flatpickr(this, {
                dateFormat: 'd/m/Y',
                allowInput: true,
                closeOnSelect: true,
                onChange: function(selectedDates, dateStr, instance) {
                    const input = instance._input;
                    const orderId = $(input).data('order-id');
                    if (orderId && dateStr && !input.dataset.saving) {
                        input.dataset.saving = 'true';
                        const [day, month, year] = dateStr.split('/');
                        const dbDate = `${year}-${month}-${day}`;
                        fetch('process/save_delivery_date.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order_id: orderId, delivery_date: dbDate })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({ icon: 'success', title: 'Đã lưu', text: 'Ngày giao đã được cập nhật!', timer: 1500, showConfirmButton: false });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message });
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire({ icon: 'error', title: 'Lỗi mạng', text: 'Không thể kết nối máy chủ.' });
                        })
                        .finally(() => {
                            delete input.dataset.saving;
                        });
                    }
                }
            });
        });
    }

    // Xử lý đồng bộ thanh cuộn
    const $realScroll = $('#scroll-wrapper');
    const $fakeScroll = $('#sticky-scroll');
    const $fakeTrack = $('#scroll-fake-track');
    const $dataTable = $('#driver-trips-table');

    function updateFakeScrollbarWidth() {
        const tableWidth = $dataTable.outerWidth();
        $fakeTrack.width(tableWidth);
        $fakeScroll.css('width', $realScroll.width());
    }

    function syncScrollPercent(from, to) {
        if (from.scrollWidth > from.clientWidth) {
            const percent = from.scrollLeft / (from.scrollWidth - from.clientWidth);
            to.scrollLeft = percent * (to.scrollWidth - to.clientWidth);
        }
    }

    $fakeScroll.on('scroll', function() {
        syncScrollPercent(this, $realScroll[0]);
    });

    $realScroll.on('scroll', function() {
        syncScrollPercent(this, $fakeScroll[0]);
    });

    // Xử lý mở popup và tải danh sách attachment
    $('#driver-trips-table').on('click', '.manage-attachments-btn', function() {
        const orderId = $(this).data('order-id');
        $('#attachmentsModal').data('order-id', orderId);
        $('#attachmentsModalLabel').text(`Quản lý Chứng từ/Bản đồ - Đơn hàng ${orderId}`);

        $.ajax({
            url: 'process/get_attachments.php',
            type: 'GET',
            dataType: 'json',
            data: { order_id: orderId },
            success: function(response) {
                if (response.success && response.attachments) {
                    let html = '';
                    response.attachments.forEach(attachment => {
                        html += `
                            <div class="attachment-item d-flex align-items-center mb-2">
                                ${attachment.type === 'file' ? `
                                    <a href="Uploads/proof/${encodeURIComponent(attachment.path)}" target="_blank" class="btn btn-sm btn-outline-success me-1">
                                        <i class="bi bi-file-earmark"></i> Xem file
                                    </a>
                                ` : `
                                    <a href="${attachment.path}" target="_blank" class="btn btn-sm btn-outline-info me-1">
                                        <i class="bi bi-map"></i> Xem bản đồ
                                    </a>
                                    <input type="text" class="form-control form-control-sm me-1" value="${attachment.path}" readonly onclick="this.select(); document.execCommand('copy'); alert('Đã sao chép link!');">
                                `}
                                <button class="btn btn-sm btn-outline-danger remove-attachment-btn" data-attachment-id="${attachment.id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>`;
                    });
                    $('#attachments-list').html(html);
                } else {
                    $('#attachments-list').html('<p>Chưa có chứng từ hoặc link bản đồ.</p>');
                }
            },
            error: function() {
                $('#attachments-list').html('<p class="text-danger">Lỗi khi tải danh sách attachment.</p>');
            }
        });

        $('#attachmentsModal').modal('show');
    });

    // Xử lý upload file trong popup
    $('#modal-upload-proof').on('change', function() {
        const file = this.files[0];
        if (!file) return;

        const orderId = $('#attachmentsModal').data('order-id');
        const formData = new FormData();
        formData.append('delivery_proof', file);
        formData.append('order_id', orderId);
        formData.append('type', 'file');

        fetch('process/upload_delivery_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Đã tải lên', text: data.message, timer: 1500, showConfirmButton: false });
                $('.manage-attachments-btn[data-order-id="' + orderId + '"]').click();
            } else {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message });
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối máy chủ.' });
        });
    });

    // Xử lý thêm link URL trong popup
    $('#modal-add-url').on('click', function() {
        const orderId = $('#attachmentsModal').data('order-id');
        const url = $('#modal-url-input').val().trim();
        if (!url) {
            Swal.fire({ icon: 'warning', title: 'Lỗi', text: 'Vui lòng nhập link.' });
            return;
        }

        fetch('process/add_delivery_url.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, url: url, type: 'url' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Đã thêm', text: data.message, timer: 1500, showConfirmButton: false });
                $('#modal-url-input').val('');
                $('.manage-attachments-btn[data-order-id="' + orderId + '"]').click();
            } else {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message });
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối máy chủ.' });
        });
    });

    // Xử lý xóa attachment trong popup
    $('#attachmentsModal').on('click', '.remove-attachment-btn', function() {
        const attachmentId = $(this).data('attachment-id');
        if (!attachmentId) return;

        Swal.fire({
            title: 'Xác nhận xóa',
            text: 'Bạn có chắc muốn xóa attachment này?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Xóa',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('process/remove_delivery_attachment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ attachment_id: attachmentId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Đã xóa', text: data.message, timer: 1500, showConfirmButton: false });
                        const orderId = $('#attachmentsModal').data('order-id');
                        $('.manage-attachments-btn[data-order-id="' + orderId + '"]').click();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối máy chủ.' });
                });
            }
        });
    });

    // --- LOGIC 1: TỰ ĐỘNG SUBMIT FORM KHI CHỌN TÀI XẾ ---
    $('#driver_id').on('change', function() {
        const selectedDriverId = $(this).val();
        if (selectedDriverId) {
            $('#driver-filter-form').submit();
        }
    });

    // --- LOGIC 2: KHỞI TẠO DATATABLES VÀ BỘ LỌC TÙY CHỈNH ---
    if ($('#driver-trips-table').length) {
        let showZeroValueTrips = false;

        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'driver-trips-table') {
                    return true;
                }
                const shippingCost = parseFloat(data[7].replace(/\./g, '')) || 0;
                return showZeroValueTrips || shippingCost !== 0;
            }
        );

        const table = $('#driver-trips-table').DataTable({
            "columns": [
                { "className": 'dt-control', "orderable": false, "data": null, "defaultContent": '' },
                null,
                null,
                null,
                null,
                null,
                null,
                { "className": 'text-end', "orderable": false },
                { "orderable": false }
            ],
            "order": [[1, 'asc']],
            "language": { "url": `lang/${$('html').attr('lang') === 'vi' ? 'vi' : 'en-GB'}.json` },
            "info": true,
            "paging": false,
            "searching": true,
            "drawCallback": function() {
                initializeFlatpickr();
                updateFakeScrollbarWidth();
            }
        });

        $('#driver-trips-table tbody').on('click', 'td.dt-control', function () {
            const tr = $(this).closest('tr');
            if (tr.parents('tbody').length === 0) return;

            const row = table.row(tr);
            const orderId = tr.data('order-id');

            if (!orderId) {
                console.error('LỖI: Không tìm thấy data-order-id trên thẻ <tr>. Hàng đang click:', tr);
                return;
            }

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
            } else {
                tr.addClass('loading');
                formatDetails(orderId).then(detailsHtml => {
                    row.child(detailsHtml, 'details-row').show();
                    tr.removeClass('loading').addClass('shown');
                }).catch(errorHtml => {
                    row.child(errorHtml, 'details-row-error').show();
                    tr.removeClass('loading');
                });
            }
        });

        // --- LOGIC 4: BẬT/TẮT HIỂN THỊ CÁC CHUYẾN ĐI GIÁ TRỊ 0 ---
        $('#toggle-zero-trips-btn').on('click', function() {
            showZeroValueTrips = !showZeroValueTrips;
            const button = $(this);
            button.text(showZeroValueTrips ? 'Ẩn các chuyến đi giá trị 0' : 'Hiện các chuyến đi giá trị 0');
            table.draw();
            updateFakeScrollbarWidth();
        });

        // Khởi tạo thanh cuộn giả
        updateFakeScrollbarWidth();
        $(window).on('resize', updateFakeScrollbarWidth);
    }

    // Khởi tạo Flatpickr lần đầu
    initializeFlatpickr();

    // --- LOGIC 3: TÍNH LƯƠNG, ĐIỀU CHỈNH VÀ LƯU BẢNG LƯƠNG ---
    if ($('#salary-calculation-section').length) {
        const calculationSection = $('#salary-calculation-section');
        const totalShippingCostEl = $('#total-shipping-cost');
        const finalPayoutEl = $('#final-payout');
        const adjustmentsList = $('#adjustments-list');
        const saveButton = $('#save-adjustments-btn');

        function formatNumber(num) {
            return num.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
        }

        function calculateFinalPayout() {
            let totalShippingCost = parseFloat(totalShippingCostEl.text().replace(/\./g, '')) || 0;
            let totalAdjustments = 0;
            calculationSection.find('.adjustment-row').each(function() {
                const amountStr = $(this).find('.adjustment-amount').val().replace(/\./g, '');
                let amount = parseFloat(amountStr) || 0;
                const type = $(this).find('.adjustment-type').val();
                if (type === 'subtract') { amount = -amount; }
                totalAdjustments += amount;
            });
            const finalPayout = totalShippingCost + totalAdjustments;
            finalPayoutEl.text(formatNumber(finalPayout));
        }

        $('#add-adjustment-btn').on('click', function() {
            const newRowHtml = `
                <div class="adjustment-row row g-2 align-items-center p-2">
                    <div class="col-md-5"><input type="text" class="form-control adjustment-description" placeholder="Diễn giải (VD: Phụ cấp xăng xe)"></div>
                    <div class="col-md-3"><select class="form-select adjustment-type"><option value="add">Cộng (+)</option><option value="subtract">Trừ (-)</option></select></div>
                    <div class="col-md-4"><div class="input-group"><input type="text" class="form-control adjustment-amount text-end" placeholder="0"><button class="btn btn-outline-danger remove-adjustment-btn" type="button"><i class="bi bi-trash"></i></button></div></div>
                </div>`;
            adjustmentsList.append(newRowHtml);
        });

        adjustmentsList.on('click', '.remove-adjustment-btn', function() {
            $(this).closest('.adjustment-row').remove();
            calculateFinalPayout();
        });

        adjustmentsList.on('input', '.adjustment-amount', function() {
            const input = $(this);
            let num = input.val().replace(/\./g, '');
            if (!isNaN(num)) { input.val(formatNumber(num)); }
            calculateFinalPayout();
        });

        adjustmentsList.on('change', '.adjustment-type', function() {
            calculateFinalPayout();
        });

        saveButton.on('click', function() {
            const button = $(this);
            button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang lưu...');
            
            const adjustmentsData = [];
            calculationSection.find('.adjustment-row').each(function() {
                const description = $(this).find('.adjustment-description').val();
                const type = $(this).find('.adjustment-type').val();
                const amount = $(this).find('.adjustment-amount').val().replace(/\./g, '');
                if (description.trim() !== '') {
                    adjustmentsData.push({ description, type, amount });
                }
            });

            const payload = {
                driver_id: button.data('driver-id'),
                year: button.data('year'),
                month: button.data('month'),
                adjustments: adjustmentsData
            };

            fetch('process/save_driver_adjustments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Thành công!', text: data.message, timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Có lỗi xảy ra', text: data.message });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({ icon: 'error', title: 'Lỗi kết nối', text: 'Không thể kết nối đến máy chủ.' });
            })
            .finally(() => {
                button.prop('disabled', false).html('<i class="bi bi-save"></i> Lưu Bảng Lương');
            });
        });

        calculateFinalPayout();
    }
});