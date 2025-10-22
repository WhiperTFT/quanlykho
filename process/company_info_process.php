<?php
// process/company_info_process.php
require_once __DIR__ . '/../includes/admin_check.php'; // Đảm bảo admin mới có quyền
require_once __DIR__ . '/../includes/logging.php'; // Thêm dòng này để dùng write_user_log()
// admin_check.php đã include auth_check.php và init.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);

    // Lấy các giá trị từ form
    $name_vi = trim($_POST['name_vi'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $address_vi = trim($_POST['address_vi'] ?? '');
    $address_en = trim($_POST['address_en'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');

    $remove_logo = isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1';
    $remove_signature = isset($_POST['remove_signature']) && $_POST['remove_signature'] == '1';

    $upload_dir = __DIR__ . '/../uploads/company/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }

    // Lấy thông tin công ty hiện tại
    $stmt_current = $pdo->prepare("SELECT logo_path, signature_path FROM company_info WHERE id = ?");
    $stmt_current->execute([$id]);
    $current_paths = $stmt_current->fetch(PDO::FETCH_ASSOC);
    $current_logo_path = $current_paths['logo_path'] ?? null;
    $current_signature_path = $current_paths['signature_path'] ?? null;

    $logo_path_to_save = $current_logo_path;
    $signature_path_to_save = $current_signature_path;

    // Xử lý upload logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_file = $_FILES['logo'];
        $logo_extension = strtolower(pathinfo($logo_file['name'], PATHINFO_EXTENSION));
        $allowed_logo_extensions = ['png', 'jpg', 'jpeg', 'gif'];

        if (in_array($logo_extension, $allowed_logo_extensions)) {
            if ($current_logo_path && file_exists(__DIR__ . '/../' . $current_logo_path)) {
                unlink(__DIR__ . '/../' . $current_logo_path);
            }
            $new_logo_filename = 'logo_' . time() . '.' . $logo_extension;
            $logo_destination = $upload_dir . $new_logo_filename;

            if (move_uploaded_file($logo_file['tmp_name'], $logo_destination)) {
                $logo_path_to_save = 'uploads/company/' . $new_logo_filename;
            } else {
                $_SESSION['error_message'] = $lang['logo_upload_failed'] ?? 'Tải lên logo thất bại.';
                header('Location: ../company_info.php');
                exit;
            }
        } else {
            $_SESSION['error_message'] = $lang['invalid_logo_type'] ?? 'Định dạng file logo không hợp lệ.';
            header('Location: ../company_info.php');
            exit;
        }
    } elseif ($remove_logo) {
        if ($current_logo_path && file_exists(__DIR__ . '/../' . $current_logo_path)) {
            unlink(__DIR__ . '/../' . $current_logo_path);
        }
        $logo_path_to_save = null;
    }

    // Xử lý upload chữ ký
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        $signature_file = $_FILES['signature'];
        $signature_extension = strtolower(pathinfo($signature_file['name'], PATHINFO_EXTENSION));
        if ($signature_extension === 'png') {
            if ($current_signature_path && file_exists(__DIR__ . '/../' . $current_signature_path)) {
                unlink(__DIR__ . '/../' . $current_signature_path);
            }
            $new_signature_filename = 'signature_' . time() . '.png';
            $signature_destination = $upload_dir . $new_signature_filename;

            if (move_uploaded_file($signature_file['tmp_name'], $signature_destination)) {
                $signature_path_to_save = 'uploads/company/' . $new_signature_filename;
            } else {
                $_SESSION['error_message'] = $lang['signature_upload_failed'] ?? 'Tải lên chữ ký thất bại.';
                header('Location: ../company_info.php');
                exit;
            }
        } else {
            $_SESSION['error_message'] = $lang['invalid_signature_type'] ?? 'Định dạng file chữ ký không hợp lệ (chỉ chấp nhận .png).';
            header('Location: ../company_info.php');
            exit;
        }
    } elseif ($remove_signature) {
        if ($current_signature_path && file_exists(__DIR__ . '/../' . $current_signature_path)) {
            unlink(__DIR__ . '/../' . $current_signature_path);
        }
        $signature_path_to_save = null;
    }

    // Cập nhật CSDL
    try {
        $sql = "INSERT INTO company_info (id, name_vi, name_en, address_vi, address_en, tax_id, phone, email, website, logo_path, signature_path) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                name_vi = VALUES(name_vi), name_en = VALUES(name_en), address_vi = VALUES(address_vi), address_en = VALUES(address_en), 
                tax_id = VALUES(tax_id), phone = VALUES(phone), email = VALUES(email), website = VALUES(website), 
                logo_path = VALUES(logo_path), signature_path = VALUES(signature_path)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $id, $name_vi, $name_en, $address_vi, $address_en, 
            $tax_id, $phone, $email, $website, 
            $logo_path_to_save, $signature_path_to_save
        ]);

        $_SESSION['success_message'] = $lang['company_info_updated'] ?? 'Thông tin công ty đã được cập nhật thành công.';

        // ✅ Ghi log
        write_user_log($pdo, $_SESSION['user_id'] ?? 0, 'update', 'company_info', $id, 'Cập nhật thông tin công ty');

    } catch (PDOException $e) {
        error_log("Error updating company info: " . $e->getMessage());
        $_SESSION['error_message'] = $lang['db_error_updating_company_info'] ?? 'Lỗi cơ sở dữ liệu khi cập nhật thông tin công ty.';
    }

    header('Location: ../company_info.php');
    exit;

} else {
    header('Location: ../company_info.php');
    exit;
}
