<?php
// File: process/login_process.php
require_once __DIR__ . '/../includes/init.php'; // Bao gồm logging.php, $pdo, $lang, session

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = $lang['error_required_fields'] ?? $lang['required_field'] ?? "Username and password are required.";

        // Ghi log: login thất bại do thiếu thông tin
        write_user_log($pdo, 'login_failed', "Thiếu username hoặc mật khẩu. Nhập: $username");

        header('Location: ../login.php');
        exit();
    }

    try {
        $sql = "SELECT id, username, password_hash, role, permissions FROM users WHERE username = :username LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true); // Bảo mật

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_permissions'] = json_decode($user['permissions'], true) ?? [];

                // Ghi log: login thành công
                write_user_log($pdo, (int)$user['id'], 'login_success', 'Đăng nhập thành công');

                // Ghi remember_token
                $remember_token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE users SET remember_token = :token WHERE id = :id");
                $stmt->execute([
                    'token' => $remember_token,
                    'id' => $user['id']
                ]);
                setcookie('remember_token', $remember_token, time() + 86400, "/");

                $redirect_url = $_SESSION['redirect_url'] ?? '../dashboard.php';
                unset($_SESSION['redirect_url']);
                header("Location: " . $redirect_url);
                exit();
            } else {
                $_SESSION['login_error'] = $lang['login_failed'] ?? "Login failed. Invalid username or password.";
                write_user_log($pdo, 'login_failed', "Sai mật khẩu cho username: $username");
                header('Location: ../login.php');
                exit();
            }
        } else {
            $_SESSION['login_error'] = $lang['login_failed'] ?? "Login failed. Invalid username or password.";
            write_user_log($pdo, 'login_failed', "Không tìm thấy tài khoản: $username");
            header('Location: ../login.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Login Process DB Error: " . $e->getMessage());
        write_user_log($pdo, 'login_error', "Lỗi PDO: " . $e->getMessage() . " (Username: $username)");
        $_SESSION['login_error'] = $lang['database_error'] ?? "A database error occurred during login. Please try again later.";
        header('Location: ../login.php');
        exit();
    }
} else {
    header('Location: ../login.php');
    exit();
}
