<?php
// File: config/database.php
// Cấu hình thông tin kết nối cơ sở dữ liệu.

// --- Các hằng số kết nối CSDL ---
// Đảm bảo các giá trị này là chính xác cho môi trường của bạn.
// Đối với môi trường production, cân nhắc việc lưu trữ thông tin nhạy cảm (như mật khẩu DB)
// một cách an toàn hơn, ví dụ qua biến môi trường.

define('DB_HOST', 'localhost');          // Thường là 'localhost' hoặc địa chỉ IP của DB server
define('DB_NAME', 'db_quanlykho');       // Tên cơ sở dữ liệu
define('DB_USER', 'root');               // Tên người dùng CSDL
define('DB_PASS', '');                   // Mật khẩu CSDL (để trống nếu không có)
define('DB_CHARSET', 'utf8mb4');         // Nên dùng utf8mb4 để hỗ trợ đầy đủ Unicode

// --- Chuỗi DSN (Data Source Name) và Tùy chọn PDO ---
// Các biến này sẽ được sử dụng trong init.php để tạo đối tượng PDO.

// DSN cho kết nối PDO MySQL
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// Các tùy chọn cho PDO
$options = [
    // QUAN TRỌNG: Bật chế độ báo lỗi PDO thông qua exceptions.
    // Điều này giúp bắt và xử lý lỗi CSDL một cách rõ ràng hơn.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

    // Đặt chế độ fetch mặc định là mảng kết hợp (associative array).
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Tắt chế độ emulate prepared statements của PDO.
    // Điều này đảm bảo sử dụng native prepared statements từ driver CSDL,
    // giúp tăng cường bảo mật (chống SQL injection) và hiệu năng.
    PDO::ATTR_EMULATE_PREPARES   => false,

    // (Tùy chọn) Có thể thêm các tùy chọn khác nếu cần, ví dụ:
    // PDO::ATTR_PERSISTENT => true, // Nếu bạn muốn sử dụng kết nối persistent (cân nhắc kỹ)
    // PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci", // Đã có charset trong DSN
];

// Lưu ý: Việc khởi tạo đối tượng $pdo được thực hiện trong includes/init.php
// File này chỉ cung cấp các thông tin cấu hình cần thiết cho việc kết nối.

?>