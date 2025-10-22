<?php
// === FILE: includes/send_email_custom.php ===
header('Content-Type: application/json');

// Composer autoload (Chỉ cần cho các thư viện khác nếu có, PHPMailer được dùng ở script nền)
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/init.php';

// Gọi hàm kết nối CSDL
$pdo = db_connect();

if (!$pdo) {
    error_log('Lỗi CSDL nghiêm trọng trong send_email_custom.php: Không thể kết nối.');
    http_response_code(500);
    exit(json_encode(["success" => false, "message" => "Lỗi máy chủ: Không thể kết nối cơ sở dữ liệu."]));
}

error_log('Kết nối CSDL thành công trong send_email_custom.php.'); // Giữ lại error_log tiêu chuẩn

// --- Nhận dữ liệu từ form gửi bằng AJAX (từ modal) ---
// Khi dùng FormData, dữ liệu text nằm trong $_POST, file nằm trong $_FILES

$log_type     = trim($_POST['log_type'] ?? 'order'); // 'order' hoặc 'quote', mặc định là 'order'
$document_id  = isset($_POST['document_id']) ? (int)$_POST['document_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0); // Ưu tiên document_id, fallback về order_id

$pdf_url      = $_POST['default_pdf_url']      ?? '';   // Lấy đường dẫn PDF mặc định từ input (trước đó bạn dùng 'pdf_url' ở đây, tôi đã sửa thành 'default_pdf_url' để khớp với frontend bạn gửi ở yêu cầu trước)
$to_email     = trim($_POST['to_email']     ?? '');
$cc_emails    = trim($_POST['cc_emails']    ?? '');
$subject      = trim($_POST['subject']      ?? '');
$body         = $_POST['body']         ?? '';   // gửi nội dung HTML/text tại đây
$extra_attachments = $_FILES['extra_attachments'] ?? []; // Nhận mảng file đính kèm thêm

error_log("Received data in send_email_custom.php: log_type=" . $log_type . ", document_id=" . $document_id . ", to=" . $to_email . ", subject=" . $subject); // Giữ lại error_log

// Kiểm tra dữ liệu cơ bản
if ($document_id <= 0 || empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL) || empty($subject) || empty($body)) {
    $error_fields = [];
    if(empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) $error_fields[] = 'Địa chỉ Email người nhận không hợp lệ';
    if(empty($subject)) $error_fields[] = 'Tiêu đề';
    if(empty($body)) $error_fields[] = 'Nội dung';
    if($document_id <= 0) $error_fields[] = 'ID tài liệu (Đơn hàng/Báo giá)';

    error_log("Validation failed in send_email_custom.php: " . implode(', ', $error_fields)); // Giữ lại error_log
    http_response_code(400); // Bad Request
    exit(json_encode([
        "success" => false,
        "message" => "Thiếu hoặc sai dữ liệu gửi email. Vui lòng kiểm tra lại: " . implode(', ', $error_fields) . "."
    ]));
}

// Chuẩn hóa dữ liệu CC
$ccList = [];
if (!empty($cc_emails)) {
   $potentialCCs = explode(',', $cc_emails);
   foreach ($potentialCCs as $cc) {
       $trimmedCC = trim($cc);
       if (!empty($trimmedCC) && filter_var($trimmedCC, FILTER_VALIDATE_EMAIL)) {
           $ccList[] = $trimmedCC;
       }
   }
}
$cc_emails_string = implode(', ', $ccList);

error_log("Validated data in send_email_custom.php. CCs: " . $cc_emails_string); // Giữ lại error_log


// --- Xử lý File đính kèm ---
// Chỉ cần xử lý upload các file đính kèm thêm VÀ LẤY ĐƯỜNG DẪN TƯƠNG ĐỐI WEB của TẤT CẢ file (PDF mặc định + file thêm)
// KHÔNG cần chuẩn bị file đính kèm cho PHPMailer ở đây nữa
$attached_file_web_paths = []; // <<< Mảng lưu đường dẫn TƯƠNG ĐỐI WEB

error_log("Processing attachments in send_email_custom.php. Default PDF URL: " . $pdf_url); // Giữ lại error_log

