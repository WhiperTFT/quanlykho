// File: assets/js/sales_orders.js
// Phiên bản CUỐI CÙNG VÀ ĐẦY ĐỦ NHẤT - Đã sửa tất cả lỗi scope và thiếu listener.
// Yêu cầu: jQuery, jQuery UI (Autocomplete, Draggable), Flatpickr, DataTables, Bootstrap JS, html2canvas, jsPDF,

$(document).ready(function() {
    // --- Khởi tạo các biến và đối tượng ---
    
    const orderFormCard = $('#order-form-card');
    const orderForm = $('#order-form');
    const orderFormTitle = $('#order-form-title');
    const orderListTitle = $('#order-list-title');
    const orderTableElement = $('#sales-orders-table'); // Lấy element table chính
    const itemTableBody = $('#item-details-body');
    const saveButton = $('#btn-save-order');
    const saveButtonText = saveButton.find('.save-text');
    const saveButtonSpinner = saveButton.find('.spinner-border');
    const formErrorMessageDiv = $('#form-error-message');
    const currencySelect = $('#currency_select');
    const buyerSignatureImg = $('#buyer-signature');
    const toggleSignatureButton = $('#toggle-signature');
    const signatureLocalStorageKey = 'buyerSignatureVisibleState'; // Key cho localStorage
    const btnRemoveDefaultAttachment = document.querySelector('.btn-remove-default-attachment'); // <<< Dòng này đã có (khoảng dòng 25)
    // const form = $('#order-form'); // Biến này trùng với orderForm, có thể xóa (đã xóa)
    const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    const APP_CONTEXT = window.APP_CONTEXT || { type: 'order', documentName: 'Tài liệu' }; // Mặc định là 'order' nếu APP_CONTEXT không được định nghĩa
    console.log("Using APP_CONTEXT in sales_orders.js:", APP_CONTEXT);
    

    // --- CẬP NHẬT ĐƯỜNG DẪN ẢNH CHỮ KÝ CHO FORM WEB ---
    let webSignatureSrc = ''; // Mặc định không có ảnh
    if (typeof window.APP_SETTINGS !== 'undefined' && typeof window.APP_SETTINGS.buyerSignatureUrl === 'string' && window.APP_SETTINGS.buyerSignatureUrl.trim() !== '') {
        webSignatureSrc = window.APP_SETTINGS.buyerSignatureUrl;
        console.log("Using signature from DB for web form: " + webSignatureSrc);
        buyerSignatureImg.attr('src', webSignatureSrc);
    } else {
        console.log("No signature_path from DB or it's empty. Signature image on form will be hidden or use placeholder if src was initially set in HTML.");
        // Nếu không có src từ DB, đảm bảo ảnh không hiển thị (hoặc bạn có thể đặt src về placeholder)
        buyerSignatureImg.hide().removeAttr('src'); // Ẩn và xóa src nếu không có từ DB
    }

    // --- KHÔI PHỤC VÀ ÁP DỤNG TRẠNG THÁI ẨN/HIỆN CHỮ KÝ TỪ LOCALSTORAGE ---
    // Chỉ khôi phục nếu có đường dẫn ảnh hợp lệ
    if (webSignatureSrc) {
        const savedState = localStorage.getItem(signatureLocalStorageKey);
        let shouldBeVisible = true; // Mặc định là hiện nếu không có trạng thái lưu

        if (savedState !== null) { // Nếu có trạng thái đã lưu
            shouldBeVisible = (savedState === 'true');
        }

        if (shouldBeVisible) {
            buyerSignatureImg.show();
            toggleSignatureButton.text(LANG?.hide_signature ?? 'Ẩn chữ ký');
        } else {
            buyerSignatureImg.hide();
            toggleSignatureButton.text(LANG?.show_signature ?? 'Hiện chữ ký');
        }
        console.log(`Signature visibility restored from localStorage: ${shouldBeVisible}`);
    } else {
        // Nếu không có ảnh chữ ký, nút toggle nên bị vô hiệu hóa hoặc ẩn đi
        buyerSignatureImg.hide();
        toggleSignatureButton.hide(); // Ví dụ: ẩn nút toggle nếu không có ảnh
        console.log("No signature image, hiding toggle button.");
    }


    // --- Đảm bảo ảnh có kích thước thật khi hiển thị (nếu không bị CSS khác ghi đè) ---
    // Khi ảnh được tải, chúng ta có thể thử xóa các giới hạn CSS nếu cần.
    // Cách tốt nhất là đảm bảo CSS của bạn không giới hạn kích thước của #buyer-signature.
    buyerSignatureImg.on('load', function() {
        // $(this).css({'width': 'auto', 'height': 'auto', 'max-width': 'none', 'max-height': 'none'});
        // Cẩn thận: Dòng trên có thể phá vỡ layout nếu ảnh quá lớn.
        // Thường thì bạn sẽ muốn giới hạn bằng CSS:
        // Ví dụ CSS: #buyer-signature { max-width: 300px; max-height: 150px; width: auto; height: auto; }
        console.log("Buyer signature image loaded on web form. Actual dimensions:", this.naturalWidth, "x", this.naturalHeight);
    }).on('error', function() {
        console.warn("Failed to load buyer signature image on web form from src: " + $(this).attr('src'));
        // Có thể ẩn ảnh và nút toggle nếu ảnh lỗi
        $(this).hide();
        toggleSignatureButton.hide();
    });

    let selectedExtraAttachments = []; // <<< Biến này để lưu trữ các file đính kèm thêm

    let itemIndex = 1; // Reset trong resetOrderForm (đã kiểm tra, biến này có thể không cần thiết cho logic thêm/xóa row hiện tại)
    const vatDefaultRate = 10.00; // VAT mặc định
    let salesOrderDataTable = null; // Biến lưu trữ instance DataTables
    let filterTimeout; // Biến cho debounce filter
    let emailStatusPollingInterval = null; // Biến lưu trữ ID của setInterval polling


    console.log("Document ready. Starting initialization...");
    console.log("orderTableElement:", orderTableElement);
    console.log("orderTableElement length:", orderTableElement.length);
    console.log("orderForm element:", orderForm);
    console.log("orderForm length:", orderForm.length);


    // --- Hàm trim ---
    function trim(str) {
        if (typeof str !== 'string') {
            return str;
        }
        return str.replace(/^\s+|\s+$/g, '');
    }

    // --- Hàm Escape HTML ---
    function escapeHtml(unsafe) { if (unsafe === null || typeof unsafe === 'undefined') return ''; return unsafe.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;"); }

    // --- Hàm Sanitize Tên File ---
    function sanitizeFilename(name) { if (!name||typeof name!=='string') return 'sales_order'; let s=name.replace(/[\s\\/:"*?<>|]+/g,'_'); s=s.replace(/[^a-zA-Z0-9_.-]/g,''); s=s.substring(0,100); return s||'sales_order'; }


    // <<< ĐỊNH NGHĨA HÀM startEmailStatusPolling NGOÀI CÁC LISTENER VÀ TRONG document.ready >>>
    // Hàm bắt đầu polling trạng thái email
    function startEmailStatusPolling(logId, logType) {
         console.log(`Starting email status polling for log ID: ${logId}, Type: ${logType}`);


        // Dừng polling cũ nếu có
        if (emailStatusPollingInterval !== null) {
            clearInterval(emailStatusPollingInterval);
            emailStatusPollingInterval = null;
        }

        emailStatusPollingInterval = setInterval(() => {
            console.log(`Polling status for log ID: ${logId}, Type: ${logType}`);

            $.ajax({
                url: PROJECT_BASE_URL + 'status_check.php', // Endpoint kiểm tra trạng thái
                type: 'GET',
                // CHỈNH SỬA: Gửi thêm log_type
                data: { log_id: logId, log_type: logType },
                // GHI CHÚ CHỈNH SỬA: `log_type` được gửi đến `status_check.php`.
                dataType: 'json',
                success: function(response) {
                    console.log(`Polling response for log ID ${logId} (Type: ${logType}):`, response);

                    if (response && typeof response.status !== 'undefined') {
                        let finalMessage = '';
                        let messageType = 'info';

                        // CHỈNH SỬA: Sử dụng response.document_info và APP_CONTEXT.documentName
                        const documentNumber = response.document_info?.number || 'N/A';
                        const currentDocumentName = response.log_type_checked === 'quote' ? (window.LANG?.sales_quote_short || 'Báo giá') : (window.LANG?.sales_order_short || 'Đơn hàng');
                        // GHI CHÚ CHỈNH SỬA:
                        // - Lấy `documentNumber` từ `response.document_info.number` (do status_check.php trả về).
                        // - Xác định `currentDocumentName` ('Đơn hàng' hoặc 'Báo giá') dựa trên `response.log_type_checked`.

                        switch (response.status) {
                            case 'sent':
                                messageType = 'success';
                                finalMessage = `Đã gửi email ${currentDocumentName} ${escapeHtml(documentNumber)} thành công.`;
                                clearInterval(emailStatusPollingInterval);
                                emailStatusPollingInterval = null;
                                break;

                            case 'failed':
                                messageType = 'error';
                                const failureReason = response.message || 'Không rõ nguyên nhân.';
                                finalMessage = `Gửi email ${currentDocumentName} ${escapeHtml(documentNumber)} thất bại: ${escapeHtml(failureReason)}`;
                                 clearInterval(emailStatusPollingInterval);
                                 emailStatusPollingInterval = null;
                                break;

                            case 'pending':
                             case 'sending':
                                 console.log(`Log ID ${logId} (Type: ${logType}): Status is still ${response.status}. Continuing polling.`);
                                 return;
                                 break;

                             case 'not_found':
                                 messageType = 'warning';
                                 finalMessage = `Lỗi: Không tìm thấy lịch sử gửi email (${currentDocumentName} ${escapeHtml(documentNumber)}) cho yêu cầu này (Log ID: ${logId}).`;
                                 clearInterval(emailStatusPollingInterval);
                                 emailStatusPollingInterval = null;
                                 console.warn("Email log not found for ID:", logId, "Type:", logType);
                                 break;

                             case null:
                             case '':
                                 messageType = 'info';
                                 console.warn(`Log ID ${logId} (Type: ${logType}): Status is null or empty ('${response.status}'), treating as pending/unknown. Continuing polling.`);
                                  return;
                                  break;

                             default:
                                 messageType = 'error';
                                 finalMessage = `Log ID ${logId} (Type: ${logType}) trả về trạng thái không xác định: ${escapeHtml(response.status)}.`;
                                 clearInterval(emailStatusPollingInterval);
                                 emailStatusPollingInterval = null;
                                 console.error("Log ID", logId, "Type:", logType, "returned unknown status value:", response.status);
                                 break;
                        }

                        // *** HIỂN THỊ THÔNG BÁO TRẠNG THÁI CUỐI CÙNG ***
                         showUserMessage(finalMessage, messageType);

                        // Tùy chọn: Làm mới DataTable hoặc Modal Log nếu nó đang mở sau khi có trạng thái cuối cùng
                         // Nếu modal log đang mở cho đơn hàng này, làm mới nó.
                        const $logModalElement = $('#viewOrderEmailLogsModal'); // Giữ nguyên ID modal log
                        const openModalDocumentId = $logModalElement.data('current-document-id'); // Lấy ID đang mở
                        const documentIdFromPolling = response.document_info?.id; // ID từ polling
                          if ($logModalElement.hasClass('show') && openModalDocumentId && documentIdFromPolling == openModalDocumentId) {
                             console.log(`Polling finished for visible log modal. Triggering log modal refresh for Document ID: ${openModalDocumentId}, Type: ${logType}`);
                             setTimeout(() => {
                                  // Tìm nút xem log trong DataTable bằng data-order-id (hoặc data-document-id nếu bạn đổi)
                                  // Class của nút xem log có thể là .btn-view-order-logs hoặc .btn-view-quote-logs
                                  // Để đơn giản, nếu DataTables dùng chung class, thì không cần đổi.
                                  // Nếu khác class, bạn cần một cách để xác định đúng nút.
                                  const $logButton = $('#sales-orders-table').find(`.btn-view-order-logs[data-order-id="${openModalDocumentId}"]`); // Hoặc tìm theo class chung nếu có
                                  if ($logButton.length) {
                                       $logButton.trigger('click');
                                  } else {
                                       console.warn(`Log button for Document ID ${openModalDocumentId} not found in current DataTable view to trigger refresh.`);
                                       if (salesOrderDataTable) { // salesOrderDataTable là biến cho bảng sales_orders
                                           salesOrderDataTable.draw(false);
                                       }
                                  }
                             }, 100);
                        } else if (salesOrderDataTable) { // salesOrderDataTable là biến cho bảng sales_orders
                             salesOrderDataTable.draw(false);
                        }


                    } else {
                         console.error(`Invalid response structure from status_check.php for log ID ${logId}, type ${logType}:`, response);
                        clearInterval(emailStatusPollingInterval);
                        emailStatusPollingInterval = null;
                        showUserMessage('Lỗi: Phản hồi kiểm tra trạng thái từ máy chủ không hợp lệ.', 'error');
                    }
                },
                error: function(xhr) {
                    console.error(`AJAX error polling status for log ID ${logId}, type ${logType}:`, xhr.status, xhr.responseText);
                    // Dừng polling nếu gặp lỗi AJAX
                    clearInterval(emailStatusPollingInterval);
                    emailStatusPollingInterval = null;

                     let errorMessage = 'Lỗi máy chủ khi kiểm tra trạng thái email.';
                     // Cố gắng lấy thông báo lỗi chi tiết từ phản hồi lỗi AJAX
                     try {
                         const res = JSON.parse(xhr.responseText);
                         if (res && res.message) {
                              errorMessage += '\nChi tiết: ' + res.message;
                         } else {
                             errorMessage += '\nChi tiết: ' + xhr.responseText;
                         }
                     } catch(e) {
                          errorMessage += '\nChi tiết: ' + xhr.responseText;
                     }
                     showUserMessage(errorMessage, 'error');
                }
            });

        }, 5000); // Poll mỗi 5 giây (5000 milliseconds)
    }

    // Hàm dừng polling trạng thái email (nếu cần gọi từ đâu đó)
    function stopEmailStatusPolling() {
        if (emailStatusPollingInterval !== null) {
            console.log("Stopping email status polling.");
            clearInterval(emailStatusPollingInterval);
            emailStatusPollingInterval = null;
        }
    }

     // <<< ĐỊNH NGHĨA HÀM updateLogModalContent NGOÀI CÁC LISTENER VÀ TRONG document.ready >>>
     // Hàm cập nhật nội dung modal log (sẽ được gọi sau khi fetch xong)
    const updateLogModalContent = (logs) => {
        const $modalContentDiv = $('#order-email-logs-content');
        let contentHtml = '';

        if (logs && logs.length > 0) {
            contentHtml += '<ul class="list-group list-group-flush">';
            logs.forEach(log => {
                // Xác định class và text cho trạng thái
                let status_badge_class = 'bg-secondary'; // default for pending/sending
                let status_text = log.status || 'Không rõ';
                switch (log.status) {
                    case 'pending':
                        status_badge_class = 'bg-warning';
                        status_text = 'Đang chờ';
                        break;
                    case 'sending':
                         status_badge_class = 'bg-info';
                         status_text = 'Đang gửi...';
                         break;
                    case 'sent':
                        status_badge_class = 'bg-success';
                        status_text = 'Thành công';
                        break;
                    case 'failed':
                        status_badge_class = 'bg-danger';
                        status_text = 'Thất bại';
                        break;
                    default:
                         status_badge_class = 'bg-secondary';
                         status_text = log.status || 'Không rõ';
                }
                const status_display_html = `<span class="badge ${status_badge_class}">${status_text}</span>`;

                // Hiển thị thời gian tạo (queued) và thời gian hoàn thành (sent_at/processed_at)
                // Cần đảm bảo ajax_email_logs.php trả về created_at_formatted và sent_at_formatted
                 const createdAtFormatted = log.created_at_formatted || log.created_at || 'Không rõ'; // Cần backend trả về created_at_formatted
                 const processedAtFormatted = log.sent_at_formatted ? `Hoàn thành: ${log.sent_at_formatted}` : ''; // Cần backend trả về sent_at_formatted

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
                                        <ul class="list-unstyled attached-files-list">
                                            </ul>
                                    </div>
                                    <button class="btn btn-sm btn-link p-0 btn-toggle-log-details">Xem chi tiết</button>

                                </li>`; // <<< Kết thúc dấu backtick
            });
            contentHtml += '</ul>';
        } else {
            contentHtml = '<p class="text-center text-muted p-3">Không tìm thấy lịch sử gửi email nào cho đơn hàng này.</p>';
        }

        // Cập nhật nội dung modal bằng jQuery
        $modalContentDiv.html(contentHtml);

        // === Xử lý Tooltip cho nút xem thông báo/lỗi ===
        // Tìm tất cả các element có data-bs-toggle="tooltip" trong nội dung modal vừa thêm
        const tooltipTriggerList = $modalContentDiv.find('[data-bs-toggle="tooltip"]');
        // Khởi tạo tooltip cho từng element tìm được
        tooltipTriggerList.each(function() {
             // Hủy tooltip cũ trước khi tạo mới (nếu có, để tránh duplicate)
            const oldTooltip = bootstrap.Tooltip.getInstance(this);
            if (oldTooltip) {
                oldTooltip.dispose();
            }
            new bootstrap.Tooltip(this); // Khởi tạo tooltip mới
        });


        // === Xử lý hiển thị File đính kèm từ JSON string ===
         // Duyệt qua từng log trong mảng logs
         logs.forEach((log, index) => {
            // Lấy thẻ li tương ứng với log hiện tại trong HTML modal
            const $logItem = $modalContentDiv.find('.list-group-item').eq(index);
            // Lấy ul để thêm danh sách file đính kèm trong dòng log đó
            const $filesList = $logItem.find('.attached-files-list');

            let attachedFilesWebPaths = []; // Mảng lưu các đường dẫn file (tương đối web)
            // Kiểm tra nếu log có trường attachment_paths và nó là chuỗi (mong đợi JSON string)
            if (log.attachment_paths && typeof log.attachment_paths === 'string') {
                try {
                    // Thử parse JSON string từ cột attachment_paths
                    const parsedPaths = JSON.parse(log.attachment_paths);
                    // Kiểm tra xem kết quả parse có phải là mảng và chứa các chuỗi không rỗng
                    if (Array.isArray(parsedPaths)) {
                         attachedFilesWebPaths = parsedPaths.filter(path => typeof path === 'string' && path !== '');
                    } else {
                         console.warn(`Log ID ${log.id}: attachment_paths is not a JSON array string:`, log.attachment_paths); // LOG WARNING
                    }
                } catch (e) {
                    console.error(`Log ID ${log.id}: Error parsing attachment_paths as JSON:`, e, log.attachment_paths); // LOG ERROR
                     attachedFilesWebPaths = []; // Nếu lỗi JSON, coi như không có file hoặc định dạng sai
                }
            } else {
                 console.warn(`Log ID ${log.id}: attachment_paths is null, undefined, or not a string:`, log.attachment_paths); // LOG WARNING
            }

            // Nếu có đường dẫn file hợp lệ
            if (attachedFilesWebPaths.length > 0) {
                attachedFilesWebPaths.forEach(fileWebPath => { // Lặp qua mảng đường dẫn
                    const fileName = fileWebPath.substring(fileWebPath.lastIndexOf('/') + 1); // Lấy tên file từ đường dẫn

                    // Tạo link tải về
                    // Sử dụng đường dẫn tương đối web trực tiếp.
                    // Cần đảm bảo đường dẫn này có thể truy cập được từ trình duyệt (ví dụ: /uploads/documents/tenfile.pdf)
                    const fileUrl = fileWebPath; // <<< ĐƯỜNG DẪN TƯƠNG ĐỐI WEB CẦN ĐÚNG >>>

                    // Tạo element li chứa link
                    const $fileItem = $(`<li><a href="${escapeHtml(fileUrl)}" target="_blank"><i class="bi bi-file-earmark"></i> ${escapeHtml(fileName)}</a></li>`);
                    $filesList.append($fileItem); // Thêm vào danh sách file đính kèm
                });
            } else {
                // Nếu không có file đính kèm, hiển thị thông báo
                $filesList.html('<li><span class="text-muted">(Không có file đính kèm)</span></li>');
            }
        }); // Kết thúc duyệt qua logs để xử lý file đính kèm JSON

        // === Xử lý Nút bật/tắt chi tiết log (Nội dung Email, File đính kèm) ===
        // Gắn listener cho nút bật/tắt chi tiết trong mỗi dòng log
        $modalContentDiv.find('.btn-toggle-log-details').on('click', function() {
            console.log(">>> Listener .btn-toggle-log-details clicked!"); // LOG TEST
            const $btn = $(this); // Nút "Xem chi tiết" hoặc "Ẩn chi tiết"
            // Tìm div chứa nội dung chi tiết (nội dung email, danh sách file) trong cùng dòng log
            const $detailsDiv = $btn.closest('.list-group-item').find('.log-details');
            $detailsDiv.slideToggle(200, function() { // Toggle hiển thị/ẩn với hiệu ứng slide
                 // Cập nhật text của nút sau khi toggle hoàn thành
                 if ($detailsDiv.is(':visible')) {
                     $btn.text('Ẩn chi tiết');
                 } else {
                     $btn.text('Xem chi tiết');
                 }
            });
        });

    }; // Kết thúc hàm updateLogModalContent
    orderFormCard.hide();
    // === THÊM LOGIC XỬ LÝ DỮ LIỆU TỪ LOCALSTORAGE TẠI ĐÂY ===
    console.log("Sales Orders page loaded. Checking localStorage for quote items..."); // LOG SO-1
    const itemsFromQuoteJson = localStorage.getItem('itemsFromQuote');
    const originalQuoteId = localStorage.getItem('originalQuoteId');
    const itemsFromQuote = JSON.parse(localStorage.getItem('itemsFromQuote'));
    const originalQuoteNumber = localStorage.getItem('originalQuoteNumber');



    if (itemsFromQuote && itemsFromQuote.length > 0) {
    console.log(">>> SO-2: Items found in localStorage from Quote ID: " + originalQuoteId, itemsFromQuote);
    console.log(">>> SO-X: Main container .container-fluid.mt-4 has been processed. Is it visible now? " + $('.container-fluid.mt-4').is(':visible'));

    // *** Đặt tất cả logic hiển thị và điền form vào trong setTimeout ***
    setTimeout(function() {
        console.log(">>> SO-TIMEOUT: Executing form display and data population after delay.");

        // Ẩn các phần tử của trang danh sách
        orderListTitle.hide();
        console.log(">>> SO-3: orderListTitle hidden.");

        orderTableElement.closest('.card').hide(); // Ẩn card chứa DataTables
        console.log(">>> SO-4: orderTableElement parent card hidden.");

        // Hiển thị form và điền dữ liệu
        orderFormCard.show(); // Dùng .show() thay vì .slideDown() ban đầu để loại trừ vấn đề hiệu ứng
        console.log(">>> SO-5: orderFormCard shown. Is it visible now? " + orderFormCard.is(':visible'));

        orderFormCard.removeClass('view-mode'); // Đảm bảo không ở chế độ xem
        orderFormTitle.text(LANG['create_new_order_from_quote'] || `Tạo đơn hàng từ báo giá ${originalQuoteNumber}`);
        saveButtonText.text(LANG['save_order'] || 'Lưu đơn hàng'); // Đổi lại thành 'Lưu đơn hàng'

        console.log(">>> SO-6: Form title updated.");

        // Reset form trước khi điền dữ liệu mới
        resetOrderForm(false); // Reset form về trạng thái tạo mới

        $('#order_id').val(''); // Đảm bảo ID đơn hàng được reset
        $('#order_number').val(originalQuoteNumber); // Điền số báo giá vào số đơn hàng


        // Điền dữ liệu item details
        itemTableBody.empty(); // Xóa các dòng mặc định đã thêm bởi resetOrderForm()
        itemsFromQuote.forEach(item => {
            addItemRow(item);
            console.log(">>> SO-8.x: Appended item row for product: " + item.product_name_snapshot);
        });
        console.log(">>> SO-9: All item rows appended. Final itemTableBody HTML: ", itemTableBody.html());

        calculateSummaryTotals();
        console.log(">>> SO-10: Recalculated summary totals.");

        // Cập nhật các event listener cho các dòng mới nếu cần (thường đã được initializeProductAutocomplete gọi)
        // initializeProductAutocomplete(itemTableBody); // Hàm này sẽ được gọi lại trong addItemRow
        // updateSTT(); // Hàm này được gọi trong resetOrderForm() và sau khi add/remove row

        showUserMessage(LANG['quote_items_ready_for_order'] || 'Chi tiết báo giá đã sẵn sàng để tạo đơn hàng. Vui lòng kiểm tra thông tin và lưu.', 'success');
        console.log(">>> SO-12: User message shown.");

        localStorage.removeItem('itemsFromQuote'); // Xóa dữ liệu sau khi sử dụng
        localStorage.removeItem('originalQuoteId');
        localStorage.removeItem('originalQuoteNumber');

        console.log(">>> SO-13: localStorage cleared.");

        // Cuộn trang lên đầu form
        $('html, body').animate({ scrollTop: orderFormCard.offset().top - 0 }, 300);
        console.log(">>> SO-14: Scrolled to form.");

        // Sau khi tất cả đã được điền và hiển thị bằng .show(), nếu muốn hiệu ứng slide, có thể dùng:
        // orderFormCard.slideDown(400); // Thêm lại nếu bạn muốn hiệu ứng slide
        // Thay vì .slideDown() ban đầu, dùng .show() trước, rồi nếu muốn hiệu ứng thì mới dùng lại .slideDown()

    }, 50); // Trì hoãn 50ms (có thể điều chỉnh)
}


    // --- Hàm Khởi Tạo Trang Chính ---
    function initializePage() {
        console.log("Initializing Sales Orders page starting...");
        initializeDatepicker(); // Khởi tạo datepicker
        initializeSupplierAutocomplete(); // Khởi tạo autocomplete nhà cung cấp
        initializeProductAutocomplete('#item-details-body'); // Khởi tạo autocomplete sản phẩm cho item rows
        initializeProductAutocomplete('.item-row-template'); // Khởi tạo autocomplete sản phẩm cho template row

        initializeSalesOrderDataTable(); // <<< Khởi tạo DataTables Server-Side >>>

        orderFormCard.hide(); // Ẩn form đơn hàng ban đầu
        setupEventListeners(); // Gắn tất cả các listener sự kiện sau khi DataTables được khởi tạo
        console.log("Sales Orders page initialized.");
    }


    

    // --- Hàm Khởi Tạo Autocomplete Nhà Cung Cấp (Supplier) ---
    function initializeSupplierAutocomplete() {
         console.log("Initializing Supplier Autocomplete..."); // LOG TEST
        const partnerInput = $("#partner_autocomplete");
        const partnerIdInput = $("#partner_id"); // Input ẩn lưu ID partner
        const partnerAddressDisplay = $("#partner_address_display"); // Element hiển thị thông tin
        const partnerTaxIdDisplay = $("#partner_tax_id_display");
        const partnerPhoneDisplay = $("#partner_phone_display");
        const partnerEmailDisplay = $("#partner_email_display");
        const partnerContactDisplay = $("#partner_contact_person_display");
        const partnerLoading = $('#partner-loading'); // Spinner loading
        const partnerTypeFilter = 'supplier'; // Lọc chỉ tìm kiếm supplier

        // Kiểm tra thư viện jQuery UI Autocomplete có tồn tại không
        if (typeof $.ui !== 'undefined' && typeof $.ui.autocomplete !== 'undefined') {
            partnerInput.autocomplete({
                source: function(request, response) {
                    // console.log("Supplier Autocomplete: Requesting term -", request.term, "Type -", partnerTypeFilter); // Có thể log quá nhiều
                    partnerLoading.removeClass('d-none'); // Hiện spinner
                    partnerInput.removeClass('is-invalid'); // Xóa trạng thái lỗi cũ
                    // Gửi yêu cầu AJAX đến backend để tìm kiếm partner
                    $.ajax({
                        url: AJAX_URL.partner_search, // Endpoint tìm kiếm partner
                        dataType: "json",
                        data: { action: 'search', term: request.term, type: partnerTypeFilter }, // Gửi term và type
                        success: function(data) {
                            partnerLoading.addClass('d-none'); // Ẩn spinner
                            if (data.success && Array.isArray(data.data)) {
                                // Chuyển đổi dữ liệu nhận được sang định dạng label/value cho Autocomplete
                                const mappedData = $.map(data.data, function(item) {
                                    if (item && typeof item.name !== 'undefined' && typeof item.id !== 'undefined') {
                                        return { label: item.name + (item.tax_id ? ` (MST: ${item.tax_id})` : ''), // Text hiển thị trong dropdown
                                                 value: item.name, // Giá trị điền vào input sau khi chọn
                                                 id: item.id, // ID partner (lưu vào input ẩn)
                                                 address: item.address || '-',
                                                 tax_id: item.tax_id || '-',
                                                 phone: item.phone || '-',
                                                 email: item.email || '-',
                                                 contact_person: item.contact_person || '-' };
                                    } return null; // Bỏ qua các item không hợp lệ
                                });
                                response(mappedData); // Truyền dữ liệu cho Autocomplete
                            } else { console.error("Error fetching suppliers:", data?.message); response([]); } // Báo lỗi nếu fetch thất bại
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            partnerLoading.addClass('d-none'); console.error("Supplier Autocomplete AJAX Error:", textStatus, errorThrown); response([]); // Xử lý lỗi AJAX
                        }
                    });
                },
                minLength: 1, // Bắt đầu tìm kiếm sau khi nhập 1 ký tự
                select: function(event, ui) {
                    event.preventDefault(); // Ngăn hành động mặc định của Autocomplete
                    partnerInput.val(ui.item.value); // Điền tên vào input
                    partnerIdInput.val(ui.item.id); // Lưu ID vào input ẩn
                    // Cập nhật các trường hiển thị thông tin partner
                    partnerAddressDisplay.text(ui.item.address);
                    partnerTaxIdDisplay.text(ui.item.tax_id);
                    partnerPhoneDisplay.text(ui.item.phone);
                    partnerEmailDisplay.text(ui.item.email);
                    partnerContactDisplay.text(ui.item.contact_person);
                    // Xóa trạng thái lỗi và thông báo lỗi
                    partnerInput.removeClass('is-invalid').closest('.mb-2').find('.invalid-feedback').text('');
                    console.log("Supplier selected:", ui.item); // LOG TEST
                    return false; // Quan trọng để ngăn Autocomplete điền giá trị mặc định sai
                },
                focus: function(event, ui) { event.preventDefault(); }, // Ngăn thay đổi giá trị input khi hover
                change: function(event, ui) {
                    // Xử lý khi giá trị trong input thay đổi (người dùng gõ hoặc chọn item)
                    if (!ui.item) { // Nếu không có item được chọn (người dùng gõ mà không chọn từ list)
                        // Xóa các thông tin đã hiển thị và ID ẩn
                        partnerIdInput.val('');
                        partnerAddressDisplay.text('-');
                        partnerTaxIdDisplay.text('-');
                        partnerPhoneDisplay.text('-');
                        partnerEmailDisplay.text('-');
                        partnerContactDisplay.text('-');
                        console.log("Supplier selection cleared."); // LOG TEST
                    }
                }
            });
            console.log("Supplier autocomplete initialized."); // LOG TEST
        } else {
            console.warn("jQuery UI Autocomplete not found. Supplier autocomplete disabled."); // LOG WARNING
            partnerInput.prop('disabled', true); // Tắt input nếu thư viện không có
        }
         console.log("Supplier Autocomplete initialization finished."); // LOG TEST
    }

    // --- Hàm Khởi Tạo Autocomplete Sản Phẩm (Product) ---
    function initializeProductAutocomplete(containerSelector) {
         console.log(`Initializing Product Autocomplete for: ${containerSelector}...`); // LOG TEST
        // Tìm các input có class 'product-autocomplete' bên trong containerSelector
        const targetElements = $(containerSelector).find('.product-autocomplete');
        // Kiểm tra thư viện jQuery UI Autocomplete có tồn tại không
        if (typeof $.ui !== 'undefined' && typeof $.ui.autocomplete !== 'undefined') {
            targetElements.each(function() { // Duyệt qua từng element tìm được
                const inputElement = $(this);
                // Tránh re-initialize Autocomplete nếu đã tồn tại trên element này
                if (!inputElement.data('ui-autocomplete')) {
                    console.log("Initializing NEW product autocomplete for:", this); // LOG TEST cho element cụ thể
                    inputElement.autocomplete({
                        source: function(request, response) {
                            const inputField = $(this.element);
                            // Xóa trạng thái lỗi cũ trước khi tìm kiếm
                            inputField.removeClass('is-invalid').closest('td').find('.invalid-feedback').text('');
                            // Gửi yêu cầu AJAX đến backend để tìm kiếm sản phẩm
                            $.ajax({
                                url: AJAX_URL.product_search, // Endpoint tìm kiếm sản phẩm
                                dataType: "json",
                                data: { action: 'search', term: request.term }, // Gửi term tìm kiếm
                                success: function(data) {
                                    if (data.success && Array.isArray(data.data)) {
                                        // Chuyển đổi dữ liệu sang định dạng label/value cho Autocomplete
                                        const mappedData = $.map(data.data, function(item) {
                                            if (item && typeof item.name !== 'undefined' && typeof item.id !== 'undefined') {
                                                return { label: item.name + (item.category_name ? ` (${item.category_name})` : ''), // Text hiển thị trong dropdown
                                                         value: item.name, // Giá trị điền vào input sau khi chọn
                                                         id: item.id, // ID sản phẩm (lưu vào input ẩn)
                                                         description: item.description || '',
                                                         category_name: item.category_name || '', // Tên danh mục
                                                         unit_name: item.unit_name || '' // Tên đơn vị
                                                       };
                                            } return null; // Bỏ qua các item không hợp lệ
                                        });
                                        response(mappedData); // Truyền dữ liệu cho Autocomplete
                                    } else { console.error("Error fetching products:", data?.message); response([]); } // Báo lỗi nếu fetch thất bại
                                },
                                error: function(jqXHR, textStatus, errorThrown) {
                                    console.error("Product Autocomplete AJAX Error:", textStatus, errorThrown); response([]); // Xử lý lỗi AJAX
                                }
                            });
                        },
                        minLength: 1, // Bắt đầu tìm kiếm sau khi nhập 1 ký tự
                        select: function(event, ui) {
                            event.preventDefault(); // Ngăn hành động mặc định
                            const row = $(this).closest('tr'); // Lấy dòng (<tr>) chứa input hiện tại
                            $(this).val(ui.item.value); // Điền tên sản phẩm vào input
                            row.find('.product-id').val(ui.item.id); // Lưu ID sản phẩm vào input ẩn trong dòng đó
                            row.find('.category-display').val(ui.item.category_name); // Điền tên danh mục vào input hiển thị
                            row.find('input[name$="[category_snapshot]"]').val(ui.item.category_name); // Lưu tên danh mục vào input ẩn snapshot
                            row.find('.unit-display').val(ui.item.unit_name); // Điền tên đơn vị vào input hiển thị
                            row.find('input[name$="[unit_snapshot]"]').val(ui.item.unit_name); // Lưu tên đơn vị vào input ẩn snapshot
                            // Xóa trạng thái lỗi và thông báo lỗi cho input hiện tại
                            $(this).removeClass('is-invalid').closest('td').find('.invalid-feedback').text('');
                            console.log("Product selected:", ui.item); // LOG TEST
                            calculateLineTotal(row); // Tính lại tổng dòng và tổng cộng
                            return false; // Quan trọng để ngăn Autocomplete điền giá trị mặc định sai
                        },
                        focus: function(event, ui) { event.preventDefault(); }, // Ngăn thay đổi giá trị input khi hover
                        change: function(event, ui) {
                            // Xử lý khi giá trị trong input thay đổi (người dùng gõ mà không chọn list)
                            const row = $(this).closest('tr'); // Lấy dòng chứa input
                            if (!ui.item) { // Nếu không có item nào được chọn
                                // Xóa các thông tin sản phẩm trong dòng và ID ẩn
                                row.find('.product-id').val('');
                                row.find('.category-display').val('');
                                row.find('input[name$="[category_snapshot]"]').val('');
                                row.find('.unit-display').val('');
                                row.find('input[name$="[unit_snapshot]"]').val('');
                                console.log("Product selection cleared for row:", row.index()); // LOG TEST
                            }
                            // Luôn tính lại tổng dù có chọn hay không để cập nhật khi giá trị input thay đổi
                            calculateLineTotal(row);
                        }
                    });
                } else { console.log("Autocomplete already initialized for:", this); } // Báo cáo nếu đã init
            });
            console.log(`Product autocomplete initialization attempt complete for selector: ${containerSelector}`); // LOG TEST
        } else {
            console.warn("jQuery UI Autocomplete not found. Product autocomplete disabled."); // LOG WARNING
            targetElements.prop('disabled', true); // Tắt input nếu thư viện không có
        }
         console.log("Product Autocomplete initialization finished."); // LOG TEST
    }


    // --- Hàm Khởi tạo DataTables (SERVER-SIDE) ---
        function initializeSalesOrderDataTable() {
                console.log("Initializing Server-Side DataTables for Sales Orders..."); // LOG TEST
                // Destroy instance cũ nếu có để tránh duplicate
                if ($.fn.dataTable.isDataTable(orderTableElement)) {
                    orderTableElement.DataTable().destroy();
                     console.log("Existing DataTable instance destroyed."); // LOG TEST
                }
        
                try {
                    salesOrderDataTable = orderTableElement.DataTable({
                        processing: true, // Hiện thông báo "Processing..."
                        serverSide: true, // Bật chế độ Server-Side
                        ajax: { // Bắt đầu cấu hình AJAX
                            url: 'process/sales_order_serverside.php', // Endpoint xử lý Server-Side
                            type: 'POST', // Gửi dữ liệu qua POST
                            data: function (d) { // Hàm tùy chỉnh thêm data cho request
                                // Gửi giá trị từ các bộ lọc tùy chỉnh của cột
                                $('.column-filter-input').each(function() {
                                    const colIndex = $(this).data('dt-column-index');
                                    if (colIndex !== undefined && d.columns && d.columns[colIndex]) { // Kiểm tra index cột tồn tại
                                        d.columns[colIndex].search.value = $(this).val();
                                    }
                                });
                                d.item_details_filter = $('#item-details-filter-input').val(); // Gửi bộ lọc chi tiết sản phẩm
        
                               
                                    // === THÊM CÁC DÒNG BỘ LỌC NĂM VÀ THÁNG VÀO ĐÂY ===
                                    // Lấy giá trị được chọn từ dropdown #filterYear
                                    d.filter_year = $('#filterYear').val();
                                    // Lấy giá trị được chọn từ dropdown #filterMonth
                                    d.filter_month = $('#filterMonth').val();
                                    // === KẾT THÚC CÁC DÒNG BỘ LỌC NĂM VÀ THÁNG ===


                                    console.log("DataTables AJAX params:", d); // LOG TEST params gửi đi
                                    return d; // TRẢ VỀ object 'd' đã cập nhật
                                },
                            // >>> Đảm bảo thuộc tính 'error' nằm ngay sau 'data' và có dấu phẩy ở trên <<<
                            error: function(jqXHR, textStatus, errorThrown) {
                                // Xử lý lỗi AJAX từ Server-Side Script
                                console.error("DataTables Server-Side AJAX Error:", textStatus, errorThrown, jqXHR.responseText); // LOG ERROR
                                let errorMsg = LANG['server_error_loading_list'] || 'Lỗi máy chủ khi tải danh sách.';
                                try { // Cố gắng parse lỗi từ server (nếu backend trả về JSON lỗi)
                                    const response = JSON.parse(jqXHR.responseText);
                                    if (response && response.message) {
                                        errorMsg = response.message;
                                    }
                                } catch(e) {}
                                showUserMessage(errorMsg, 'error'); // Hiển thị thông báo lỗi cho người dùng
                                // Hiển thị lỗi trong tbody của bảng
                                orderTableElement.find('tbody').html(`<tr><td colspan="7" class="text-center text-danger">${escapeHtml(errorMsg)}</td></tr>`);
                                $('.dataTables_processing').hide(); // Ẩn thông báo processing
                            }
                        }, // <-- Đóng đối tượng ajax {}. >>> Đảm bảo DẤU ĐÓNG } này tồn tại. <<<
                        // >>> PHẢI CÓ DẤU PHẨY , ở đây vì có thuộc tính 'columns' theo sau <<<
                        columns: [ // <-- Bắt đầu định nghĩa thuộc tính 'columns' (có dấu phẩy trước đó)
                            { // 0: Child Row Control
                                className: 'details-control dt-body-center', orderable: false, data: null, defaultContent: '<i class="bi bi-plus-square text-success"></i>', width: "20px"
                            },
                            { data: 'order_number', name: 'so.order_number', className: 'dt-body-left' }, // 1: Số đơn hàng
                            { data: 'order_date_formatted', name: 'so.order_date', className: 'dt-body-center' }, // 2: Ngày ĐH
                            { data: 'supplier_name', name: 'p.name', className: 'dt-body-left' }, // 3: Nhà cung cấp
                            { // 4: Tổng tiền (Grand Total)
                                data: 'grand_total_formatted', name: 'so.grand_total', className: 'dt-body-right', orderable: true, searchable: false,
                                render: function(data, type, row) { return data + ' <small>' + (row.currency || 'VND') + '</small>'; }
                            }, // <-- Dấu phẩy kết thúc định nghĩa cột 4
                            { // 5: Các nút hành động
                                data: null, orderable: false, searchable: false, className: 'text-end action-cell dt-nowrap',
                                render: function(data, type, row) {
                                    // ... code render nút ...
                                    const orderNumber = escapeHtml(row.order_number || '');
                                    const orderId = row.id;
                                    const safeOrderNumber = sanitizeFilename(row.order_number);
                                    const pdfPath = `pdf/${safeOrderNumber}.pdf`;
        
                                    const viewPdfBtn = `<a href="${pdfPath}" target="_blank" class="btn btn-sm btn-outline-secondary btn-view-pdf" data-id="${orderId}" data-order-number-raw="${orderNumber}" title="${LANG['view_pdf']||'Xem PDF'}"><i class="bi bi-file-earmark-pdf"></i></a>`;
                                    // Nút Gửi Email trong list (sử dụng class btn-send-email)
                                    const sendEmailBtn = `<button class="btn btn-sm btn-outline-primary btn-send-email ms-1" data-id="${orderId}" data-order-number="${orderNumber}" data-pdf-url="${pdfPath}" title="Gửi Email"><i class="bi bi-envelope-fill"></i></button>`;
                                    const editBtn = `<button class="btn btn-sm btn-outline-warning btn-edit-document ms-1" data-id="${orderId}" title="${LANG['edit']||'Sửa'}"><i class="bi bi-pencil-square"></i></button>`;
                                    const deleteBtn = `<button class="btn btn-sm btn-outline-danger btn-delete-document ms-1" data-id="${orderId}" data-number="${orderNumber}" title="${LANG['delete']||'Xóa'}"><i class="bi bi-trash"></i></button>`;
        
                                    return viewPdfBtn + sendEmailBtn + editBtn + deleteBtn;
                                }
                            }, // <-- Dấu phẩy kết thúc định nghĩa cột 5
                            { // 6: Cột mới - Xem Logs Email
                                data: null, orderable: false, searchable: false, className: 'dt-body-center',
                                render: function(data, type, row) {
                                    const orderId = row.id;
                                    const orderNumber = escapeHtml(row.order_number || '');
                                    // Nút xem log email
                                    return `<button class="btn btn-sm btn-outline-info btn-view-order-logs" data-order-id="${orderId}" data-order-number="${orderNumber}" title="Xem lịch sử Email"><i class="bi bi-mailbox"></i></button>`;
                                }
                            } // <-- Không có dấu phẩy nếu đây là cột cuối cùng trong mảng columns
                        ], // <-- Đóng mảng columns ]. >>> Đảm bảo DẤU ĐÓNG ] VÀ DẤU PHẨY , tồn tại nếu có thuộc tính khác sau columns <<<
                        order: [[1, 'desc']], // Mặc định sắp xếp theo cột "order_number" (index 1) giảm dần
                        language: { // Cấu hình ngôn ngữ
                            url: (typeof LANG !== 'undefined' && LANG.language === 'vi') ? 'lang/vi.json' : 'lang/en-GB.json',
                        },
                        paging: true, // Bật phân trang
                        lengthChange: true, // Cho phép thay đổi số dòng trên trang
                        lengthMenu: [
                            [25, 50, -1], // Các giá trị số thực tế (dùng -1 cho 'Tất cả')
                            [25, 50, "Tất cả"] // Các chuỗi hiển thị tương ứng trong dropdown
                        ],
                        searching: false, // TẮT SEARCHING CHUNG (vì dùng filter riêng)
                        info: true, // Hiện thông tin "Showing x to y of z entries"
                        autoWidth: false, // Tắt tự động tính chiều rộng cột
                        responsive: true // Bật Responsive Table
                        // stateSave: true, stateDuration: 3600 // Lưu trạng thái bảng (tùy chọn)
                    }); // <<< Đảm bảo DẤU ĐÓNG NGOẶC NHỌN } CUỐI CÙNG CHO DataTables({...}) <<<
                    console.log("Server-Side DataTables initialized successfully."); // LOG TEST
        
        
                } catch (e) { // Bắt lỗi nếu DataTables init thất bại
                    console.error("Error initializing DataTables:", e); // LOG ERROR
                    showUserMessage(LANG['error_initializing_datatable'] || 'Lỗi khi khởi tạo bảng.', 'error'); // Thông báo lỗi cho người dùng
                }
                 console.log("DataTables initialization finished."); // LOG TEST
            } // <-- Đóng hàm initializeSalesOrderDataTable


    // --- Hàm định dạng chi tiết Child Row ---
    // Được gọi bởi DataTables khi mở child row
    function formatChildRowDetails(details, currency) {
        console.log("Formatting child row details...", details, currency); // LOG TEST
        if (!details || details.length === 0) return `<div class="p-3 text-muted text-center">${LANG['no_items_in_order'] || 'Không tìm thấy sản phẩm.'}</div>`;

        let htmlContent =`<div class="child-row-container p-2">
            <h6 class="ms-3 mt-1 mb-2">${LANG['item_details']||'Chi tiết sản phẩm'}:</h6>
            <table class="table table-sm table-bordered table-striped child-row-details-table" style="width:95%;margin:auto;">
                <thead class="table-light">
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>${LANG['product']||'Sản phẩm'}</th>
                        <th>${LANG['category']||'Danh mục'}</th>
                        <th>${LANG['unit']||'Đơn vị'}</th>
                        <th class="text-end">${LANG['quantity']||'Số lượng'}</th>
                        <th class="text-end">${LANG['unit_price']||'Đơn giá'}</th>
                        <th class="text-end">${LANG['line_subtotal']||'Tổng cộng dòng'}</th>
                    </tr>
                </thead>
                <tbody>`;

        // Format số lượng và giá theo locale và tiền tệ
        let quantityFormatter = { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 2 }; // Số lượng 0-2 thập phân
        // Đơn giá và tổng dòng: 0 thập phân cho VND, 2 cho các loại khác
        let priceFormatter = (currency === 'VND' ? { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 } : { style: 'decimal', minimumFractionDigits: 2, maximumFractionDigits: 2 });
        let displayLocale = (typeof LANG !== 'undefined' && LANG.language === 'vi' ? 'vi-VN' : 'en-US'); // Locale hiển thị số
        let currencySymbol = (currency === 'VND' ? ' đ' : (currency === 'USD' ? ' $' : ` ${currency}`)); // Ký hiệu tiền tệ hiển thị


        details.forEach((item, index)=>{
            const quantity = parseFloat(item.quantity) || 0;
            const unitPrice = parseFloat(item.unit_price) || 0;
            const lineSubtotal = quantity * unitPrice; // Tổng cộng dòng (chưa VAT)

            htmlContent +=`<tr>
                <td class="text-center">${index+1}</td>
                <td>${escapeHtml(item.product_name_snapshot)}</td>
                <td>${escapeHtml(item.category_snapshot)||'-'}</td>
                <td>${escapeHtml(item.unit_snapshot)||'-'}</td>
                <td class="text-end">${quantity.toLocaleString(displayLocale, quantityFormatter)}</td>
                <td class="text-end">${unitPrice.toLocaleString(displayLocale, priceFormatter)}${currencySymbol}</td>
                <td class="text-end fw-bold">${lineSubtotal.toLocaleString(displayLocale, priceFormatter)}${currencySymbol}</td>
            </tr>`;
        });

        htmlContent+=`</tbody></table></div>`;
        console.log("Child row HTML generated."); // LOG TEST
        return htmlContent;
    }

    // --- Hàm Tính Toán Tổng Tiền Dòng (Frontend) ---
    // Tính subtotal cho một dòng item và gọi tính tổng cộng chung
    function calculateLineTotal(row) {
        // console.log("Calculating line total for row:", row); // Có thể log quá nhiều
        const currency = currencySelect.val(); // Lấy tiền tệ hiện tại
        const quantity = parseFloat(row.find('.quantity').val()) || 0; // Lấy số lượng
        const unitPrice = parseFloat(row.find('.unit-price').val()) || 0; // Lấy đơn giá
        const lineTotal = quantity * unitPrice; // Tính tổng dòng (chưa VAT)

        // Format và hiển thị tổng dòng
        let displayFormatter = (currency === 'VND') ? { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 } : { style: 'decimal', minimumFractionDigits: 2, maximumFractionDigits: 2 };
        let displayLocale = (typeof LANG !== 'undefined' && LANG.language === 'vi' ? 'vi-VN' : 'en-US');
        row.find('.line-total').val(lineTotal.toLocaleString(displayLocale, displayFormatter)); // Hiển thị tổng dòng đã format

        calculateSummaryTotals(); // Gọi tính tổng cộng chung
    }

    // --- Hàm Tính Toán Tổng Cộng Hóa Đơn (Frontend) ---
    // Tính subtotal, vat total, grand total cho toàn bộ đơn hàng
    function calculateSummaryTotals() {
        console.log("Calculating summary totals..."); // LOG TEST
        const currency = currencySelect.val(); // Lấy tiền tệ
        const vatRate = parseFloat($('#summary-vat-rate').val()) || 0; // Lấy VAT rate
        let subTotal = 0; // Khởi tạo subtotal

        // Duyệt qua từng dòng item để tính tổng subtotal của tất cả các dòng hợp lệ
        itemTableBody.find('tr').each(function() {
            const quantity = parseFloat($(this).find('.quantity').val()) || 0;
            const unitPrice = parseFloat($(this).find('.unit-price').val()) || 0;
            // Chỉ cộng vào subtotal nếu số lượng > 0 và đơn giá >= 0
            if (quantity > 0 && unitPrice >= 0) {
                subTotal += quantity * unitPrice;
            }
        });

        const vatTotal = subTotal * (vatRate / 100); // Tính VAT
        const grandTotal = subTotal + vatTotal; // Tính tổng cộng

        // Format và hiển thị các tổng cộng
        let displayFormatter = (currency === 'VND') ? { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 } : { style: 'decimal', minimumFractionDigits: 2, maximumFractionDigits: 2 };
        let displayLocale = (typeof LANG !== 'undefined' && LANG.language === 'vi' ? 'vi-VN' : 'en-US');
        let currencySymbol = (currency === 'VND' ? ' đ' : (currency === 'USD' ? ' $' : ` ${currency}`));

        $('#summary-subtotal').text(subTotal.toLocaleString(displayLocale, displayFormatter) + currencySymbol);
        $('#summary-vattotal').text(vatTotal.toLocaleString(displayLocale, displayFormatter) + currencySymbol);
        $('#summary-grandtotal').text(grandTotal.toLocaleString(displayLocale, displayFormatter) + currencySymbol);

        // Cập nhật giá trị ẩn (dùng khi submit form)
        $('#input-subtotal').val(subTotal.toFixed(2)); // Lưu giá trị với 2 chữ số thập phân cho backend
        $('#input-vattotal').val(vatTotal.toFixed(2));
        $('#input-grandtotal').val(grandTotal.toFixed(2));
        $('#input-vatrate').val(vatRate.toFixed(2)); // Lưu rate VAT
        console.log("Summary totals calculated."); // LOG TEST
    }

    // --- Hàm Cập Nhật Số Thứ Tự và Name Attribute của Item Rows ---
    function updateSTT() {
        console.log("Updating STT and input names..."); // LOG TEST
        // Duyệt qua từng dòng (<tr>) trong tbody của bảng item details
        itemTableBody.find('tr').each(function(index) {
            $(this).find('.stt-col').text(index + 1); // Cập nhật số thứ tự hiển thị (bắt đầu từ 1)
            // Cập nhật name attribute cho input và select trong dòng đó
            $(this).find('input, select').each(function() {
                const currentName = $(this).attr('name');
                if (currentName) {
                    // Sử dụng regex để thay thế index cũ trong name attribute bằng index mới
                    // Regex items\[\d+\] khớp với "items[" + một hoặc nhiều chữ số + "]"
                    $(this).attr('name', currentName.replace(/items\[\d+\]/, `items[${index}]`));
                }
            });
        });
        console.log("STT and input names updated.");
    }

    // --- Hàm Reset Form Đơn Hàng ---
    function resetOrderForm(isEdit = false) {
        console.log("Resetting order form. Is Edit:", isEdit); // LOG TEST
        orderForm[0].reset(); // Reset các input form gốc về giá trị mặc định ban đầu
        orderForm.find('input[type="hidden"]').val(''); // Xóa giá trị các input ẩn (bao gồm #order_id)
        // $('#order_id').val(''); // Đảm bảo order_id ẩn được reset (lệnh trên đã bao gồm)
    $('#order_quote_id_form').val('');

        itemTableBody.empty(); // Xóa tất cả các dòng item hiện có
        addItemRow(); // Thêm một dòng item trống mặc định để form không bị rỗng

        // Reset các trường liên quan đến Partner (Supplier)
        $('#partner_autocomplete').val(''); // Xóa tên hiển thị
        $('#partner_id').val(''); // Xóa ID ẩn
        $('#partner_address_display, #partner_tax_id_display, #partner_phone_display, #partner_email_display, #partner_contact_person_display').text('-'); // Reset text hiển thị thông tin partner

        // Reset các trường tổng cộng hiển thị và input ẩn của chúng
        $('#summary-subtotal, #summary-vattotal, #summary-grandtotal').text('0');
        $('#input-subtotal, #input-vattotal, #input-grandtotal').val('0.00');

        // Reset Datepicker
        const flatpickrInstance = document.querySelector(".datepicker")._flatpickr;
        if (flatpickrInstance) flatpickrInstance.clear(); // Xóa ngày đã chọn trong instance flatpickr

        $('#order_number').val(''); // Reset số đơn hàng

        // --- Reset CKEditor cho #notes ---
        // Kiểm tra xem window.editors và instance 'notes' có tồn tại và có hàm setData không
        if (window.editors && window.editors.notes && typeof window.editors.notes.setData === 'function') {
            window.editors.notes.setData(''); // Sử dụng CKEditor API để xóa nội dung
            console.log("CKEditor content for #notes cleared.");
        } else if ($('#notes').length) {
            // Fallback nếu CKEditor chưa init hoặc instance không sẵn sàng, xóa textarea trực tiếp
            $('#notes').val('');
            console.warn("CKEditor for #notes not initialized or accessible, clearing textarea directly.");
        } else {
            console.warn("#notes element not found for clearing.");
        }


        // --- Reset CKEditor cho #emailBody ---
        // Kiểm tra xem window.editors và instance 'emailBody' có tồn tại và có hàm setData không
        if (window.editors && window.editors.emailBody && typeof window.editors.emailBody.setData === 'function') {
            window.editors.emailBody.setData(''); // Sử dụng CKEditor API để xóa nội dung
            console.log("CKEditor content for #emailBody cleared.");
        } else if ($('#emailBody').length) {
            // Fallback nếu CKEditor chưa init hoặc instance không sẵn sàng, xóa textarea trực tiếp
            $('#emailBody').val('');
            console.warn("CKEditor for #emailBody not initialized or accessible, clearing textarea directly.");
        } else {
            console.warn("#emailBody element not found for clearing.");
        }

        // Đây là phần cảnh báo chung nếu CKEditor library không được tải.
        // Thường thì bạn đã nhúng nó ở footer.php, nên phần này ít khi xảy ra nếu cấu hình đúng.
        if (typeof ClassicEditor === 'undefined') {
            console.warn("CKEditor library is globally undefined. Ensure CKEditor script is loaded before this JS file.");
        }


        currencySelect.val('VND'); // Reset tiền tệ về VND mặc định
        const itemCurrencySymbol = (currencySelect.val() === 'VND') ? 'đ' : '$';
        $('.item-row-template').find('.currency-symbol-unit').text(itemCurrencySymbol); // Cập nhật ký hiệu tiền tệ trong template row

        // Reset VAT rate và input ẩn của nó
        $('#summary-vat-rate').val(vatDefaultRate.toFixed(2));
        $('#input-vatrate').val(vatDefaultRate.toFixed(2));

        // Xóa trạng thái lỗi và thông báo lỗi trên toàn form
        orderForm.find('.is-invalid').removeClass('is-invalid');
        orderForm.find('.invalid-feedback').text('');
        formErrorMessageDiv.addClass('d-none').text(''); // Ẩn div thông báo lỗi chung

        // Thiết lập trạng thái form (tạo mới hay sửa/xem)
        orderFormCard.removeClass('view-mode'); // Đảm bảo không ở chế độ xem

        // Bật lại tất cả các input, select, textarea trừ readonly
        orderForm.find('input, select, textarea').not('[readonly]').prop('disabled', false);

        // Hiển thị và bật lại các nút thêm/xóa item và tạo số ĐH
        orderForm.find('#add-item-row, .remove-item-row, #btn-generate-order-number').show().prop('disabled', false);

        saveButton.show().prop('disabled', false); // Hiện và bật nút lưu
        saveButtonText.show(); // Hiện text nút lưu
        saveButtonSpinner.addClass('d-none'); // Ẩn spinner nút lưu

        // Cập nhật text tiêu đề form và text nút lưu dựa vào isEdit
        const formTitleKey = isEdit ? 'edit_order' : 'create_new_order';
        const saveButtonTextKey = isEdit ? 'update' : 'save_order';
        orderFormTitle.text(LANG[formTitleKey] || (isEdit ? 'Edit Order' : 'Create New Order'));
        saveButtonText.text(LANG[saveButtonTextKey] || (isEdit ? 'Update' : 'Save Order'));

        // Reset index item (không cần thiết nếu dùng .find('tr').length)
        // itemIndex = 0;

        updateSTT(); // Cập nhật lại STT cho dòng item mặc định (là dòng duy nhất)

        $('#btn-download-pdf').prop('disabled', true); // Tắt nút tải PDF ban đầu

        console.log("Order form reset.");
    }

    // --- Hàm Thêm Dòng Item vào Form ---
    // data: object chứa dữ liệu item nếu load từ đơn hàng cũ
    function addItemRow(data = {}) {
        console.log("Adding item row. Data:", data); // LOG TEST
        const templateRow = $('.item-row-template').clone(); // Clone template row
        templateRow.removeClass('item-row-template d-none').removeAttr('style'); // Xóa class template và style ẩn
        const newItemIndex = itemTableBody.find('tr').length; // Lấy index mới dựa trên số dòng hiện có (bắt đầu từ 0)

        const currentCurrencyCode = currencySelect.val(); // Lấy tiền tệ hiện tại
        const currencySymbol = (currentCurrencyCode === 'VND') ? 'đ' : '$';
        templateRow.find('.currency-symbol-unit').text(currencySymbol); // Cập nhật ký hiệu tiền tệ trong dòng mới

        templateRow.find('.stt-col').text(newItemIndex + 1); // Cập nhật số thứ tự hiển thị (bắt đầu từ 1)

        // Cập nhật name attribute cho input trong dòng mới và reset giá trị/trạng thái lỗi
        templateRow.find('input, select').each(function() {
            const currentName = $(this).attr('name');
            if (currentName) {
                // Sử dụng regex để thay thế index cũ trong name attribute bằng index mới (newItemIndex)
                // Regex items\[\d+\] khớp với "items[" + một hoặc nhiều chữ số + "]"
                $(this).attr('name', currentName.replace(/items\[\d+\]/, `items[${newItemIndex}]`));
            }
            // Xóa trạng thái lỗi và thông báo lỗi cho các input trong dòng mới
            $(this).removeClass('is-invalid').closest('td').find('.invalid-feedback').text('');

            // Reset giá trị cho các input
            if ($(this).is('input:not(.quantity, .unit-price), select, textarea')) {
                 $(this).val(''); // Xóa giá trị cho các input text/select khác quantity/price
            } else if ($(this).hasClass('quantity')) {
                 // Điền giá trị số lượng từ data hoặc mặc định là 1
                 $(this).val(data.quantity !== undefined ? parseFloat(data.quantity) || 0 : 1);
            } else if ($(this).hasClass('unit-price')) {
                 // Điền giá trị đơn giá từ data hoặc mặc định là 0
                 $(this).val(data.unit_price !== undefined ? parseFloat(data.unit_price) || 0 : 0);
            }
        });

        // Điền dữ liệu nếu có (khi load đơn hàng cũ)
        if (data.id) templateRow.find('td:first-child').append(`<input type="hidden" name="items[${newItemIndex}][detail_id]" value="${data.id}">`); // Lưu detail_id nếu sửa item cũ
        if (data.product_id) templateRow.find('.product-id').val(data.product_id); // Lưu ID sản phẩm ẩn
        if (data.product_name_snapshot) templateRow.find('.product-autocomplete').val(data.product_name_snapshot); // Điền tên sản phẩm hiển thị
        if (data.category_snapshot) { templateRow.find('.category-display').val(data.category_snapshot); templateRow.find('input[name$="[category_snapshot]"]').val(data.category_snapshot); } // Điền danh mục hiển thị và ẩn
        if (data.unit_snapshot) { templateRow.find('.unit-display').val(data.unit_snapshot); templateRow.find('input[name$="[unit_snapshot]"]').val(data.unit_snapshot); } // Điền đơn vị hiển thị và ẩn
        // Quantity và Unit Price đã được điền ở trên dựa vào data hoặc mặc định

        itemTableBody.append(templateRow); // Thêm dòng mới vào tbody của bảng item details
        initializeProductAutocomplete(templateRow); // Khởi tạo lại Autocomplete cho input sản phẩm trong dòng mới
        calculateLineTotal(templateRow); // Tính tổng dòng và tổng cộng ban đầu cho dòng mới này

        console.log("Added item row with index:", newItemIndex, "Data:", data);
        return templateRow; // Trả về đối tượng jQuery của dòng mới được thêm
    }

    // --- Hàm Load Dữ Liệu Đơn Hàng Để Sửa/Xem ---
    // orderId: ID của đơn hàng cần load
    function loadOrderForEdit(orderId) {
        console.log("Loading order for edit/view:", orderId); // LOG TEST
        $.ajax({
            url: AJAX_URL.sales_order, // Endpoint xử lý đơn hàng
            type: 'GET',
            data: { action: 'get_details', id: orderId }, // Action yêu cầu lấy chi tiết
            dataType: 'json', // Mong đợi phản hồi là JSON
            beforeSend: function() {
                // Hiển thị hiệu ứng loading trên form và tắt các nút chính
                orderFormCard.addClass('opacity-50');
                $('#btn-save-order, #btn-cancel-order-form, #btn-download-pdf').prop('disabled', true);
            },
            success: function(response) {
                console.log("Order details received:", response); // LOG TEST phản hồi
                if (response.success && response.data) {
                    resetOrderForm(true); // Reset form về trạng thái sửa (bao gồm thêm 1 dòng item mặc định)

                    const header = response.data.header; // Dữ liệu header
                $('#order_quote_id_form').val(header.quote_id || '');
                    const details = response.data.details; // Mảng dữ liệu item details

                    // Điền dữ liệu Header vào form
                    $('#order_id').val(header.id); // Điền ID đơn hàng (ẩn)
                    $('#order_number').val(header.order_number); // Điền số đơn hàng
                    // Điền ngày đặt hàng
                    const flatpickrInstance = document.querySelector("#order_date")._flatpickr;
                    if (flatpickrInstance && header.order_date_formatted) {
                         // Sử dụng setDate với định dạng đúng để tránh lỗi
                         try {
                             flatpickrInstance.setDate(header.order_date_formatted, true, "d/m/Y");
                         } catch (e) {
                             console.error("Error setting datepicker date:", e);
                             flatpickrInstance.setDate(header.order_date, true); // Thử set bằng định dạng YYYY-MM-DD nếu có
                         }
                    }
                    currencySelect.val(header.currency || 'VND').trigger('change'); // Điền tiền tệ và kích hoạt change event để cập nhật ký hiệu
                    $('#notes').val(header.notes || ''); // Điền ghi chú vào textarea

                    // Cập nhật CKEditor nếu nó đã được khởi tạo cho #notes
                    // Kiểm tra xem window.editors và instance 'notes' có tồn tại và có hàm setData không
                    if (window.editors && window.editors.notes && typeof window.editors.notes.setData === 'function') {
                        var newContentForNotes = $('#notes').val(); // Lấy nội dung từ textarea gốc
                        window.editors.notes.setData(newContentForNotes); // Cập nhật nội dung cho CKEditor
                        console.log("CKEditor content for #notes updated with new data.");
                    } else if ($('#notes').length) {
                        // Fallback nếu CKEditor chưa được khởi tạo hoặc instance không sẵn sàng,
                        // thì giá trị đã được đặt trực tiếp vào textarea.
                        // CKEditor sẽ tự động lấy giá trị này khi nó được khởi tạo (nếu khởi tạo sau).
                        $('#notes').val(newContentForNotes); // Đảm bảo textarea ẩn được cập nhật (nếu cần)
                        console.warn("CKEditor for #notes not yet initialized or accessible. The textarea value has been set.");
                    } else {
                        console.warn("#notes element not found for updating.");
                    }

                    // Điền VAT rate
                    const vatRateHeader = parseFloat(header.vat_rate);
                    $('#summary-vat-rate').val(isNaN(vatRateHeader) ? vatDefaultRate.toFixed(2) : vatRateHeader.toFixed(2));
                    $('#input-vatrate').val(isNaN(vatRateHeader) ? vatDefaultRate.toFixed(2) : vatRateHeader.toFixed(2));


                    // Điền thông tin Partner (Supplier) - Sử dụng dữ liệu snapshot nếu có
                    const supplierInfoSnapshot = header.supplier_info_data; // Dữ liệu snapshot partner
                    if (supplierInfoSnapshot && typeof supplierInfoSnapshot === 'object') { // Nếu snapshot là object
                        $('#partner_id').val(header.supplier_id); // Lưu ID partner
                        $('#partner_autocomplete').val(supplierInfoSnapshot.name || ''); // Điền tên hiển thị
                        $('#partner_address_display').text(supplierInfoSnapshot.address || '-'); // Cập nhật thông tin hiển thị
                        $('#partner_tax_id_display').text(supplierInfoSnapshot.tax_id || '-');
                        $('#partner_phone_display').text(supplierInfoSnapshot.phone || '-');
                        $('#partner_email_display').text(supplierInfoSnapshot.email || '-');
                        $('#partner_contact_person_display').text(supplierInfoSnapshot.contact_person || '-');
                         console.log("Loaded supplier info from object snapshot."); // LOG TEST
                    } else if (supplierInfoSnapshot && typeof supplierInfoSnapshot === 'string') { // Nếu snapshot là JSON string
                         try {
                             const parsedSupplierInfo = JSON.parse(supplierInfoSnapshot);
                              if (parsedSupplierInfo && typeof parsedSupplierInfo === 'object') {
                                   $('#partner_id').val(header.supplier_id); // Lưu ID partner
                                   $('#partner_autocomplete').val(parsedSupplierInfo.name || ''); // Điền tên hiển thị
                                   $('#partner_address_display').text(parsedSupplierInfo.address || '-'); // Cập nhật thông tin hiển thị
                                   $('#partner_tax_id_display').text(parsedSupplierInfo.tax_id || '-');
                                   $('#partner_phone_display').text(parsedSupplierInfo.phone || '-');
                                   $('#partner_email_display').text(parsedSupplierInfo.email || '-');
                                   $('#partner_contact_person_display').text(parsedSupplierInfo.contact_person || '-');
                                    console.log("Loaded supplier info from JSON snapshot string."); // LOG TEST
                              } else { console.warn("Supplier snapshot data from server is not a valid JSON object.", orderId); } // LOG WARNING
                         } catch (e) {
                             console.error("Error parsing supplier_info_data for order", orderId, ":", e); // LOG ERROR
                             // Nếu parse lỗi, xóa thông tin hiển thị và ID ẩn
                             $('#partner_id').val(''); $('#partner_autocomplete').val(''); $('#partner_address_display, #partner_tax_id_display, #partner_phone_display, #partner_email_display, #partner_contact_person_display').text('-');
                         }
                    }
                    else { // Nếu không có snapshot hoặc snapshot không hợp lệ
                        $('#partner_id').val(''); $('#partner_autocomplete').val(''); $('#partner_address_display, #partner_tax_id_display, #partner_phone_display, #partner_email_display, #partner_contact_person_display').text('-');
                        console.warn("Supplier snapshot missing/invalid for order ID:", orderId); // LOG WARNING
                    }

                    // Điền dữ liệu Details (Item rows)
                    itemTableBody.empty(); // Xóa dòng mặc định đã thêm trong resetOrderForm(true)
                    if (details && details.length > 0) {
                        details.forEach(item => addItemRow(item)); // Thêm từng dòng item từ dữ liệu
                    } else {
                        addItemRow(); // Nếu không có item nào, thêm 1 dòng trống mặc định
                    }

                    calculateSummaryTotals(); // Tính lại các tổng cộng sau khi thêm item

                    // Kích hoạt nút tải PDF nếu có số đơn hàng (chỉ khi số đơn hàng không rỗng)
                    $('#btn-download-pdf').prop('disabled', !header.order_number);

                    // Kiểm tra status đơn hàng để thiết lập chế độ (xem hay sửa)
                    if (header.status !== 'draft') {
                        orderFormCard.addClass('view-mode'); // Thêm class CSS 'view-mode'
                        orderFormTitle.text((LANG['view_order_details']||'Xem chi tiết đơn hàng') + ` (${header.order_number})`); // Cập nhật tiêu đề
                        // Tắt (disable) tất cả input, select, textarea trừ các nút đặc biệt
                        orderForm.find('input,select,textarea').not('#btn-cancel-order-form,#btn-download-pdf,#toggle-signature').prop('disabled', true);
                        // Ẩn các nút thêm/xóa item và tạo số ĐH
                        orderForm.find('#add-item-row,.remove-item-row,#btn-generate-order-number').hide();
                        saveButton.hide(); // Ẩn nút lưu
                        $('#btn-cancel-order-form').text(LANG['close']||'Đóng'); // Đổi text nút hủy thành đóng
                        console.log("Loaded in VIEW mode (status:", header.status, ")"); // LOG TEST
                    } else { // Nếu status là 'draft', cho phép sửa
                        orderFormCard.removeClass('view-mode'); // Xóa class CSS 'view-mode'
                        orderFormTitle.text((LANG['edit_order']||'Sửa đơn hàng') + ` (${header.order_number})`); // Cập nhật tiêu đề
                        saveButtonText.text(LANG['update']||'Cập nhật'); // Đổi text nút lưu thành cập nhật
                         // Bật lại (enable) tất cả input, select, textarea trừ readonly
                        orderForm.find('input,select,textarea').not('[readonly]').prop('disabled', false);
                        // Hiện lại các nút thêm/xóa item và tạo số ĐH
                        orderForm.find('#add-item-row,.remove-item-row,#btn-generate-order-number').show();
                        saveButton.show(); // Hiện lại nút lưu
                        $('#btn-cancel-order-form').text(LANG['cancel']||'Hủy'); // Giữ text nút hủy
                        console.log("Loaded in EDIT mode (status: draft)"); // LOG TEST
                    }

                    orderFormCard.slideDown(); // Hiện form bằng hiệu ứng slide down
                    orderListTitle.hide(); // Ẩn tiêu đề danh sách

                    // Cuộn trang lên đầu form nếu không ở chế độ xem (để người dùng dễ thấy form)
                    if (!orderFormCard.hasClass('view-mode')) {
                        $('html, body').animate({ scrollTop: orderFormCard.offset().top - 0 }, 300);
                    }

                } else {
                    // Xử lý trường hợp backend trả về success: false
                    showUserMessage(response.message || LANG['error_loading_details'] || 'Lỗi khi tải chi tiết đơn hàng.', 'error'); // Thông báo lỗi
                    resetOrderForm(); // Reset form nếu tải thất bại
                    orderFormCard.removeClass('view-mode'); // Đảm bảo không ở chế độ xem
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Xử lý lỗi AJAX
                console.error("AJAX Error loading order details:", textStatus, errorThrown); // LOG ERROR
                showUserMessage(LANG['server_error_loading_details'] || 'Lỗi máy chủ khi tải chi tiết đơn hàng.', 'error'); // Thông báo lỗi
                resetOrderForm(); // Reset form nếu gặp lỗi server
                 orderFormCard.removeClass('view-mode'); // Đảm bảo không ở chế độ xem
            },
            complete: function() {
                orderFormCard.removeClass('opacity-50'); // Xóa hiệu ứng loading trên form
                // Bật lại các nút sau khi hoàn thành (chỉ bật nút lưu nếu không ở chế độ xem)
                if (!orderFormCard.hasClass('view-mode')) {
                    saveButton.prop('disabled', false);
                }
                $('#btn-cancel-order-form').prop('disabled', false); // Nút hủy/đóng luôn bật
                 console.log("Load order details AJAX request complete."); // LOG TEST
            }
        });
    }


    // --- Hàm Xử lý Lỗi Validation từ Server và hiển thị trên Form ---
    function handleFormValidationErrors(errors) {
         // Hiển thị div báo lỗi chung và xóa nội dung/class lỗi cũ
         formErrorMessageDiv.text(LANG['validation_failed'] || 'Vui lòng kiểm tra và sửa các lỗi trong form.').removeClass('d-d-none').removeClass('d-none');
         console.log("Handling server-side validation errors:", errors); // LOG TEST

         // Hàm helper để thêm class lỗi và thông báo lỗi cho một input cụ thể
         const addError = (fieldName, errorMessage) => {
             let inputElement = $(`#${fieldName}`); // Tìm input theo ID

             // Xử lý các trường đặc biệt không có ID khớp trực tiếp hoặc cần xử lý riêng
             if (inputElement.length === 0 && fieldName === 'partner_id') { // Nếu lỗi cho partner_id, apply cho input autocomplete
                 inputElement = $('#partner_autocomplete');
             }
             else if (inputElement.length === 0 && fieldName === 'vat_rate') { // Nếu lỗi cho vat_rate, apply cho input hiển thị VAT rate
                 inputElement = $('#summary-vat-rate');
             }
             // Thêm các trường đặc biệt khác nếu có

             if (inputElement.length) {
                 // Thêm class lỗi is-invalid
                 inputElement.addClass('is-invalid');
                 // Tìm hoặc thêm div báo lỗi cụ thể dưới input
                 // Tìm element cha gần nhất phù hợp với cấu trúc form của bạn (mb-2, mb-3, input-group, cột Bootstrap)
                 let feedbackDiv = inputElement.closest('.mb-2, .mb-3, .input-group, .col-sm-7, .col-md-6, .col-sm-5').find('.invalid-feedback').first();
                 if (feedbackDiv.length) {
                     // Nếu tìm thấy div feedback, cập nhật text và hiện nó
                     feedbackDiv.text(errorMessage).show();
                 } else {
                      // Nếu không tìm thấy div feedback, thêm mới div feedback sau input element
                     inputElement.after(`<div class="invalid-feedback d-block">${escapeHtml(errorMessage)}</div>`);
                 }
             } else {
                 // Nếu không tìm thấy inputelement cho fieldName, thêm lỗi vào div lỗi chung
                 console.warn(`Input field not found for validation error: "${fieldName}"`); // LOG WARNING
                 formErrorMessageDiv.append(`<br/>(${fieldName}): ${escapeHtml(errorMessage)}`);
             }
         };

         // Xử lý lỗi của các trường header (không phải items)
         $.each(errors, (fieldName, messages) => {
             // Kiểm tra nếu fieldName không phải 'items' hoặc 'items_general' và có mảng thông báo lỗi
             if (fieldName !== 'items' && fieldName !== 'items_general' && Array.isArray(messages) && messages.length > 0) {
                 addError(fieldName, messages[0]); // Chỉ hiển thị thông báo lỗi đầu tiên cho trường đó
             }
         });

         // Xử lý lỗi chung liên quan đến items (ví dụ: cần ít nhất 1 item hợp lệ)
         if(errors['items_general'] && Array.isArray(errors['items_general']) && errors['items_general'].length > 0){
             errors['items_general'].forEach(msg => formErrorMessageDiv.append('<br/>' + escapeHtml(msg)));
         }


         // Xử lý lỗi cụ thể cho từng dòng item
         // errors.items mong đợi là một object mà key là index dòng (string hoặc số) và value là mảng các thông báo lỗi cho dòng đó
         if(errors.items && typeof errors.items === 'object'){
             $.each(errors.items, (itemIndex, itemErrors) => {
                 // Tìm dòng item (<tr>) trong bảng item details bằng index
                 const row = itemTableBody.find('tr').eq(parseInt(itemIndex));

                 if(row.length && Array.isArray(itemErrors)){ // Kiểm tra dòng tồn tại và có mảng lỗi cho dòng đó
                     console.log(`Validation errors for item row ${itemIndex}:`, itemErrors); // LOG TEST

                     itemErrors.forEach(errorMessage => {
                         let errorHandled = false; // Flag để kiểm tra lỗi đã được gắn vào input cụ thể chưa

                         // Cố gắng gắn lỗi vào các input cụ thể trong dòng item dựa vào nội dung thông báo lỗi
                         if(errorMessage.toLowerCase().includes('product')){ // Nếu thông báo lỗi liên quan đến sản phẩm
                             row.find('.product-autocomplete').addClass('is-invalid').closest('td').find('.invalid-feedback').text(errorMessage).show();
                             errorHandled = true;
                         } else if(errorMessage.toLowerCase().includes('quantity')){ // Nếu thông báo lỗi liên quan đến số lượng
                             row.find('.quantity').addClass('is-invalid').closest('td').find('.invalid-feedback').text(errorMessage).show();
                             errorHandled = true;
                         } else if(errorMessage.toLowerCase().includes('price')){ // Nếu thông báo lỗi liên quan đến giá (bao gồm 'unit price')
                             row.find('.unit-price').addClass('is-invalid').closest('td').find('.invalid-feedback').text(errorMessage).show();
                             errorHandled = true;
                         }

                         // Nếu lỗi không khớp với input cụ thể nào trong dòng, thêm thông báo lỗi vào cuối dòng
                         if (!errorHandled) {
                             row.find('td:last').append(`<div class="text-danger small invalid-feedback d-block">${escapeHtml(errorMessage)}</div>`);
                         }
                     });
                 }
             });
         }

         // Cuộn trang lên đầu lỗi đầu tiên (ưu tiên div lỗi chung, sau đó là input lỗi đầu tiên)
         if(formErrorMessageDiv.is(':visible')) {
              $('html,body').animate({ scrollTop: formErrorMessageDiv.offset().top - 0 }, 300);
         } else {
             const firstInvalidElement = orderForm.find('.is-invalid').first();
             if (firstInvalidElement.length) {
                 $('html,body').animate({ scrollTop: firstInvalidElement.offset().top - 0 }, 300);
             }
         }
         console.log("Finished handling validation errors."); // LOG TEST
    }

    // --- Hàm Xuất PDF (Đã cập nhật để lưu server và mở tab mới) ---
    function downloadOrderPDF() {
        console.log("Generating Order PDF for viewing and server-side saving..."); // LOG TEST
        const elementToCapture = document.getElementById('pdf-export-content'); // Element chứa nội dung cần xuất
        const downloadButton = $('#btn-download-pdf');
        const buttonText = downloadButton.find('.export-text');
        const buttonSpinner = downloadButton.find('.spinner-border');
        let filename = sanitizeFilename($('#order_number').val()); // Lấy số ĐH làm tên file, làm sạch

        // Kiểm tra element và tên file
        if (!elementToCapture) { console.error("PDF export failed: #pdf-export-content not found."); showUserMessage(LANG['pdf_export_error_element'] || 'Không tìm thấy nội dung xuất PDF.', 'error'); return; }
        if (!filename) {
            // Nếu số đơn hàng trống, tạo tên file mặc định
            const orderIdForFilename = $('#order_id').val() || Date.now(); // Dùng ID đơn hàng hoặc timestamp
            filename = 'sales_order_' + orderIdForFilename;
            console.warn("Order number empty, using default filename:", filename); // LOG WARNING
        }

        // Vô hiệu hóa nút và hiện spinner trong quá trình xử lý
        downloadButton.prop('disabled', true);
        buttonText.hide();
        buttonSpinner.removeClass('d-none');

        const pdfContentArea = $(elementToCapture);
        // Các element cần ẩn đi khi export PDF (nút, input form, v.v.)
        const elementsToHide = pdfContentArea.find('#add-item-row, #btn-generate-order-number, .remove-item-row, #add-item-row-container, #save-signature-pos-size, #signature-feedback, #signature-upload, #toggle-signature, .action-cell-item, .tox-menubar, .tox-toolbar-container, .tox-statusbar , .tox-editor-header, #form-error-message, .invalid-feedback'); // Thêm các element lỗi nếu cần
        console.log(`Hiding ${elementsToHide.length} elements for PDF export.`);
        elementsToHide.addClass('hide-on-pdf-export'); // Thêm class CSS để ẩn

        // Sử dụng html2canvas để render nội dung HTML thành canvas (để tạo ảnh)
        new Promise((resolve, reject) => {
            html2canvas(elementToCapture, {
                scale: 0.8, // Tăng scale lên 2 để hình ảnh sắc nét hơn
                useCORS: true, // Cho phép load ảnh từ nguồn khác domain nếu cần (ví dụ: ảnh chữ ký)
                logging: false, // Tắt log của html2canvas
                backgroundColor: '#ffffff' // Đặt màu nền trắng (để tránh nền trong suốt)
            }).then(resolve).catch(reject);
        })
        .then(canvas => {
            console.log("html2canvas rendered successfully."); // LOG TEST
            const imgData = canvas.toDataURL('image/png'); // Lấy dữ liệu ảnh dạng base64 (PNG)
            const { jsPDF } = window.jspdf; // Lấy thư viện jsPDF từ window object

            // Tạo instance jsPDF
            const pdf = new jsPDF({
                orientation: 'portrait', // Chiều dọc
                unit: 'mm', // Đơn vị milimeters
                format: 'a4' // Khổ giấy A4
            });

            // Tính toán kích thước và vị trí ảnh trên trang PDF để vừa vặn
            const imageProperties = pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth(); // Chiều rộng trang PDF
            const pdfHeight = pdf.internal.pageSize.getHeight(); // Chiều cao trang PDF
            const margin = 10; // Margin 10mm
            const availableWidth = pdfWidth - 2 * margin; // Chiều rộng khả dụng
            const availableHeight = pdfHeight - 2 * margin; // Chiều cao khả dụng

            let imageWidth = imageProperties.width;
            let imageHeight = imageProperties.height;
            const imageRatio = imageWidth / imageHeight; // Tỷ lệ ảnh

            // Scale ảnh để vừa với khổ giấy (giữ tỷ lệ)
            if(imageWidth > availableWidth){
                imageWidth = availableWidth;
                imageHeight = imageWidth / imageRatio;
            }
            if(imageHeight > availableHeight){
                imageHeight = availableHeight;
                imageWidth = imageHeight * imageRatio;
            }

            // Tính toán vị trí để căn giữa ảnh (ngang) và đặt ở top margin (dọc)
            const marginLeft = (pdfWidth - imageWidth) / 2;
            const marginTop = margin; // Bắt đầu từ top margin

            // Thêm ảnh vào PDF
            pdf.addImage(imgData, 'PNG', marginLeft, marginTop, imageWidth, imageHeight);
            console.log("Image added to jsPDF."); // LOG TEST

            // Mở PDF trong tab mới để xem trước
            try {
                const pdfBlob = pdf.output('blob'); // Xuất PDF dưới dạng Blob
                const blobUrl = URL.createObjectURL(pdfBlob); // Tạo URL tạm thời cho Blob
                const newWindow = window.open(blobUrl, '_blank'); // Mở trong tab mới
                if(!newWindow){
                    showUserMessage(LANG['popup_blocked_pdf']||'Trình duyệt đã chặn popup xem trước PDF. Vui lòng cho phép hiển thị popup.', 'warning'); // Thông báo nếu popup bị chặn
                }else{
                    console.log("PDF opened in new tab."); // LOG TEST
                }
            } catch(viewError){
                console.error("Error opening PDF blob:", viewError); // LOG ERROR
                showUserMessage(LANG['pdf_open_error']||'Không thể mở xem trước PDF. Vui lòng thử lại.', 'error'); // Thông báo lỗi
            }

            // Gửi PDF lên server để lưu file vật lý
            try {
                const pdfBase64 = pdf.output('datauristring'); // Xuất PDF dạng data URI (base64 string)
                console.log("Sending PDF to server for saving..."); // LOG TEST
                $.ajax({
                    url:'includes/save_pdf.php', // Endpoint lưu PDF (cần tồn tại và xử lý POST JSON)
                    type:'POST',
                    contentType:'application/json', // Set content type là JSON vì gửi base64 trong JSON object
                    data:JSON.stringify({ filename: filename + '.pdf', pdf_data: pdfBase64 }), // Gửi tên file và data base64
                    dataType:'json', // Mong đợi phản hồi là JSON
                    success:function(saveResponse){
                        if(saveResponse.success){
                            console.log('PDF saved on server successfully:', filename + '.pdf'); // LOG TEST thành công
                            // showUserMessage(LANG['pdf_saved_success']||'Đã lưu PDF trên máy chủ.', 'success'); // Tùy chọn: thông báo lưu thành công
                        }else{
                            console.error('Server save error:', saveResponse.message); // LOG ERROR
                            showUserMessage(LANG['pdf_saved_server_error']||'Lỗi khi lưu PDF trên máy chủ: '+escapeHtml(saveResponse.message), 'error'); // Thông báo lỗi
                        }
                    },
                    error:function(jqXHR, textStatus, errorThrown){
                        console.error('AJAX Save Error:', textStatus, errorThrown); // LOG ERROR
                        showUserMessage(LANG['server_error_saving_pdf']||'Lỗi máy chủ khi gửi yêu cầu lưu PDF.', 'error'); // Thông báo lỗi
                    }
                });
            } catch(ajaxError){
                console.error("AJAX init error for saving PDF:", ajaxError); // LOG ERROR
                showUserMessage(LANG['pdf_save_request_error']||'Không thể gửi yêu cầu lưu PDF.', 'error'); // Thông báo lỗi
            }
        })
        .catch(error => { // Bắt lỗi từ html2canvas hoặc Promise
            console.error("html2canvas or Promise failed:", error); // LOG ERROR
            showUserMessage(LANG['pdf_export_render_error'] || 'Lỗi khi tạo ảnh từ nội dung.', 'error'); // Thông báo lỗi
        })
        .finally(() => { // Luôn chạy sau khi promise hoàn thành (thành công hoặc thất bại)
            console.log("Restoring hidden elements and enabling button."); // LOG TEST
            elementsToHide.removeClass('hide-on-pdf-export'); // Hiện lại các element đã ẩn
            downloadButton.prop('disabled', false); // Bật lại nút tải PDF
            buttonText.show();
            buttonSpinner.addClass('d-none');
            console.log("PDF generation/save process finished."); // LOG TEST
        });
    }


    // --- Hàm Hiển Thị Thông Báo (Đã sửa lỗi const trong phạm vi này) ---
    // type có thể là 'success', 'error', 'warning', 'info'
    function showUserMessage(message, type = 'success') {
        let alertContainer=$('#alert-container'); // Tìm container alert
        if(!alertContainer.length){ // Nếu chưa có, tạo mới và thêm vào body
            $('body').append('<div id="alert-container" class="position-fixed top-0 end-0 p-3" style="z-index:1100"></div>');
            alertContainer=$('#alert-container'); // Lấy lại container vừa tạo
        }
        if(alertContainer.length){ // Nếu container tồn tại
            // Xác định class Bootstrap alert dựa trên type
            let alertType=(type==='error'?'danger':(type==='warning'?'warning':(type==='info'?'info':'success')));
            let alertId='alert-'+Date.now(); // ID duy nhất cho mỗi alert
            // Tạo HTML cho alert
            let alertHtml=`<div id="${alertId}" class="alert alert-${alertType} alert-dismissible fade show" role="alert" style="min-width:250px;">
                ${escapeHtml(message)} // Hiển thị thông báo (đã escape HTML)
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> // Nút đóng
            </div>`;
            alertContainer.append(alertHtml); // Thêm alert vào container

            let newAlert=$('#'+alertId); // Lấy alert vừa thêm bằng ID
            // Tự động đóng alert sau 5 giây
            setTimeout(()=>{
                try{
                    // Cố gắng lấy instance Bootstrap Alert và gọi close
                    const bsAlert = bootstrap.Alert.getInstance(newAlert);
                    if(bsAlert){
                         bsAlert.close();
                    } else {
                         // Nếu không lấy được instance, có thể tạo mới và đóng
                         (new bootstrap.Alert(newAlert)).close();
                    }
                }catch(e){
                    // Xử lý lỗi nếu đóng không thành công, chỉ xóa element
                    console.warn("Could not close alert:",e,newAlert); // LOG WARNING
                    newAlert.remove();
                }
            },5000); // Thời gian hiển thị alert: 5000ms = 5 giây
        }else{
            // Fallback: nếu không tạo được container alert, dùng alert() của trình duyệt
            alert((type==='error'?'ERROR: ':(type==='warning'?'WARNING: ':(type==='info'?'INFO: ':'')))+message);
        }
    }



    // --- Hàm Setup Tất Cả Event Listeners ---
    // Đây là nơi tất cả các sự kiện click, change, submit, keyup... được gắn vào các element
    function setupEventListeners() {
        console.log("setupEventListeners function started for " + APP_CONTEXT.type);

        // --- Listener cho nút Tạo Mới Đơn Hàng ---
        $('#btn-create-new-order').on('click', function() {
            console.log(">>> Listener #btn-create-new-order clicked!"); // LOG TEST
            resetOrderForm(); // Reset form về trạng thái tạo mới
            // resetOrderForm đã bao gồm addItemRow(), nên không cần gọi lại ở đây
            orderFormCard.slideDown(); // Hiện form
            orderListTitle.hide(); // Ẩn tiêu đề danh sách
            $('html, body').animate({ scrollTop: orderFormCard.offset().top - 0 }, 300); // Cuộn lên form
            $('#partner_autocomplete').focus(); // Focus vào trường nhà cung cấp
        });

        // --- Listener cho nút Hủy Form (hoặc Đóng Form ở chế độ View) ---
        $('#btn-cancel-order-form').on('click', function() {
            console.log(">>> Listener #btn-cancel-order-form clicked!"); // LOG TEST
            orderFormCard.slideUp(function(){ // Ẩn form với hiệu ứng slide up
                resetOrderForm(); // Reset form sau khi ẩn hoàn thành
            });
            orderListTitle.show(); // Hiện lại tiêu đề danh sách DataTables
        });

        // --- Listener cho nút Tạo Số Đơn Hàng Tự Động ---
        $('#btn-generate-order-number').on('click', function() {
            console.log(">>> Listener #btn-generate-order-number clicked!"); // LOG TEST
            const button = $(this);
            button.prop('disabled',true); // Tắt nút trong khi xử lý AJAX
            $.ajax({
                url: AJAX_URL.sales_order, // Endpoint xử lý đơn hàng
                type: 'GET', // Method GET cho yêu cầu lấy dữ liệu
                data: { action: 'generate_order_number' }, // Action yêu cầu tạo số
                dataType: 'json', // Mong đợi phản hồi là JSON
                success: function(response) {
                    console.log(">>> Generate Order # AJAX success response:", response); // LOG TEST phản hồi
                    if(response.success && response.order_number){
                        // Điền số đơn hàng mới và xóa trạng thái lỗi nếu có
                        $('#order_number').val(response.order_number)
                                         .removeClass('is-invalid')
                                         .closest('.input-group') // Tìm element cha gần nhất (ví dụ: div input-group)
                                         .find('.invalid-feedback').text(''); // Xóa thông báo lỗi cụ thể
                        showUserMessage(response.message || (LANG['number_generated'] || 'Đã tạo số đơn hàng.'), 'success'); // Thông báo thành công
                    } else {
                        console.error("Error generating order number:", response.message); // LOG ERROR
                        showUserMessage(response.message || (LANG['error_generating_number'] || 'Lỗi khi tạo số đơn hàng.'), 'error'); // Thông báo lỗi
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error(">>> AJAX Generate Order # Error:", textStatus, errorThrown); // LOG ERROR AJAX
                    showUserMessage(LANG['server_error'] || 'Lỗi máy chủ.', 'error'); // Thông báo lỗi chung máy chủ
                },
                complete: function() {
                    button.prop('disabled',false); // Luôn bật lại nút sau khi request hoàn thành
                }
            });
        });

        // --- Listener khi thay đổi giá trị VAT Rate (Input/Change) ---
        $('#summary-vat-rate').on('input change', calculateSummaryTotals); // Bất kỳ khi input thay đổi (gõ) hoặc change (blur)

        // --- Listener cho nút Thêm Dòng Item ---
        $('#add-item-row').on('click', function(){
            console.log(">>> Listener #add-item-row clicked!"); // LOG TEST
            addItemRow(); // Gọi hàm thêm dòng item
            itemTableBody.find('tr:last .product-autocomplete').focus(); // Focus vào input sản phẩm của dòng vừa thêm
        });

        // --- Listener cho nút Xóa Dòng Item (Delegated Event trên itemTableBody) ---
        itemTableBody.on('click', '.remove-item-row', function() {
            console.log(">>> Listener .remove-item-row clicked!"); // LOG TEST
            const row = $(this).closest('tr'); // Lấy dòng (<tr>) chứa nút xóa đã bấm
            if(itemTableBody.find('tr').length > 1){
                // Nếu số dòng item hiện tại lớn hơn 1, xóa dòng đã bấm
                row.fadeOut(300, function(){ // Hiệu ứng fade out
                    $(this).remove(); // Xóa element HTML của dòng
                    updateSTT(); // Cập nhật số thứ tự và name attribute cho các dòng còn lại
                    calculateSummaryTotals(); // Tính lại tổng cộng sau khi xóa
                });
            } else {
                // Nếu chỉ còn 1 dòng, reset nội dung dòng đó thay vì xóa hoàn toàn
                console.log("Last row, resetting instead of removing."); // LOG TEST
                // Xóa giá trị và trạng thái lỗi trên dòng cuối cùng
                row.find('input[type=text], input[type=number], input[type=hidden]').val('');
                row.find('.product-autocomplete').val(''); // Xóa tên sản phẩm autocomplete
                row.find('.product-id').val(''); // Xóa ID sản phẩm ẩn
                row.find('.category-display').val(''); // Xóa hiển thị danh mục
                row.find('input[name$="[category_snapshot]"]').val(''); // Xóa snapshot danh mục
                row.find('.unit-display').val(''); // Xóa hiển thị đơn vị
                row.find('input[name$="[unit_snapshot]"]').val(''); // Xóa snapshot đơn vị

                row.find('.quantity').val(1); // Reset số lượng về 1
                row.find('.unit-price').val('0'); // Reset đơn giá về 0

                row.find('.is-invalid').removeClass('is-invalid'); // Xóa class lỗi
                row.find('.invalid-feedback').text(''); // Xóa thông báo lỗi cụ thể

                calculateLineTotal(row); // Tính lại tổng cộng (sẽ là 0)
            }
        });

        // --- Listener khi thay đổi Số Lượng hoặc Đơn Giá của Item (Delegated Event) ---
        itemTableBody.on('input change', '.quantity, .unit-price', function() {
            // console.log(">>> Listener quantity/unit-price changed!"); // Có thể log quá nhiều nếu bật
            calculateLineTotal($(this).closest('tr')); // Tính lại tổng dòng và tổng cộng cho dòng đã thay đổi
        });

        // --- Listener khi thay đổi Tiền Tệ (Select) ---
        currencySelect.on('change', function() {
            const newCurrencyCode = $(this).val(); // Lấy mã tiền tệ mới
            const newCurrencySymbol = (newCurrencyCode === 'VND') ? 'đ' : '$'; // Xác định ký hiệu tiền tệ
            console.log(`Currency changed: ${newCurrencyCode}, Symbol: ${newCurrencySymbol}`); // LOG TEST

            // Cập nhật ký hiệu tiền tệ hiển thị trên các dòng item hiện có
            itemTableBody.find('.currency-symbol-unit').text(newCurrencySymbol);
            // Cập nhật ký hiệu tiền tệ trong template row để dùng cho dòng mới thêm sau này
            $('.item-row-template .currency-symbol-unit').text(newCurrencySymbol);

            calculateSummaryTotals(); // Tính lại tổng cộng (chỉ cập nhật hiển thị tiền tệ, giá trị số không đổi)
        });

        // <<< THÊM LISTENER CHO NÚT XÓA FILE PDF MẶC ĐỊNH >>>
        $('.btn-remove-default-attachment').on('click', function() {
        console.log("Removing default PDF attachment display.");
        // Ẩn phần hiển thị file PDF mặc định
        $('#emailAttachmentDisplay').addClass('d-none'); // Hoặc dùng slideUp() cho hiệu ứng
        // Bạn không cần xóa giá trị khỏi input hidden emailPdfUrlForJsInput ở đây.
        // Logic kiểm tra để gửi hay không sẽ nằm trong submit handler.
        }); // <<< Khối này đã có (khoảng dòng 2302)


        // <<< LISTENER CHO SUBMIT FORM CHÍNH (#order-form) >>>
    orderForm.on('submit', function(e) {
    console.log(">>> Listener orderForm submit event triggered!");
    const currentAction = $('#current_order_action').val();
    e.preventDefault();

    let isValid = true;
    formErrorMessageDiv.addClass('d-none').text('');
    orderForm.find('.is-invalid').removeClass('is-invalid');
    orderForm.find('.invalid-feedback').text('');

    // Client-side Validation - Kiểm tra các trường header bắt buộc
    if (!$('#partner_id').val()) {
        $('#partner_autocomplete').addClass('is-invalid').closest('.mb-2').find('.invalid-feedback').text(LANG['supplier_required'] || 'Vui lòng chọn nhà cung cấp.').show();
        isValid = false;
    }
    if (!$('#order_date').val()) {
        $('#order_date').addClass('is-invalid').closest('.col-sm-7,.col-md-6').find('.invalid-feedback').text(LANG['order_date_required'] || 'Vui lòng chọn ngày đặt hàng.').show();
        isValid = false;
    }
    if (!$('#order_number').val()) {
        $('#order_number').addClass('is-invalid').closest('.input-group').find('.invalid-feedback').text(LANG['order_number_required'] || 'Vui lòng nhập số đơn hàng.').show();
        isValid = false;
    }
    const vatRateValue = parseFloat($('#summary-vat-rate').val());
    if (isNaN(vatRateValue) || vatRateValue < 0 || vatRateValue > 100) {
        $('#summary-vat-rate').addClass('is-invalid').closest('.input-group').find('.invalid-feedback').text(LANG['invalid_vat_rate'] || 'Thuế VAT không hợp lệ (0-100).').show();
        isValid = false;
    }

    // Client-side Validation - Kiểm tra chi tiết sản phẩm (items)
    let hasValidItems = false;
    let hasAnyItemRow = itemTableBody.find('tr').length > 0;

    if (!hasAnyItemRow) {
        isValid = false;
        formErrorMessageDiv.text(LANG['order_must_have_items'] || 'Đơn hàng phải có ít nhất một dòng sản phẩm.').removeClass('d-d-none').removeClass('d-none');
    } else {
        itemTableBody.find('tr').each(function(index) {
            const row = $(this),
                  productNameInput = row.find('.product-autocomplete'),
                  quantityInput = row.find('.quantity'),
                  unitPriceInput = row.find('.unit-price');

            let rowIsValid = true;
            let itemRowHasMeaningfulData = productNameInput.val() || parseFloat(quantityInput.val()) > 0 || parseFloat(unitPriceInput.val()) > 0;

            if (itemRowHasMeaningfulData || itemTableBody.find('tr').length === 1) {
                if (!productNameInput.val()) {
                    productNameInput.addClass('is-invalid').closest('td').find('.invalid-feedback').text(LANG['product_name_required'] || 'Bắt buộc.').show();
                    isValid = false;
                    rowIsValid = false;
                }
                const quantityValue = parseFloat(quantityInput.val());
                if (isNaN(quantityValue) || quantityValue <= 0) {
                    quantityInput.addClass('is-invalid').closest('td').find('.invalid-feedback').text(LANG['invalid_quantity'] || 'Số lượng không hợp lệ (> 0).').show();
                    isValid = false;
                    rowIsValid = false;
                }
                const unitPriceValue = parseFloat(unitPriceInput.val());
                if (isNaN(unitPriceValue) || unitPriceValue < 0) {
                    unitPriceInput.addClass('is-invalid').closest('td').find('.invalid-feedback').text(LANG['invalid_unit_price'] || 'Đơn giá không hợp lệ (>= 0).').show();
                    isValid = false;
                    rowIsValid = false;
                }

                if (rowIsValid && itemRowHasMeaningfulData) {
                    hasValidItems = true;
                }
            }
        });

        if (hasAnyItemRow && !hasValidItems) {
            isValid = false;
            if (formErrorMessageDiv.hasClass('d-none')) {
                formErrorMessageDiv.text(LANG['order_must_have_valid_items'] || 'Đơn hàng phải có ít nhất một dòng sản phẩm hợp lệ (đủ tên, số lượng > 0, giá >= 0).').removeClass('d-d-none').removeClass('d-none');
            }
        }
    }

    if (!isValid) {
        console.warn("Form validation failed. Scrolling to first error.");
        const firstInvalidElement = orderForm.find('.is-invalid').first();
        if (formErrorMessageDiv.is(':visible')) {
            $('html,body').animate({ scrollTop: formErrorMessageDiv.offset().top - 0 }, 300);
        } else if (firstInvalidElement.length) {
            $('html,body').animate({ scrollTop: firstInvalidElement.offset().top - 0 }, 300);
        }
        return;
    }



    // Tạo itemsArray, đảm bảo không có trùng lặp
    let itemsArray = [];
    let processedRows = new Set(); // Theo dõi các dòng đã xử lý để tránh lặp
    itemTableBody.find('tr').each(function() {
        const row = $(this);
        const rowIndex = row.data('index') || row.index(); // Sử dụng data-index hoặc index để phân biệt
        if (processedRows.has(rowIndex)) return; // Bỏ qua nếu đã xử lý

        const quantityValue = parseFloat(row.find('.quantity').val());
        const unitPriceValue = parseFloat(row.find('.unit-price').val());
        const productNameValue = row.find('.product-autocomplete').val();

        if (productNameValue && !isNaN(quantityValue) && quantityValue > 0 && !isNaN(unitPriceValue) && unitPriceValue >= 0) {
            const item = {
                detail_id: row.find('input[name$="[detail_id]"]').val() || null,
                product_id: row.find('.product-id').val() || null,
                product_name_snapshot: productNameValue,
                category_snapshot: row.find('input[name$="[category_snapshot]"]').val(),
                unit_snapshot: row.find('input[name$="[unit_snapshot]"]').val(),
                quantity: quantityValue,
                unit_price: unitPriceValue
            };
            itemsArray.push(item);
            processedRows.add(rowIndex);
        }
    });

    console.log("Items Array trước khi gửi:", itemsArray);

    const orderData = {
        order_id: $('#order_id').val() || null,
        partner_id: $('#partner_id').val(),
        order_date: $('#order_date').val(),
        order_number: $('#order_number').val(),
        quote_id: $('#order_quote_id_form').val() || null,
        currency: currencySelect.val(),
        notes: $('#notes').val(),
        vat_rate: $('#summary-vat-rate').val(),
        items: itemsArray, // Sử dụng itemsArray đã tạo
        status: $('#order_status_select').val() || 'draft',
    };

    
    console.log("Submitting Order Data:", orderData);

    saveButton.prop('disabled', true);
    saveButtonText.hide();
    saveButtonSpinner.removeClass('d-none');
    const action = orderData.order_id ? 'edit' : 'add';

    $.ajax({
        url: AJAX_URL.sales_order + '?action=' + action,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(orderData),
        dataType: 'json',
        success: function(response) {
            console.log(">>> Order Save AJAX success response:", response);
            if (response.success) {
                let savedOrderId = null;
                if (response.order_id) {
                    savedOrderId = response.order_id;
                } else if (response.data && response.data.id) {
                    savedOrderId = response.data.id;
                } else if (orderData.order_id) {
                    savedOrderId = orderData.order_id;
                }

                showUserMessage(response.message || LANG['save_success'] || 'Đã lưu đơn hàng thành công!', 'success');
                orderFormCard.slideUp(function() {
                    resetOrderForm();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
                orderListTitle.show();
                if (salesOrderDataTable) {
                    salesOrderDataTable.draw(false);
                }

                if (savedOrderId) {
                    console.log(`Order saved/updated. Order ID: ${savedOrderId}. Now calling export_pdf.php to generate and get PDF path.`);
                    const isSignatureVisibleOnForm = $('#buyer-signature').is(':visible');
                    $.ajax({
                        url: `process/export_pdf.php?id=${savedOrderId}&show_signature=${isSignatureVisibleOnForm}&type=order`,
                        type: 'GET',
                        dataType: 'json',
                        success: function(exportResponse) {
                            console.log("Response from export_pdf.php:", exportResponse);
                            if (exportResponse && exportResponse.success && exportResponse.pdf_web_path) {
                                let actualPdfUrlToOpen = exportResponse.pdf_web_path;
                                console.log(`PDF path received: ${actualPdfUrlToOpen}. Opening this path.`);
                                try {
                                    const newTab = window.open(actualPdfUrlToOpen, '_blank');
                                    if (newTab) {
                                        newTab.focus();
                                        console.log("New tab for static PDF initiated.");
                                    } else {
                                        console.warn("window.open returned null for static PDF. Popup might be blocked.");
                                        showUserMessage("Trình duyệt có thể đã chặn mở tab PDF. Vui lòng kiểm tra cài đặt popup.", "warning");
                                    }
                                } catch (e) {
                                    console.error("Error attempting to open static PDF tab:", e);
                                    showUserMessage("Có lỗi khi cố gắng mở tab PDF.", "error");
                                }
                            } else {
                                console.warn("export_pdf.php did not return a valid PDF path or was not successful. Response:", exportResponse);
                                const exportMessage = (exportResponse && exportResponse.message) ? exportResponse.message : 'Không thể lấy đường dẫn PDF sau khi tạo.';
                                showUserMessage('Lỗi xuất PDF: ' + escapeHtml(exportMessage), 'error');
                            }
                        },
                        error: function(xhrExport) {
                            console.error("Error calling export_pdf.php:", xhrExport.status, xhrExport.responseText);
                            showUserMessage("Có lỗi khi yêu cầu tạo file PDF từ server. " + (xhrExport.responseText ? `Chi tiết: ${xhrExport.responseText.substring(0,100)}...` : ''), "error");
                        }
                    });
                } else {
                    console.warn("Could not trigger PDF generation: savedOrderId is missing or invalid after save. Response:", response);
                }
            }
        },
        error: function(xhr) {
            console.error(">>> AJAX Error saving order:", xhr.status, xhr.responseText);
            let errorMessage = LANG['server_error_saving_order'] || 'Lỗi máy chủ khi lưu đơn hàng.';
            formErrorMessageDiv.removeClass('d-d-none').removeClass('d-none');

            try {
                const res = JSON.parse(xhr.responseText);
                if (res && res.message) {
                    errorMessage = res.message;
                    if (res.suggestion && $('#order_number').length) {
                        $('#order_number').val(res.suggestion).removeClass('is-invalid').closest('.input-group').find('.invalid-feedback').text('');
                        errorMessage += " " + (LANG['suggestion_applied'] || "Đã áp dụng gợi ý số đơn hàng.");
                    } else if (res.errors) {
                        handleFormValidationErrors(res.errors);
                        errorMessage = LANG['validation_failed'] || 'Validation failed.';
                    } else {
                        formErrorMessageDiv.text(errorMessage).removeClass('d-d-none').removeClass('d-none');
                    }
                } else {
                    formErrorMessageDiv.text(errorMessage + ` (Status: ${xhr.status})`).removeClass('d-d-none').removeClass('d-none');
                }
            } catch(e) {
                console.error("Error parsing AJAX error responseText:", e, "Response Text:", xhr.responseText);
                formErrorMessageDiv.text(errorMessage + ` (Status: ${xhr.status}). Chi tiết phản hồi: ${xhr.responseText.substring(0, 200)}...`).removeClass('d-d-none').removeClass('d-none');
            }

            const firstInvalidElement = orderForm.find('.is-invalid').first();
            if (formErrorMessageDiv.is(':visible')) {
                $('html,body').animate({ scrollTop: formErrorMessageDiv.offset().top - 0 }, 300);
            } else if (firstInvalidElement.length) {
                $('html,body').animate({ scrollTop: firstInvalidElement.offset().top - 0 }, 300);
            }
        },
        complete: function() {
            saveButton.prop('disabled', false);
            saveButtonText.show();
            saveButtonSpinner.addClass('d-none');
            console.log("Order Save AJAX request complete.");
        }
    });
});


        // <<< LISTENER CHO INPUT CHỌN FILE ĐÍNH KÈM THÊM (TRONG MODAL EMAIL) >>>
        // Gắn listener cho sự kiện 'change' trên input file #emailExtraAttachments
        $('#emailExtraAttachments').on('change', function() {
            console.log(">>> Listener #emailExtraAttachments change event triggered!"); // LOG TEST
            // Lấy danh sách các file vừa được chọn từ input element
            const files = this.files;

            // Reset mảng lưu trữ file đã chọn và xóa hiển thị cũ trong modal
            selectedExtraAttachments = []; // Reset mảng
            $('#emailExtraAttachmentsList').empty(); // Xóa tất cả các item file đang hiển thị


            if (files.length > 0) {
                 $('#emailExtraAttachmentsList').html('<strong>Các file đính kèm thêm:</strong>'); // Thêm tiêu đề danh sách file
                // Duyệt qua từng file vừa chọn
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    selectedExtraAttachments.push(file); // Thêm file vào mảng lưu trữ

                    // Tạo HTML để hiển thị tên file và nút xóa
                    // Sử dụng data-index để biết file nào cần xóa sau này
                    const fileItemHtml = `
                        <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                            <span class="file-name small me-2">${escapeHtml(file.name)}</span>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-attachment" data-index="${selectedExtraAttachments.length - 1}">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>`;
                    $('#emailExtraAttachmentsList').append(fileItemHtml); // Thêm item file vào danh sách hiển thị trong modal
                }
            } else {
                // Nếu không có file nào được chọn, hiển thị thông báo
                $('#emailExtraAttachmentsList').html('<span class="text-muted">Chưa có file đính kèm thêm nào được chọn.</span>');
            }
             console.log("Selected extra attachments:", selectedExtraAttachments); // LOG TEST mảng lưu trữ sau khi cập nhật
        });

        // <<< LISTENER CHO NÚT XÓA FILE ĐÍNH KÈM THÊM (TRONG MODAL EMAIL) - Delegated Event >>>
        // Gắn listener cho sự kiện click trên các nút có class .btn-remove-attachment bên trong #emailExtraAttachmentsList
        $('#emailExtraAttachmentsList').on('click', '.btn-remove-attachment', function() {
            console.log(">>> Listener .btn-remove-attachment clicked!"); // LOG TEST
            const fileIndex = $(this).data('index'); // Lấy index của file cần xóa từ data attribute của nút

            // Kiểm tra index có hợp lệ không
            if (fileIndex > -1 && fileIndex < selectedExtraAttachments.length) {
                // Xóa file khỏi mảng lưu trữ bằng phương thức splice
                selectedExtraAttachments.splice(fileIndex, 1);
                 console.log(`File at index ${fileIndex} removed from array.`); // LOG TEST

                // Cập nhật lại hiển thị danh sách file trong modal sau khi xóa
                $('#emailExtraAttachmentsList').empty(); // Xóa hiển thị cũ
                if (selectedExtraAttachments.length > 0) {
                     $('#emailExtraAttachmentsList').html('<strong>Các file đính kèm thêm:</strong>'); // Thêm tiêu đề
                    // Duyệt qua mảng file đã chọn sau khi xóa để hiển thị lại
                    selectedExtraAttachments.forEach((file, index) => {
                         // Cần cập nhật lại data-index cho các nút xóa mới để chúng trỏ đúng index
                         const fileItemHtml = `
                            <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                                <span class="file-name small me-2">${escapeHtml(file.name)}</span>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-attachment" data-index="${index}">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>`;
                        $('#emailExtraAttachmentsList').append(fileItemHtml); // Thêm item file vào danh sách hiển thị
                    });
                } else {
                    // Nếu mảng rỗng sau khi xóa, hiển thị thông báo
                    $('#emailExtraAttachmentsList').html('<span class="text-muted">Chưa có file đính kèm thêm nào được chọn.</span>');
                }
                 console.log("Updated selected extra attachments:", selectedExtraAttachments); // LOG TEST mảng lưu trữ sau khi xóa
            } else {
                console.warn(`Attempted to remove attachment with invalid index: ${fileIndex}`); // LOG WARNING
            }
        });

         // <<< LISTENER CHO SUBMIT FORM GỬI EMAIL (TRONG MODAL EMAIL) >>>
        // Listener cho form submit bên trong Modal Email
        $('#sendEmailForm').on('submit', function (e) {
            console.log(">>> Listener #sendEmailForm submit event triggered!");
            e.preventDefault();
            const $form = $(this);
            const $btn = $form.find('#btnSubmitSendEmail');
            const $spinner = $btn.find('.spinner-border'); // Spinner loading
            const $btnText = $btn.contents().filter(function() {
               return this.nodeType === 3; // Lấy text node (để sửa text hiển thị)
            });
            const originalBtnText = $btnText.text(); // Lưu text gốc của nút

            let emailBodyContent = '';
                // Kiểm tra xem window.editors và instance 'emailBody' có tồn tại và có hàm getData không
                if (window.editors && window.editors.emailBody && typeof window.editors.emailBody.getData === 'function') {
                    emailBodyContent = window.editors.emailBody.getData(); // Lấy nội dung HTML từ CKEditor
                    console.log("Fetched content from CKEditor for #emailBody.");
                } else if ($('#emailBody').length) {
                    console.warn("CKEditor for #emailBody not initialized or accessible. Falling back to textarea value.");
                    emailBodyContent = $('#emailBody').val() || ''; // Fallback về giá trị của textarea
                } else {
                    console.warn("#emailBody element not found for fetching content.");
                }
            // --- Kết thúc lấy nội dung ---

            // Lấy dữ liệu từ form modal gửi email
            const documentId = $('#sendEmailModal').data('current-document-id');
            const logType = $('#sendEmailModal').data('current-document-type');
            const documentNumber = $('#sendEmailModal').data('current-document-number') || 'N/A'; // Lấy lại số document
            // GHI CHÚ CHỈNH SỬA: Lấy `documentId` và `logType` từ data đã lưu trên modal.

            // ... (lấy các giá trị toEmail, ccEmails, subject, bodyContent, pdfUrl như cũ) ...
            const toEmail = trim($('#emailTo').val() || '');
            const ccEmails = trim($('#emailCc').val() || '');
            const subjectValue = trim($('#emailSubject').val() || ''); // Đổi tên biến để tránh xung đột với subject trong scope khác
            const defaultPdfUrlFromInput = $('#emailPdfUrl').val(); // Lấy URL PDF mặc định từ input
            const bodyContent = trim($('#emailBody').val() || '');
            // ... (Client-side Validation cho form gửi email giữ nguyên, nhưng kiểm tra documentId và logType)
            let isValid = true;
            let validationMessages = [];
            if (!toEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(toEmail)) validationMessages.push('Email người nhận không hợp lệ.');
            // ... (validate CCs, subjectValue, bodyContent) ...
            if (!subjectValue) validationMessages.push('Vui lòng nhập Tiêu đề.');
            if (!bodyContent) validationMessages.push('Vui lòng nhập Nội dung.');

            if (!documentId || !logType) { // Kiểm tra thêm documentId và logType
                 validationMessages.push('Lỗi nội bộ: Không xác định được thông tin tài liệu hoặc loại log.');
                 isValid = false;
            }
            if (validationMessages.length > 0) {
                 validationMessages.forEach(msg => showUserMessage(msg, 'warning'));
                 console.warn("Client-side email form validation failed.");
                 return;
            }
            // GHI CHÚ CHỈNH SỬA: Validation kiểm tra thêm `documentId` và `logType`.

            const formData = new FormData();
            // CHỈNH SỬA: Gửi document_id và log_type
            formData.append('document_id', documentId);
            formData.append('log_type', logType);
            // GHI CHÚ CHỈNH SỬA: Các tham số chính được gửi đi.
            formData.append('to_email', toEmail);
            formData.append('cc_emails', ccEmails);
            formData.append('subject', subjectValue);
            formData.append('body', bodyContent);
            // formData.append('order_number', documentNumber); // Gửi thêm số document nếu backend create_email_log.php cần
                                                            // File create_email_log.php đã được sửa để nhận order_number_from_post

            const $emailAttachmentDisplay = $('#emailAttachmentDisplay');
            if ($emailAttachmentDisplay.is(':visible') && defaultPdfUrlFromInput) {
                formData.append('default_pdf_url', defaultPdfUrlFromInput);
            }

            selectedExtraAttachments.forEach((file) => {
                 formData.append('extra_attachments[]', file, file.name);
            });

            console.log(`Sending email log creation request with FormData for ${logType} ID: ${documentId}`);
            

            // Vô hiệu hóa nút gửi và hiện spinner trong khi xử lý
            $btn.prop('disabled', true);
            $btnText.text(' Đang tiếp nhận...'); // Cập nhật text nút
            $spinner.removeClass('d-none'); // Hiện spinner


            // --- ẨN MODAL NGAY LẬP TỨC SAU KHI BẤM GỬI VÀ TRƯỚC KHI GỌI AJAX ---
            // Lấy instance modal Bootstrap và ẩn đi
            const emailModalEl = document.getElementById('sendEmailModal');
            const emailModal = bootstrap.Modal.getInstance(emailModalEl);
            if (emailModal) {
                emailModal.hide();
                 console.log("#sendEmailModal hidden."); // LOG TEST
            } else {
                 console.warn("Email modal instance not found to hide."); // LOG WARNING
            }


            // *** Gửi yêu cầu AJAX (POST) đến create_email_log.php ***
            // Endpoint này chịu trách nhiệm ghi log vào DB và kích hoạt worker
            $.ajax({
                url: PROJECT_BASE_URL + 'includes/send_email_custom.php', // Hoặc 'create_email_log.php'
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    console.log(">>> send_email_custom.php success response:", response);
                    if (response && response.success && response.log_id && response.log_type) {
                        // CHỈNH SỬA: Sử dụng response.log_type (do backend trả về) khi gọi polling
                        const createdLogId = response.log_id;
                        const createdLogType = response.log_type; // Lấy log_type từ response
                        // GHI CHÚ CHỈNH SỬA: Lấy `createdLogType` từ phản hồi của backend.

                        const initialMessage = response.message || `Yêu cầu gửi email ${createdLogType === 'quote' ? (window.LANG?.sales_quote_short || 'Báo giá') : (window.LANG?.sales_order_short || 'Đơn hàng')} đã được tiếp nhận.`;
                        showUserMessage(initialMessage, 'info');
                        console.log("Initial message displayed. Created Log ID:", createdLogId, "Type:", createdLogType);

                        selectedExtraAttachments = [];
                        $('#emailExtraAttachmentsList').html('<span class="text-muted">Chưa có file đính kèm thêm nào được chọn.</span>');
                        $('#emailExtraAttachments').val('');

                        if (typeof startEmailStatusPolling === 'function') {
                             console.log("Calling startEmailStatusPolling with Log ID:", createdLogId, "and Log Type:", createdLogType);
                             // CHỈNH SỬA: Truyền createdLogType cho startEmailStatusPolling
                             setTimeout(() => {
                                 startEmailStatusPolling(createdLogId, createdLogType);
                             }, 1500); // Trễ một chút
                             // GHI CHÚ CHỈNH SỬA: `createdLogType` được truyền cho hàm polling.
                         } else {
                             console.error("startEmailStatusPolling function is not defined.");
                             showUserMessage('Lỗi nội bộ: Chức năng kiểm tra trạng thái email không khả dụng.', 'error');
                         }

                        if (salesOrderDataTable) { // salesOrderDataTable là biến của trang sales_orders
                           console.log("Refreshing DataTable after email queue request.");
                           salesOrderDataTable.draw(false);
                        }
                    } else {
                        console.warn(">>> send_email_custom.php success: response.success is false or missing data.", response);
                        const errorMessage = response.message || 'Lỗi khi tạo yêu cầu gửi email.';
                        showUserMessage('Lỗi: ' + errorMessage, 'error');
                    }
                },

                error: function (xhr) {
                    // Xử lý lỗi AJAX (lỗi kết nối, lỗi HTTP status 4xx, 5xx từ backend)
                    console.error(">>> AJAX error sending data to create_email_log.php:", xhr.status, xhr.responseText); // LOG ERROR AJAX

                    let errorMessage = 'Lỗi máy chủ khi xử lý yêu cầu gửi email.'; // Thông báo lỗi mặc định
                     // Cố gắng lấy thông báo lỗi chi tiết từ phản hồi lỗi AJAX (responseText) nếu có
                     try {
                         const res = JSON.parse(xhr.responseText);
                         if (res && res.message) {
                              errorMessage = 'Lỗi: ' + res.message; // Sử dụng thông báo lỗi từ server nếu là JSON có message
                         } else {
                             errorMessage += ` (Status: ${xhr.status})`; // Thêm status nếu không có message
                         }
                     } catch(e) {
                          // Xảy ra lỗi khi cố gắng parse responseText thành JSON (có thể backend trả về HTML lỗi PHP, warning...)
                          console.error("Error parsing AJAX error responseText from create_email_log.php:", e, "Response Text:", xhr.responseText); // LOG ERROR chi tiết
                          errorMessage += ` (Status: ${xhr.status}) - Phản hồi không phải JSON.`; // Bổ sung thông tin nếu response không phải JSON
                          // Có thể hiển thị 1 phần responseText nếu cần debug thêm
                          // errorMessage += `\nChi tiết phản hồi: ${xhr.responseText.substring(0, 100)}...`;
                     }
                   showUserMessage(errorMessage, 'error'); // Hiển thị thông báo lỗi cho người dùng
                   console.error("Error response text:", xhr.responseText); // LOG ERROR chi tiết response text (quan trọng để debug backend)

               },

               complete: function () {
                   // Luôn chạy sau khi request hoàn thành (dù thành công hay thất bại)
                   // Bật lại nút gửi và khôi phục text/ẩn spinner
                   $btn.prop('disabled', false);
                   $btnText.text(originalBtnText); // Khôi phục text gốc của nút
                   $spinner.addClass('d-none'); // Ẩn spinner
                    console.log("create_email_log.php AJAX request complete."); // LOG TEST
               }
           });
       });


        // --- Listener cho Child Rows (Delegated Event trên itemTableBody) ---
        // Gắn listener cho sự kiện click trên các element có class .details-control bên trong tbody của bảng DataTables
        orderTableElement.find('tbody').on('click', 'td.details-control', function() {
             console.log(">>> Listener td.details-control clicked!"); // LOG TEST
            event.stopPropagation(); // Ngăn sự kiện click lan truyền lên các element cha (như <tr>, <tbody>)

            const tr = $(this).closest('tr'); // Lấy dòng (<tr>) chứa element đã bấm
            const row = salesOrderDataTable.row(tr); // Lấy đối tượng row của DataTables tương ứng
            const icon = $(this).find('i'); // Lấy icon (+/-) bên trong element .details-control

            // Kiểm tra nếu hàng con (child row) đang hiển thị cho dòng này
            if (row.child.isShown()) {
                // Nếu đang mở, đóng child row lại
                row.child.hide(); // Ẩn child row
                tr.removeClass('shown'); // Xóa class 'shown' trên dòng chính
                icon.removeClass('bi-dash-square text-danger').addClass('bi-plus-square text-success'); // Đổi icon thành icon mở
                 console.log("Child row closed."); // LOG TEST
            } else {
                // Nếu đang đóng, mở child row
                const orderData = row.data(); // Lấy dữ liệu của dòng (được cung cấp bởi DataTables Server-Side)
                if (!orderData || !orderData.id) {
                    console.error("Missing order data for child row. Cannot load details."); // LOG ERROR
                    return;
                }
                const orderId = orderData.id; // Lấy ID đơn hàng từ dữ liệu dòng
                const currency = orderData.currency; // Lấy tiền tệ từ dữ liệu dòng (để format giá trong child row)

                // Hiển thị nội dung loading trong child row khi đang tải chi tiết
                row.child('<div class="text-center p-2"><div class="spinner-border spinner-border-sm" role="status"></div> Đang tải chi tiết...</div>').show();
                tr.addClass('shown'); // Thêm class 'shown' vào dòng chính
                icon.removeClass('bi-plus-square text-success').addClass('bi-dash-square text-danger'); // Đổi icon thành icon đóng

                // Gọi AJAX để lấy chi tiết đơn hàng cho child row (items)
                $.ajax({
                    url: AJAX_URL.sales_order, // Endpoint xử lý đơn hàng
                    type: 'GET', // Method GET cho yêu cầu lấy dữ liệu
                    data: { action: 'get_details', id: orderId }, // Action yêu cầu lấy chi tiết đơn hàng theo ID
                    dataType: 'json', // Mong đợi phản hồi là JSON
                    success: function(response) {
                        console.log(">>> Child row details AJAX success response:", response); // LOG TEST phản hồi
                        if (response.success && response.data?.details) {
                            // Nếu thành công và có dữ liệu chi tiết, format và hiển thị trong child row
                            row.child(formatChildRowDetails(response.data.details, currency)).show();
                             console.log("Child row details loaded and shown."); // LOG TEST
                        } else {
                             // Báo lỗi nếu backend không thành công hoặc không có dữ liệu chi tiết
                             console.warn(">>> Child row details AJAX success: response.success is false or no details.", response); // LOG WARNING
                            row.child('<div class="p-2 text-danger">Lỗi khi tải chi tiết đơn hàng.</div>').show(); // Hiển thị thông báo lỗi trong child row
                        }
                    },
                    error: function(xhr) {
                         // Xử lý lỗi AJAX
                         console.error(">>> Child row details AJAX Error:", xhr.status, xhr.responseText); // LOG ERROR
                        row.child('<div class="p-2 text-danger">Lỗi máy chủ khi tải chi tiết đơn hàng.</div>').show(); // Hiển thị thông báo lỗi máy chủ trong child row
                    }
                });

            }
        });


        // <<< LISTENER CHO NÚT GỬI EMAIL (.btn-send-email) - Delegated Event trên tbody của DataTables >>>
        // Nút này mở modal gửi email
         orderTableElement.find('tbody').on('click', '.btn-send-email', function () {
            console.log(">>> Listener .btn-send-email clicked!");
            const $button = $(this);
            const documentId = $button.data('id'); // Lấy ID đơn hàng (hoặc báo giá)
            const documentNumber = String($button.data('order-number') || ''); // Lấy số đơn hàng (hoặc báo giá)
            const pdfUrl = $button.data('pdf-url');

            console.log(`Data attributes: ID=${documentId}, DocumentNumber=${documentNumber}, PdfUrl=${pdfUrl}, ContextType=${APP_CONTEXT.type}`);

            if (!documentId || !documentNumber) { // pdfUrl có thể rỗng nếu không có PDF mặc định
                alert('Lỗi: Thiếu thông tin cần thiết từ nút gửi email.');
                console.error('Send email click: Missing required data attributes from button.');
                return;
            }

            // CHỈNH SỬA: Lưu documentId và APP_CONTEXT.type vào modal
            $('#sendEmailModal').data('current-document-id', documentId);
            $('#sendEmailModal').data('current-document-type', APP_CONTEXT.type); // Sử dụng APP_CONTEXT.type
            $('#sendEmailModal').data('current-document-number', documentNumber); // Lưu số để dùng lại
            // GHI CHÚ CHỈNH SỬA: Lưu cả `documentId` và `APP_CONTEXT.type` vào data của modal.

            const $modalTitleSpan = $('#sendEmailModal').find('#modal-send-email-order-number-display');
            if ($modalTitleSpan.length) {
                // CHỈNH SỬA: Sử dụng APP_CONTEXT.documentName
                $modalTitleSpan.text(`${APP_CONTEXT.documentName} ${documentNumber}`);
                // GHI CHÚ CHỈNH SỬA: Tiêu đề modal sử dụng `APP_CONTEXT.documentName`.
            }

            console.log(`Requesting default email info for ${APP_CONTEXT.type} ID: ${documentId}...`);
            // CHỈNH SỬA: Gửi thêm `type: APP_CONTEXT.type` cho get_partner_email.php
            $.post('includes/get_partner_email.php', { id: documentId, type: APP_CONTEXT.type }, function (response) {
            // GHI CHÚ CHỈNH SỬA: `type: APP_CONTEXT.type` được gửi đi.
                console.log(">>> get_partner_email.php success response:", response);

                if (response && response.success) {
                    const emailModalEl = document.getElementById('sendEmailModal');
                    const emailToInput = document.getElementById('emailTo');
                    const emailCcInput = document.getElementById('emailCc');
                    const emailSubjectInput = document.getElementById('emailSubject');
                    const emailBodyTextarea = document.getElementById('emailBody');
                    const emailDocumentIdInput = document.getElementById('emailDocumentId'); // Đổi tên input ẩn nếu cần, hoặc dùng data()
                    const emailLogTypeInput = document.getElementById('emailLogType'); // Input ẩn mới cho log_type
                    const emailPdfUrlInput = document.getElementById('emailPdfUrl');
                    const emailAttachmentLink = document.getElementById('emailAttachmentLink');
                    const emailAttachmentDisplay = document.getElementById('emailAttachmentDisplay');

                    if (!emailModalEl || !emailToInput /* ... các kiểm tra khác ... */) {
                         alert('Lỗi: Không tìm thấy đầy đủ các thành phần của modal gửi email.');
                         console.error("Missing required modal elements for send email.");
                         return;
                    }

                    emailToInput.value = response.email || '';
                    emailCcInput.value = response.cc_emails || '';
                    // CHỈNH SỬA: Sử dụng APP_CONTEXT.documentName và documentNumber
                    emailSubjectInput.value = `${APP_CONTEXT.documentName} STV - ${documentNumber}`;
                    emailBodyTextarea.value = `Kính gửi Quý công ty,\n\nCông ty STV xin gửi đến Quý công ty ${APP_CONTEXT.documentName} số: ${documentNumber}.\nVui lòng xem chi tiết trong file PDF đính kèm.\n\nThanks and best regard!`;
                    // GHI CHÚ CHỈNH SỬA: Tiêu đề và nội dung email mặc định sử dụng `APP_CONTEXT.documentName` và `documentNumber`.

                    // Không cần input ẩn emailDocumentIdInput và emailLogTypeInput nếu lấy từ data() của modal khi submit
                    // emailDocumentIdInput.value = documentId;
                    // emailLogTypeInput.value = APP_CONTEXT.type;
                    emailPdfUrlInput.value = pdfUrl || ''; // Cho phép pdfUrl rỗng

                    if (pdfUrl) {
                        const filename = pdfUrl.substring(pdfUrl.lastIndexOf('/') + 1);
                        emailAttachmentLink.textContent = filename;
                        emailAttachmentLink.href = pdfUrl;
                        $(emailAttachmentDisplay).removeClass('d-none').addClass('d-flex');
                    } else {
                        $(emailAttachmentDisplay).addClass('d-none');
                        emailAttachmentLink.textContent = '';
                        emailAttachmentLink.href = '#';
                    }

                    selectedExtraAttachments = [];
                    $('#emailExtraAttachments').val('');
                    $('#emailExtraAttachmentsList').html('<span class="text-muted">Chưa có file đính kèm thêm nào được chọn.</span>');

                    // Cập nhật CKEditor nếu nó đã được khởi tạo cho #emailBody
                    // Biến 'emailBodyTextarea' không còn cần thiết nữa vì chúng ta sẽ lấy nội dung trực tiếp từ response
                    // và đặt vào CKEditor.
                    if (window.editors && window.editors.emailBody && typeof window.editors.emailBody.setData === 'function') {
                        // Giả định `response.email_body` chứa nội dung HTML bạn muốn đặt vào editor.
                        window.editors.emailBody.setData(response.email_body || ''); // Đặt nội dung email
                        console.log("CKEditor content for #emailBody updated with new data from response.email_body.");
                    } else if ($('#emailBody').length) {
                        // Fallback nếu CKEditor chưa init hoặc instance không sẵn sàng,
                        // hãy đảm bảo textarea HTML được cập nhật.
                        $('#emailBody').val(response.email_body || '');
                        console.warn("CKEditor for #emailBody not initialized or accessible. Updating textarea directly.");
                    } else {
                        console.warn("#emailBody element not found for updating.");
                    }

                    const emailModal = bootstrap.Modal.getOrCreateInstance(emailModalEl);
                    emailModal.show();
                } else {
                     alert('Lỗi khi lấy thông tin email đối tác: ' + (response.message || 'Không rõ nguyên nhân.'));
                }
            }, 'json').fail(function (xhr) {
                console.error(">>> AJAX error fetching partner email:", xhr.status, xhr.responseText);
                alert("Lỗi máy chủ khi lấy thông tin email đối tác. Chi tiết: " + xhr.responseText.substring(0, 200));
            });
        });


        // <<< LISTENER CHO NÚT XEM LOG ĐƠN HÀNG (.btn-view-order-logs) - Delegated Event trên tbody của DataTables >>>
         // Nút này mở modal xem lịch sử gửi email
         orderTableElement.find('tbody').on('click', '.btn-view-order-logs', function() {
            console.log(">>> Listener .btn-view-order-logs clicked!");
            const button = $(this);
            const documentId = button.data('order-id'); // Giữ nguyên data attribute này cho sales_orders
            const documentNumber = button.data('order-number');

            console.log(`Clicked view logs for ${APP_CONTEXT.type} ID: ${documentId}, Number: ${documentNumber}`);

            if (!documentId) {
                alert('Lỗi: Không xác định được ID tài liệu.');
                console.error('View logs click: Missing document ID from data attribute.');
                return;
            }

            // Lấy các phần tử của modal xem log
            const modalElement = document.getElementById('viewOrderEmailLogsModal'); // Modal chính xem log
            console.log("modalElement found:", modalElement); // Dòng log bạn đã thêm

            // --- THÊM KHỐI KIỂM TRA NÀY ---
            if (!modalElement) {
                console.error("ERROR: Modal element #viewOrderEmailLogsModal not found in the DOM. Cannot open log modal."); // Ghi log lỗi chi tiết
                showUserMessage('Lỗi nội bộ: Không tìm thấy cửa sổ xem lịch sử email trên trang. Vui lòng thử tải lại trang hoặc liên hệ hỗ trợ kỹ thuật.', 'error'); // Thông báo lỗi thân thiện cho người dùng
                return; // Dừng thực thi hàm nếu không tìm thấy modal
            }
            // --- KẾT THÚC KHỐI THÊM MỚI ---


            const modalTitleSpan = document.getElementById('modal-order-log-number');
            const modalContentDiv = document.getElementById('order-email-logs-content');
            
            // Kiểm tra các phần tử cần thiết bên trong modal
            if (!modalTitleSpan || !modalContentDiv) {
                 console.error('ERROR: Missing required inner modal elements for #viewOrderEmailLogsModal (#modal-order-log-number, #order-email-logs-content). Check HTML structure.'); // Log lỗi chi tiết
                 showUserMessage('Lỗi nội bộ: Cấu trúc cửa sổ xem lịch sử email không đầy đủ. Vui lòng liên hệ hỗ trợ kỹ thuật.', 'error'); // Thông báo lỗi thân thiện
                 return; // Dừng nếu các phần tử con không tồn tại
            }


            // Lấy hoặc tạo instance modal Bootstrap (Chỉ chạy khi modalElement tồn tại)
            const orderLogModal = bootstrap.Modal.getOrCreateInstance(modalElement);
            $(modalElement).data('current-document-id', documentId); // Lưu ID để polling có thể làm mới đúng modal
             $(modalElement).data('current-document-type', APP_CONTEXT.type); // Lưu cả type

            // CHỈNH SỬA: Sử dụng APP_CONTEXT.documentName
            modalTitleSpan.textContent = `${APP_CONTEXT.documentName} ${documentNumber || `ID ${documentId}`}`;
            // GHI CHÚ CHỈNH SỬA: Tiêu đề modal log sử dụng `APP_CONTEXT.documentName`.
            $(modalContentDiv).html('<div class="text-center p-3"><div class="spinner-border spinner-border-sm" role="status"></div> Đang tải lịch sử...</div>');
            orderLogModal.show();

            // CHỈNH SỬA: Gọi AJAX đến ajax_email_logs.php với log_type từ APP_CONTEXT
            $.ajax({
                url: 'process/ajax_email_logs.php',
                type: 'GET',
                data: {
                    action: 'get_for_document',
                    document_id: documentId,
                    log_type: APP_CONTEXT.type // Sử dụng APP_CONTEXT.type
                },
                // GHI CHÚ CHỈNH SỬA: `log_type: APP_CONTEXT.type` được gửi đi.
                dataType: 'json',
                success: function(response) {
                    console.log(">>> ajax_email_logs.php success response:", response);
                    const $modalContentDivJQ = $('#order-email-logs-content'); // jQuery object
                    if (response && response.success) {
                        // CHỈNH SỬA: updateLogModalContent có thể cần biết response.log_type_processed để hiển thị đúng tên tài liệu nếu cần
                        updateLogModalContent(response.logs); // response.logs được truyền vào
                        // Nếu updateLogModalContent cần log_type, bạn có thể truyền response.log_type_processed
                    } else {
                        const errorMessage = `<p class="text-center text-danger p-3">Lỗi khi tải lịch sử: ${escapeHtml(response.message || 'Lỗi không xác định.')}</p>`;
                        $modalContentDivJQ.html(errorMessage);
                    }
                },
                 error: function(xhr) {
                    console.error(">>> AJAX error fetching document logs:", xhr.status, xhr.responseText);
                    const $modalContentDivJQ = $('#order-email-logs-content');
                    $modalContentDivJQ.html(`<p class="text-center text-danger p-3">Lỗi máy chủ khi tải lịch sử email. Chi tiết: ${xhr.responseText.substring(0,200)}</p>`);
                 }
            });
        }); // Kết thúc listener .btn-view-order-logs


        // <<< LISTENER CHO NÚT SỬA (.btn-edit-document) - Delegated Event trên tbody của DataTables >>>
         // Nút này chuyển sang chế độ sửa và load dữ liệu đơn hàng vào form
         orderTableElement.find('tbody').on('click', '.btn-edit-document', function() {
             console.log(">>> Listener .btn-edit-document clicked!"); // LOG TEST
             const button = $(this); // Lấy nút đã bấm
             const orderId = button.data('id'); // Lấy ID đơn hàng từ data attribute của nút
             console.log("Edit Order ID:", orderId); // LOG TEST

             // Kiểm tra ID đơn hàng
             if (!orderId) {
                 alert('Lỗi: Không xác định được ID đơn hàng để sửa.'); // Thông báo lỗi người dùng
                 console.error('Edit click: Missing order ID from data attribute.'); // LOG ERROR
                 return; // Dừng xử lý
             }

             // Gọi hàm load dữ liệu đơn hàng vào form để sửa
             if (typeof loadOrderForEdit === 'function') {
                 loadOrderForEdit(orderId); // Hàm này load dữ liệu và hiện form sửa
             } else {
                 console.error("loadOrderForEdit function is not defined. Cannot load order for editing."); // LOG ERROR (lỗi code)
                 alert('Lỗi: Chức năng sửa không khả dụng.'); // Thông báo lỗi người dùng
             }
         }); // Kết thúc listener .btn-edit-document


        // <<< LISTENER CHO NÚT XÓA (.btn-delete-document) - Delegated Event trên tbody của DataTables >>>
         // Nút này xóa đơn hàng
         orderTableElement.find('tbody').on('click', '.btn-delete-document', function() {
             console.log(">>> Listener .btn-delete-document clicked!"); // LOG TEST
             const button = $(this); // Lấy nút đã bấm
             const orderId = button.data('id'); // Lấy ID đơn hàng từ data attribute
             const orderNumber = button.data('number'); // Lấy số đơn hàng từ data attribute
             console.log(`Delete Order ID: ${orderId}, Number: ${orderNumber}`); // LOG TEST

             // Kiểm tra thông tin đơn hàng
             if (!orderId || !orderNumber) {
                  alert('Lỗi: Thiếu thông tin đơn hàng để xóa.'); // Thông báo lỗi người dùng
                  console.error('Delete click: Missing order ID or number from data attribute.'); // LOG ERROR
                  return; // Dừng xử lý
             }

             // Hiển thị hộp thoại xác nhận xóa trước khi gửi yêu cầu
             if (confirm(`Bạn có chắc chắn muốn xóa đơn hàng ${orderNumber} này không?`)) {
                 console.log(`User confirmed delete for Order ID: ${orderId}`); // LOG TEST
                 // Gửi yêu cầu AJAX (POST) để xóa đơn hàng
                 $.ajax({
                     url: AJAX_URL.sales_order, // Endpoint xử lý đơn hàng
                     type: 'POST', // Sử dụng POST cho hành động xóa (thường dùng POST hoặc DELETE trong RESTful)
                     data: {
                         action: 'delete', // Action yêu cầu xóa
                         id: orderId // ID đơn hàng cần xóa
                     },
                     dataType: 'json', // Mong đợi phản hồi là JSON
                     success: function(response) {
                         console.log(">>> Delete AJAX success response:", response); // LOG TEST phản hồi thành công
                         // Phản hồi mong đợi: { success: true/false, message: "..." }
                         if (response.success) {
                             showUserMessage(response.message || (LANG['delete_success'] || 'Đã xóa đơn hàng thành công.'), 'success'); // Thông báo thành công
                             // Tải lại DataTables sau khi xóa thành công để cập nhật danh sách
                             if (salesOrderDataTable) {
                                 salesOrderDataTable.draw(false); // false giữ nguyên vị trí trang hiện tại trong DataTables
                             }
                         } else {
                             // Xử lý trường hợp backend trả về success: false
                             console.error(">>> Delete AJAX error: response.success is false.", response); // LOG ERROR
                             showUserMessage(response.message || (LANG['delete_error'] || 'Lỗi khi xóa đơn hàng.'), 'error'); // Thông báo lỗi
                         }
                     },
                     error: function(xhr) {
                         // Xử lý lỗi AJAX (kết nối, status 4xx/5xx từ backend)
                         console.error(">>> AJAX error deleting order:", xhr.status, xhr.responseText); // LOG ERROR AJAX
                         let errorMessage = LANG['server_error_deleting_order'] || 'Lỗi máy chủ khi xóa đơn hàng.'; // Thông báo lỗi mặc định
                         // Cố gắng lấy thông báo lỗi chi tiết từ responseText nếu có
                         try {
                             const res = JSON.parse(xhr.responseText);
                             if (res && res.message) {
                                  errorMessage += '\nChi tiết: ' + res.message;
                             } else {
                                 errorMessage += '\nChi tiết: ' + xhr.responseText;
                             }
                         } catch(e) {
                              errorMessage += '\nChi tiết: ' + xhr.responseText; // Nếu không parse được JSON
                         }
                         showUserMessage(errorMessage, 'error'); // Hiển thị thông báo lỗi cho người dùng
                     }
                 });
             } else {
                 console.log(`User cancelled delete for Order ID: ${orderId}`); // LOG TEST (người dùng hủy xóa)
             }
         }); // Kết thúc listener .btn-delete-document


        // --- Listener cho Bộ lọc Cột & Chi tiết (Chỉ dùng 'keyup' và Debounce) ---
        // Gắn listener cho sự kiện 'keyup' trên các input filter
        $('.column-filter-input, #item-details-filter-input').on('keyup', function(event) {
            // console.log(`Filter event: Type=${event.type}, Target=${event.target.id || event.target.placeholder}`); // Có thể log quá nhiều
            clearTimeout(filterTimeout); // Xóa timeout cũ nếu người dùng tiếp tục gõ
            // Thiết lập timeout mới
            filterTimeout = setTimeout(() => {
                // Sau khi dừng gõ 500ms, gọi DataTables draw để áp dụng filter
                if (salesOrderDataTable) {
                    console.log("Filter debounce timeout executed. Calling DataTables draw()..."); // LOG TEST
                    salesOrderDataTable.draw(); // Tải lại bảng với filter mới
                }
            }, 100); // Debounce delay 100ms
        });

        // --- Listener cho nút Reset Filters ---
        $('#reset-filters-sales-orders-table').on('click', function() {
            console.log(">>> Listener #reset-filters-sales-orders-table clicked! Resetting filters."); // LOG TEST
            if (salesOrderDataTable) {
                // Xóa giá trị của tất cả các input filter
                $('.column-filter-input, #item-details-filter-input').val('');
                // Tải lại DataTables để hiển thị dữ liệu không filter
                salesOrderDataTable.draw();
            }
        });

             // === THÊM LISTENER CHO BỘ LỌC NĂM VÀ THÁNG MỚI ===
     // Lắng nghe sự kiện thay đổi giá trị trên cả hai dropdown #filterYear và #filterMonth
     $('#filterYear, #filterMonth').on('change', function() {
        console.log("Year or Month filter changed. Redrawing DataTable.");
        // Kiểm tra biến salesOrderDataTable đã được khởi tạo chưa trước khi gọi draw()
        if(salesOrderDataTable) {
            salesOrderDataTable.draw(); // Kích hoạt DataTables vẽ lại để áp dụng bộ lọc mới
        }
    });
    // --- KẾT THÚC LISTENER BỘ LỌC NĂM VÀ THÁNG ---

        // --- Listener cho nút Export PDF và Toggle Signature ---
         // Listener cho nút tải PDF
         $('#btn-download-pdf').on('click', function() {
              console.log(">>> Listener #btn-download-pdf clicked!"); // LOG TEST
             // Chỉ gọi hàm downloadPDF nếu nút không bị disabled (nút này có thể disabled khi load form)
             if (!$(this).prop('disabled')) {
                  downloadOrderPDF(); // Gọi hàm xuất PDF
             }
         });

         // Listener cho nút toggle hiển thị chữ ký
         toggleSignatureButton.on('click', function() {
             console.log(">>> Listener #toggle-signature clicked!"); // LOG TEST
             buyerSignatureImg.toggle(); // Toggle hiển thị/ẩn ảnh chữ ký
             // Cập nhật text của nút toggle dựa trên trạng thái hiển thị của ảnh
             $(this).text(buyerSignatureImg.is(':visible') ? (LANG.hide_signature ?? 'Ẩn chữ ký') : (LANG.show_signature ?? 'Hiện chữ ký'));
         });

         // Khởi tạo jQuery UI Draggable cho ảnh chữ ký (để người dùng kéo thả)
         // Kiểm tra tồn tại thư viện $.ui.draggable trước khi gọi draggable()
         if (typeof $.ui !== 'undefined' && typeof $.ui.draggable !== 'undefined') {
             try {
                 // Gắn chức năng kéo thả cho ảnh chữ ký
                 buyerSignatureImg.draggable({
                     containment:'#pdf-export-content', // Giới hạn khu vực kéo thả bên trong element có ID 'pdf-export-content'
                     scroll:false // Không cuộn trang khi kéo
                 });
                 console.log("Signature draggable initialized."); // LOG TEST
             } catch(e) {
                 console.error("Signature Draggable initialization error:", e); // LOG ERROR
             }
         } else {
             console.warn("jQuery UI Draggable not found. Signature will not be draggable."); // LOG WARNING
         }
         // <<< LISTENER CHO EXPAND/COLLAPSE ALL CHILD ROWS >>>
        $('#expand-collapse-all').on('click', function() {
            console.log(">>> Listener #expand-collapse-all clicked!"); // LOG TEST

            const button = $(this);
            const icon = button.find('i');
            // Xác định trạng thái hiện tại dựa trên icon (expand hay collapse)
            const isExpanding = icon.hasClass('bi-arrows-expand');

            if (salesOrderDataTable) {
                // Duyệt qua TẤT CẢ các dòng trong DataTables
                salesOrderDataTable.rows().nodes().each(function(rowNode, index) {
                    const row = salesOrderDataTable.row(rowNode);
                    // Tìm phần tử cell điều khiển child row (.details-control) trong dòng hiện tại
                    const controlCell = $(rowNode).find('td.details-control');

                    if (isExpanding) {
                        // Nếu nút đang ở trạng thái "Mở rộng tất cả"
                        // Kiểm tra nếu child row của dòng hiện tại chưa hiển thị
                        if (!row.child.isShown()) {
                            // Kích hoạt sự kiện click trên cell điều khiển.
                            // Listener cho td.details-control đã có sẵn và sẽ xử lý việc load data (nếu cần) và hiển thị/ẩn child row.
                            controlCell.trigger('click');
                        }
                    } else {
                        // Nếu nút đang ở trạng thái "Thu gọn tất cả"
                        // Kiểm tra nếu child row của dòng hiện tại đang hiển thị
                        if (row.child.isShown()) {
                             // Kích hoạt sự kiện click trên cell điều khiển để đóng child row
                            controlCell.trigger('click');
                        }
                    }
                });

                // Sau khi xử lý tất cả các dòng, cập nhật icon và text của nút Expand/Collapse All
                if (isExpanding) {
                    // Đổi icon thành biểu tượng thu gọn
                    icon.removeClass('bi-arrows-expand me-1').addClass('bi-arrows-collapse me-1');
                    // Cập nhật text của nút thành "Thu gọn tất cả"
                     // Sử dụng contents().last().replaceWith() để chỉ thay đổi text node cuối cùng
                    button.contents().last().replaceWith(LANG['collapse_all'] ?? 'Collapse All');
                    console.log("Switched button to Collapse All.");
                } else {
                    // Đổi icon thành biểu tượng mở rộng
                     icon.removeClass('bi-arrows-collapse me-1').addClass('bi-arrows-expand me-1');
                     // Cập nhật text của nút thành "Mở rộng tất cả"
                     button.contents().last().replaceWith(LANG['expand_all'] ?? 'Expand All');
                    console.log("Switched button to Expand All.");
                }

            } else {
                console.warn("DataTables instance not initialized. Cannot expand/collapse.");
            }
        });


    } // End setupEventListeners


    // --- Gọi hàm khởi tạo chính khi DOM đã sẵn sàng ---
    // Đảm bảo code bên trong $(document).ready được thực thi sau khi DOM được parse hoàn chỉnh
    initializePage();

    

}); // End $(document).ready (Kết thúc block code chạy khi DOM sẵn sàng)


// <<< ĐỊNH NGHĨA HÀM openOrderLogModal NGOÀI document.ready >>>
// Hàm này được dùng để mở modal log từ bên ngoài scope của $(document).ready),
// ví dụ: có thể gọi từ code polling email status để làm mới modal log nếu nó đang mở.
function openOrderLogModal(orderId, orderNumber) {
    console.log("openOrderLogModal function called for Order ID:", orderId); // LOG TEST
    const modalElement = document.getElementById('viewOrderEmailLogsModal'); // Element modal
    const modalTitleSpan = document.getElementById('modal-order-log-number'); // Element hiển thị số ĐH trong title
    // Lấy hoặc tạo instance modal Bootstrap
   const orderLogModal = bootstrap.Modal.getOrCreateInstance(modalElement);

    // Kiểm tra các phần tử modal cần thiết có tồn tại không
    if (modalElement && modalTitleSpan) {
        // Lưu Order ID vào data attribute của modal xem log (để dùng khi làm mới từ polling)
        $(modalElement).data('current-order-id', orderId);
        console.log(`Stored Order ID ${orderId} on #viewOrderEmailLogsModal.`); // LOG TEST

        // Cập nhật tiêu đề modal
        modalTitleSpan.textContent = orderNumber || `ID ${orderId}`; // Hiển thị số ĐH hoặc ID

        // Hiển thị modal trước khi tải nội dung log
        orderLogModal.show();
        console.log("#viewOrderEmailLogsModal shown."); // LOG TEST

        // Tải nội dung log vào modal
        // Cách đơn giản nhất là kích hoạt click trên nút log tương ứng nếu nó đã tồn tại trong DOM hiện tại
        // Tìm nút log trong DataTables bằng order-id
        const $logButton = $('#sales-orders-table').find(`.btn-view-order-logs[data-order-id="${orderId}"]`);

        if ($logButton.length) {
             // Nếu tìm thấy nút trong DOM hiện tại, kích hoạt sự kiện click của nó.
             // Listener .btn-view-order-logs đã được setupEventListeners gắn rồi.
             console.log(`Found log button for Order ID ${orderId}, triggering click to load logs.`); // LOG TEST
            $logButton.trigger('click'); // Kích hoạt listener .btn-view-order-logs
             // Logic bên trong listener .btn-view-order-logs sẽ xử lý hiển thị loading và fetch AJAX
        } else {
            // Nếu nút chưa render trong DOM hiện tại (ví dụ: đang ở trang DataTables khác), không thể kích hoạt click.
            // Cần fetch log thủ công và gọi updateLogModalContent trực tiếp.
            console.warn(`Log button for Order ID ${orderId} not found in current DataTable view. Manually fetching logs...`); // LOG WARNING

            const modalContentDiv = document.getElementById('order-email-logs-content'); // Lấy phần tử nội dung modal
            if (modalContentDiv) {
                 // Hiển thị loading thủ công trong nội dung modal
                 $(modalContentDiv).html('<div class="text-center p-3"><div class="spinner-border spinner-border-sm" role="status"></div> Đang tải lịch sử...</div>');

                 // Gọi trực tiếp AJAX fetch log và sau đó gọi updateLogModalContent
                 $.ajax({
                    url: 'process/ajax_email_logs.php', type: 'GET', data: { action: 'get_for_order', order_id: orderId }, dataType: 'json',
                     success: function(response) {
                         console.log(">>> Manual fetch logs success response:", response); // LOG TEST
                         if (response && response.success) {
                             // Nếu thành công, gọi hàm cập nhật nội dung modal log
                             updateLogModalContent(response.logs); // Tái sử dụng hàm update content
                         } else {
                              // Báo lỗi nếu backend không thành công
                             $('#order-email-logs-content').html(`<p class="text-center text-danger p-3">Lỗi khi tải lịch sử: ${escapeHtml(response.message || 'Lỗi không xác định.')}</p>`);
                         }
                     },
                     error: function(xhr) {
                         // Xử lý lỗi AJAX
                         console.error(">>> Manual fetch logs AJAX error:", xhr.status, xhr.responseText); // LOG ERROR
                         $('#order-email-logs-content').html(`<p class="text-center text-danger p-3">Lỗi máy chủ khi tải lịch sử email.</p>`);
                     }
                 });
            } else {
                 console.error("#order-email-logs-content not found for manual log fetch."); // LOG ERROR
            }
        }
    } else {
        console.error("Cannot open log modal: Modal element or title span not found. Check HTML IDs."); // LOG ERROR (kiểm tra lại HTML modal)
    }
}