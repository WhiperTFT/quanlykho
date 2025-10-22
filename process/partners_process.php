<?php
// process/partners_process.php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json');

$action   = $_GET['action'] ?? null;
$response = ['success' => false, 'message' => ($lang['error_processing_request'] ?? 'Lỗi xử lý yêu cầu.'), 'data' => null];
$user_id  = $_SESSION['user_id'] ?? null;

// ==== Ngôn ngữ mặc định
$lang['required_field']                = $lang['required_field']                ?? 'Trường này là bắt buộc';
$lang['invalid_email']                 = $lang['invalid_email']                 ?? 'Địa chỉ email không hợp lệ.';
$lang['invalid_cc_emails']             = $lang['invalid_cc_emails']             ?? 'Một hoặc nhiều địa chỉ Email CC không hợp lệ.';
$lang['tax_id_exists']                 = $lang['tax_id_exists']                 ?? 'Mã số thuế đã tồn tại.';
$lang['partner_updated_success']       = $lang['partner_updated_success']       ?? 'Cập nhật đối tác thành công.';
$lang['partner_added_success']         = $lang['partner_added_success']         ?? 'Thêm đối tác thành công.';
$lang['partner_deleted_success']       = $lang['partner_deleted_success']       ?? 'Xóa đối tác thành công.';
$lang['cannot_delete_partner_constraint_violation'] = $lang['cannot_delete_partner_constraint_violation'] ?? 'Không thể xóa đối tác do có dữ liệu liên quan.';

