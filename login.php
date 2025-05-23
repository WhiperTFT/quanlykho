<?php
$page_title = "Login"; // Đặt tiêu đề trang trước khi include header
require_once __DIR__ . '/includes/init.php'; // Load init để có $lang

// Nếu đã đăng nhập thì chuyển về dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?= $current_lang_code ?? 'vi' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['login'] ?? 'Login' ?> - <?= $lang['appName'] ?? 'Inventory' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #e0e7ff, #c3dafe);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 0;
        }

        main.container {
            max-width: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .footer {
            display: none;
        }

        /* --- Kích thước đã được tăng ~20% và sử dụng :hover --- */

        .login-container {
            position: relative;
            width: 360px; /* 300px * 1.2 */
            height: 120px; /* 100px * 1.2 */
            transition: all 0.5s ease;
        }

        .login-container:hover { /* Hiệu ứng hover cho container, làm nó lớn hơn một chút */
            width: 372px !important;   /* (Giá trị cũ 310px) * 1.2 */
            height: 132px !important;  /* (Giá trị cũ 110px) * 1.2 */
        }

        .login-box { /* Box ban đầu */
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px; /* 10px * 1.2 */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
            transition: all 0.5s ease;
            z-index: 2;
            opacity: 1; /* Mặc định hiển thị */
            visibility: visible;
        }

        .login-container:hover .login-box { /* Ẩn đi khi hover vào container */
            opacity: 0;
            visibility: hidden;
            transform: scale(0.9);
        }

        .login-box h2 {
            font-size: 1.8em; /* 1.5em * 1.2 */
            color: #1e40af;
            text-transform: uppercase;
            margin-bottom: 6px; /* 5px * 1.2 */
        }

        .login-box p {
            font-size: 1.08em; /* 0.9em * 1.2 */
            color: #6b7280;
        }

        .login-form { /* Form chính, ban đầu ẩn */
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #ffffff;
            border-radius: 12px; /* 10px * 1.2 */
            padding: 18px; /* 15px * 1.2 */
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
            opacity: 0; /* Mặc định ẩn */
            visibility: hidden; /* Mặc định ẩn */
            transform: scale(0.9); /* Hiệu ứng thu nhỏ ban đầu */
            transition: all 0.5s ease;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-container:hover .login-form { /* Hiển thị form khi hover vào container */
            opacity: 1;
            visibility: visible;
            transform: scale(1); /* Phóng to ra kích thước bình thường */
        }

        .login-form .form-floating input {
            border: 1px solid #d1d5db;
            border-radius: 6px; /* 5px * 1.2 */
            font-size: 1.08em; /* 0.9em * 1.2 */
            transition: border-color 0.3s ease;
        }

        .login-form .form-floating input:focus {
            border-color: #1e40af;
            box-shadow: none;
        }

        .login-form button {
            width: 100%;
            padding: 10px; /* ~8px * 1.2 */
            background: #1e40af;
            border: none;
            border-radius: 6px; /* 5px * 1.2 */
            color: white;
            font-size: 1.08em; /* 0.9em * 1.2 */
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .login-form button:hover {
            background: #1e3a8a;
        }

        .rotating-border { /* Viền xoay ban đầu */
            position: absolute;
            top: 50%;
            left: 50%;
            width: 367px !important;   /* ~306px * 1.2 */
            height: 127px !important;  /* ~106px * 1.2 */
            transform: translate(-50%, -50%);
            border-radius: 12px; /* 10px * 1.2 */
            background: conic-gradient(#60a5fa, #a78bfa, #f472b6, #facc15, #60a5fa);
            z-index: 0;
            transition: all 0.5s ease !important;
        }
        
        .login-container:hover .rotating-border { /* Viền xoay khi hover, mở rộng cho form */
            width: 386px !important;   /* (Giá trị cũ 322px) * 1.2 */
            height: 312px !important;  /* (Giá trị cũ 260px) * 1.2 */
        }

        .rotating-border::before {
            content: '';
            position: absolute;
            top: 4px;    /* 3px * 1.2 (làm tròn) */
            left: 4px;   /* 3px * 1.2 */
            right: 4px;  /* 3px * 1.2 */
            bottom: 4px; /* 3px * 1.2 */
            background: linear-gradient(135deg, #e0e7ff, #c3dafe);
            border-radius: 8px; /* 7px * 1.2 (làm tròn) */
        }

        .rotating-border::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            border-radius: 12px; /* 10px * 1.2, đồng bộ với .rotating-border */
            background: conic-gradient(#60a5fa, #a78bfa, #f472b6, #facc15, #60a5fa);
            animation: rotateColors 4s linear infinite;
            z-index: -1;
        }
        
        @keyframes rotateColors {
            0% { background: conic-gradient(#60a5fa, #a78bfa, #f472b6, #facc15, #60a5fa); }
            25% { background: conic-gradient(#facc15, #60a5fa, #a78bfa, #f472b6, #facc15); }
            50% { background: conic-gradient(#f472b6, #facc15, #60a5fa, #a78bfa, #f472b6); }
            75% { background: conic-gradient(#a78bfa, #f472b6, #facc15, #60a5fa, #a78bfa); }
            100% { background: conic-gradient(#60a5fa, #a78bfa, #f472b6, #facc15, #60a5fa); }
        }

        .alert {
            margin-bottom: 12px; /* 10px * 1.2 */
            font-size: 0.96em; /* 0.8em * 1.2 */
        }

        .lang-switch {
            margin-top: 12px; /* 10px * 1.2 */
            text-align: center;
        }

        .lang-switch a {
            color: #1e40af;
            text-decoration: none;
            font-size: 0.96em; /* 0.8em * 1.2 */
        }

        .lang-switch a:hover {
            text-decoration: underline;
        }

        .copyright {
            margin-top: 12px; /* 10px * 1.2 */
            text-align: center;
            color: #6b7280;
            font-size: 0.96em; /* 0.8em * 1.2 */
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="login-container">
            <div class="rotating-border"></div>
            <div class="login-box">
                <h2><?= $lang['login'] ?? 'Login' ?></h2>
                <p>(<?= $lang['please_login'] ?? 'Please sign in' ?>)</p>
            </div>
            <form class="login-form" action="process/login_process.php" method="POST">
                <?php
                // Hiển thị thông báo lỗi nếu có
                if (isset($_SESSION['login_error'])) {
                    echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
                    unset($_SESSION['login_error']);
                }
                ?>
                <div class="form-floating mb-2">
                    <input type="text" class="form-control" id="username" name="username" placeholder="<?= $lang['username'] ?? 'Username' ?>" required autofocus>
                    <label for="username"><?= $lang['username'] ?? 'Username' ?></label>
                </div>
                <div class="form-floating mb-2">
                    <input type="password" class="form-control" id="password" name="password" placeholder="<?= $lang['password'] ?? 'Password' ?>" required>
                    <label for="password"><?= $lang['password'] ?? 'Password' ?></label>
                </div>
                <button class="w-100 btn" type="submit"><?= $lang['login'] ?? 'Login' ?></button>
                <div class="lang-switch">
                    <?php if ($current_lang_code == 'vi'): ?>
                        <a href="?lang=en"><?= $lang['english'] ?? 'English' ?></a>
                    <?php else: ?>
                        <a href="?lang=vi"><?= $lang['vietnamese'] ?? 'Vietnamese' ?></a>
                    <?php endif; ?>
                </div>
                <p class="copyright">© <?= date('Y') ?> <?= $lang['appName'] ?? 'Inventory App' ?></p>
            </form>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
<?php $pdo = null; // Đóng kết nối ?>