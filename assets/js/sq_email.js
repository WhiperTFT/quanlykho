// File: assets/js/sq_email.js

// --- Hàm Bắt Đầu Polling Trạng Thái Email ---
function startEmailStatusPolling(logId, logType) {
    console.log(`Starting email status polling for quote log ID: ${logId}, Type: ${logType}`);
    if (emailStatusPollingInterval !== null) {
        clearInterval(emailStatusPollingInterval);
    }
    emailStatusPollingInterval = setInterval(() => {
        $.ajax({
            url: PROJECT_BASE_URL + 'status_check.php', // Endpoint chung
            type: 'GET', data: { log_id: logId, log_type: logType }, dataType: 'json',
            success: function (response) {
                if (response && typeof response.status !== 'undefined') {
                    let finalMessage = '', messageType = 'info';
                    const docNumber = response.document_info?.number || 'N/A';
                    // Luôn dùng documentName từ APP_CONTEXT cho tính nhất quán
                    const currentDocName = APP_CONTEXT.documentName;

                    switch (response.status) {
                        case 'sent':
                            messageType = 'success';
                            finalMessage = `Đã gửi email ${currentDocName} ${escapeHtml(docNumber)} thành công.`;
                            clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                            break;
                        case 'failed':
                            messageType = 'error';
                            finalMessage = `Gửi email ${currentDocName} ${escapeHtml(docNumber)} thất bại: ${escapeHtml(response.message || 'Không rõ nguyên nhân.')}`;
                            clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                            break;
                        case 'pending': case 'sending': return; // Tiếp tục polling
                        case 'not_found':
                            messageType = 'warning';
                            finalMessage = `Lỗi: Không tìm thấy lịch sử gửi email (${currentDocName} ${escapeHtml(docNumber)}) (Log ID: ${logId}).`;
                            clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                            break;
                        default: // unknown, null, empty
                            messageType = 'error'; // Coi là lỗi nếu trạng thái không xác định
                            finalMessage = `${currentDocName} ${escapeHtml(docNumber)}: Trạng thái email không xác định (${escapeHtml(response.status)}).`;
                            console.warn(`Unknown or empty email status for log ID ${logId}: ${response.status}`);
                            clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null; // Dừng polling
                            break;
                    }
                    showUserMessage(finalMessage, messageType); // showUserMessage từ sq_helpers.js

                    const $logModal = $('#viewQuoteEmailLogsModal'); // ID modal log cho báo giá
                    const openModalDocId = $logModal.data('current-document-id');
                    const polledDocId = response.document_info?.id;

                    if ($logModal.hasClass('show') && openModalDocId && polledDocId == openModalDocId) {
                        setTimeout(() => { // Delay nhỏ để đảm bảo UI không bị giật
                            const $logButton = $(`#sales-quotes-table .btn-view-quote-logs[data-quote-id="${openModalDocId}"]`);
                            if ($logButton.length) $logButton.trigger('click'); // Kích hoạt lại việc load log
                            else if (salesQuoteDataTable) salesQuoteDataTable.draw(false);
                        }, 200);
                    } else if (salesQuoteDataTable) {
                        salesQuoteDataTable.draw(false); // Refresh bảng chính
                    }
                } else {
                    clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                    showUserMessage('Lỗi: Phản hồi kiểm tra trạng thái email không hợp lệ.', 'error');
                }
            },
            error: function (xhr) {
                clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                showUserMessage(`Lỗi máy chủ khi kiểm tra trạng thái email (${xhr.status}).`, 'error');
            }
        });
    }, 5000); // 5 giây
}

// --- Hàm Dừng Polling Trạng Thái Email ---
function stopEmailStatusPolling() {
    if (emailStatusPollingInterval !== null) {
        console.log("Stopping email status polling for quote.");
        clearInterval(emailStatusPollingInterval);
        emailStatusPollingInterval = null;
    }
}

