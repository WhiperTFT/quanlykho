<?php
// company_info.php
require_once __DIR__ . '/includes/init.php'; // Khởi tạo session, ngôn ngữ, DB
require_once __DIR__ . '/includes/auth_check.php'; // Đảm bảo người dùng đã đăng nhập


$page_title = $lang['company_info'];

// Lấy thông tin công ty hiện tại từ DB
$stmt = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
$company_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Nếu chưa có dòng nào thì tạo một mảng rỗng để form không bị lỗi
if (!$company_info) {
    $company_info = [
        'id' => 1,
        'name_vi' => '', 'name_en' => '', 'address_vi' => '', 'address_en' => '',
        'tax_id' => '', 'phone' => '', 'email' => '', 'website' => '', 'logo_path' => null,
        'signature_path' => null,
    ];
    // Cân nhắc: Có thể tự động INSERT một dòng rỗng vào DB ở đây nếu muốn
    // try {
    //     $pdo->exec("INSERT INTO company_info (id, name_vi) VALUES (1, 'Chưa có tên') ON DUPLICATE KEY UPDATE id=id");
    // } catch (PDOException $e) { /* handle error */ }
}


require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= $lang['manage_company_info'] ?? 'Quản lý thông tin công ty' ?></h1>
</div>

<div class="card">
    <div class="card-body">
        <form action="process/company_info_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $company_info['id'] ?? 1 ?>">

            <div class="row g-3">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="name_vi" class="form-label required"><?= $lang['company_name_vi'] ?? 'Tên công ty (VI)' ?></label>
                        <input type="text" class="form-control" id="name_vi" name="name_vi" value="<?= htmlspecialchars($company_info['name_vi'] ?? '') ?>" required>
                    </div>
                     <div class="mb-3">
                        <label for="name_en" class="form-label"><?= $lang['company_name_en'] ?? 'Tên công ty (EN)' ?></label>
                        <input type="text" class="form-control" id="name_en" name="name_en" value="<?= htmlspecialchars($company_info['name_en'] ?? '') ?>">
                    </div>
                     <div class="mb-3">
                        <label for="address_vi" class="form-label"><?= $lang['address_vi'] ?? 'Địa chỉ (VI)' ?></label>
                        <textarea class="form-control" id="address_vi" name="address_vi" rows="3"><?= htmlspecialchars($company_info['address_vi'] ?? '') ?></textarea>
                    </div>
                     <div class="mb-3">
                        <label for="address_en" class="form-label"><?= $lang['address_en'] ?? 'Địa chỉ (EN)' ?></label>
                        <textarea class="form-control" id="address_en" name="address_en" rows="3"><?= htmlspecialchars($company_info['address_en'] ?? '') ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tax_id" class="form-label"><?= $lang['tax_id'] ?? 'Mã số thuế' ?></label>
                            <input type="text" class="form-control" id="tax_id" name="tax_id" value="<?= htmlspecialchars($company_info['tax_id'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label"><?= $lang['phone'] ?? 'Điện thoại' ?></label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($company_info['phone'] ?? '') ?>">
                        </div>
                    </div>
                     <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label"><?= $lang['email'] ?? 'Email' ?></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($company_info['email'] ?? '') ?>">
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="website" class="form-label"><?= $lang['website'] ?? 'Website' ?></label>
                            <input type="url" class="form-control" id="website" name="website" placeholder="https://example.com" value="<?= htmlspecialchars($company_info['website'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="logo" class="form-label"><?= $lang['upload_logo'] ?? 'Tải lên Logo mới' ?></label>
                        <input class="form-control" type="file" id="logo" name="logo" accept="image/png, image/jpeg, image/gif">
                        <small class="form-text text-muted"><?= $lang['logo_upload_note'] ?? 'Chỉ chấp nhận file .png, .jpg, .gif. Để trống nếu không muốn thay đổi.' ?></small>
                    </div>
                    <div class="mb-3">
                        <label><?= $lang['current_logo'] ?? 'Logo hiện tại' ?></label>
                        <div class="mt-2">
                            <?php if (!empty($company_info['logo_path']) && file_exists(__DIR__ . '/' . $company_info['logo_path'])): ?>
                                <img src="<?= htmlspecialchars($company_info['logo_path']) ?>" alt="Current Logo" class="img-thumbnail" style="max-height: 100px; max-width: 100%;">
                                <div class="mt-2">
                                     <input type="checkbox" class="form-check-input" id="remove_logo" name="remove_logo" value="1">
                                     <label class="form-check-label" for="remove_logo"><?= $lang['remove_logo'] ?? 'Xóa logo hiện tại' ?></label>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">(<?= $lang['no_data'] ?? 'Chưa có logo' ?>)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="mb-3">
                        <label for="signature" class="form-label"><?= $lang['upload_signature'] ?? 'Tải lên Chữ ký mới (PNG)' ?></label>
                        <input class="form-control" type="file" id="signature" name="signature" accept="image/png">
                        <small class="form-text text-muted"><?= $lang['signature_upload_note'] ?? 'Chỉ chấp nhận file .png. Để trống nếu không muốn thay đổi.' ?></small>
                    </div>
                    <div class="mb-3">
                        <label><?= $lang['current_signature'] ?? 'Chữ ký hiện tại' ?></label>
                        <div class="mt-2">
                            <?php if (!empty($company_info['signature_path']) && file_exists(__DIR__ . '/' . $company_info['signature_path'])): ?>
                                <img src="<?= htmlspecialchars($company_info['signature_path']) ?>" alt="Current Signature" class="img-thumbnail" style="max-height: 100px; max-width: 100%; background-color: #f8f9fa;">
                                <div class="mt-2">
                                     <input type="checkbox" class="form-check-input" id="remove_signature" name="remove_signature" value="1">
                                     <label class="form-check-label" for="remove_signature"><?= $lang['remove_signature'] ?? 'Xóa chữ ký hiện tại' ?></label>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">(<?= $lang['no_data'] ?? 'Chưa có chữ ký' ?>)</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

            <hr>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= $lang['save_changes'] ?? 'Lưu thay đổi' ?></button>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>