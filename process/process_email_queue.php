<?php
// === FILE: process/process_email_queue.php ===
// Script chạy nền để xử lý hàng đợi email
// CHỈNH SỬA: Cập nhật để xử lý cả order và quote logs
//ini_set('max_execution_time', 300); // Tăng lên 300 giây (5 phút)
//ini_set('memory_limit', '512M'); // Tăng bộ nhớ lên 512MB
// Đường dẫn đến file log tạm (đảm bảo thư mục log tồn tại và có quyền ghi)
// SỬ DỤNG ĐƯỜNG DẪN MÀ FILE TEST LOG ĐƠN GIẢN ĐÃ THÀNH CÔNG GHI VÀO
$log_file_path = __DIR__ . '/../logs/queue_debug_system.log'; // <<< SỬ DỤNG ĐƯỜNG DẪN NÀY
$log_base_dir = __DIR__ . '/../logs/';
 if (!is_dir($log_base_dir)) {
     mkdir($log_base_dir, 0775, true); // Tạo thư mục nếu chưa có
}

// Hàm ghi log trực tiếp ra file
function direct_log($message, $log_path) {
    // Thêm timestamp vào mỗi dòng log
    $timestamp = date('Y-m-d H:i:s');
    // Sử dụng @ để ẩn warning nếu ghi file thất bại (đã kiểm tra quyền nhưng cẩn thận vẫn tốt)
    // GHI CHÚ: Thêm prefix để dễ phân biệt log từ script này
    @file_put_contents($log_path, "[QUEUE_PROCESSOR][{$timestamp}] {$message}\n", FILE_APPEND);
}

// <<< THÊM LOG GHI RA FILE NGAY ĐẦU SCRIPT >>>
direct_log("[STARTUP] Script process_email_queue.php started. PHP Version: " . PHP_VERSION, $log_file_path);
// <<< KẾT THÚC LOG STARTUP >>>


// KHÔNG trả về bất kỳ output nào ra ngoài standard output, chỉ ghi log nếu cần

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composer autoload (Đường dẫn tính từ file process/process_email_queue.php)
// <<< THÊM LOG TRƯỚC KHI REQUIRE AUTOLOAD >>>
direct_log("[DEBUG] Attempting to require vendor/autoload.php", $log_file_path);
require_once __DIR__ . '/../vendor/autoload.php'; // <<< ĐIỂM CẦN KIỂM TRA NẾU LỖI XẢY RA SAU LOG STARTUP
// <<< THÊM LOG SAU KHI REQUIRE AUTOLOAD >>>
direct_log("[DEBUG] vendor/autoload.php required.", $log_file_path);


// Kết nối database
// <<< THÊM LOG TRƯỚC KHI REQUIRE DATABASE >>>
direct_log("[DEBUG] Attempting to require config/database.php", $log_file_path);
require_once __DIR__ . '/../config/database.php'; // <<< ĐIỂM CẦN KIỂM TRA NẾU LỖI XẢY RA SAU LOG VENDOR
// <<< THÊM LOG SAU KHI REQUIRE DATABASE >>>
direct_log("[DEBUG] config/database.php required.", $log_file_path);


// ... (Gọi hàm kết nối CSDL) ...
$pdo = db_connect();

if (!$pdo) {
    // <<< THÊM LOG LỖI KẾT NỐI CSDL >>>
    direct_log('[ERROR] Lỗi CSDL nghiêm trọng: Không thể kết nối.', $log_file_path);
    exit(1);
}
// <<< THÊM LOG KẾT NỐI CSDL THÀNH CÔNG >>>
 direct_log('[DEBUG] Kết nối CSDL thành công.', $log_file_path);


// === Lấy ID bản ghi email VÀ LOẠI LOG từ tham số dòng lệnh ===
// CHỈNH SỬA: Nhận thêm $log_type từ tham số dòng lệnh
if (!isset($argv[1]) || !isset($argv[2])) { // $argv[1] là log_id, $argv[2] là log_type
    // <<< THÊM LOG LỖI THIẾU THAM SỐ >>>
    direct_log('Lỗi: Thiếu ID bản ghi email (log_id) hoặc loại log (log_type) từ tham số dòng lệnh. Args: ' . print_r($argv, true), $log_file_path);
    // GHI CHÚ CHỈNH SỬA: Cần 2 tham số: log_id và log_type.
    exit(1); // Lỗi này xuất hiện nếu không truyền đủ ID và type khi chạy script
}

$log_id = (int) $argv[1];
$log_type = trim($argv[2]); // 'order' hoặc 'quote'
// GHI CHÚ CHỈNH SỬA: `$log_type` được lấy từ tham số dòng lệnh thứ hai.

