<?php
// File: includes/document_header.php
// Hiển thị thông tin công ty ở đầu tài liệu
// Cần biến $company_info và $lang từ file gọi nó

if (!isset($company_info)) {
    // echo "<p class='text-danger'>Company info not loaded.</p>";
    return; // Không hiển thị gì nếu thiếu thông tin
}
if (!isset($lang)) {
    $lang = []; // Khởi tạo để tránh lỗi nếu $lang chưa có
}

// Ưu tiên hiển thị tên/địa chỉ theo ngôn ngữ hiện tại
global $current_lang; // Lấy biến ngôn ngữ toàn cục từ init.php
$companyName = ($current_lang === 'vi' && !empty($company_info['name_vi'])) ? $company_info['name_vi'] : (!empty($company_info['name_en']) ? $company_info['name_en'] : ($company_info['name_vi'] ?? ''));
$companyAddress = ($current_lang === 'vi' && !empty($company_info['address_vi'])) ? $company_info['address_vi'] : (!empty($company_info['address_en']) ? $company_info['address_en'] : ($company_info['address_vi'] ?? ''));
$logo_path_from_db = $company_info['logo_path'] ?? null;
$logo_display_path = 'assets/img/placeholder_logo.png'; // Ảnh mặc định - Đảm bảo bạn có file này

// **SỬA LỖI ĐƯỜNG DẪN LOGO:**
// Kiểm tra file tồn tại dựa trên đường dẫn gốc của dự án
// Giả định $logo_path_from_db lưu đường dẫn tương đối từ gốc web (ví dụ: uploads/logos/abc.png)
if (!empty($logo_path_from_db)) {
    $absolute_path_check = __DIR__ . '/../' . $logo_path_from_db; // Đường dẫn vật lý để kiểm tra
    if (file_exists($absolute_path_check)) {
        // Sử dụng đường dẫn tương đối từ gốc web cho thẻ src
        $logo_display_path = htmlspecialchars($logo_path_from_db);
    } else {
        error_log("Company logo file not found at physical path: " . $absolute_path_check);
    }
}

?>
<div class="document-header mb-4">
    <div class="row align-items-center">
        <div class="col-md-3 text-center text-md-start mb-3 mb-md-0">
            <img src="<?= $logo_display_path ?>" alt="<?= $lang['company_logo'] ?? 'Company Logo' ?>" style="max-height: 130px; width: auto;">
        </div>
        <div class="col-md-9 text-start company-info fw-bold">
            <h4 class="fw-bold mb-1"><?= htmlspecialchars($companyName) ?></h4>
            <?php if ($companyAddress): ?>   
            <p class="mb-1 small"><i class="bi bi-geo-alt me-1"></i><?= nl2br(htmlspecialchars($companyAddress)) ?></p>
            <?php endif; ?>
            <p class="mb-0 small">
                <?php if (!empty($company_info['tax_id'])): ?>
                    <span class="me-3"><i class="bi bi-receipt me-1"></i><?= $lang['tax_id'] ?? 'Tax ID' ?>: <?= htmlspecialchars($company_info['tax_id']) ?></span>
                <?php endif; ?>
                <?php if (!empty($company_info['phone'])): ?>
                    <span class="me-3"><i class="bi bi-telephone me-1"></i><?= $lang['phone'] ?? 'Phone' ?>: <?= htmlspecialchars($company_info['phone']) ?></span>
                <?php endif; ?>
                <?php if (!empty($company_info['email'])): ?>
                    <span><i class="bi bi-envelope me-1"></i><?= $lang['email'] ?? 'Email' ?>: <?= htmlspecialchars($company_info['email']) ?></span>
                <?php endif; ?>
            </p>
            <?php if (!empty($company_info['website'])): ?>
                <p class="mb-0 small"><i class="bi bi-globe me-1"></i><?= $lang['website'] ?? 'Website' ?>: <a href="<?= htmlspecialchars($company_info['website']) ?>" target="_blank"><?= htmlspecialchars($company_info['website']) ?></a></p>
            <?php endif; ?>
        </div>
    </div>
</div>
