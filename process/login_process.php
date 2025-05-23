<?php
// process/login_process.php
require_once __DIR__ . '/../includes/init.php'; // Cần init để có $pdo, $lang, session, log_activity

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        // Sử dụng key ngôn ngữ nếu có, hoặc thông báo mặc định
        $_SESSION['login_error'] = $lang['error_required_fields'] ?? $lang['required_field'] ?? "Username and password are required.";
        header('Location: ../login.php');
        exit();
    }

    try {
        // --- SỬA: THÊM CỘT 'permissions' vào câu truy vấn ---
        $sql = "SELECT id, username, password_hash, role, permissions FROM users WHERE username = :username LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC); // Sử dụng PDO::FETCH_ASSOC để đảm bảo kết quả là mảng kết hợp

        if ($user) {
            // Xác thực mật khẩu
            if (password_verify($password, $user['password_hash'])) {
                // Đăng nhập thành công
                session_regenerate_id(true); // Tạo session ID mới để bảo mật

                // --- LƯU THÔNG TIN USER VÀ QUYỀN VÀO SESSION ---
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // **ĐOẠN CODE QUAN TRỌNG:** Giải mã JSON permissions và lưu vào session
                // json_decode(string $json, bool $associative = false, int $depth = 512, int $flags = 0): mixed
                // Tham số true thứ hai để đảm bảo trả về mảng kết hợp
                // Sử dụng ?? [] để đảm bảo $_SESSION['user_permissions'] luôn là một mảng ngay cả khi cột permissions là NULL hoặc rỗng
                $_SESSION['user_permissions'] = json_decode($user['permissions'], true) ?? [];

                // Ghi log đăng nhập thành công
                log_activity($_SESSION['user_id'], $lang['log_login_success'] ?? "User logged in successfully.", $pdo);

                // Xóa lỗi login cũ (nếu có)
                unset($_SESSION['login_error']);

                // Chuyển hướng đến trang dashboard hoặc trang đã lưu trước đó
                $redirect_url = $_SESSION['redirect_url'] ?? '../dashboard.php';
                unset($_SESSION['redirect_url']); // Xóa link chuyển hướng cũ
                header("Location: " . $redirect_url);
                exit();

            } else {
                // Mật khẩu sai
                $_SESSION['login_error'] = $lang['login_failed'] ?? "Login failed. Invalid username or password.";
                // Ghi log đăng nhập thất bại
                log_activity(null, ($lang['log_login_failed'] ?? "Failed login attempt for username:") . " " . $username, $pdo);
                header('Location: ../login.php');
                exit();
            }
        } else {
            // Không tìm thấy người dùng với username này
            $_SESSION['login_error'] = $lang['login_failed'] ?? "Login failed. Invalid username or password.";
             // Ghi log đăng nhập thất bại
            log_activity(null, ($lang['log_login_failed'] ?? "Failed login attempt for username:") . " " . $username, $pdo);
            header('Location: ../login.php');
            exit();
        }

    } catch (PDOException $e) {
        // Xử lý lỗi database trong quá trình truy vấn
        error_log("Login Process DB Error: " . $e->getMessage());
        log_activity(null, "CRITICAL: Login database error - " . $e->getMessage(), $pdo);
        $_SESSION['login_error'] = $lang['database_error'] ?? "A database error occurred during login. Please try again later.";
        header('Location: ../login.php');
        exit();
    }

} else {
    // Nếu không phải POST request, chuyển về trang login
    header('Location: ../login.php');
    exit();
}
?>