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

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><i class="bi bi-rulers me-2"></i><?= $lang['units_management'] ?? 'Units Management' ?></h1>
        <button class="btn btn-primary btn-add-unit">
            <i class="bi bi-plus-circle"></i> <?= $lang['add_unit'] ?? 'Add New Unit' ?>
        </button>
    </div>

    <?php
    // Hiển thị lại thông báo lỗi nếu có từ bước fetch dữ liệu hoặc từ session
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
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
                                    <td><?= nl2br(htmlspecialchars($unit['description'] ?? '')) // Hiển thị xuống dòng nếu có ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($unit['created_at'])) // Format ngày giờ ?></td>
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
                                <td colspan="5" class="text-center"><?= $lang['no_units_found'] ?? 'No units found.' ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
// ==== Logger tiện dụng cho trang Đơn vị tính ====

function sendUserLog(action, description = '', level = 'info') {
  try {
    return fetch('process/log_api.php?action=log', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, description, level })
    }).catch(()=>{});
  } catch(_) {}
}
// Khi DOM ready => log lần vào trang
document.addEventListener('DOMContentLoaded', () => {
  sendUserLog('units_view', 'Người dùng mở trang Quản lý Đơn vị tính', 'info');
});

// Expose cho units.js gọi được
window.sendUserLog = sendUserLog;
</script>


<?php
// Truyền biến $lang sang Javascript
echo '<script>const LANG = ' . json_encode($lang) . ';</script>';

// Include footer (đã chứa jQuery và Bootstrap JS từ file bạn cung cấp)
require_once __DIR__ . '/includes/footer.php';

// Nhúng file JS riêng cho trang units
echo '<script src="assets/js/units.js?v=' . time() . '"></script>';
?>
