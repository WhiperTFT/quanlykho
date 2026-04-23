// cleaned: console logs optimized, debug system applied
// File: assets/js/sq_pdf.js
function downloadQuotePDF() {
    const pdfContentArea = $('#pdf-export-content');
    const downloadButton = $('#btn-download-pdf');
    const buttonText = downloadButton.find('.download-text');
    const buttonSpinner = downloadButton.find('.spinner-border');
    const quoteNumber = $('#quote_number').val() || 'BaoGia';
    const pdf_lang = $('#pdf_language_selector').val() || 'vi';

    if (!pdfContentArea.length) {
        showUserMessage(LANG['pdf_content_not_found'] || 'Không tìm thấy vùng nội dung PDF.', 'error');
        return;
    }

    downloadButton.prop('disabled', true);
    buttonText.hide();
    buttonSpinner.removeClass('d-none');

    const elementsToHide = pdfContentArea.find('#add-item-row, #btn-generate-quote-number, .remove-item-row, #add-item-row-container, #save-signature-pos-size, #signature-feedback, #signature-upload, #toggle-signature, .action-cell-item, .tox-menubar, .tox-toolbar-container, .tox-statusbar, .tox-editor-header, #form-error-message, .invalid-feedback');
    elementsToHide.addClass('hide-on-pdf-export');

    PDFTranslator.translate('#pdf-export-content', pdf_lang).then(() => {
        return html2canvas(pdfContentArea[0], {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        }).then(canvas => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            const imgData = canvas.toDataURL('image/jpeg', 0.95);
            const imgWidth = 210;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;

            doc.addImage(imgData, 'JPEG', 0, 0, imgWidth, imgHeight);

            const blob = doc.output('blob');
            const formData = new FormData();
            formData.append('pdf_blob', blob, `${quoteNumber}.pdf`);
            formData.append('quote_id', $('#quote_id').val() || '');

            return $.ajax({
                url: `${PROJECT_BASE_URL}process/save_quote_pdf.php`,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        doc.save(`${quoteNumber}.pdf`);
                        showUserMessage(LANG['pdf_saved_success'] || 'Lưu và tải PDF thành công!', 'success');
                    } else {
                        showUserMessage(LANG['pdf_save_error'] || 'Lỗi khi lưu PDF lên server: ' + (response.message || ''), 'error');
                    }
                },
                error: () => showUserMessage(LANG['server_error_saving_pdf'] || 'Lỗi server khi lưu PDF báo giá.', 'error')
            });
        });
    })
    .catch((err) => {
        console.error("PDF Export Error:", err);
        showUserMessage(LANG['pdf_export_render_error'] || 'Lỗi tạo PDF báo giá.', 'error');
    })
    .finally(() => {
        elementsToHide.removeClass('hide-on-pdf-export');
        downloadButton.prop('disabled', false);
        buttonText.show();
        buttonSpinner.addClass('d-none');
    });
}