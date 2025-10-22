<?php
// File: create_email_log.php - Đã thêm logic kích hoạt worker và log debug
$log_base_dir = __DIR__ . '/logs/'; // Đường dẫn vật lý đến thư mục logs
$endpoint_log_file = $log_base_dir . 'endpoint_hit.log';
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
error_log("[CREATE_EMAIL_LOG] Script started execution.");
$pdo = db_connect();
if (!$pdo) {
    error_log('[CREATE_EMAIL_LOG] Lỗi CSDL nghiêm trọng: Không thể kết nối.');
    http_response_code(500);
    exit(json_encode(["success" => false, "message" => "Lỗi máy chủ: Không thể kết nối cơ sở dữ liệu."]));
}
error_log('[CREATE_EMAIL_LOG] Kết nối CSDL thành công.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('[CREATE_EMAIL_LOG] Invalid request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    http_response_code(405);
    exit();
}
// Lấy dữ liệu từ FormData ($_POST và $_FILES)
$log_type = trim($_POST['log_type'] ?? 'order');
$document_id_val = $_POST['document_id'] ?? $_POST['order_id'] ?? $_POST['quote_id'] ?? 0;
$document_id = (int) $document_id_val;

$order_id_legacy = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
if ($document_id === 0 && $order_id_legacy > 0 && $log_type === 'order') {
    $document_id = $order_id_legacy;
}

$to_email = $_POST['to_email'] ?? '';
$cc_emails = $_POST['cc_emails'] ?? null;
$subject = $_POST['subject'] ?? '';
$body = $_POST['body'] ?? '';
$default_pdf_url = $_POST['default_pdf_url'] ?? null;

$order_number_from_post = $_POST['order_number'] ?? null;

error_log("[CREATE_EMAIL_LOG] Received data: log_type={$log_type}, document_id={$document_id}, to={$to_email}, subject={$subject}, order_number_post={$order_number_from_post}");


// --- Xử lý File đính kèm ---
$all_attachment_paths = [];

error_log("[CREATE_EMAIL_LOG] Processing attachments. Default PDF URL: " . ($default_pdf_url ?? 'N/A'));

// a) Thêm file PDF mặc định nếu có
if (!empty($default_pdf_url)) {
    $all_attachment_paths[] = $default_pdf_url;
    error_log("[CREATE_EMAIL_LOG] Default PDF URL added to list: " . $default_pdf_url);
}


// b) Xử lý các file đính kèm thêm từ người dùng
if (isset($_FILES['extra_attachments']) && is_array($_FILES['extra_attachments']) && isset($_FILES['extra_attachments']['tmp_name'])) {
    error_log("[CREATE_EMAIL_LOG] Processing extra attachments.");

    $upload_dir_physical = __DIR__ . '/uploads/documents/';
    $upload_dir_web = 'uploads/documents/';

    if (!is_dir($upload_dir_physical)) {
        error_log("[CREATE_EMAIL_LOG] Upload directory does not exist, attempting to create: " . $upload_dir_physical);
        if (!mkdir($upload_dir_physical, 0775, true)) {
            error_log('[CREATE_EMAIL_LOG] Không thể tạo thư mục upload vật lý khi lưu file đính kèm email: ' . $upload_dir_physical);
        } else {
            error_log('[CREATE_EMAIL_LOG] Upload directory created successfully: ' . $upload_dir_physical);
        }
    }

    if (is_dir($upload_dir_physical) && is_writable($upload_dir_physical)) {
        error_log("[CREATE_EMAIL_LOG] Upload directory exists and is writable.");
        $file_count = count($_FILES['extra_attachments']['name']);
        error_log("[CREATE_EMAIL_LOG] Number of extra files detected: " . $file_count);

        for ($key = 0; $key < $file_count; $key++) {
            if ($_FILES['extra_attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_tmp_path = $_FILES['extra_attachments']['tmp_name'][$key];
                $file_name = basename($_FILES['extra_attachments']['name'][$key]);

                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $file_base_name = pathinfo($file_name, PATHINFO_FILENAME);
                $unique_file_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $file_base_name) . '_' . uniqid() . '.' . $file_extension;

                $destination_path_physical = $upload_dir_physical . $unique_file_name;
                $destination_path_web = $upload_dir_web . $unique_file_name;

                error_log("[CREATE_EMAIL_LOG] Attempting to move uploaded file '" . $file_name . "' to: " . $destination_path_physical);

                if (move_uploaded_file($file_tmp_path, $destination_path_physical)) {
                    $all_attachment_paths[] = $destination_path_web;
                    error_log("[CREATE_EMAIL_LOG] File moved successfully. Web path: " . $destination_path_web);
                } else {
                    error_log("[CREATE_EMAIL_LOG] Failed to move uploaded file '{$file_name}'. Error code: " . $_FILES['extra_attachments']['error'][$key]);
                }
            } else if ($_FILES['extra_attachments']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                 error_log("[CREATE_EMAIL_LOG] Upload error for file '{$_FILES['extra_attachments']['name'][$key]}': Code {$_FILES['extra_attachments']['error'][$key]}");
            }
        }
    } else {
        error_log('[CREATE_EMAIL_LOG] Upload directory not writable or does not exist: ' . $upload_dir_physical);
    }
}

