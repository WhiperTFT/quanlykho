<?php
// File: includes/get_partner_email.php

header('Content-Type: application/json'); // Đảm bảo trả về JSON

// Bao gồm file kết nối CSDL (chứa hàm db_connect)
// Đường dẫn này giả định 'includes' và 'config' là ngang cấp trong thư mục gốc
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/init.php';
// === THÊM DÒNG NÀY ĐỂ GỌI HÀM KẾT NỐI ===
$conn = db_connect();
// ========================================

// === THÊM ĐOẠN KIỂM TRA KẾT NỐI ===
if (!$pdo) { // Kiểm tra xem kết nối có thành công không
    error_log('Lỗi CSDL nghiêm trọng trong get_partner_email.php: Không thể kết nối.');
    http_response_code(500);
    // Sử dụng thông báo lỗi khớp với lỗi bạn nhận được hoặc thông báo chuẩn hơn
    // exit(json_encode(["success" => false, "message" => "Lỗi: Không thể kết nối đến cơ sở dữ liệu."]));
    exit(json_encode(["success" => false, "message" => "Lỗi máy chủ: Không thể kết nối CSDL."]));
}
// === KẾT THÚC ĐOẠN KIỂM TRA ===


// 1. Lấy document_id và document_type từ dữ liệu POST gửi lên từ AJAX
// CHỈNH SỬA: Đổi tên 'id' thành 'document_id' cho rõ ràng hơn và thêm 'document_type'
$document_id = $_POST['id'] ?? $_POST['document_id'] ?? null; // Chấp nhận cả 'id' hoặc 'document_id' để tương thích tạm thời
$document_type = $_POST['type'] ?? 'order'; // Mặc định là 'order' nếu không có 'type' được gửi lên. 'type' sẽ là 'order' hoặc 'quote'

// GHI CHÚ CHỈNH SỬA:
// - Đã thêm biến `$document_type` để nhận loại tài liệu ('order' hoặc 'quote') từ yêu cầu AJAX.
//   Mặc định là 'order' nếu không được cung cấp để giữ tương thích với cách gọi cũ từ sales_orders.js.
// - Đổi tên biến `$order_id` thành `$document_id` để phản ánh đúng ý nghĩa là ID của order hoặc quote.
//   Vẫn chấp nhận `$_POST['id']` để tương thích ngược với lời gọi AJAX hiện tại từ `sales_orders.js` (sẽ được cập nhật sau).


if (!$document_id) {
    http_response_code(400); // Bad Request
    // CHỈNH SỬA: Cập nhật thông báo lỗi cho rõ ràng
    echo json_encode(["success" => false, "message" => "Thiếu ID của tài liệu (đơn hàng/báo giá)."]);
    // GHI CHÚ CHỈNH SỬA: Thông báo lỗi được cập nhật để phản ánh đúng việc có thể là đơn hàng hoặc báo giá.
    exit;
}

try {
    // 2. Chuẩn bị câu lệnh SQL dựa trên document_type
    // CHỈNH SỬA: Logic để chọn câu SQL dựa trên $document_type
    $sql = "";
    $partner_id_column = ""; // Tên cột ID đối tác trong bảng sales_orders hoặc sales_quotes
    $document_table_alias = ""; // Alias cho bảng sales_orders hoặc sales_quotes

    if ($document_type === 'quote') {
        // Giả định:
        // - Bảng báo giá là 'sales_quotes'.
        // - Cột liên kết với đối tác (khách hàng) trong 'sales_quotes' là 'customer_id'.
        // - Bảng 'partners' chứa thông tin của cả nhà cung cấp và khách hàng, hoặc bạn có bảng 'customers' riêng.
        //   Nếu dùng bảng 'customers' riêng, cần JOIN với bảng đó. Ở đây giả định JOIN với 'partners'.
        $sql = "SELECT p.email, p.cc_emails
                FROM sales_quotes sq
                JOIN partners p ON sq.customer_id = p.id 
                WHERE sq.id = :document_id";
        // GHI CHÚ CHỈNH SỬA (quote):
        // - Câu SQL được thay đổi để truy vấn từ bảng `sales_quotes` (alias `sq`).
        // - JOIN với bảng `partners` (alias `p`) thông qua cột `sq.customer_id`.
        //   Bạn cần đảm bảo rằng bảng `sales_quotes` có cột `customer_id` và bảng `partners` chứa thông tin khách hàng.
        //   Nếu bạn có bảng `customers` riêng, bạn sẽ JOIN với bảng đó.
    } else { // Mặc định hoặc $document_type === 'order'
        $sql = "SELECT p.email, p.cc_emails
                FROM sales_orders so
                JOIN partners p ON so.supplier_id = p.id
                WHERE so.id = :document_id";
        // GHI CHÚ CHỈNH SỬA (order):
        // - Đây là câu SQL gốc cho 'order', không thay đổi logic nhưng được đặt trong nhánh else.
    }

    $stmt = $pdo->prepare($sql);

    // 3. Gán giá trị và thực thi
    $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
    $stmt->execute();

    // 4. Lấy kết quả
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. Xử lý và trả về kết quả
    if ($result) {
        // Tìm thấy thông tin email
        echo json_encode([
            "success" => true,
            "email" => $result['email'] ?? '',
            "cc_emails" => $result['cc_emails'] ?? '' // Sử dụng cc_emails
        ]);
    } else {
        // Không tìm thấy tài liệu hoặc đối tác tương ứng
        // CHỈNH SỬA: Cập nhật thông báo log cho rõ ràng
        error_log("Không tìm thấy email cho {$document_type} ID: {$document_id} trong get_partner_email.php");
        // GHI CHÚ CHỈNH SỬA: Thông báo log được cập nhật để bao gồm cả `document_type`.
        echo json_encode([
            "success" => true, // Vẫn trả về success để JS không báo lỗi AJAX nghiêm trọng, chỉ là không có dữ liệu
            "email" => '',
            "cc_emails" => ''
        ]);
    }

} catch (PDOException $e) {
    // Xử lý lỗi nếu có vấn đề với CSDL khi truy vấn
    http_response_code(500); // Internal Server Error
    // CHỈNH SỬA: Cập nhật thông báo log cho rõ ràng
    error_log("Database Query Error in get_partner_email.php for {$document_type} ID: {$document_id}: " . $e->getMessage());
    // GHI CHÚ CHỈNH SỬA: Thông báo log lỗi CSDL được cập nhật để bao gồm `document_type` và `document_id`.
    echo json_encode([
        "success" => false,
        "message" => "Lỗi truy vấn cơ sở dữ liệu.",
        // "error_detail" => $e->getMessage() // Bật khi debug
    ]);
} catch (Exception $e) {
    // Xử lý các lỗi khác
    http_response_code(500);
    // CHỈNH SỬA: Cập nhật thông báo log cho rõ ràng
    error_log("General Error in get_partner_email.php for {$document_type} ID: {$document_id}: " . $e->getMessage());
    // GHI CHÚ CHỈNH SỬA: Thông báo log lỗi chung được cập nhật.
    echo json_encode([
        "success" => false,
        "message" => "Đã xảy ra lỗi không mong muốn."
        // "error_detail" => $e->getMessage() // Bật khi debug
    ]);
}

?>