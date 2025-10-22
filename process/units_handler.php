<?php
// File: process/units_handler.php (Backend xử lý AJAX cho Đơn vị tính)

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php'; // $pdo, $lang, session
require_once __DIR__ . '/../includes/auth_check.php'; // Check login
require_once __DIR__ . '/../includes/admin_check.php'; // Check admin permission

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

// --- Input validation helper (Adapt from previous handlers) ---
function validateUnitInput(array $input, array $rules): array {
    $errors = [];
    foreach ($rules as $field => $ruleSet) {
        $value = $input[$field] ?? null;
        $rulesArray = explode('|', $ruleSet);
        foreach ($rulesArray as $rule) {
             $ruleName = $rule;
             $ruleParam = null;
             if (strpos($rule, ':') !== false) { list($ruleName, $ruleParam) = explode(':', $rule, 2); }

             switch ($ruleName) {
                 case 'required':
                     if (empty($value) && $value !== '0') { $errors[$field][] = "Trường này là bắt buộc."; }
                     break;
                 case 'string':
                     if ($value !== null && !is_string($value)) { $errors[$field][] = "Giá trị phải là chuỗi."; }
                     break;
                 case 'max':
                     if (is_string($value) && mb_strlen($value) > (int)$ruleParam) { $errors[$field][] = "Không được vượt quá $ruleParam ký tự."; }
                     break;
                 case 'nullable': break; // Allows null
                 case 'integer':
                     if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) { $errors[$field][] = "Giá trị phải là số nguyên.";}
                     break;
             }
        }
    }
    return $errors;
}

// --- Helper function to check duplicate unit name ---
function checkUnitNameDuplicate(PDO $pdo, string $name, ?int $currentId = null): bool {
    $sql = "SELECT 1 FROM units WHERE LOWER(name) = LOWER(:name)";
    $params = [':name' => $name];
    if ($currentId !== null) {
        $sql .= " AND id != :current_id";
        $params[':current_id'] = $currentId;
    }
    $sql .= " LIMIT 1";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        error_log("Error checking unit name duplicate: " . $e->getMessage());
        throw new RuntimeException("Lỗi kiểm tra trùng lặp tên đơn vị.");
    }
}

// --- Helper function to check if unit is in use ---
function isUnitInUse(PDO $pdo, int $unitId): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM products WHERE unit_id = :unit_id LIMIT 1");
        $stmt->execute([':unit_id' => $unitId]);
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        error_log("Error checking if unit is in use: " . $e->getMessage());
        // Assume it's in use if check fails, safer approach
        throw new RuntimeException("Lỗi kiểm tra đơn vị đang sử dụng.");
    }
}