// --- Hàm Cập Nhật Nội Dung Modal Log Email ---
const updateLogModalContent = (logs) => { // Tham số logs là mảng các object log
    const $modalContentDiv = $('#quote-email-logs-content'); // ID div nội dung trong modal log báo giá
    let contentHtml = '';

    if (logs && logs.length > 0) {
        contentHtml += '<ul class="list-group list-group-flush">';
        logs.forEach(log => {
            let statusBadgeClass = 'bg-secondary', statusText = log.status || 'Không rõ';
            switch (log.status) {
                case 'pending': statusBadgeClass = 'bg-warning text-dark'; statusText = LANG['status_pending'] || 'Đang chờ'; break;
                case 'sending': statusBadgeClass = 'bg-info text-dark'; statusText = LANG['status_sending'] || 'Đang gửi'; break;
                case 'sent': statusBadgeClass = 'bg-success'; statusText = LANG['status_sent'] || 'Thành công'; break;
                case 'failed': statusBadgeClass = 'bg-danger'; statusText = LANG['status_failed'] || 'Thất bại'; break;
            }
            const statusDisplayHtml = `<span class="badge ${statusBadgeClass} p-1">${escapeHtml(statusText)}</span>`;
            const createdAtFormatted = log.created_at_formatted || log.created_at || 'N/A';
            const processedAtFormatted = log.sent_at_formatted ? `Hoàn thành: ${log.sent_at_formatted}` : (log.processed_at_formatted ? `Xử lý: ${log.processed_at_formatted}` : '');


            contentHtml += `<li class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1 small">${escapeHtml(log.subject || '(Không có tiêu đề)')}</h6>
                                    <small class="text-muted"><em>${createdAtFormatted}</em></small>
                                </div>
                                <p class="mb-1 small"><strong>To:</strong> ${escapeHtml(log.to_email)}${log.cc_emails ? `<br><strong>CC:</strong> ${escapeHtml(log.cc_emails)}` : ''}</p>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small><strong>Trạng thái:</strong> ${statusDisplayHtml} ${processedAtFormatted ? ` | <em class="text-muted small">${processedAtFormatted}</em>` : ''}</small>
                                    <button class="btn btn-sm btn-outline-secondary btn-show-log-message py-0 px-1" data-bs-toggle="tooltip" title="${escapeHtml(log.message || '(Không có thông báo/lỗi)')}"><i class="bi bi-info-circle"></i></button>
                                </div>
                                <div class="log-details mt-1 border-top pt-1" style="display: none;">
                                    <h6 class="small mb-1">Nội dung Email:</h6>
                                    <div class="email-body-content border p-2 mb-1 small" style="max-height: 150px; overflow-y: auto;">${log.body ? log.body : '<span class="text-muted small">(Không có nội dung)</span>'}</div>
                                    <h6 class="small mb-1">File đính kèm:</h6>
                                    <ul class="list-unstyled attached-files-list small"></ul>
                                </div>
                                <button class="btn btn-sm btn-link p-0 btn-toggle-log-details small">Xem chi tiết</button>
                            </li>`;
        });
        contentHtml += '</ul>';
    } else {
        contentHtml = `<p class="text-center text-muted p-3">${LANG['no_email_logs_found'] || 'Không tìm thấy lịch sử gửi email nào.'}</p>`;
    }
    $modalContentDiv.html(contentHtml);

    // Re-initialize tooltips for new content
    $modalContentDiv.find('[data-bs-toggle="tooltip"]').each(function () {
        const oldTooltip = bootstrap.Tooltip.getInstance(this);
        if (oldTooltip) oldTooltip.dispose();
        new bootstrap.Tooltip(this);
    });

    // Populate attached files
    logs.forEach((log, index) => {
        const $logItem = $modalContentDiv.find('.list-group-item').eq(index);
        const $filesList = $logItem.find('.attached-files-list');
        let attachedFilesWebPaths = [];
        if (log.attachment_paths && typeof log.attachment_paths === 'string') {
            try {
                const parsedPaths = JSON.parse(log.attachment_paths);
                if (Array.isArray(parsedPaths)) attachedFilesWebPaths = parsedPaths.filter(path => typeof path === 'string' && path.trim() !== '');
            } catch (e) { console.error(`Log ID ${log.id}: Error parsing attachment_paths JSON:`, e); }
        }
        if (attachedFilesWebPaths.length > 0) {
            attachedFilesWebPaths.forEach(fileWebPath => {
                const fileName = fileWebPath.substring(fileWebPath.lastIndexOf('/') + 1);
                $filesList.append(`<li><a href="${PROJECT_BASE_URL}${escapeHtml(fileWebPath)}" target="_blank" class="text-decoration-none"><i class="bi bi-paperclip"></i> ${escapeHtml(fileName)}</a></li>`);
            });
        } else {
            $filesList.html('<li><span class="text-muted">(Không có file đính kèm)</span></li>');
        }
    });

    // Toggle log details
    $modalContentDiv.find('.btn-toggle-log-details').on('click', function () {
        const $btn = $(this);
        const $detailsDiv = $btn.closest('.list-group-item').find('.log-details');
        $detailsDiv.slideToggle(150, () => $btn.text($detailsDiv.is(':visible') ? 'Ẩn chi tiết' : 'Xem chi tiết'));
    });
};

// Hàm openQuoteLogModal sẽ được xử lý trong sq_events.js như một phần của listener
// hoặc nếu cần gọi global thì sẽ đặt ở đây.