$attachment_paths_json = json_encode($all_attachment_paths);
error_log("[CREATE_EMAIL_LOG] Finished attachment processing. Final JSON paths: " . $attachment_paths_json);


// --- Kiểm tra dữ liệu bắt buộc ---
if ($document_id <= 0 || empty($to_email) || empty($subject) || empty($body)) {
    error_log("[CREATE_EMAIL_LOG] Missing required data for log insertion. document_id: {$document_id}");
    echo json_encode(['success' => false, 'message' => 'Missing required data (document_id, to_email, subject, body).']);
    http_response_code(400);
    exit();
}

$pdo->beginTransaction();

try {
    error_log("[CREATE_EMAIL_LOG] Attempting to insert email log into database for type: {$log_type}.");

    $target_log_table = ($log_type === 'quote') ? 'quote_email_logs' : 'email_logs';

    $sql = "
        INSERT INTO {$target_log_table} (
            order_id, to_email, cc_emails, subject, body, attachment_paths, status, created_at";
    if ($order_number_from_post !== null) {
        $sql .= ", order_number";
    }
    $sql .= "
        ) VALUES (
            :document_id, :to_email, :cc_emails, :subject, :body, :attachment_paths, 'pending', NOW()";
    if ($order_number_from_post !== null) {
        $sql .= ", :order_number";
    }
    $sql .= "
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
    $stmt->bindParam(':to_email', $to_email);
    $stmt->bindParam(':cc_emails', $cc_emails);
    $stmt->bindParam(':subject', $subject);
    $stmt->bindParam(':body', $body);
    $stmt->bindParam(':attachment_paths', $attachment_paths_json);
    if ($order_number_from_post !== null) {
        $stmt->bindParam(':order_number', $order_number_from_post);
    }

    $stmt->execute();
    $new_log_id = $pdo->lastInsertId();

    error_log("[CREATE_EMAIL_LOG] " . ucfirst($log_type) . " email queued successfully with Log ID: " . $new_log_id . " into table " . $target_log_table);

    $pdo->commit();

    error_log("[CREATE_EMAIL_LOG] Database transaction committed.");

    // --- BẮT ĐẦU: LOGIC KÍCH HOẠT WORKER ---
    // ĐÃ BỎ PHẦN GHI LOG RIÊNG CHO EXEC

    $php_executable_path = 'C:\\xampp\\php\\php.exe'; // <<< XÁC NHẬN LẠI ĐƯỜNG DẪN NÀY >>>
    $script_path = __DIR__ . '\\process\\process_email_queue.php';

    $script_path = str_replace('/', '\\', $script_path);
    $php_executable_path = str_replace('/', '\\', $php_executable_path);

    error_log("[CREATE_EMAIL_LOG] PHP Exec Path for worker: " . $php_executable_path);
    error_log("[CREATE_EMAIL_LOG] Worker Script Path: " . $script_path);

    // CHỈNH SỬA: Bỏ phần redirect output >> "..."
    $command = 'start /B "Email Processor ' . $new_log_id . '" "' . $php_executable_path . '" "' . $script_path . '" ' . $new_log_id . ' ' . $log_type;

    error_log("[CREATE_EMAIL_LOG] Executing worker command: " . $command);

    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);

    error_log("[CREATE_EMAIL_LOG] Exec command finished. Return Variable: " . $return_var);

    if ($return_var !== 0) {
        error_log("[CREATE_EMAIL_LOG] Lỗi khi kích hoạt script nền cho {$log_type} (return code: " . $return_var . "). Lệnh: " . $command);
        $response_message = "Yêu cầu gửi email " . ($log_type === 'quote' ? 'báo giá' : 'đơn hàng') . " đã được tiếp nhận, nhưng có thể có lỗi khi kích hoạt xử lý nền. Vui lòng kiểm tra log server.";
    } else {
        error_log("[CREATE_EMAIL_LOG] Script nền đã được kích hoạt thành công cho Log ID: " . $new_log_id . " (Type: {$log_type})");
        $response_message = "Yêu cầu gửi email " . ($log_type === 'quote' ? 'báo giá' : 'đơn hàng') . " đã được đưa vào hàng đợi xử lý.";
    }

    // --- KẾT THÚC LOGIC KÍCH HOẠT WORKER ---

    // Sau khi kích hoạt worker, trả về phản hồi JSON thành công cho Front-end
    echo json_encode([
        'success' => true, // Vẫn coi là thành công ở tầng ghi log và kích hoạt
        'message' => $response_message,
        'log_id' => $new_log_id,
        'log_type' => $log_type
    ]);
    http_response_code(201);


} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[CREATE_EMAIL_LOG] Database error for {$log_type}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    http_response_code(500);
} catch (Exception $e) {
     error_log("[CREATE_EMAIL_LOG] General error for {$log_type}: " . $e->getMessage());
     echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
     http_response_code(500);
}

error_log("[CREATE_EMAIL_LOG] Script finished execution for {$log_type}.");

?>