// a) Thêm file PDF mặc định nếu có và tồn tại (Dùng đường dẫn tương đối web $pdf_url)
if (!empty($pdf_url)) {
    // Tính đường dẫn vật lý từ đường dẫn URL tương đối (pdf_url đã là tương đối web từ frontend)
    $pdf_path_physical = realpath(__DIR__ . "/../pdf/" . basename($pdf_url)); // Điều chỉnh nếu cấu trúc thư mục khác

    if (file_exists($pdf_path_physical)) {
        $attached_file_web_paths[] = $pdf_url; // <<< LƯU ĐƯỜNG DẪN TƯƠNG ĐỐI WEB vào mảng
        error_log("Default PDF exists and added to list: " . $pdf_url); // Giữ lại error_log
    } else {
        error_log("File PDF mặc định không tồn tại khi chuẩn bị lưu log email: " . $pdf_path_physical);
        // Vẫn cho phép queue email nhưng không đính kèm PDF này
    }
}

// b) Xử lý các file đính kèm thêm từ người dùng
if (!empty($extra_attachments) && is_array($extra_attachments) && isset($extra_attachments['tmp_name'])) {
    error_log("Processing extra attachments in send_email_custom.php."); // Giữ lại error_log

    $upload_dir_base_physical = __DIR__ . '/../uploads/documents'; // Đường dẫn vật lý gốc thư mục upload
    $upload_dir_base_web = 'uploads/documents'; // Đường dẫn TƯƠNG ĐỐI WEB gốc thư mục upload
    $current_month_folder = date('Y_m');
    $current_month_dir_physical = $upload_dir_base_physical . '/' . $current_month_folder; // Đường dẫn vật lý thư mục tháng
    $current_month_dir_web = $upload_dir_base_web . '/' . $current_month_folder; // Đường dẫn TƯƠNG ĐỐI WEB thư mục tháng

    // Tạo thư mục theo tháng nếu chưa tồn tại (vật lý)
    if (!is_dir($current_month_dir_physical)) {
        if (!mkdir($current_month_dir_physical, 0775, true)) {
            error_log('Không thể tạo thư mục upload vật lý khi lưu file đính kèm email: ' . $current_month_dir_physical);
        } else {
            error_log('Upload directory created successfully: ' . $current_month_dir_physical);
             chmod($current_month_dir_physical, 0775);
        }
    }

    // Kiểm tra xem thư mục đã tồn tại và ghi được không (vật lý)
    if (is_dir($current_month_dir_physical) && is_writable($current_month_dir_physical)) {
        $file_count = count($extra_attachments['name']);
        error_log("Number of extra files to process: " . $file_count); // Giữ lại error_log

        for ($i = 0; $i < $file_count; $i++) {
            if ($extra_attachments['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp_path = $extra_attachments['tmp_name'][$i];
                $file_name = $extra_attachments['name'][$i];

                // TẠO TÊN FILE DUY NHẤT
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $file_base_name = pathinfo($file_name, PATHINFO_FILENAME);
                $sanitized_base_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $file_base_name);
                $unique_file_name = $sanitized_base_name . '_' . uniqid() . '.' . $file_extension;

                // Đường dẫn lưu file vật lý
                $destination_path_physical = $current_month_dir_physical . '/' . $unique_file_name;
                // Đường dẫn lưu vào CSDL (tương đối web)
                $destination_path_web = $current_month_dir_web . '/' . $unique_file_name;

                // Di chuyển file tạm sang thư mục đích
                if (move_uploaded_file($file_tmp_path, $destination_path_physical)) {
                    $attached_file_web_paths[] = $destination_path_web; // <<< LƯU ĐƯỜNG DẪN TƯƠNG ĐỐI WEB vào mảng
                     error_log("File moved successfully. Web path: " . $destination_path_web); // Giữ lại error_log
                } else {
                    error_log("Lỗi khi di chuyển file upload cho email queue: " . $file_name . " to " . $destination_path_physical);
                }
            } else if ($extra_attachments['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                 error_log("Lỗi upload file cho email queue: " . $extra_attachments['name'][$i] . " - Error code: " . $extra_attachments['error'][$i]);
            }
        }
    } else {
        error_log('Thư mục upload vật lý không tồn tại hoặc không ghi được khi lưu file đính kèm email: ' . $current_month_dir_physical);
    }
}

// Sửa lỗi ArgumentCountError: print_r() chỉ nhận tối đa 2 tham số
error_log("Finished processing attachments in send_email_custom.php. Final web paths: " . json_encode($attached_file_web_paths));


// --- Ghi log email vào CSDL với trạng thái pending ---
try {
    $pdo->beginTransaction(); // Bắt đầu transaction

    // Chuyển mảng đường dẫn TƯƠNG ĐỐI WEB thành chuỗi JSON để lưu vào DB
    $attachment_paths_json = json_encode($attached_file_web_paths);
    if ($attachment_paths_json === false) {
        // Xử lý lỗi encode JSON nếu có
        error_log("Lỗi encode JSON attachment_paths: " . json_last_error_msg());
        $attachment_paths_json = '[]'; // Lưu mảng rỗng nếu lỗi
    }

    error_log("Inserting email log into DB for type: {$log_type}. JSON attachments: " . $attachment_paths_json); // Giữ lại error_log

    $target_log_table = ($log_type === 'quote') ? 'quote_email_logs' : 'email_logs';

    $sql = "INSERT INTO {$target_log_table} (order_id, to_email, cc_emails, subject, body, attachment_paths, status, created_at, sent_at, message)
            VALUES (:document_id, :to_email, :cc_emails, :subject, :body, :attachment_paths, 'pending', NOW(), NULL, NULL)";

    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
    $stmt->bindParam(':to_email', $to_email);
    $stmt->bindParam(':cc_emails', $cc_emails_string); // Lưu chuỗi CC đã chuẩn hóa
    $stmt->bindParam(':subject', $subject);
    $stmt->bindParam(':body', $body);
    $stmt->bindParam(':attachment_paths', $attachment_paths_json);

    $stmt->execute();

    $new_log_id = $pdo->lastInsertId();
    error_log(ucfirst($log_type) . " email queued successfully with Log ID: " . $new_log_id . " into table " . $target_log_table . " by send_email_custom.php"); // Giữ lại error_log

    $pdo->commit(); // Commit transaction

    error_log("Database transaction committed in send_email_custom.php."); // Giữ lại error_log

    // --- Kích hoạt script xử lý nền (cho Windows/XAMPP) ---
    // ĐÃ BỎ PHẦN GHI LOG RIÊNG CHO EXEC

    $php_executable_path = 'C:\\xampp\\php\\php.exe'; // <<< XÁC NHẬM LẠI ĐƯỜNG DẪN NÀY >>>
    $script_path = __DIR__ . '\\..\\process\\process_email_queue.php'; // Sử dụng \\ cho rõ ràng trên Windows

    error_log("PHP Exec Path for worker: " . $php_executable_path); // Giữ lại error_log
    error_log("Worker Script Path: " . $script_path); // Giữ lại error_log

    // CHỈNH SỬA: Bỏ phần redirect output >> "..."
    $command = 'start /B "Email Processor ' . $new_log_id . '" "' . $php_executable_path . '" "' . $script_path . '" ' . $new_log_id . ' ' . $log_type;

    error_log("Executing worker command: " . $command); // Giữ lại error_log

    $output = []; // Mảng để chứa output từ lệnh (nếu không redirect file)
    $return_var = 0; // Biến để chứa return code

    // Thực thi lệnh nền
    exec($command, $output, $return_var);

    error_log("Exec command finished in send_email_custom.php. Return Variable: " . $return_var); // Giữ lại error_log
    // Output từ lệnh exec (nếu có và không bị redirect) sẽ nằm trong $output, nhưng với start /B thường là rỗng.


    if ($return_var !== 0) {
        error_log("Lỗi khi kích hoạt script nền cho {$log_type} (return code: " . $return_var . "). Lệnh: " . $command); // Giữ lại error_log
        $response_message = "Yêu cầu gửi email " . ($log_type === 'quote' ? 'báo giá' : 'đơn hàng') . " đã được tiếp nhận và đưa vào hàng đợi, nhưng có thể có lỗi khi kích hoạt xử lý nền. Vui lòng kiểm tra log server.";
        $response_success = true; // Vẫn coi là thành công ở tầng ghi log
    } else {
        error_log("Script nền đã được kích hoạt thành công cho Log ID: " . $new_log_id . " (Type: {$log_type})"); // Giữ lại error_log
        $response_message = "Yêu cầu gửi email " . ($log_type === 'quote' ? 'báo giá' : 'đơn hàng') . " của bạn đã được tiếp nhận và đưa vào hàng đợi xử lý.";
        $response_success = true;
    }

    // --- Trả về phản hồi thành công cho AJAX ---
    http_response_code(200); // OK
    echo json_encode([
        "success" => $response_success,
        "message" => $response_message,
        "log_id" => $new_log_id,
        "log_type" => $log_type
    ]);

} catch (PDOException $e) {
    // Xử lý lỗi khi ghi log vào CSDL
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Lỗi khi ghi log email vào CSDL cho {$log_type} trong send_email_custom.php: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(["success" => false, "message" => "Lỗi máy chủ: Không thể lưu yêu cầu gửi email. " . $e->getMessage()]);
} catch (Exception $e) {
     error_log("General error in send_email_custom.php for {$log_type}: " . $e->getMessage());
     echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
     http_response_code(500);
}

error_log("send_email_custom.php finished for {$log_type}.");

?>