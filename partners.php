<?php
// partners.php
require_once __DIR__ . '/includes/init.php'; // Khởi tạo session, ngôn ngữ, DB
require_once __DIR__ . '/includes/auth_check.php';
$page_title = $lang['manage_partners'];
require_once __DIR__ . '/includes/header.php';
?>
<style>
    /* CSS cho dấu sao (*) ở các trường bắt buộc trong modal */
    /* Nếu bạn muốn giữ dấu * cho các trường không bắt buộc nhưng "nên có", hãy giữ lại class 'required' trên label */
    .form-label.should-have::after { /* Đổi tên class nếu bạn muốn phân biệt */
        content: " *";
        color: orange; /* Hoặc một màu khác để gợi ý, không phải màu đỏ của lỗi */
        font-weight: normal;
    }
    .form-label.required::after { /* Vẫn giữ cho các trường thực sự bắt buộc */
        content: " *";
        color: red;
        font-weight: normal;
    }
    
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= $lang['manage_partners'] ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#partnerModal" id="addPartnerBtn">
            <i class="bi bi-plus-lg"></i> <?= $lang['add_partner'] ?>
        </button>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <input type="text" id="partner-search" class="form-control" placeholder="<?= $lang['filter_partners'] ?>">
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover" id="partner-table">
        <thead class="table-dark"> <?php /* Hoặc table-light, hoặc không có class nào để dùng kiểu mặc định */ ?>
            <tr>
                <th>#</th>
                <th><?= $lang['partner_name'] ?></th>
                <th><?= $lang['partner_type'] ?></th>
                <th><?= $lang['tax_id'] ?></th>
                <th><?= $lang['phone'] ?></th>
                <th><?= $lang['email'] ?></th>
                <th><?= $lang['email_cc'] ?></th> <?php /* ← CỘT MỚI ĐƯỢC THÊM */ ?>
                <th><?= $lang['contact_person'] ?></th>
                <th><?= $lang['action'] ?></th>
            </tr>
        </thead>
        <tbody>
            <?php /* Dòng hiển thị "Loading..." và "Không có dữ liệu" sẽ được JavaScript quản lý */ ?>
            <tr>
                <td colspan="9" class="text-center" id="loading-row">Đang tải...</td> <?php /* Cập nhật colspan thành 9 */ ?>
            </tr>
        </tbody>
    </table>
</div>

<div class="modal fade" id="partnerModal" tabindex="-1" aria-labelledby="partnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="partnerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="partnerModalLabel"><?= htmlspecialchars($lang['add_partner'] ?? 'Thêm Đối tác') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="partner_id" name="partner_id">
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <?php // Ví dụ: Tên đối tác và loại đối tác vẫn bắt buộc ?>
                            <label for="partner_name" class="form-label required"><?= htmlspecialchars($lang['partner_name'] ?? 'Tên Đối tác') ?></label>
                            <input type="text" class="form-control" id="partner_name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="partner_type" class="form-label required"><?= htmlspecialchars($lang['partner_type'] ?? 'Loại') ?></label>
                            <select class="form-select" id="partner_type" name="type" required>
                                <option value="" selected disabled><?= htmlspecialchars($lang['select_type'] ?? 'Chọn loại') ?></option>
                                <option value="customer"><?= htmlspecialchars($lang['customer'] ?? 'Khách hàng') ?></option>
                                <option value="supplier"><?= htmlspecialchars($lang['supplier'] ?? 'Nhà cung cấp') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="partner_tax_id" class="form-label"><?= htmlspecialchars($lang['tax_id'] ?? 'Mã số thuế') ?></label>
                        <input type="text" class="form-control" id="partner_tax_id" name="tax_id">
                        <small id="tax_id_warning" class="text-danger d-none"><?= htmlspecialchars($lang['tax_id_exists'] ?? 'Mã số thuế đã tồn tại.') ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="partner_address" class="form-label"><?= htmlspecialchars($lang['address'] ?? 'Địa chỉ') ?></label>
                        <textarea class="form-control" id="partner_address" name="address" rows="2"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label for="partner_phone" class="form-label"><?= htmlspecialchars($lang['phone'] ?? 'Điện thoại') ?></label>
                            <input type="tel" class="form-control" id="partner_phone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php // Email không còn bắt buộc ?>
                            <label for="partner_email" class="form-label"><?= htmlspecialchars($lang['email'] ?? 'Email') ?> (<?= htmlspecialchars($lang['recipient_to'] ?? 'Gửi chính') ?>)</label>
                            <input type="email" class="form-control" id="partner_email" name="email"> <?php /* XÓA thuộc tính 'required' */ ?>
                            <small id="email_error" class="text-danger d-none"><?= htmlspecialchars($lang['invalid_email'] ?? 'Email không hợp lệ.') ?></small>
                            <small class="form-text text-muted"><?= htmlspecialchars($lang['emails_instruction'] ?? 'Phân tách nhiều email bằng dấu phẩy.') ?></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <?php // Email CC không còn bắt buộc ?>
                        <label for="partner_cc_emails" class="form-label"><?= htmlspecialchars($lang['email_cc'] ?? 'Email CC') ?> (<?= htmlspecialchars($lang['recipient_cc'] ?? 'Người nhận CC') ?>)</label>
                        <input type="text" class="form-control" id="partner_cc_emails" name="cc_emails" placeholder="<?= htmlspecialchars($lang['enter_cc_emails_placeholder'] ?? 'VD: cc1@example.com, cc2@example.com') ?>"> <?php /* XÓA thuộc tính 'required' */ ?>
                        <small id="cc_emails_error" class="text-danger d-none"><?= htmlspecialchars($lang['invalid_cc_emails'] ?? 'Một hoặc nhiều email CC không hợp lệ.') ?></small>
                        <small class="form-text text-muted"><?= htmlspecialchars($lang['cc_emails_instruction'] ?? 'Các email này sẽ được CC trong các trao đổi.') ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="partner_contact_person" class="form-label"><?= htmlspecialchars($lang['contact_person'] ?? 'Người liên hệ') ?></label>
                        <input type="text" class="form-control" id="partner_contact_person" name="contact_person">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($lang['cancel'] ?? 'Hủy') ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= htmlspecialchars($lang['save'] ?? 'Lưu') ?></button>
                </div>
            </form>
            <div id="modal-error-message" class="alert alert-danger mt-3 d-none" role="alert"></div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
<script src="assets/js/partners.js"></script>
