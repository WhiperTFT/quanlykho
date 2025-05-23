<?php
// process/partners_process.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/init.php';
require_once __DIR__ . '/../includes/auth_check.php'; // Giả sử $lang và $pdo đã được nạp ở đây hoặc trong auth_check.php
header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$response = ['success' => false, 'message' => ($lang['error_processing_request'] ?? 'Lỗi xử lý yêu cầu.'), 'data' => null];
$user_id = $_SESSION['user_id'] ?? null;

// Gán giá trị mặc định cho các key ngôn ngữ nếu chúng chưa được định nghĩa
// Điều này giúp tránh lỗi "Undefined array key" nếu bạn chưa thêm tất cả vào file lang
$lang['required_field'] = $lang['required_field'] ?? 'Trường này là bắt buộc';
$lang['invalid_email'] = $lang['invalid_email'] ?? 'Địa chỉ email không hợp lệ.';
$lang['invalid_cc_emails'] = $lang['invalid_cc_emails'] ?? 'Một hoặc nhiều địa chỉ Email CC không hợp lệ.';
$lang['tax_id_exists'] = $lang['tax_id_exists'] ?? 'Mã số thuế đã tồn tại.';
$lang['partner_updated_success'] = $lang['partner_updated_success'] ?? 'Cập nhật đối tác thành công.';
$lang['partner_added_success'] = $lang['partner_added_success'] ?? 'Thêm đối tác thành công.';
$lang['partner_deleted_success'] = $lang['partner_deleted_success'] ?? 'Xóa đối tác thành công.';


