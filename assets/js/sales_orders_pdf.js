// File: assets/js/sales_orders_pdf.js

// --- Hàm Xuất PDF ---
function downloadOrderPDF() {
    console.log("Generating Order PDF for viewing and server-side saving...");
    const elementToCapture = document.getElementById('pdf-export-content');
    const downloadButton = $('#btn-download-pdf');
    const buttonText = downloadButton.find('.export-text');
    const buttonSpinner = downloadButton.find('.spinner-border');
    let filename = sanitizeFilename($('#order_number').val());

    if (!elementToCapture) { console.error("PDF export failed: #pdf-export-content not found."); showUserMessage(LANG['pdf_export_error_element'] || 'Không tìm thấy nội dung xuất PDF.', 'error'); return; }
    if (!filename) {
        const orderIdForFilename = $('#order_id').val() || Date.now();
        filename = 'sales_order_' + orderIdForFilename;
        console.warn("Order number empty, using default filename:", filename);
    }

    downloadButton.prop('disabled', true);
    buttonText.hide();
    buttonSpinner.removeClass('d-none');

    const pdfContentArea = $(elementToCapture);
    const elementsToHide = pdfContentArea.find('#add-item-row, #btn-generate-order-number, .remove-item-row, #add-item-row-container, #save-signature-pos-size, #signature-feedback, #signature-upload, #toggle-signature, .action-cell-item, .tox-menubar, .tox-toolbar-container, .tox-statusbar , .tox-editor-header, #form-error-message, .invalid-feedback');
    console.log(`Hiding ${elementsToHide.length} elements for PDF export.`);
    elementsToHide.addClass('hide-on-pdf-export');

    new Promise((resolve, reject) => {
        html2canvas(elementToCapture, {
            scale: 0.8, // Tỷ lệ scale, điều chỉnh nếu cần
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        }).then(resolve).catch(reject);
    })
        .then(canvas => {
            console.log("html2canvas rendered successfully.");
            const imgData = canvas.toDataURL('image/png');
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

            const imageProperties = pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = pdf.internal.pageSize.getHeight();
            const margin = 10;
            const availableWidth = pdfWidth - 2 * margin;
            const availableHeight = pdfHeight - 2 * margin;
            let imageWidth = imageProperties.width;
            let imageHeight = imageProperties.height;
            const imageRatio = imageWidth / imageHeight;

            if (imageWidth > availableWidth) { imageWidth = availableWidth; imageHeight = imageWidth / imageRatio; }
            if (imageHeight > availableHeight) { imageHeight = availableHeight; imageWidth = imageHeight * imageRatio; }

            const marginLeft = (pdfWidth - imageWidth) / 2;
            const marginTop = margin;
            pdf.addImage(imgData, 'PNG', marginLeft, marginTop, imageWidth, imageHeight);
            console.log("Image added to jsPDF.");

            try {
                const pdfBlob = pdf.output('blob');
                const blobUrl = URL.createObjectURL(pdfBlob);
                const newWindow = window.open(blobUrl, '_blank');
                if (!newWindow) {
                    showUserMessage(LANG['popup_blocked_pdf'] || 'Trình duyệt đã chặn popup xem trước PDF. Vui lòng cho phép hiển thị popup.', 'warning');
                } else { console.log("PDF opened in new tab."); }
            } catch (viewError) {
                console.error("Error opening PDF blob:", viewError);
                showUserMessage(LANG['pdf_open_error'] || 'Không thể mở xem trước PDF. Vui lòng thử lại.', 'error');
            }

            try {
                const pdfBase64 = pdf.output('datauristring');
                console.log("Sending PDF to server for saving...");
                $.ajax({
                    url: PROJECT_BASE_URL + 'includes/save_pdf.php', // Đảm bảo PROJECT_BASE_URL được định nghĩa đúng
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ filename: filename + '.pdf', pdf_data: pdfBase64 }),
                    dataType: 'json',
                    success: function (saveResponse) {
                        if (saveResponse.success) {
                            console.log('PDF saved on server successfully:', filename + '.pdf');
                        } else {
                            console.error('Server save error:', saveResponse.message);
                            showUserMessage(LANG['pdf_saved_server_error'] || 'Lỗi khi lưu PDF trên máy chủ: ' + escapeHtml(saveResponse.message), 'error');
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error('AJAX Save Error:', textStatus, errorThrown);
                        showUserMessage(LANG['server_error_saving_pdf'] || 'Lỗi máy chủ khi gửi yêu cầu lưu PDF.', 'error');
                    }
                });
            } catch (ajaxError) {
                console.error("AJAX init error for saving PDF:", ajaxError);
                showUserMessage(LANG['pdf_save_request_error'] || 'Không thể gửi yêu cầu lưu PDF.', 'error');
            }
        })
        .catch(error => {
            console.error("html2canvas or Promise failed:", error);
            showUserMessage(LANG['pdf_export_render_error'] || 'Lỗi khi tạo ảnh từ nội dung.', 'error');
        })
        .finally(() => {
            console.log("Restoring hidden elements and enabling button.");
            elementsToHide.removeClass('hide-on-pdf-export');
            downloadButton.prop('disabled', false);
            buttonText.show();
            buttonSpinner.addClass('d-none');
            console.log("PDF generation/save process finished.");
        });
}