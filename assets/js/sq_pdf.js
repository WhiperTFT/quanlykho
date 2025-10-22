// File: assets/js/sq_pdf.js

// --- Hàm Xuất PDF Báo Giá ---
function downloadQuotePDF() {
    console.log("Generating Quote PDF for viewing and server-side saving...");
    const elementToCapture = document.getElementById('pdf-export-content'); // ID chung
    const downloadButton = $('#btn-download-pdf'); // ID chung
    const buttonText = downloadButton.find('.export-text'); // class chung
    const buttonSpinner = downloadButton.find('.spinner-border'); // class chung, kiểm tra lại nếu class spinner khác cho quote: .spinner-bquote
    let filename = sanitizeFilename($('#quote_number').val()); // ID cho số báo giá

    if (!elementToCapture) { console.error("PDF export failed: #pdf-export-content not found."); showUserMessage(LANG['pdf_export_error_element'] || 'Không tìm thấy nội dung PDF.', 'error'); return; }
    if (!filename) {
        const quoteIdForFilename = $('#quote_id').val() || Date.now(); // ID cho báo giá
        filename = 'sales_quote_' + quoteIdForFilename; // Tên file mặc định cho báo giá
        console.warn("Quote number empty, using default filename:", filename);
    }

    downloadButton.prop('disabled', true); buttonText.hide(); buttonSpinner.removeClass('d-none');
    const pdfContentArea = $(elementToCapture);
    const elementsToHide = pdfContentArea.find('#add-item-row, #btn-generate-quote-number, .remove-item-row, #add-item-row-container, #save-signature-pos-size, #signature-feedback, #signature-upload, #toggle-signature, .action-cell-item, .tox-menubar, .tox-toolbar-container, .tox-statusbar , .tox-editor-header, #form-error-message, .invalid-feedback');
    elementsToHide.addClass('hide-on-pdf-export');

    new Promise((resolve, reject) => {
        html2canvas(elementToCapture, { scale: 0.8, useCORS: true, logging: false, backgroundColor: '#ffffff' }).then(resolve).catch(reject);
    })
    .then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
        const imgProps = pdf.getImageProperties(imgData);
        const pdfW = pdf.internal.pageSize.getWidth(), pdfH = pdf.internal.pageSize.getHeight();
        const margin = 10, availW = pdfW - 2 * margin, availH = pdfH - 2 * margin;
        let imgW = imgProps.width, imgH = imgProps.height, ratio = imgW / imgH;
        if (imgW > availW) { imgW = availW; imgH = imgW / ratio; }
        if (imgH > availH) { imgH = availH; imgW = imgH * ratio; }
        pdf.addImage(imgData, 'PNG', (pdfW - imgW) / 2, margin, imgW, imgH);

        try {
            const pdfBlob = pdf.output('blob'); const blobUrl = URL.createObjectURL(pdfBlob);
            const newWin = window.open(blobUrl, '_blank');
            if (!newWin) showUserMessage(LANG['popup_blocked_pdf'] || 'Trình duyệt chặn popup PDF.', 'warning');
        } catch (e) { showUserMessage(LANG['pdf_open_error'] || 'Không thể mở PDF.', 'error'); }

        try {
            const pdfBase64 = pdf.output('datauristring');
            $.ajax({
                url: PROJECT_BASE_URL + 'includes/save_pdf.php', // Endpoint chung để lưu PDF
                type: 'POST', contentType: 'application/json',
                data: JSON.stringify({ filename: filename + '.pdf', pdf_data: pdfBase64, type: 'quote' }), // Thêm type
                dataType: 'json',
                success: (res) => {
                    if (res.success) console.log('Quote PDF saved on server:', filename + '.pdf');
                    else showUserMessage(LANG['pdf_saved_server_error'] || 'Lỗi lưu PDF trên server: ' + escapeHtml(res.message), 'error');
                },
                error: () => showUserMessage(LANG['server_error_saving_pdf'] || 'Lỗi server khi lưu PDF báo giá.', 'error')
            });
        } catch (e) { showUserMessage(LANG['pdf_save_request_error'] || 'Không thể gửi yêu cầu lưu PDF báo giá.', 'error');}
    })
    .catch(() => showUserMessage(LANG['pdf_export_render_error'] || 'Lỗi tạo ảnh PDF báo giá.', 'error'))
    .finally(() => {
        elementsToHide.removeClass('hide-on-pdf-export');
        downloadButton.prop('disabled', false); buttonText.show(); buttonSpinner.addClass('d-none');
    });
}