// <<< THÊM LOG ID VÀ TYPE ĐANG XỬ LÝ >>>
direct_log("Processing log ID: {$log_id} for type: {$log_type}", $log_file_path);

// CHỈNH SỬA: Xác định tên bảng log mục tiêu dựa trên log_type
$target_log_table = '';
if ($log_type === 'quote') {
    $target_log_table = 'quote_email_logs';
} elseif ($log_type === 'order') {
    $target_log_table = 'email_logs';
} else {
    direct_log("[ERROR] Invalid log_type received: {$log_type}. Exiting.", $log_file_path);
    exit(1); // Thoát nếu log_type không hợp lệ
}
direct_log("Target log table determined: {$target_log_table}", $log_file_path);
// GHI CHÚ CHỈNH SỬA: `$target_log_table` được xác định để sử dụng trong các câu lệnh SQL.

// === Xử lý bản ghi email ===
try {
    // Bắt đầu Transaction
    $pdo->beginTransaction();

    // Lấy bản ghi email
    // CHỈNH SỬA: Truy vấn từ $target_log_table
    direct_log("Attempting to SELECT log ID {$log_id} from {$target_log_table} (status = pending)", $log_file_path);
    $sql = "SELECT * FROM {$target_log_table} WHERE id = :id AND status = 'pending' LIMIT 1 FOR UPDATE";
    // GHI CHÚ CHỈNH SỬA: Câu lệnh SELECT giờ đây sử dụng `$target_log_table`.
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $log_id, PDO::PARAM_INT);
    $stmt->execute();
    $email_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email_record) {
        // Bản ghi không tồn tại hoặc đã được xử lý
        direct_log("Log ID {$log_id} (type: {$log_type}) not found in {$target_log_table} or already processed (status is not pending). Rolling back transaction.", $log_file_path);
        $pdo->rollBack();
        exit(0);
    }
    direct_log("Found log ID {$log_id} in {$target_log_table} with status: " . $email_record['status'], $log_file_path);

    // Cập nhật trạng thái thành 'sending'
    // CHỈNH SỬA: Cập nhật vào $target_log_table
    direct_log("Attempting to UPDATE status to 'sending' for log ID {$log_id} in {$target_log_table}", $log_file_path);
    $update_sql = "UPDATE {$target_log_table} SET status = 'sending', sent_at = NOW(), message = NULL WHERE id = :id";
    // GHI CHÚ CHỈNH SỬA: Câu lệnh UPDATE giờ đây sử dụng `$target_log_table`.
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->bindParam(':id', $log_id, PDO::PARAM_INT);
    $update_stmt->execute();
    direct_log("Status updated to 'sending' for log ID {$log_id} in {$target_log_table}. Committing transaction.", $log_file_path);


    // Commit Transaction
    $pdo->commit();
    direct_log("Transaction committed for log ID {$log_id} (type: {$log_type}). Starting email send process.", $log_file_path);


    // --- Cấu hình và gửi mail bằng PHPMailer ---
    // Phần này giữ nguyên vì nó dựa trên $email_record, không phụ thuộc trực tiếp vào $log_type ở đây nữa.
    // Cấu trúc của $email_record (tên các cột như to_email, subject, body, attachment_paths) được giả định là giống nhau
    // giữa email_logs và quote_email_logs.
    direct_log("Starting PHPMailer configuration for log ID {$log_id} (type: {$log_type})", $log_file_path);
    $mail = new PHPMailer(true);
    // $mail->SMTPDebug = 4; // Bật debug chi tiết của PHPMailer nếu cần, ghi vào $log_file_path
    // ob_start(); // Bắt output của SMTPDebug
    $send_success = false;
    $message = null; // Thông báo lỗi hoặc thành công từ việc gửi mail

    try {
        // Cấu hình SMTP
        $mail->CharSet = 'UTF-8';
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Để debug, có thể ghi log chi tiết từ PHPMailer
        $mail->SMTPDebug = 2; // GHI CHÚ: Giữ lại mức debug của bạn
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'saothienvuong80@gmail.com'; // Gmail của bạn
        $mail->Password = 'hlzp slvz eeku vclh';      // App Password Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Hoặc ENCRYPTION_SMTPS cho port 465
        $mail->Port = 587; // Hoặc 465 nếu dùng SMTPS
        direct_log("PHPMailer SMTP configured.", $log_file_path);

        // Người gửi, người nhận, CC
        direct_log("Recipient: " . $email_record['to_email'] . ", CC: " . ($email_record['cc_emails'] ?? 'None'), $log_file_path);
        $mail->setFrom('saothienvuong80@gmail.com', 'Test STV'); // CHỈNH SỬA: Tên người gửi có thể cấu hình
        $mail->addAddress($email_record['to_email']);

        if (!empty($email_record['cc_emails'])) {
           $ccList = explode(',', $email_record['cc_emails']);
           foreach ($ccList as $cc) {
               $trimmedCC = trim($cc);
               if (!empty($trimmedCC) && filter_var($trimmedCC, FILTER_VALIDATE_EMAIL)) {
                   $mail->addCC($trimmedCC);
                   direct_log("Added CC: {$trimmedCC}", $log_file_path);
               }
           }
        }

        // Đính kèm file
        $attached_file_web_paths = json_decode($email_record['attachment_paths'], true);
        direct_log("Attachment paths from DB (JSON decoded for log ID {$log_id}): " . print_r($attached_file_web_paths, true), $log_file_path);

        if (is_array($attached_file_web_paths)) {
            foreach ($attached_file_web_paths as $file_web_path) {
                 $file_physical_path = __DIR__ . "/../" . ltrim($file_web_path, '/');
                if (file_exists($file_physical_path)) {
                    direct_log("Attaching physical file: {$file_physical_path} (Original web path: {$file_web_path})", $log_file_path);
                    $file_name_in_email = basename($file_web_path); // Lấy tên file gốc để hiển thị trong email
                    $mail->addAttachment($file_physical_path, $file_name_in_email);
                } else {
                    direct_log("[WARNING] Physical file not found for attachment (Log ID {$log_id}, type {$log_type}): {$file_physical_path}. Web path: {$file_web_path}", $log_file_path);
                    $message .= "Thiếu file đính kèm: " . basename($file_web_path) . ". "; // Thêm vào thông báo lỗi
                }
            }
        } else {
             direct_log("[WARNING] attachment_paths is not a valid JSON array or is null (Log ID {$log_id}, type {$log_type}): " . $email_record['attachment_paths'], $log_file_path);
             if (!empty($email_record['attachment_paths'])) { // Chỉ thêm lỗi nếu attachment_paths không rỗng nhưng parse lỗi
                $message .= "Lỗi định dạng đường dẫn file đính kèm. ";
             }
        }

        // Nội dung Email
        direct_log("Email Subject for log ID {$log_id}: " . $email_record['subject'], $log_file_path);
        $mail->isHTML(true);
        $mail->Subject = $email_record['subject'];
        $mail->Body    = $email_record['body'];

        // --- Gửi mail ---
        direct_log("Attempting to send email for log ID {$log_id} (type: {$log_type})", $log_file_path);
        $mail->send(); // <<< DÒNG GỬI MAIL THẬT SỰ >>>
        $send_success = true;
        $message = 'Email sent successfully.' . ($message ?? ''); // Nối với thông báo thiếu file nếu có
        direct_log("Email sent successfully for Log ID: {$log_id} (type: {$log_type}). Message: {$message}", $log_file_path);

    } catch (Exception $e) {
        $send_success = false;
        $error_info = $mail->ErrorInfo ?? $e->getMessage();
        $message = "Gửi email thất bại: " . $error_info . ($message ?? ''); // Nối với thông báo thiếu file nếu có
        direct_log("[ERROR] Error sending email for Log ID {$log_id} (type: {$log_type}): {$message}", $log_file_path);
    }
    // $smtp_debug_output = ob_get_clean(); // Lấy output của SMTPDebug
    // if (!empty($smtp_debug_output)) {
    //    direct_log("[PHPMailer SMTP Debug - Log ID {$log_id}]:\n{$smtp_debug_output}", $log_file_path);
    // }


    // --- Cập nhật trạng thái cuối cùng vào CSDL ---
    try {
        $final_status = $send_success ? 'sent' : 'failed';
        // CHỈNH SỬA: Cập nhật vào $target_log_table
        direct_log("Attempting to UPDATE final status to '{$final_status}' for log ID {$log_id} in {$target_log_table}. Message: " . ($message ?? 'N/A'), $log_file_path);
        $update_sql_final = "UPDATE {$target_log_table} SET status = :status, sent_at = NOW(), message = :message WHERE id = :id";
        // GHI CHÚ CHỈNH SỬA: Câu lệnh UPDATE cuối cùng sử dụng `$target_log_table`.
        $update_stmt_final = $pdo->prepare($update_sql_final);
        $update_stmt_final->bindParam(':status', $final_status);
        $update_stmt_final->bindParam(':message', $message); // Lưu thông báo lỗi/thành công từ PHPMailer
        $update_stmt_final->bindParam(':id', $log_id, PDO::PARAM_INT);
        $update_stmt_final->execute();
        direct_log("Log ID {$log_id} (type: {$log_type}) final status updated to '{$final_status}' in {$target_log_table}.", $log_file_path);

    } catch (PDOException $e_update) {
        direct_log("[ERROR] Lỗi CSDL khi cập nhật trạng thái cuối cùng cho Log ID {$log_id} (type: {$log_type}) in {$target_log_table}: " . $e_update->getMessage(), $log_file_path);
        // Dù lỗi cập nhật CSDL, email có thể đã được gửi hoặc thất bại. Trạng thái này cần được xem xét.
    }

} catch (PDOException $e_initial) {
    // Xử lý lỗi CSDL khi cố gắng lấy/cập nhật trạng thái 'pending' ban đầu
    direct_log("[ERROR] Lỗi CSDL ban đầu khi xử lý Log ID {$log_id} (type: {$log_type}): " . $e_initial->getMessage(), $log_file_path);
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        direct_log("Transaction rolled back due to initial PDOException for log ID {$log_id}.", $log_file_path);
    }
    // Cố gắng cập nhật bản ghi về trạng thái lỗi nếu có lỗi CSDL ban đầu
    // Cần đảm bảo $target_log_table đã được xác định hoặc có một cơ chế an toàn
    if (!empty($target_log_table)) { // Chỉ thử update nếu target_log_table đã được set
        try {
             $fail_message = "Lỗi CSDL trong quá trình xử lý ban đầu: " . $e_initial->getMessage();
             direct_log("Attempting to mark log ID {$log_id} as 'failed' in {$target_log_table} due to initial DB error. Message: {$fail_message}", $log_file_path);
             $update_fail_sql = "UPDATE {$target_log_table} SET status = 'failed', sent_at = NOW(), message = :message WHERE id = :id";
             $update_fail_stmt = $pdo->prepare($update_fail_sql);
             // $update_fail_stmt->bindValue(':status', 'failed'); // Không cần thiết vì đã có trong SQL
             $update_fail_stmt->bindParam(':message', $fail_message);
             $update_fail_stmt->bindParam(':id', $log_id, PDO::PARAM_INT);
             $update_fail_stmt->execute();
             direct_log("Attempted to update log ID {$log_id} to 'failed' in {$target_log_table} after initial DB error.", $log_file_path);
        } catch (PDOException $e_final_fail) {
            direct_log("[CRITICAL ERROR] Không thể cập nhật trạng thái lỗi cho Log ID {$log_id} (type: {$log_type}) in {$target_log_table} sau lỗi CSDL ban đầu: " . $e_final_fail->getMessage(), $log_file_path);
        }
    } else {
        direct_log("[CRITICAL ERROR] target_log_table not set for log ID {$log_id} (type: {$log_type}) during initial PDOException handling. Cannot mark as failed.", $log_file_path);
    }
    exit(1); // Thoát với mã lỗi
} catch (Exception $e_general) { // Bắt các Exception chung khác không phải PDOException từ đầu
    direct_log("[ERROR] Lỗi chung không xác định khi xử lý Log ID {$log_id} (type: {$log_type}): " . $e_general->getMessage() . "\n" . $e_general->getTraceAsString(), $log_file_path);
    if ($pdo->inTransaction()) { // Đảm bảo rollback nếu transaction còn mở
        $pdo->rollBack();
        direct_log("Transaction rolled back due to general Exception for log ID {$log_id}.", $log_file_path);
    }
     // Cố gắng cập nhật trạng thái lỗi
    if (!empty($target_log_table)) {
        try {
            $fail_message_general = "Lỗi chung không xác định: " . $e_general->getMessage();
            direct_log("Attempting to mark log ID {$log_id} as 'failed' in {$target_log_table} due to general error. Message: {$fail_message_general}", $log_file_path);
            $update_fail_sql_general = "UPDATE {$target_log_table} SET status = 'failed', sent_at = NOW(), message = :message WHERE id = :id";
            $update_fail_stmt_general = $pdo->prepare($update_fail_sql_general);
            $update_fail_stmt_general->bindParam(':message', $fail_message_general);
            $update_fail_stmt_general->bindParam(':id', $log_id, PDO::PARAM_INT);
            $update_fail_stmt_general->execute();
        } catch (PDOException $e_final_fail_general) {
            direct_log("[CRITICAL ERROR] Không thể cập nhật trạng thái lỗi cho Log ID {$log_id} (type: {$log_type}) in {$target_log_table} sau lỗi chung: " . $e_final_fail_general->getMessage(), $log_file_path);
        }
    }
    exit(1);
}


// <<< THÊM LOG KẾT THÚC SCRIPT >>>
direct_log("Script process_email_queue.php finished for Log ID: {$log_id} (type: {$log_type})", $log_file_path);
exit(0);
?>