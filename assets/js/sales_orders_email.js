// File: assets/js/sales_orders_email.js

// --- Hàm Bắt Đầu Polling Trạng Thái Email ---
function startEmailStatusPolling(logId, logType) {
    console.log(`Starting email status polling for log ID: ${logId}, Type: ${logType}`);
    if (emailStatusPollingInterval !== null) {
        clearInterval(emailStatusPollingInterval);
        emailStatusPollingInterval = null;
    }

    emailStatusPollingInterval = setInterval(() => {
        console.log(`Polling status for log ID: ${logId}, Type: ${logType}`);
        $.ajax({
            url: PROJECT_BASE_URL + 'status_check.php',
            type: 'GET',
            data: { log_id: logId, log_type: logType },
            dataType: 'json',
            success: function (response) {
                console.log(`Polling response for log ID ${logId} (Type: ${logType}):`, response);
                if (response && typeof response.status !== 'undefined') {
                    let finalMessage = '';
                    let messageType = 'info';
                    const documentNumber = response.document_info?.number || 'N/A';
                    const currentDocumentName = response.log_type_checked === 'quote' ? (window.LANG?.sales_quote_short || 'Báo giá') : (window.LANG?.sales_order_short || 'Đơn hàng');

                    switch (response.status) {
                        case 'sent':
                            messageType = 'success';
                            finalMessage = `Đã gửi email ${currentDocumentName} ${escapeHtml(documentNumber)} thành công.`;
                            clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                            break;
                        case 'failed':
                            messageType = 'error';
                            const failureReason = response.message || 'Không rõ nguyên nhân.';
                            finalMessage = `Gửi email ${currentDocumentName} ${escapeHtml(documentNumber)} thất bại: ${escapeHtml(failureReason)}`;
                            clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                            break;
                        case 'pending': case 'sending':
                            console.log(`Log ID ${logId} (Type: ${logType}): Status is still ${response.status}. Continuing polling.`); return;
                        case 'not_found':
                            messageType = 'warning';
                            finalMessage = `Lỗi: Không tìm thấy lịch sử gửi email (${currentDocumentName} ${escapeHtml(documentNumber)}) cho yêu cầu này (Log ID: ${logId}).`;
                            clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                            console.warn("Email log not found for ID:", logId, "Type:", logType);
                            break;
                        case null: case '':
                            messageType = 'info';
                            console.warn(`Log ID ${logId} (Type: ${logType}): Status is null or empty ('${response.status}'), treating as pending/unknown. Continuing polling.`); return;
                        default:
                            messageType = 'error';
                            finalMessage = `Log ID ${logId} (Type: ${logType}) trả về trạng thái không xác định: ${escapeHtml(response.status)}.`;
                            clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                            console.error("Log ID", logId, "Type:", logType, "returned unknown status value:", response.status);
                            break;
                    }
                    showUserMessage(finalMessage, messageType);

                    const $logModalElement = $('#viewOrderEmailLogsModal');
                    const openModalDocumentId = $logModalElement.data('current-document-id');
                    const documentIdFromPolling = response.document_info?.id;
                    if ($logModalElement.hasClass('show') && openModalDocumentId && documentIdFromPolling == openModalDocumentId) {
                        console.log(`Polling finished for visible log modal. Triggering log modal refresh for Document ID: ${openModalDocumentId}, Type: ${logType}`);
                        setTimeout(() => {
                            const $logButton = $('#sales-orders-table').find(`.btn-view-order-logs[data-order-id="${openModalDocumentId}"]`);
                            if ($logButton.length) {
                                $logButton.trigger('click');
                            } else {
                                console.warn(`Log button for Document ID ${openModalDocumentId} not found in current DataTable view to trigger refresh.`);
                                if (salesOrderDataTable) salesOrderDataTable.draw(false);
                            }
                        }, 100);
                    } else if (salesOrderDataTable) {
                        salesOrderDataTable.draw(false);
                    }
                } else {
                    console.error(`Invalid response structure from status_check.php for log ID ${logId}, type ${logType}:`, response);
                    clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                    showUserMessage('Lỗi: Phản hồi kiểm tra trạng thái từ máy chủ không hợp lệ.', 'error');
                }
            },
            error: function (xhr) {
                console.error(`AJAX error polling status for log ID ${logId}, type ${logType}:`, xhr.status, xhr.responseText);
                clearInterval(emailStatusPollingInterval); emailStatusPollingInterval = null;
                let errorMessage = 'Lỗi máy chủ khi kiểm tra trạng thái email.';
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res && res.message) errorMessage += '\nChi tiết: ' + res.message;
                    else errorMessage += '\nChi tiết: ' + xhr.responseText;
                } catch (e) { errorMessage += '\nChi tiết: ' + xhr.responseText; }
                showUserMessage(errorMessage, 'error');
            }
        });
    }, 5000);
}

// --- Hàm Dừng Polling Trạng Thái Email ---
function stopEmailStatusPolling() {
    if (emailStatusPollingInterval !== null) {
        console.log("Stopping email status polling.");
        clearInterval(emailStatusPollingInterval);
        emailStatusPollingInterval = null;
    }
}

