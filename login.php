<?php
// login.php
$page_title = "Login";
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/lunar.php';

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
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            background: linear-gradient(135deg, #e0e7ff, #c3dafe);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        main.container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>
<?php if (isset($_GET['message']) && $_GET['message'] === 'login_required'): ?>
    <div class="alert alert-warning login-alert">
        Vui lòng đăng nhập để tiếp tục
    </div>
    <script>
        setTimeout(() => {
            const alert = document.querySelector('.login-alert');
            if (alert) {
                alert.style.transition = "opacity 0.5s ease";
                alert.style.opacity = 0;
                setTimeout(() => alert.remove(), 500);
            }
        }, 4000);
    </script>
<?php endif; ?>
<main class="container">
    <div class="clock-container">
        <div class="clock glow" id="clock">00:00:00</div>
        <div class="date" id="solarDate"></div>
        <div class="lunar-date" id="lunarDate"></div>
    </div>
    <div class="login-container">
        <div class="rotating-border"></div>
        <div class="login-box">
            <h2><?= $lang['login'] ?? 'Login' ?></h2>
            <p>(<?= $lang['please_login'] ?? 'Please sign in' ?>)</p>
        </div>
        <form class="login-form" action="process/login_process.php" method="POST">
            <?php
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

<?php

$today = getdate();
$lunarDate = LunarConverter::toString($today['mday'], $today['mon'], $today['year']);?>
<script>
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds}`;

        const lang = '<?= $current_lang_code ?? 'vi' ?>';
        const daysVi = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
        const daysEn = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const day = lang === 'vi' ? daysVi[now.getDay()] : daysEn[now.getDay()];
        const date = String(now.getDate()).padStart(2, '0');
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const year = now.getFullYear();
        const dateFormat = lang === 'vi' ? `${day}, ${date} Tháng ${month}, ${year}` : `${day}, ${month}/${date}/${year}`;
        document.getElementById('solarDate').textContent = dateFormat;
        document.getElementById('lunarDate').textContent = '<?= $lunarDate; ?>';
    }

    setInterval(updateClock, 1000);
    updateClock();
</script>
<script>
    const container = document.querySelector('.login-container');
    let delayTimeout, resetTimeout;

    container.addEventListener('mouseenter', () => {
        clearTimeout(delayTimeout);
        clearTimeout(resetTimeout);
        container.classList.add('delayed-hover');
        container.classList.remove('resetting');
    });

    container.addEventListener('mouseleave', () => {
        delayTimeout = setTimeout(() => {
            container.classList.remove('delayed-hover');
            container.classList.add('resetting');

            resetTimeout = setTimeout(() => {
                container.classList.remove('resetting');
            }, 2000);
        }, 2000);
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $pdo = null; ?>
