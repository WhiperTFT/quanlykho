<?php
// File: includes/document_header.php
// Hiển thị thông tin công ty ở đầu tài liệu

// GIẢ ĐỊNH:
// 1. init.php đã được include TRƯỚC KHI file này được gọi.
//    Do đó, $pdo, $current_lang_code, $lang, PROJECT_BASE_URL đã có sẵn.
// 2. Biến $company_info (chứa dữ liệu từ bảng company_info, id=1) đã được truy vấn và
//    truyền vào hoặc có sẵn trong phạm vi toàn cục trước khi file này được gọi.

// Kiểm tra các biến cần thiết
if (!isset($pdo)) {
    error_log("document_header.php: PDO connection (\$pdo) is not available. Ensure init.php ran correctly.");
    echo "<p class='text-danger p-3'>Lỗi hệ thống: Không thể tải thông tin công ty (DB).</p>";
    return;
}
if (!isset($current_lang_code)) {
    error_log("document_header.php: Current language code (\$current_lang_code) is not available. Ensure init.php ran correctly.");
    // Bạn có thể đặt một giá trị mặc định nếu muốn, ví dụ 'vi', nhưng tốt hơn là init.php phải đảm bảo nó có.
    // $current_lang_code = 'vi';
    echo "<p class='text-danger p-3'>Lỗi hệ thống: Không thể xác định ngôn ngữ.</p>";
    return;
}
if (!isset($lang) || !is_array($lang)) {
    $lang = []; // Khởi tạo để tránh lỗi nếu $lang chưa có, nhưng đây là dấu hiệu init.php có vấn đề.
    error_log("document_header.php: Language array (\$lang) is not available or not an array. Ensure init.php ran correctly.");
}

// Nếu $company_info chưa được cung cấp bởi file gọi, thì thử lấy ở đây.
// Tuy nhiên, tốt nhất là file gọi nên chuẩn bị sẵn $company_info.
if (!isset($company_info) || !is_array($company_info)) {
    try {
        $stmt_doc_header_company = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
        if ($stmt_doc_header_company) {
            $company_info = $stmt_doc_header_company->fetch(PDO::FETCH_ASSOC);
        }
        if (!$company_info) {
            $company_info = []; // Đặt là mảng rỗng nếu không tìm thấy
            error_log("document_header.php: Company info (id=1) not found in database.");
        }
    } catch (PDOException $e) {
        error_log("document_header.php: PDOException while fetching company_info: " . $e->getMessage());
        $company_info = []; // Đặt là mảng rỗng khi có lỗi
    }
}

// Mặc định tên và địa chỉ (sử dụng key từ mảng $lang cho dễ dịch nếu cần)
$defaultCompanyName = $lang['default_company_name'] ?? 'Tên Công Ty'; // Thêm 'default_company_name' vào file lang nếu muốn
$defaultCompanyAddress = $lang['default_company_address'] ?? 'Địa Chỉ Công Ty'; // Thêm 'default_company_address' vào file lang

// Xác định tên công ty sẽ hiển thị
$companyNameToDisplay = $company_info['name_vi'] ?? $defaultCompanyName; // Mặc định là name_vi hoặc tên mặc định chung
if ($current_lang_code === 'en' && !empty($company_info['name_en'])) {
    $companyNameToDisplay = $company_info['name_en'];
}

// Xác định địa chỉ công ty sẽ hiển thị
$companyAddressToDisplay = $company_info['address_vi'] ?? $defaultCompanyAddress; // Mặc định là address_vi hoặc địa chỉ mặc định chung
if ($current_lang_code === 'en' && !empty($company_info['address_en'])) {
    $companyAddressToDisplay = $company_info['address_en'];
}

// Xử lý đường dẫn Logo
$logo_db_path = $company_info['logo_path'] ?? null;
// Cần một ảnh placeholder trong assets nếu không có logo hoặc logo bị lỗi
$logo_display_url = PROJECT_BASE_URL . 'assets/images/default_logo.png'; // Đảm bảo bạn có file này

if (!empty($logo_db_path)) {
    // Giả định $logo_db_path là đường dẫn tương đối từ thư mục gốc dự án, ví dụ: "uploads/logos/ten_logo.png"
    // Đường dẫn đầy đủ trên server để kiểm tra file tồn tại
    $logo_server_path = $_SERVER['DOCUMENT_ROOT'] . rtrim(PROJECT_BASE_URL, '/') . '/' . ltrim($logo_db_path, '/');

    if (file_exists($logo_server_path)) {
        // Đường dẫn URL để hiển thị trong thẻ <img>
        $logo_display_url = PROJECT_BASE_URL . ltrim($logo_db_path, '/');
    } else {
        error_log("document_header.php: Company logo file not found at server path: " . $logo_server_path . " (DB logo_path: " . $logo_db_path . ")");
        // Giữ nguyên $logo_display_url mặc định
    }
} else {
    // Ghi log nếu không có logo_path trong DB
    error_log("document_header.php: logo_path is empty in company_info table.");
    // Giữ nguyên $logo_display_url mặc định
}

?>
<div class="document-header mb-4">
    <div class="row align-items-center">
        <div class="col-md-3 text-center text-md-start mb-3 mb-md-0">
            <?php if($logo_display_url): // Chỉ hiển thị img nếu có đường dẫn logo ?>
            <img src="<?= htmlspecialchars($logo_display_url) ?>" alt="<?= $lang['logo'] ?? 'Company Logo' ?>" style="max-height: 90px; width: auto;">
            <?php endif; ?>
        </div>
        <div class="col-md-9 text-start company-info">
            <h4 class="fw-bold mb-1" style="font-size: 1.3rem; color: #333;"><?= htmlspecialchars($companyNameToDisplay) ?></h4>
            <?php if ($companyAddressToDisplay && $companyAddressToDisplay !== $defaultCompanyAddress): // Chỉ hiển thị nếu có địa chỉ thực sự ?>  
            <p class="mb-1" style="font-size: 0.85rem;"><i class="bi bi-geo-alt-fill me-1"></i><?= nl2br(htmlspecialchars($companyAddressToDisplay)) ?></p>
            <?php endif; ?>
            <p class="mb-0" style="font-size: 0.85rem;">
                <?php if (!empty($company_info['tax_id'])): ?>
                    <span class="me-3"><i class="bi bi-file-earmark-text-fill me-1"></i><?= $lang['tax_id'] ?? 'Tax ID' ?>: <?= htmlspecialchars($company_info['tax_id']) ?></span>
                <?php endif; ?>
                <?php if (!empty($company_info['phone'])): ?>
                    <span class="me-3"><i class="bi bi-telephone-fill me-1"></i><?= $lang['phone'] ?? 'Phone' ?>: <?= htmlspecialchars($company_info['phone']) ?></span>
                <?php endif; ?>
                <?php if (!empty($company_info['email'])): ?>
                    <span><i class="bi bi-envelope-fill me-1"></i><?= $lang['email'] ?? 'Email' ?>: <?= htmlspecialchars($company_info['email']) ?></span>
                <?php endif; ?>
            </p>
            <?php if (!empty($company_info['website'])): ?>
                <p class="mb-0" style="font-size: 0.85rem;"><i class="bi bi-globe me-1"></i><?= $lang['website'] ?? 'Website' ?>: <a href="<?= htmlspecialchars($company_info['website']) ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars($company_info['website']) ?></a></p>
            <?php endif; ?>
        </div>
    </div>
</div>