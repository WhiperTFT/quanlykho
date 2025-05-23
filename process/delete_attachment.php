<?php
// File: process/delete_attachment.php

header('Content-Type: application/json');

// Nạp các file cần thiết (kết nối CSDL, session, auth...)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/init.php'; // Giả định init.php có db_connect(), is_logged_in()...
require_once __DIR__ . '/../includes/auth_check.php'; // Đảm bảo người dùng đăng nhập

$pdo = db_connect();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi kết nối CSDL."]);
    exit;
}

// Kiểm tra người dùng đã đăng nhập chưa (Quan trọng!)
if (!is_logged_in()) {
    http_response_code(401); // Unauthorized
    echo json_encode(["success" => false, "message" => "Bạn cần đăng nhập để thực hiện thao tác này."]);
    exit;
}

// Kiểm tra phương thức yêu cầu (nên là POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["success" => false, "message" => "Phương thức yêu cầu không hợp lệ."]);
    exit;
}

// Lấy và kiểm tra attachment_id từ dữ liệu POST
$attachment_id = filter_input(INPUT_POST, 'attachment_id', FILTER_VALIDATE_INT);

if (!$attachment_id || $attachment_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(["success" => false, "message" => "ID file đính kèm không hợp lệ."]);
    exit;
}

try {
    // --- Quan trọng: Kiểm tra quyền xóa ---
    // Trước khi xóa, bạn NÊN kiểm tra xem người dùng hiện tại có quyền xóa file đính kèm này không.
    // Ví dụ: Kiểm tra xem đơn hàng liên quan có thuộc về chi nhánh/đơn vị mà người dùng có quyền quản lý không,
    // hoặc kiểm tra xem người dùng có vai trò (role) cho phép xóa file đính kèm không.
    // Đoạn code kiểm tra quyền này phụ thuộc vào logic quản lý quyền của dự án bạn.
    // Dưới đây là ví dụ truy vấn lấy thông tin file và order_id để kiểm tra quyền sau đó.

    $stmt_check = $pdo->prepare("SELECT id, order_id, file_path FROM email_attachments WHERE id = :id");
    $stmt_check->bindParam(':id', $attachment_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $attachment_info = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$attachment_info) {
        http_response_code(404); // Not Found
        echo json_encode(["success" => false, "message" => "Không tìm thấy file đính kèm."]);
        exit;
    }

    // --- THÊM LOGIC KIỂM TRA QUYỀN TẠI ĐÂY ---
    // if (!check_user_permission_to_delete_attachment($attachment_info['order_id'], $_SESSION['user_id'])) {
    //     http_response_code(403); // Forbidden
    //     echo json_encode(["success" => false, "message" => "Bạn không có quyền xóa file đính kèm này."]);
    //     exit;
    // }
    // --- KẾT THÚC LOGIC KIỂM TRA QUYỀN ---

    // Lấy đường dẫn file vật lý trên server
    // Đảm bảo file_path được lưu là đường dẫn tương đối hoặc tuyệt đối mà PHP có thể truy cập
    $file_to_delete = __DIR__ . '/../' . ltrim($attachment_info['file_path'], '/'); // Điều chỉnh đường dẫn nếu cần

    // Bắt đầu Transaction để đảm bảo tính toàn vẹn dữ liệu
    $pdo->beginTransaction();

    // Xóa bản ghi khỏi CSDL
    $stmt_delete_db = $pdo->prepare("DELETE FROM email_attachments WHERE id = :id");
    $stmt_delete_db->bindParam(':id', $attachment_id, PDO::PARAM_INT);
    $stmt_delete_db->execute();

    // Kiểm tra xem có bản ghi nào bị ảnh hưởng không
    if ($stmt_delete_db->rowCount() > 0) {
        // Xóa file vật lý nếu nó tồn tại
        if (file_exists($file_to_delete) && is_file($file_to_delete)) {
             if (unlink($file_to_delete)) {
                 $pdo->commit(); // Commit Transaction nếu xóa DB và file thành công
                 echo json_encode(["success" => true, "message" => "Đã xóa file đính kèm thành công."]);
             } else {
                 $pdo->rollBack(); // Rollback nếu xóa file thất bại
                 error_log("Failed to unlink attachment file: " . $file_to_delete);
                 http_response_code(500);
                 echo json_encode(["success" => false, "message" => "Đã xóa bản ghi trong CSDL nhưng không xóa được file vật lý. Vui lòng kiểm tra quyền ghi của thư mục."]);
             }
        } else {
            // Nếu bản ghi trong DB tồn tại nhưng file vật lý không có, vẫn coi là xóa thành công (đã xóa khỏi DB)
             $pdo->commit();
             echo json_encode(["success" => true, "message" => "Đã xóa bản ghi file đính kèm khỏi CSDL (file vật lý không tồn tại hoặc không tìm thấy)."]);
        }
    } else {
        // Không có bản ghi nào bị xóa, có thể id không tồn tại (dù đã kiểm tra ở trên, kiểm tra lại vẫn tốt)
         $pdo->rollBack(); // Không cần rollback nếu không có gì để xóa
         http_response_code(404);
         echo json_encode(["success" => false, "message" => "Không tìm thấy file đính kèm để xóa (ID không tồn tại sau khi kiểm tra ban đầu)."]);
    }


} catch (PDOException $e) {
    $pdo->rollBack(); // Rollback nếu có lỗi CSDL
    error_log("Database Error deleting attachment ID $attachment_id: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(["success" => false, "message" => "Lỗi truy vấn CSDL khi xóa file đính kèm."]);
} catch (Exception $e) {
     $pdo->rollBack(); // Rollback nếu có lỗi khác
     error_log("General Error deleting attachment ID $attachment_id: " . $e->getMessage());
     http_response_code(500);
     echo json_encode(["success" => false, "message" => "Đã xảy ra lỗi không mong muốn khi xóa file đính kèm."]);
}

?>