<?php
// === File: contact.php ===
require_once 'includes/init.php';
require_once 'includes/header.php';

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $message) {
        $pdo = db_connect();
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $message]);
        $success = true;
    }
}
?>

<div class="container my-5">
    <h2>Liên hệ / Đặt hàng</h2>
    <?php if ($success): ?>
        <div class="alert alert-success">Cảm ơn bạn! Chúng tôi sẽ liên hệ lại sớm nhất.</div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Họ tên</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nội dung</label>
            <textarea name="message" class="form-control" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Gửi liên hệ</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
