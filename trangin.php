<?php
// File: trangin.php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
// Nếu có kiểm soát đăng nhập/role thì gọi ở đây, vd: require_login();
$page_title = 'Trung tâm in tài liệu';
?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="bi bi-printer me-2"></i>Trung tâm in tài liệu</h1>
    <div>
      <button class="btn btn-outline-secondary me-2" id="btn-refresh-printers">
        <i class="bi bi-arrow-repeat me-1"></i>Nạp máy in
      </button>
      <button class="btn btn-outline-primary" id="btn-open-queue" data-bs-toggle="modal" data-bs-target="#queueModal">
        <i class="bi bi-list-ul me-1"></i>Hàng chờ
      </button>
    </div>
  </div>

  <div class="row g-4">
    <!-- Kéo-thả / Upload -->
    <div class="col-lg-4">
      <div class="dropzone text-center p-4" id="dropzone">
        <div class="mb-3">
          <i class="bi bi-cloud-arrow-up" style="font-size: 2.2rem; color:#0d6efd"></i>
        </div>
        <h5 class="mb-2">Kéo & thả file vào đây</h5>
        <p class="text-muted mb-3">Hoặc chọn file từ thiết bị. Hỗ trợ PDF, ảnh (PNG/JPG/JPEG), Word, Excel…</p>
        <input type="file" id="fileInput" class="d-none" multiple>
        <button class="btn btn-primary" id="btn-browse"><i class="bi bi-folder2-open me-1"></i>Chọn file</button>
        <div class="small text-muted mt-2">File được lưu tạm trong phiên và sẽ bị xóa sau khi in/xóa danh sách.</div>
      </div>

      <div class="mt-4 card">
        <div class="card-header py-2">
          <strong>Tùy chọn in</strong>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Máy in</label>
            <div class="d-flex gap-2">
              <select class="form-select" id="printerSelect"></select>
              <span class="align-self-center text-muted small" id="defaultPrinterBadge"></span>
            </div>
            <div class="form-text">Chọn máy in trên máy chủ (Windows).</div>
          </div>
          <div class="row g-2">
            <div class="col-4">
              <label class="form-label">Số bản</label>
              <input type="number" id="copies" class="form-control" value="1" min="1" max="999">
            </div>
            <div class="col-8">
              <label class="form-label">Trang (vd: 1-3,5)</label>
              <input type="text" id="pageRanges" class="form-control" placeholder="Để trống = tất cả">
            </div>
          </div>
          <div class="row g-2 mt-2">
            <div class="col-6">
              <label class="form-label">Hai mặt</label>
              <select id="duplex" class="form-select">
                <option value="off">Không</option>
                <option value="long">Long-Edge</option>
                <option value="short">Short-Edge</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Xoay/Chiều</label>
              <select id="orientation" class="form-select">
                <option value="auto">Tự động</option>
                <option value="portrait">Dọc</option>
                <option value="landscape">Ngang</option>
              </select>
            </div>
          </div>
          <div class="form-text mt-2">
            Tùy chọn có thể khác nhau tùy driver máy in và loại file.
          </div>
        </div>
      </div>

      <div class="d-grid gap-2 mt-3">
        <button class="btn btn-success" id="btn-print-selected"><i class="bi bi-printer-fill me-1"></i>In các file đã chọn</button>
        <button class="btn btn-outline-danger" id="btn-clear-all"><i class="bi bi-trash me-1"></i>Xóa danh sách</button>
      </div>
    </div>

    <!-- Danh sách file & xem trước -->
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
          <div>
            <strong>Danh sách file</strong>
            <span class="badge bg-secondary ms-2" id="fileCount">0</span>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="toggleSelectAll">
            <label class="form-check-label" for="toggleSelectAll">Chọn tất cả</label>
          </div>
        </div>
        <div class="card-body file-list" id="fileList"></div>
      </div>

      <div class="card">
        <div class="card-header py-2"><strong>Xem trước</strong></div>
        <div class="card-body">
          <div class="viewer" id="viewer">
            <div class="h-100 d-flex align-items-center justify-content-center text-muted">
              Chọn 1 file để xem trước…
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Hàng chờ -->
  <div class="modal fade" id="queueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-list-ul me-2"></i>Hàng chờ in của máy in đã chọn</h5>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted small">Hiển thị trực tiếp từ hàng đợi của Windows.</div>
            <button class="btn btn-outline-secondary btn-sm" id="btn-refresh-queue"><i class="bi bi-arrow-repeat me-1"></i>Nạp lại</button>
          </div>
          <div id="queueTableWrapper">
            <div class="text-center text-muted py-4">Chưa có dữ liệu…</div>
          </div>
        </div>
        <div class="modal-footer">
          <div class="text-muted small me-auto">Bạn có thể xóa/tạm dừng job trực tiếp tại Trình quản lý thiết bị in của Windows.</div>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toasts -->
  <style>
    .dropzone { border: 2px dashed #0d6efd; border-radius: 14px; padding: 28px; background: #f8fbff; transition: .2s; }
    .dropzone.dragover { background: #e7f1ff; }
    .file-card { border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
    .file-thumb { width: 72px; height: 72px; object-fit: cover; border-radius: 10px; background: #f3f4f6; }
    .file-list { max-height: 48vh; overflow: auto; }
    .printer-badge { font-size: .85rem; }
    .toast-container { z-index: 1060; }
    .viewer { width: 100%; height: 70vh; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 12px; }
    .viewer iframe, .viewer embed { width: 100%; height: 100%; border: 0; }
    .printer-badge .badge { font-weight: 500; letter-spacing: .2px; }
  </style>
  <div class="toast-container position-fixed top-0 end-0 p-3" id="toastArea"></div>
</div>

<!-- Bạn đã có sẵn JS/CSS trong header/footer; chỉ cần nạp file JS giao diện riêng -->
<script src="assets/js/print_center.js?v=1.1.0"></script>

<?php
require_once __DIR__ . '/includes/footer.php';