// --- Hàm Cập Nhật Nội Dung Modal Log Email ---
const updateLogModalContent = (logs) => {
    const $modalContentDiv = $('#order-email-logs-content');
    let contentHtml = '';

    if (logs && logs.length > 0) {
        contentHtml += '<ul class="list-group list-group-flush">';
        logs.forEach(log => {
            let status_badge_class = 'bg-secondary'; let status_text = log.status || 'Không rõ';
            switch (log.status) {
                case 'pending': status_badge_class = 'bg-warning'; status_text = 'Đang chờ'; break;
                case 'sending': status_badge_class = 'bg-info'; status_text = 'Đang gửi...'; break;
                case 'sent': status_badge_class = 'bg-success'; status_text = 'Thành công'; break;
                case 'failed': status_badge_class = 'bg-danger'; status_text = 'Thất bại'; break;
            }
            const status_display_html = `<span class="badge ${status_badge_class}">${status_text}</span>`;
            const createdAtFormatted = log.created_at_formatted || log.created_at || 'Không rõ';
            const processedAtFormatted = log.sent_at_formatted ? `Hoàn thành: ${log.sent_at_formatted}` : '';

            contentHtml += `<li class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1">${escapeHtml(log.subject || '(Không có tiêu đề)')}</h6>
                                    <small class="text-muted">Yêu cầu: ${createdAtFormatted}</small>
                                </div>
                                <p class="mb-1">
                                    <strong>To:</strong> ${escapeHtml(log.to_email)}
                                    ${log.cc_emails ? `<br><strong>CC:</strong> ${escapeHtml(log.cc_emails)}` : ''}
                                </p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small>
                                        <strong>Trạng thái:</strong> ${status_display_html}
                                         ${processedAtFormatted ? ` | <small class="text-muted">${processedAtFormatted}</small>` : ''}
                                    </small>
                                     <button class="btn btn-sm btn-outline-secondary btn-show-log-message"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="${escapeHtml(log.message || '(Không có thông báo/lỗi)')}">
                                        <i class="bi bi-info-circle"></i> Xem TB/Lỗi
                                    </button>
                                </div>
                                <div class="log-details mt-2" style="display: none;">
                                    <h6 class="small mb-1">Nội dung Email:</h6>
                                    <div class="email-body-content border p-2 mb-2" style="max-height: 200px; overflow-y: auto;">
                                         ${log.body ? log.body : '<span class="text-muted">(Không có nội dung)</span>'}
                                    </div>
                                    <h6 class="small mb-1">File đính kèm:</h6>
                                    <ul class="list-unstyled attached-files-list"></ul>
                                </div>
                                <button class="btn btn-sm btn-link p-0 btn-toggle-log-details">Xem chi tiết</button>
                            </li>`;
        });
        contentHtml += '</ul>';
    } else {
        contentHtml = '<p class="text-center text-muted p-3">Không tìm thấy lịch sử gửi email nào cho đơn hàng này.</p>';
    }
    $modalContentDiv.html(contentHtml);

    const tooltipTriggerList = $modalContentDiv.find('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.each(function () {
        const oldTooltip = bootstrap.Tooltip.getInstance(this);
        if (oldTooltip) oldTooltip.dispose();
        new bootstrap.Tooltip(this);
    });

    logs.forEach((log, index) => {
        const $logItem = $modalContentDiv.find('.list-group-item').eq(index);
        const $filesList = $logItem.find('.attached-files-list');
        let attachedFilesWebPaths = [];
        if (log.attachment_paths && typeof log.attachment_paths === 'string') {
            try {
                const parsedPaths = JSON.parse(log.attachment_paths);
                if (Array.isArray(parsedPaths)) {
                    attachedFilesWebPaths = parsedPaths.filter(path => typeof path === 'string' && path !== '');
                } else { console.warn(`Log ID ${log.id}: attachment_paths is not a JSON array string:`, log.attachment_paths); }
            } catch (e) { console.error(`Log ID ${log.id}: Error parsing attachment_paths as JSON:`, e, log.attachment_paths); attachedFilesWebPaths = []; }
        } else { console.warn(`Log ID ${log.id}: attachment_paths is null, undefined, or not a string:`, log.attachment_paths); }

        if (attachedFilesWebPaths.length > 0) {
            attachedFilesWebPaths.forEach(fileWebPath => {
                const fileName = fileWebPath.substring(fileWebPath.lastIndexOf('/') + 1);
                const fileUrl = fileWebPath; // Đường dẫn tương đối web
                const $fileItem = $(`<li><a href="${escapeHtml(fileUrl)}" target="_blank"><i class="bi bi-file-earmark"></i> ${escapeHtml(fileName)}</a></li>`);
                $filesList.append($fileItem);
            });
        } else {
            $filesList.html('<li><span class="text-muted">(Không có file đính kèm)</span></li>');
        }
    });

    $modalContentDiv.find('.btn-toggle-log-details').on('click', function () {
        const $btn = $(this);
        const $detailsDiv = $btn.closest('.list-group-item').find('.log-details');
        $detailsDiv.slideToggle(200, function () {
            $btn.text($detailsDiv.is(':visible') ? 'Ẩn chi tiết' : 'Xem chi tiết');
        });
    });
};