// ==== Helpers
function normalize_partner_types($input) {
    // Cho phép nhận mảng hoặc chuỗi CSV
    $allowed = ['customer','supplier','company'];
    $order   = array_flip($allowed); // để sort theo thứ tự chuẩn

    $arr = [];
    if (is_array($input)) {
        $arr = $input;
    } elseif (is_string($input)) {
        // tách theo dấu phẩy, trim
        $arr = array_map('trim', explode(',', $input));
    } else {
        $arr = [];
    }

    // lọc theo allowed, loại trùng, bỏ rỗng
    $arr = array_values(array_unique(array_filter($arr, function($v) use ($allowed) {
        return in_array($v, $allowed, true);
    })));

    // sắp theo thứ tự chuẩn: customer, supplier, company
    usort($arr, function($a, $b) use ($order) {
        return ($order[$a] ?? 999) <=> ($order[$b] ?? 999);
    });

    // trả về chuỗi CSV (đúng format MySQL SET)
    return implode(',', $arr);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function normalize_cc_emails($cc_emails_raw, &$error_msg = null) {
    $cc_emails_raw = trim((string)$cc_emails_raw);
    if ($cc_emails_raw === '') return '';

    $parts = array_map('trim', explode(',', $cc_emails_raw));
    $parts = array_values(array_filter($parts, fn($e) => $e !== ''));
    $parts = array_values(array_unique($parts));

    foreach ($parts as $em) {
        if (!validate_email($em)) {
            $error_msg = 'invalid';
            return '';
        }
    }
    return implode(',', $parts);
}

try {
    switch ($action) {
        case 'list': {
            $stmt = $pdo->query("
                SELECT id, name, type, tax_id, phone, email, cc_emails, contact_person
                FROM partners
                ORDER BY name ASC
            ");
            $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'message' => 'Partners loaded.', 'data' => $partners];
            break;
        }

        case 'check_duplicate': {
            $tax_id    = trim($_GET['tax_id'] ?? '');
            $partner_id = isset($_GET['partner_id']) ? (int)$_GET['partner_id'] : 0;

            if ($tax_id !== '') {
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
                $response = ['success' => true, 'exists' => false];
            }
            break;
        }

        case 'get_partner': {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT id, name, type, tax_id, address, phone, email, cc_emails, contact_person
                    FROM partners WHERE id = ?
                ");
                $stmt->execute([$id]);
                $partner = $stmt->fetch(PDO::FETCH_ASSOC);
                $response = $partner
                    ? ['success' => true, 'data' => $partner]
                    : ['success' => false, 'message' => 'Partner not found.'];
            } else {
                $response['message'] = 'Invalid partner ID.';
            }
            break;
        }

        case 'save': {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Lỗi đọc dữ liệu JSON đầu vào.';
        echo json_encode($response); exit;
    }

    $id             = isset($input['partner_id']) ? filter_var($input['partner_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null;
    $name           = trim($input['name'] ?? '');
    $type_raw       = $input['type'] ?? ''; // giờ chỉ 1 giá trị: 'customer' hoặc 'supplier'
    $tax_id         = trim($input['tax_id'] ?? '');
    $address        = trim($input['address'] ?? '');
    $phone          = trim($input['phone'] ?? '');
    $email          = trim($input['email'] ?? '');
    $cc_emails_raw  = $input['cc_emails'] ?? '';
    $contact_person = trim($input['contact_person'] ?? '');

    // Validate bắt buộc
    if ($name === '') {
        $response['message'] = ($lang['required_field'] ?? 'Trường này là bắt buộc') . ' (Tên Đối tác)';
        echo json_encode($response); exit;
    }

    // Ép kiểu type: chỉ cho 1 trong 2 giá trị 'customer' | 'supplier'
    $type = null;
    $allowed_one = ['customer','supplier'];
    if (is_array($type_raw)) {
        // nếu lỡ gửi mảng, lấy phần tử đầu tiên hợp lệ
        foreach ($type_raw as $t) { if (in_array($t, $allowed_one, true)) { $type = $t; break; } }
    } else {
        $type_raw = trim((string)$type_raw);
        if (in_array($type_raw, $allowed_one, true)) $type = $type_raw;
    }
    if (!$type) {
        $response['message'] = ($lang['required_field'] ?? 'Trường này là bắt buộc') . ' (Loại Đối tác)';
        echo json_encode($response); exit;
    }

    // Validate email
    if ($email !== '' && !validate_email($email)) {
        $response['message'] = $lang['invalid_email'] ?? 'Địa chỉ email không hợp lệ.';
        echo json_encode($response); exit;
    }

    // Validate CC emails
    $cc_err = null;
    $cc_emails = normalize_cc_emails($cc_emails_raw, $cc_err);
    if ($cc_err === 'invalid') {
        $response['message'] = $lang['invalid_cc_emails'] ?? 'Một hoặc nhiều email CC không hợp lệ.';
        echo json_encode($response); exit;
    }

    // === SOFT CHECK trùng MST: không chặn, chỉ trả metadata để UI muốn thì hiển thị cảnh báo ===
    $dup_info = ['duplicate' => false, 'count' => 0, 'ids' => []];
    if ($tax_id !== '') {
        $sql_check = "SELECT id FROM partners WHERE tax_id = :tax_id";
        $params_check = [':tax_id' => $tax_id];
        if ($id) {
            $sql_check .= " AND id != :id";
            $params_check[':id'] = $id;
        }
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute($params_check);
        $rows_dup = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
        if ($rows_dup && count($rows_dup) > 0) {
            $dup_info = ['duplicate' => true, 'count' => count($rows_dup), 'ids' => array_map('intval', $rows_dup)];
            // KHÔNG exit; vẫn cho lưu
        }
    }

    $params = [
        ':name'           => $name,
        ':type'           => $type, // 1 giá trị duy nhất
        ':tax_id'         => ($tax_id !== '' ? $tax_id : null),
        ':address'        => ($address !== '' ? $address : null),
        ':phone'          => ($phone !== '' ? $phone : null),
        ':email'          => ($email !== '' ? $email : null),
        ':cc_emails'      => ($cc_emails !== '' ? $cc_emails : null),
        ':contact_person' => ($contact_person !== '' ? $contact_person : null),
    ];

    if ($id) {
        $sql = "UPDATE partners
                SET name = :name,
                    type = :type,
                    tax_id = :tax_id,
                    address = :address,
                    phone = :phone,
                    email = :email,
                    cc_emails = :cc_emails,
                    contact_person = :contact_person
                WHERE id = :id";
        $params[':id'] = $id;

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            if ($user_id) {
                write_user_log($pdo, $user_id, 'save_partner', "Cập nhật đối tác: $name (ID: $id, type: $type)");
            }
            $response['success']  = true;
            $response['message']  = $lang['partner_updated_success'] ?? 'Cập nhật đối tác thành công.';
            $response['id']       = $id;
            $response['duplicate_warning'] = $dup_info;
        } else {
            $response['message'] = 'Database error during save.';
            if ($user_id) {
                write_user_log($pdo, $user_id, 'error_partner', "Lỗi khi lưu đối tác (update): " . implode(', ', $stmt->errorInfo()));
            }
        }
    } else {
        $sql = "INSERT INTO partners (name, type, tax_id, address, phone, email, cc_emails, contact_person)
                VALUES (:name, :type, :tax_id, :address, :phone, :email, :cc_emails, :contact_person)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            $new_id = (int)$pdo->lastInsertId();
            if ($user_id) {
                write_user_log($pdo, $user_id, 'save_partner', "Thêm đối tác mới: $name (ID: $new_id, type: $type)");
            }
            $response['success']  = true;
            $response['message']  = $lang['partner_added_success'] ?? 'Thêm đối tác thành công.';
            $response['data']     = ['new_id' => $new_id];
            $response['id']       = $new_id;
            $response['duplicate_warning'] = $dup_info;
        } else {
            $response['message'] = 'Database error during save.';
            if ($user_id) {
                write_user_log($pdo, $user_id, 'error_partner', "Lỗi khi lưu đối tác (insert): " . implode(', ', $stmt->errorInfo()));
            }
        }
    }
    break;
}
case 'clone_opposite_type': {
    // Nhận id từ nhiều nguồn: POST -> GET -> JSON body
    $src_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$src_id) {
        $src_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    }
    if (!$src_id) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // chấp nhận 'id' hoặc 'partner_id'
                $try = $json['id'] ?? ($json['partner_id'] ?? null);
                if ($try !== null) {
                    $src_id = filter_var($try, FILTER_VALIDATE_INT);
                }
            }
        }
    }

    if (!$src_id || $src_id <= 0) {
        $response['message'] = 'Thiếu hoặc sai ID đối tác nguồn.';
        // log để dễ chẩn đoán
        if ($user_id) {
            write_user_log($pdo, $user_id, 'clone_partner_invalid_id', 'Không nhận được id hợp lệ trong clone_opposite_type');
        }
        break;
    }

    // Lấy đối tác nguồn
    $stmt = $pdo->prepare("
        SELECT id, name, type, tax_id, address, phone, email, cc_emails, contact_person
        FROM partners
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$src_id]);
    $src = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$src) {
        $response['message'] = 'Không tìm thấy đối tác nguồn.';
        break;
    }

    $src_type = strtolower(trim($src['type'] ?? ''));
    if (!in_array($src_type, ['customer','supplier'], true)) {
        $response['message'] = 'Chỉ hỗ trợ nhân bản giữa Khách hàng và Nhà cung cấp.';
        break;
    }

    // Xác định loại đối lập
    $dst_type = ($src_type === 'customer') ? 'supplier' : 'customer';

    // Tạo dữ liệu copy
    $params = [
        ':name'           => $src['name'],
        ':type'           => $dst_type,
        ':tax_id'         => ($src['tax_id'] !== '' ? $src['tax_id'] : null),
        ':address'        => ($src['address'] !== '' ? $src['address'] : null),
        ':phone'          => ($src['phone'] !== '' ? $src['phone'] : null),
        ':email'          => ($src['email'] !== '' ? $src['email'] : null),
        ':cc_emails'      => ($src['cc_emails'] !== '' ? $src['cc_emails'] : null),
        ':contact_person' => ($src['contact_person'] !== '' ? $src['contact_person'] : null),
    ];

    $sql = "INSERT INTO partners (name, type, tax_id, address, phone, email, cc_emails, contact_person)
            VALUES (:name, :type, :tax_id, :address, :phone, :email, :cc_emails, :contact_person)";
    $ins = $pdo->prepare($sql);
    if ($ins->execute($params)) {
        $new_id = (int)$pdo->lastInsertId();

        if ($user_id) {
            write_user_log(
                $pdo,
                $user_id,
                'clone_partner',
                "Nhân bản đối tác ID $src_id ({$src['name']}) sang loại $dst_type -> ID mới $new_id"
            );
        }

        $response = [
            'success' => true,
            'message' => 'Đã nhân bản đối tác sang loại còn lại.',
            'data'    => [
                'source_id' => (int)$src_id,
                'new_id'    => $new_id,
                'new_type'  => $dst_type
            ]
        ];
    } else {
        $response['message'] = 'Lỗi khi nhân bản đối tác.';
        if ($user_id) {
            write_user_log($pdo, $user_id, 'error_partner', "Lỗi clone_opposite_type từ $src_id: " . implode(', ', $ins->errorInfo()));
        }
    }
    break;
}


        
            // process/partners_process.php
            case 'check_tax_id': {
                header('Content-Type: application/json; charset=utf-8');
                $tax = trim($_GET['tax_id'] ?? '');
                $exclude_id = (int)($_GET['exclude_id'] ?? 0);

                if ($tax === '') {
                    echo json_encode(['exists' => false, 'count' => 0, 'rows' => []]);
                    exit;
                }

                $sql = "SELECT id, name, type FROM partners WHERE tax_id = :tax";
                if ($exclude_id > 0) $sql .= " AND id <> :exclude_id";
                $st = $pdo->prepare($sql);
                $st->bindValue(':tax', $tax);
                if ($exclude_id > 0) $st->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'exists' => count($rows) > 0,
                    'count'  => count($rows),
                    'rows'   => $rows
                ]);
                exit;
            }


        case 'delete': {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt_name = $pdo->prepare("SELECT name FROM partners WHERE id = ?");
                $stmt_name->execute([$id]);
                $partner_name = $stmt_name->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM partners WHERE id = ?");
                if ($stmt->execute([$id])) {
                    if ($user_id) {
                        write_user_log($pdo, $user_id, 'delete_partner', "Xóa đối tác: $partner_name (ID: $id)");
                    }
                    $response = ['success' => true, 'message' => $lang['partner_deleted_success']];
                } else {
                    if (($stmt->errorInfo()[1] ?? null) == 1451) {
                        $response['message'] = $lang['cannot_delete_partner_constraint_violation'];
                    } else {
                        $response['message'] = 'Database error during delete.';
                    }
                    if ($user_id) {
                        write_user_log($pdo, $user_id, 'error_partner', "Lỗi xóa đối tác ID $id: " . implode(', ', $stmt->errorInfo()));
                    }
                }
            } else {
                $response['message'] = 'Invalid partner ID for deletion.';
            }
            break;
        }

        default:
            $response['message'] = 'Invalid action specified.';
            break;
    }
} catch (PDOException $e) {
    if ($user_id) {
        write_user_log($pdo, $user_id, 'critical_partner', "Lỗi PDO: " . $e->getMessage());
    }
    $response['message'] = 'Database Error: ' . $e->getMessage();
} catch (Exception $e) {
    if ($user_id) {
        write_user_log($pdo, $user_id, 'critical_partner', "Lỗi khác: " . $e->getMessage());
    }
    $response['message'] = 'General Error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
