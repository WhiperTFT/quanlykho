<?php
// company_info.php
require_once __DIR__ . '/includes/admin_check.php';
$page_title = $lang['company_info'];

// Lấy thông tin công ty hiện tại từ DB
$stmt = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
$company_info_data = $stmt->fetch(PDO::FETCH_ASSOC); // Đổi tên biến để tránh trùng lặp với $lang['company_info']

// Nếu chưa có dòng nào thì tạo một mảng rỗng để form không bị lỗi
if (!$company_info_data) {
    $company_info_data = [
        'id' => 1,
        'name_vi' => '', 'name_en' => '', 'address_vi' => '', 'address_en' => '',
        'tax_id' => '', 'phone' => '', 'email' => '', 'website' => '', 'logo_path' => null,
        'signature_path' => null,
    ];
}

// ---- LOGIC CHỌN NGÔN NGỮ CHO TÊN VÀ ĐỊA CHỈ ----
$display_name = $company_info_data['name_vi']; // Mặc định là Tiếng Việt
$display_address = $company_info_data['address_vi']; // Mặc định là Tiếng Việt

// $current_lang_code đã được định nghĩa trong init.php và có sẵn ở đây
if (isset($current_lang_code) && $current_lang_code == 'en') {
    if (!empty($company_info_data['name_en'])) {
        $display_name = $company_info_data['name_en'];
    }
    // Nếu name_en rỗng, $display_name vẫn giữ giá trị name_vi (đã gán ở trên)

    if (!empty($company_info_data['address_en'])) {
        $display_address = $company_info_data['address_en'];
    }
    // Nếu address_en rỗng, $display_address vẫn giữ giá trị address_vi
}
// ---- KẾT THÚC LOGIC CHỌN NGÔN NGỮ ----


require_once __DIR__ . '/includes/header.php'; // $page_title và các biến $display_... đã sẵn sàng
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <h1><i class="bi bi-building me-2"></i><?= $page_title ?></h1>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <form action="process/company_info_process.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= htmlspecialchars($company_info_data['id']) ?>">

                <div class="row">
                    <div class="col-md-6">
                        <fieldset class="mb-3 p-3 border rounded">
                            <legend class="w-auto px-2 h6"><?= $lang['company_name'] ?? 'Company Name' ?></legend>
                            <div class="mb-3">
                                <label for="name_display" class="form-label"><?= $lang['company_name_by_selected_language'] ?? 'Company Name (by selected language)' ?></label>
                                <input type="text" class="form-control" id="name_display" value="<?= htmlspecialchars($display_name) ?>" readonly disabled>
                            </div>
                            <div class="mb-3">
                                <label for="name_vi" class="form-label"><?= $lang['company_name_vi'] ?? 'Company Name (VI)' ?> (*)</label>
                                <input type="text" class="form-control" id="name_vi" name="name_vi" value="<?= htmlspecialchars($company_info_data['name_vi'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="name_en" class="form-label"><?= $lang['company_name_en'] ?? 'Company Name (EN)' ?></label>
                                <input type="text" class="form-control" id="name_en" name="name_en" value="<?= htmlspecialchars($company_info_data['name_en'] ?? '') ?>">
                            </div>
                        </fieldset>

                        <fieldset class="mb-3 p-3 border rounded">
                            <legend class="w-auto px-2 h6"><?= $lang['address'] ?? 'Address' ?></legend>
                            <div class="mb-3">
                                <label for="address_display" class="form-label"><?= $lang['display_address_by_language'] ?? 'Localized Display Address' ?></label>
                                <textarea class="form-control" id="address_display" rows="2" readonly disabled><?= htmlspecialchars($display_address) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="address_vi" class="form-label"><?= $lang['address_vi'] ?? 'Address (VI)' ?></label>
                                <textarea class="form-control" id="address_vi" name="address_vi" rows="2"><?= htmlspecialchars($company_info_data['address_vi'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="address_en" class="form-label"><?= $lang['address_en'] ?? 'Address (EN)' ?></label>
                                <textarea class="form-control" id="address_en" name="address_en" rows="2"><?= htmlspecialchars($company_info_data['address_en'] ?? '') ?></textarea>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-md-6">
                        <fieldset class="mb-3 p-3 border rounded">
                            <legend class="w-auto px-2 h6"><?= $lang['contact_and_legal_info'] ?? 'Contact & Legal Information' ?></legend>
                            <div class="mb-3">
                                <label for="tax_id" class="form-label"><?= $lang['tax_id'] ?? 'Tax ID' ?></label>
                                <input type="text" class="form-control" id="tax_id" name="tax_id" value="<?= htmlspecialchars($company_info_data['tax_id'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label"><?= $lang['phone'] ?? 'Phone' ?></label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($company_info_data['phone'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label"><?= $lang['email'] ?? 'Email' ?></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($company_info_data['email'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="website" class="form-label"><?= $lang['website'] ?? 'Website' ?></label>
                                <input type="url" class="form-control" id="website" name="website" value="<?= htmlspecialchars($company_info_data['website'] ?? '') ?>">
                            </div>
                        </fieldset>

                        <fieldset class="mb-3 p-3 border rounded">
                            <legend class="w-auto px-2 h6"><?= $lang['picture'] ?? 'Picture' ?></legend>
                            <div class="mb-3">
                                <label for="logo" class="form-label"><?= $lang['logo'] ?? 'Logo' ?> (.png, .jpg, .gif)</label>
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/png, image/jpeg, image/gif">
                                <?php if (!empty($company_info_data['logo_path']) && file_exists(__DIR__ . '/' . $company_info_data['logo_path'])): ?>
                                    <div class="mt-2">
                                        <label><?= $lang['current_logo'] ?? 'Current Logo' ?></label><br>
                                        <img src="<?= htmlspecialchars(PROJECT_BASE_URL . $company_info_data['logo_path']) ?>" alt="Current Logo" class="img-thumbnail mb-2" style="max-height: 100px; background-color: #f8f9fa;">
                                        <div>
                                            <input type="checkbox" class="form-check-input" id="remove_logo" name="remove_logo" value="1">
                                            <label class="form-check-label" for="remove_logo"><?= $lang['remove_logo'] ?? 'Remove current logo' ?></label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="signature" class="form-label"><?= $lang['signature'] ?? 'Signature' ?> (.png)</label>
                                <input type="file" class="form-control" id="signature" name="signature" accept="image/png">
                                <?php if (!empty($company_info_data['signature_path']) && file_exists(__DIR__ . '/' . $company_info_data['signature_path'])): ?>
                                    <div class="mt-2">
                                        <label><?= $lang['current_signature'] ?? 'Chữ kỹ hiện tại' ?></label><br>
                                        <img src="<?= htmlspecialchars(PROJECT_BASE_URL . $company_info_data['signature_path']) ?>" alt="Current Signature" class="img-thumbnail mb-2" style="max-height: 70px; background-color: #f8f9fa;">
                                        <div>
                                            <input type="checkbox" class="form-check-input" id="remove_signature" name="remove_signature" value="1">
                                            <label class="form-check-label" for="remove_signature"><?= $lang['remove_signature'] ?? 'Xóa chữ ký hiện tại' ?></label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </fieldset>
                    </div>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= $lang['save_changes'] ?? 'Save Changes' ?></button>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>