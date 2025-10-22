<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $driver_id = intval($_POST['id']);
    $stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ?");
    $stmt->execute([$driver_id]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($driver) {
        ?>
        <div class="driver-card">
    <h3><?= htmlspecialchars($driver['ten']) ?></h3>

    <p>
        <span class="label">CCCD:</span> <?= htmlspecialchars($driver['cccd']) ?>
        <button class="btn btn-sm btn-link text-danger btn-remove-field float-end" title="Xóa dòng này"><i class="bi bi-trash"></i></button>
    </p>

    <?php if ($driver['ngay_cap']) : ?>
        <p>
            <span class="label">Ngày cấp:</span> <?= date('d/m/Y', strtotime($driver['ngay_cap'])) ?>
            <button class="btn btn-sm btn-link text-danger btn-remove-field float-end" title="Xóa dòng này"><i class="bi bi-trash"></i></button>
        </p>
    <?php endif; ?>

    <?php if ($driver['noi_cap']) : ?>
        <p>
            <span class="label">Nơi cấp:</span> <?= htmlspecialchars($driver['noi_cap']) ?>
            <button class="btn btn-sm btn-link text-danger btn-remove-field float-end" title="Xóa dòng này"><i class="bi bi-trash"></i></button>
        </p>
    <?php endif; ?>

    <?php if ($driver['sdt']) : ?>
        <p>
            <span class="label">SĐT:</span> <?= htmlspecialchars($driver['sdt']) ?>
            <button class="btn btn-sm btn-link text-danger btn-remove-field float-end" title="Xóa dòng này"><i class="bi bi-trash"></i></button>
        </p>
    <?php endif; ?>

    <?php if ($driver['bien_so_xe']) : ?>
        <p>
            <span class="label">Biển số xe:</span> <?= htmlspecialchars($driver['bien_so_xe']) ?>
            <button class="btn btn-sm btn-link text-danger btn-remove-field float-end" title="Xóa dòng này"><i class="bi bi-trash"></i></button>
        </p>
    <?php endif; ?>

    <?php if ($driver['ghi_chu']) : ?>
        <p>
            <span class="label">Ghi chú:</span> <?= nl2br(htmlspecialchars($driver['ghi_chu'])) ?>
            <button class="btn btn-sm btn-link text-danger btn-remove-field float-end" title="Xóa dòng này"><i class="bi bi-trash"></i></button>
        </p>
    <?php endif; ?>
</div>

        <?php
    } else {
        echo '<div class="text-danger">Không tìm thấy tài xế.</div>';
    }
}
?>
