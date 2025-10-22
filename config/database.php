<?php
// config/database.php

// --- Ghi nhớ các hằng số này ---\
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_quanlykho');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4'); // Thêm charset

// Biến $pdo sẽ được tạo trong init.php hoặc ở đây nếu muốn dùng chung
// Biến này sẽ được kiểm tra trong init.php
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // QUAN TRỌNG: Ném exception khi có lỗi
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Biến $pdo sẽ được gán ở đây hoặc trong init.php
// Để nhất quán với init.php hiện tại, chúng ta sẽ không gán $pdo ở đây,
// mà để init.php thực hiện. File này chỉ cung cấp thông tin kết nối.
?>