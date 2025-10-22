<?php
// file: menu_management.php
require_once 'includes/init.php';
require_once 'includes/admin_check.php';
require_login();

// Kiểm tra quyền đặc biệt cho trang quản lý menu
if (!has_permission('menu_management_view')) {
    header("Location: dashboard.php");
    exit();
}

$pageTitle = $lang['menu_management'] ?? 'Quản lý Menu';
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list-stars me-2"></i><?= htmlspecialchars($pageTitle) ?></h5>
            <button id="add-menu-btn" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg me-1"></i> <?= $lang['add_menu'] ?? 'Thêm Menu' ?>
            </button>
        </div>
        <div class="card-body">
            <p class="text-muted">
                <?= $lang['menu_management_instructions'] ?? 'Kéo và thả các mục để sắp xếp lại menu. Nhấn vào nút Sửa hoặc Xóa để quản lý.' ?>
            </p>

            <div id="menu-tree" class="sortable-menu">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="small text-muted mt-2">Đang tải menu...</div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <button id="save-order-btn" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> <?= $lang['save_menubar'] ?? 'Lưu lại' ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div class="modal fade" id="menu-modal" tabindex="-1" aria-labelledby="menuModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
          <h5 class="modal-title" id="menuModalLabel"><?= $lang['add_edit_menu'] ?? 'Thêm/Sửa Menu' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $lang['close'] ?? 'Đóng' ?>"></button>
      </div>
      <div class="modal-body">
        <form id="menu-form">
          <input type="hidden" id="menu-id" name="id">

          <div class="mb-3">
            <label for="menu-name" class="form-label"><?= $lang['menu_name'] ?? 'Tên Menu' ?></label>
            <input type="text" class="form-control" id="menu-name" name="name" required autocomplete="off">
            <div class="form-text">Nhập tiếng Việt. Trường tiếng Anh bên dưới sẽ tự đề xuất dịch – vẫn có thể sửa tay.</div>
          </div>

          <div class="mb-3">
            <label for="menu-name-en" class="form-label"><?= $lang['menu_name_en'] ?? 'Tên Menu (Tiếng Anh)' ?></label>
            <input type="text" class="form-control" id="menu-name-en" name="name_en" placeholder="English menu name..." autocomplete="off">
          </div>

          <div class="mb-3">
            <label for="menu-url" class="form-label"><?= $lang['menu_url'] ?? 'URL' ?></label>
            <input type="text" class="form-control" id="menu-url" name="url" placeholder="e.g., users.php hoặc # nếu là menu cha" autocomplete="off">
          </div>

          <div class="mb-3">
            <label for="menu-icon-input" class="form-label"><?= $lang['menu_icon'] ?? 'Biểu tượng' ?></label>
            <div class="input-group">
              <span class="input-group-text" id="icon-preview"><i class="bi bi-circle"></i></span>
              <input type="text" class="form-control" id="menu-icon-input" name="icon" placeholder="e.g., bi-people-fill" autocomplete="off">
            </div>
            <div class="form-text">
              <?= $lang['menu_icon_helper'] ?? 'Chọn một biểu tượng từ' ?>
              <a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener">Bootstrap Icons</a>.
              Hệ thống sẽ gợi ý icon theo tiêu đề.
            </div>
            <!-- Gợi ý icon -->
            <div id="icon-suggestion-wrap" class="mt-2" style="display:none;">
              <div class="small text-muted mb-1">Gợi ý biểu tượng:</div>
              <div id="icon-suggestions" class="d-flex flex-wrap gap-2"></div>
            </div>
          </div>

          <div class="mb-3">
            <label for="menu-permission" class="form-label"><?= $lang['permission_key'] ?? 'Khóa Quyền Hạn' ?></label>
            <input type="text" class="form-control" id="menu-permission" name="permission_key" placeholder="e.g., users_view" autocomplete="off">
            <div class="form-text"><?= $lang['permission_key_helper'] ?? 'Để trống nếu menu không yêu cầu quyền.' ?></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $lang['close'] ?? 'Đóng' ?></button>
          <button type="button" class="btn btn-primary" id="save-menu-btn"><?= $lang['save'] ?? 'Lưu' ?></button>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- jQuery UI phải load sau jQuery và trước file JS này -->
<script src="assets/js/jquery-ui.min.js"></script>
<script src="assets/js/menu_management.js"></script>