// --- Main Logic ---
try {
    if ($method === 'POST') {
        switch ($action) {
            case 'add':
            case 'edit':
                $inputData = [
                    'unit_id' => filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT) ?: null,
                    'name' => trim($_POST['name'] ?? ''),
                    'description' => trim($_POST['description'] ?? '') ?: null // Store null if empty
                ];

                $rules = [
                    'name' => 'required|string|max:100',
                    'description' => 'nullable|string|max:65535', // TEXT type limit
                    'unit_id' => 'nullable|integer'
                ];

                $validationErrors = validateUnitInput($inputData, $rules);
                if (!empty($validationErrors)) {
                    echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                    exit;
                }

                // Check for duplicate name (UNIQUE constraint in DB also helps)
                if (checkUnitNameDuplicate($pdo, $inputData['name'], $inputData['unit_id'])) {
                     // Add error specifically for the 'name' field
                     $validationErrors['name'][] = $lang['unit_name_exists'] ?? 'Unit name already exists.';
                     echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                     exit;
                }

                $pdo->beginTransaction();
                try {
                    if ($action === 'add') {
                        $sql = "INSERT INTO units (name, description, created_at, updated_at) VALUES (:name, :description, NOW(), NOW())";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':name' => $inputData['name'], ':description' => $inputData['description']]);
                        $newId = (int)$pdo->lastInsertId();
                        $message = $lang['unit_added_success'] ?? 'Unit added successfully.';
                         $returnData = ['id' => $newId, 'name' => $inputData['name'], 'description' => $inputData['description'], 'created_at' => date('Y-m-d H:i:s')]; // Return data for potential DOM update
                    } else { // edit
                        if (!$inputData['unit_id']) {
                            throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid unit ID for edit.');
                        }
                        $sql = "UPDATE units SET name = :name, description = :description, updated_at = NOW() WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':name' => $inputData['name'], ':description' => $inputData['description'], ':id' => $inputData['unit_id']]);
                        $message = $lang['unit_updated_success'] ?? 'Unit updated successfully.';
                         $returnData = ['id' => $inputData['unit_id'], 'name' => $inputData['name'], 'description' => $inputData['description']]; // Return updated data
                    }
                    $pdo->commit();
                    // Ghi log
                    write_user_log($pdo, (int)$_SESSION['user_id'], 'unit_edit', 'Đã cập nhật đơn vị ID=' . $inputData['unit_id'] . ', tên: ' . $inputData['name']);
                    write_user_log($pdo, (int)$_SESSION['user_id'], 'unit_add', 'Đã thêm đơn vị: ' . $inputData['name']);
                    echo json_encode(['success' => true, 'message' => $message, 'data' => $returnData]);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break; // End add/edit case

            case 'delete':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid unit ID.');
                }

                // **CRITICAL Check: Is the unit currently used by any product?**
                if (isUnitInUse($pdo, $id)) {
                    throw new RuntimeException($lang['cannot_delete_unit_in_use'] ?? 'Cannot delete this unit because it is currently used by products.');
                }

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("DELETE FROM units WHERE id = :id");
                    $stmt->execute([':id' => $id]);

                    if ($stmt->rowCount() > 0) {
                        $pdo->commit();
                        write_user_log($pdo, (int)$_SESSION['user_id'], 'unit_delete', 'Đã xóa đơn vị ID=' . $id);

                        echo json_encode(['success' => true, 'message' => $lang['unit_deleted_success'] ?? 'Unit deleted successfully.']);
                    } else {
                        throw new RuntimeException($lang['unit_not_found_or_cannot_delete'] ?? 'Unit not found or cannot be deleted.');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break; // End delete case

            default:
                throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action.');
        } // End POST switch

    } elseif ($method === 'GET') {
        switch ($action) {
            case 'get': // Get unit details for editing
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid unit ID.');
                }
                $stmt = $pdo->prepare("SELECT id, name, description FROM units WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $unit = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($unit) {
                    echo json_encode(['success' => true, 'data' => $unit]);
                } else {
                    throw new RuntimeException($lang['unit_not_found'] ?? 'Unit not found.');
                }
                break; // End get case

            case 'check_duplicate': // Check duplicate name via GET
                $name = trim($_GET['name'] ?? '');
                $currentId = filter_input(INPUT_GET, 'current_id', FILTER_VALIDATE_INT) ?: null;

                if (empty($name)) {
                     echo json_encode(['exists' => false]);
                     exit;
                }
                $exists = checkUnitNameDuplicate($pdo, $name, $currentId);
                echo json_encode(['exists' => $exists]);
                break; // End check_duplicate case

             default:
                throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action.');
        } // End GET switch
    } else {
        http_response_code(405); // Method Not Allowed
        throw new RuntimeException($lang['invalid_request_method'] ?? 'Invalid request method.');
    }

} catch (PDOException $e) {
    error_log("Database Error in units_handler.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $lang['database_error'] ?? 'A database error occurred.']);
} catch (InvalidArgumentException $e) {
     http_response_code(400); // Bad Request
     echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (RuntimeException $e) {
     // Specific logic errors (e.g., cannot delete, not found)
     http_response_code(400); // Or 409 Conflict for cannot delete? 400 is generally okay.
     error_log("Runtime Error in units_handler.php: " . $e->getMessage());
     echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Unexpected Error in units_handler.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $lang['server_error'] ?? 'An unexpected server error occurred.']);
}

exit;
?>