try {
    switch ($action) {
        // --- Lấy danh sách đối tác ---
        case 'list':
            // DÒNG NÀY ĐÚNG RỒI (đã có cc_emails)
            $stmt = $pdo->query("SELECT id, name, type, tax_id, phone, email, cc_emails, contact_person FROM partners ORDER BY name ASC");
            $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'message' => 'Partners loaded.', 'data' => $partners];
            break;

        // --- Kiểm tra trùng Mã số thuế ---
        case 'check_duplicate':
            $tax_id = trim($_GET['tax_id'] ?? '');
            $partner_id = isset($_GET['partner_id']) ? (int)$_GET['partner_id'] : 0;

            if (!empty($tax_id)) {
                $sql = "SELECT id FROM partners WHERE tax_id = :tax_id";
                $params = [':tax_id' => $tax_id];

                if ($partner_id > 0) {
                    $sql .= " AND id != :partner_id";
                    $params[':partner_id'] = $partner_id;
                }
                $sql .= " LIMIT 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $exists = $stmt->fetchColumn() !== false;
                $response = ['success' => true, 'exists' => $exists];
            } else {
                $response = ['success' => true, 'exists' => false]; // Nếu tax_id rỗng thì không coi là trùng
            }
            break;

        // --- Lấy thông tin một đối tác (để sửa) ---
        case 'get_partner':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare("SELECT id, name, type, tax_id, address, phone, email, cc_emails, contact_person FROM partners WHERE id = ?");
                $stmt->execute([$id]);
                $partner = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($partner) {
                    $response = ['success' => true, 'data' => $partner];
                } else {
                    $response['message'] = 'Partner not found.';
                }
            } else {
                 $response['message'] = 'Invalid partner ID.';
            }
            break;

        // --- Thêm hoặc Sửa đối tác ---
        case 'save':
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
                $response['message'] = 'Lỗi đọc dữ liệu JSON đầu vào.';
                echo json_encode($response);
                exit;
            }

            $id = isset($input['partner_id']) ? filter_var($input['partner_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null;
            $name = trim($input['name'] ?? '');
            $type = $input['type'] ?? '';
            $tax_id = trim($input['tax_id'] ?? '');
            $address = trim($input['address'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $email = trim($input['email'] ?? ''); // Sẽ là chuỗi rỗng nếu không nhập
            $cc_emails = trim($input['cc_emails'] ?? ''); // Sẽ là chuỗi rỗng nếu không nhập
            $contact_person = trim($input['contact_person'] ?? '');

            // Validate dữ liệu cơ bản (Name, Type vẫn bắt buộc)
            if (empty($name) || empty($type) || !in_array($type, ['customer', 'supplier'])) {
                $response['message'] = ($lang['required_field'] ?? 'Trường này là bắt buộc.') . ' (Tên Đối tác, Loại Đối tác)';
                echo json_encode($response);
                exit;
            }

            // <<< SỬA ĐỔI: Validate email - KHÔNG BẮT BUỘC, nhưng nếu có nhập thì phải đúng định dạng
            // Chỉ kiểm tra định dạng nếu $email không rỗng
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = $lang['invalid_email'] ?? 'Địa chỉ email không hợp lệ.';
                echo json_encode($response);
                exit;
            }

            // Validate cc_emails: KHÔNG BẮT BUỘC, nhưng nếu có nhập thì từng email phải đúng định dạng
            if (!empty($cc_emails)) {
                $cc_email_array_raw = explode(',', $cc_emails);
                $valid_cc_emails_to_save = [];

                foreach ($cc_email_array_raw as $single_email_raw) {
                    $cc_email_item = trim($single_email_raw);
                    if (!empty($cc_email_item)) {
                        if (!filter_var($cc_email_item, FILTER_VALIDATE_EMAIL)) {
                            $response['message'] = $lang['invalid_cc_emails'] ?? 'Một hoặc nhiều địa chỉ Email CC không hợp lệ.';
                            echo json_encode($response);
                            exit;
                        }
                        $valid_cc_emails_to_save[] = $cc_email_item;
                    }
                }
                $cc_emails = implode(',', $valid_cc_emails_to_save);
            }
            // Nếu người dùng không nhập gì vào cc_emails, $cc_emails sẽ giữ nguyên giá trị rỗng của nó.

            // Kiểm tra trùng Tax ID (chỉ nếu tax_id không rỗng)
            if (!empty($tax_id)) {
                $sql_check = "SELECT id FROM partners WHERE tax_id = :tax_id";
                $params_check = [':tax_id' => $tax_id];
                if ($id) {
                    $sql_check .= " AND id != :id";
                    $params_check[':id'] = $id;
                }
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute($params_check);
                if ($stmt_check->fetch()) {
                    $response['message'] = $lang['tax_id_exists'];
                    echo json_encode($response);
                    exit;
                }
            }

            // Quyết định giá trị để lưu vào CSDL (chuỗi rỗng hoặc NULL)
            $email_to_save = !empty($email) ? $email : null;
            $cc_emails_to_save = !empty($cc_emails) ? $cc_emails : null;
            $tax_id_to_save = !empty($tax_id) ? $tax_id : null;
            $address_to_save = !empty($address) ? $address : null;
            $phone_to_save = !empty($phone) ? $phone : null;
            $contact_person_to_save = !empty($contact_person) ? $contact_person : null;

            if ($id) { // Update
                $sql = "UPDATE partners SET name = :name, type = :type, tax_id = :tax_id, address = :address, phone = :phone, email = :email, cc_emails = :cc_emails, contact_person = :contact_person WHERE id = :id";
                $params = [
                    ':name' => $name,
                    ':type' => $type,
                    ':tax_id' => $tax_id_to_save,
                    ':address' => $address_to_save,
                    ':phone' => $phone_to_save,
                    ':email' => $email_to_save,       // <<< SỬ DỤNG GIÁ TRỊ ĐÃ CHUẨN BỊ
                    ':cc_emails' => $cc_emails_to_save,
                    ':contact_person' => $contact_person_to_save,
                    ':id' => $id
                ];
                $log_action = "Updated partner ID: " . $id;
                $success_message = $lang['partner_updated_success'];
            } else { // Insert
                $sql = "INSERT INTO partners (name, type, tax_id, address, phone, email, cc_emails, contact_person) VALUES (:name, :type, :tax_id, :address, :phone, :email, :cc_emails, :contact_person)";
                $params = [
                    ':name' => $name,
                    ':type' => $type,
                    ':tax_id' => $tax_id_to_save,
                    ':address' => $address_to_save,
                    ':phone' => $phone_to_save,
                    ':email' => $email_to_save,       // <<< SỬ DỤNG GIÁ TRỊ ĐÃ CHUẨN BỊ
                    ':cc_emails' => $cc_emails_to_save,
                    ':contact_person' => $contact_person_to_save
                ];
                $log_action = "Added new partner: " . $name;
                $success_message = $lang['partner_added_success'];
            }

            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                if (function_exists('log_activity')) {
                   log_activity($user_id, $log_action, $pdo);
                }
                $response['success'] = true;
                $response['message'] = $success_message;
                if (!$id && $pdo) {
                    $response['data'] = ['new_id' => $pdo->lastInsertId()];
                }
            } else {
                $response['message'] = 'Database error during save.';
                 if (function_exists('log_activity') && $pdo) {
                    log_activity($user_id, "ERROR saving partner: " . implode(", ", $stmt->errorInfo()), $pdo);
                 }
            }
            break;

        // --- Xóa đối tác ---
        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                // Trước khi xóa, bạn có thể muốn kiểm tra ràng buộc khóa ngoại nếu không muốn lỗi SQLSTATE[23000]
                // Ví dụ: Kiểm tra xem partner này có trong bảng sales_orders không
                // $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_orders WHERE supplier_id = ?"); // Giả sử là supplier
                // $checkStmt->execute([$id]);
                // if ($checkStmt->fetchColumn() > 0) {
                //     $response['message'] = $lang['cannot_delete_partner_has_orders'] ?? 'Không thể xóa đối tác này vì có đơn hàng liên quan.';
                //     echo json_encode($response);
                //     exit;
                // }


                $stmt_name = $pdo->prepare("SELECT name FROM partners WHERE id = ?");
                $stmt_name->execute([$id]);
                $partner_name = $stmt_name->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
                if ($stmt->execute([$id])) {
                    if (function_exists('log_activity')) {
                        log_activity($user_id, "Deleted partner ID: " . $id . ($partner_name ? " (Name: $partner_name)" : ""), $pdo);
                    }
                    $response = ['success' => true, 'message' => $lang['partner_deleted_success']];
                } else {
                    // Xử lý lỗi ràng buộc khóa ngoại một cách thân thiện hơn
                    if ($stmt->errorInfo()[1] == 1451) { // Mã lỗi cho foreign key constraint violation
                         $response['message'] = $lang['cannot_delete_partner_constraint_violation'] ?? 'Không thể xóa đối tác do có dữ liệu liên quan.';
                    } else {
                        $response['message'] = 'Database error during delete.';
                    }
                    if (function_exists('log_activity') && $pdo) {
                       log_activity($user_id, "ERROR deleting partner ID: $id - " . implode(", ", $stmt->errorInfo()), $pdo);
                    }
                }
            } else {
                 $response['message'] = 'Invalid partner ID for deletion.';
            }
            break;

        default:
            $response['message'] = 'Invalid action specified.';
            break;
    }
} catch (PDOException $e) {
    if (function_exists('log_activity') && isset($pdo)) { // Kiểm tra $pdo tồn tại
       log_activity($user_id, "CRITICAL Partner Processing PDOException: " . $e->getMessage(), $pdo);
    }
    $response['message'] = "Database Error: " . $e->getMessage(); // Hiển thị lỗi cụ thể hơn cho dev, nhưng có thể cần thông báo chung cho user
    if ($e->getCode() == '23000') { // Integrity constraint violation
        $response['message'] = $lang['integrity_constraint_violation'] ?? 'Lỗi ràng buộc dữ liệu. Vui lòng kiểm tra lại thông tin.';
    }

} catch (Exception $e) {
    if (function_exists('log_activity') && isset($pdo)) { // Kiểm tra $pdo tồn tại
        log_activity($user_id, "CRITICAL Partner Processing Exception: " . $e->getMessage(), $pdo);
    }
    $response['message'] = "General Error: " . $e->getMessage();
}

echo json_encode($response);
exit();
?>