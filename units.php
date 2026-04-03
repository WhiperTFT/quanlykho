<?php
// File: units.php (Trang quản lý Đơn vị tính)

$page_title = "Quản lý Đơn vị tính"; // Đặt tiêu đề trang
require_once __DIR__ . '/includes/init.php'; // Kiểm tra đăng nhập
require_once __DIR__ . '/includes/header.php'; // Include header, init, $pdo, $lang
require_login();
// --- Lấy danh sách đơn vị tính từ CSDL ---
try {
    $stmt = $pdo->query("SELECT id, name, description, created_at FROM units ORDER BY name ASC");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error fetching units: " . $e->getMessage());
    $units = []; // Khởi tạo mảng rỗng nếu có lỗi
    // Hiển thị thông báo lỗi cho người dùng
    $_SESSION['error_message'] = $lang['database_error'] ?? 'Database error fetching units.';
    // Cân nhắc có nên dừng trang ở đây không
}

?>

<div class="page-header">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-rulers me-2 text-primary"></i><?= $lang['units_management'] ?? 'Units Management' ?></h1>
        <p class="text-muted mb-0 small">Quản lý đơn vị đo lường sử dụng trong hệ thống</p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary btn-add-unit">
            <i class="bi bi-plus-circle me-1"></i> <?= $lang['add_unit'] ?? 'Add New Unit' ?>
        </button>
    </div>
</div>

<div class="content-card shadow-sm">
    <div class="content-card-header">
        <span><i class="bi bi-table me-2 text-primary"></i>Danh sách đơn vị tính</span>
    </div>
    <div class="content-card-body-flush">
        <div class="table-responsive">
            <table class="table table-hover table-custom mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col"><?= $lang['unit_name'] ?? 'Unit Name' ?></th>
                        <th scope="col"><?= $lang['description'] ?? 'Description' ?></th>
                        <th scope="col"><?= $lang['created_at'] ?? 'Created At' ?></th>
                        <th scope="col" class="text-end"><?= $lang['actions'] ?? 'Actions' ?></th>
                    </tr>
                </thead>
                <tbody id="unitsTableBody">
                    <?php if (!empty($units)): ?>
                        <?php $count = 1; ?>
                        <?php foreach ($units as $unit): ?>
                            <tr id="unit-row-<?= $unit['id'] ?>">
                                <th scope="row"><?= $count++ ?></th>
                                <td><?= htmlspecialchars($unit['name']) ?></td>
                                <td><?= nl2br(htmlspecialchars($unit['description'] ?? '')) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($unit['created_at'])) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-warning me-1 btn-edit-unit"
                                            data-id="<?= $unit['id'] ?>"
                                            title="<?= $lang['edit'] ?? 'Edit' ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-delete-unit"
                                            data-id="<?= $unit['id'] ?>"
                                            data-name="<?= htmlspecialchars($unit['name']) ?>"
                                            title="<?= $lang['delete'] ?? 'Delete' ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted"><?= $lang['no_units_found'] ?? 'No units found.' ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="unitModal" tabindex="-1" aria-labelledby="unitModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="unitModalLabel"><?= $lang['add_unit'] ?? 'Add Unit' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $lang['close'] ?? 'Close' ?>"></button>
      </div>
      <form id="unitForm" novalidate>
        <div class="modal-body">
            <input type="hidden" id="unitId" name="unit_id"> <div class="mb-3">
                <label for="unitName" class="form-label"><?= $lang['unit_name'] ?? 'Unit Name' ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="unitName" name="name" required maxlength="100">
                <div id="unitNameError" class="invalid-feedback"></div>
            </div>
            <div class="mb-3">
                <label for="unitDescription" class="form-label"><?= $lang['description'] ?? 'Description' ?></label>
                <textarea class="form-control" id="unitDescription" name="description" rows="3"></textarea>
                 <div class="invalid-feedback"></div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $lang['cancel'] ?? 'Cancel' ?></button>
          <button type="submit" class="btn btn-primary" id="saveUnitBtn"><?= $lang['save'] ?? 'Save' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
// Ghi lại lần vào trang (Dùng hàm toàn cục từ script.js)
document.addEventListener('DOMContentLoaded', () => {
  if (typeof window.sendUserLog === 'function') {
    window.sendUserLog('units_view', 'Người dùng mở trang Quản lý Đơn vị tính', 'info');
  }
});
</script>


<?php
// Truyền biến $lang sang Javascript
echo '<script>const LANG = ' . json_encode($lang) . ';</script>';

// Include footer (đã chứa jQuery và Bootstrap JS từ file bạn cung cấp)
require_once __DIR__ . '/includes/footer.php';

// Nhúng file JS riêng cho trang units
echo '<script src="assets/js/units.js?v=' . time() . '"></script>';
?>
