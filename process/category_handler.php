<?php
// File: process/category_handler.php

// --- Strict types for better code quality ---
declare(strict_types=1);

// --- Include necessary files ---
// Use absolute path based on the current file's directory
require_once __DIR__ . '/../includes/init.php'; // Provides $pdo, $lang, session_start() etc.

// --- Set response header to JSON ---
header('Content-Type: application/json');

// --- Get action and request method ---
$action = $_REQUEST['action'] ?? null; // Use $_REQUEST to handle both GET and POST for action parameter
$method = $_SERVER['REQUEST_METHOD'];

// --- Input validation helper ---
function validateInput(array $input, array $rules): array {
    $errors = [];
    foreach ($rules as $field => $ruleSet) {
        $value = $input[$field] ?? null;
        $rulesArray = explode('|', $ruleSet);

        foreach ($rulesArray as $rule) {
            $ruleName = $rule;
            $ruleParam = null;
            if (strpos($rule, ':') !== false) {
                list($ruleName, $ruleParam) = explode(':', $rule, 2);
            }

            switch ($ruleName) {
                case 'required':
                    if (empty($value) && $value !== '0') { // Allow '0' as a valid value
                        $errors[$field][] = "Trường này là bắt buộc."; // Use $lang['field_required'] if available
                    }
                    break;
                case 'string':
                    if (!is_string($value)) {
                         $errors[$field][] = "Giá trị phải là chuỗi.";
                    }
                    break;
                case 'integer':
                    if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== null && $value !== '') { // Allow null/empty for optional integers
                        $errors[$field][] = "Giá trị phải là số nguyên.";
                    }
                    break;
                 case 'nullable':
                    // This rule doesn't add errors, just allows null
                    break;
                 case 'max': // Example: max:255
                    if (is_string($value) && mb_strlen($value) > (int)$ruleParam) {
                        $errors[$field][] = "Giá trị không được vượt quá $ruleParam ký tự.";
                    }
                    break;
                // Add more rules as needed (min, email, etc.)
            }
        }
    }
    return $errors;
}


// --- Helper function to check duplicates ---
/**
 * Checks if a category name already exists at the same parent level.
 *
 * @param PDO $pdo PDO database connection object.
 * @param string $name The category name to check.
 * @param int|null $parentId The ID of the parent category (null for top-level).
 * @param int|null $currentId The ID of the category being edited (to exclude itself).
 * @return bool True if a duplicate exists, false otherwise.
 */
function checkCategoryDuplicate(PDO $pdo, string $name, ?int $parentId, ?int $currentId = null): bool {
    // Normalize empty string parentId from form to null for DB query
    if ($parentId === '') $parentId = null;

    $sql = "SELECT 1 FROM categories WHERE LOWER(name) = LOWER(:name)";
    $params = [':name' => $name];

    if ($parentId === null) {
         $sql .= " AND parent_id IS NULL";
    } else {
        $sql .= " AND parent_id = :parent_id";
        $params[':parent_id'] = $parentId;
    }

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
        error_log("Error checking category duplicate: " . $e->getMessage());
        // Decide how to handle DB error during check: maybe throw exception or return false?
        // Returning false might allow duplicates if DB fails, throwing is safer.
        throw new RuntimeException("Lỗi kiểm tra trùng lặp danh mục."); // Use $lang if available
    }
}


// --- Main Logic ---
try {
    // --- Handle POST requests (Add, Edit, Delete) ---
    if ($method === 'POST') {
        switch ($action) {
            case 'add':
            case 'edit':
                // --- Input Data ---
                $inputData = [
                    'name' => trim($_POST['name'] ?? ''),
                    'parent_id' => $_POST['parent_id'] ?? null, // Will be 'null' string or ID
                    'category_id' => $_POST['category_id'] ?? null // For edit action
                ];

                // --- Convert 'null' string parent_id to actual null ---
                if ($inputData['parent_id'] === 'null' || $inputData['parent_id'] === '') {
                    $inputData['parent_id'] = null;
                } else {
                    // Ensure parent_id is integer if not null
                     $inputData['parent_id'] = filter_var($inputData['parent_id'], FILTER_VALIDATE_INT) ?: null;
                }
                 // Ensure category_id is integer if provided for edit
                 $inputData['category_id'] = filter_var($inputData['category_id'], FILTER_VALIDATE_INT) ?: null;


                // --- Validation Rules ---
                $rules = [
                    'name' => 'required|string|max:255',
                    'parent_id' => 'nullable|integer', // parent_id can be null or an integer
                    'category_id' => 'nullable|integer' // category_id only needed for edit
                ];
                if ($action === 'edit' && $inputData['category_id'] === null) {
                     // If it's an edit action, category_id becomes required implicitly by logic later
                     // Or add 'required' rule specifically for edit action here if needed
                }

                $validationErrors = validateInput($inputData, $rules);
                if (!empty($validationErrors)) {
                    echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                    exit;
                }

                // --- Check for Duplicates ---
                 if (checkCategoryDuplicate($pdo, $inputData['name'], $inputData['parent_id'], $inputData['category_id'])) {
                     // Use 'field' for specific field error targeting in JS
                     echo json_encode(['success' => false, 'message' => $lang['category_name_exists'] ?? 'Category name already exists at this level.', 'field' => 'name']);
                     exit;
                 }

                // --- Database Operation ---
                $pdo->beginTransaction();
                try {
                    if ($action === 'add') {
                        $sql = "INSERT INTO categories (name, parent_id, created_at, updated_at) VALUES (:name, :parent_id, NOW(), NOW())";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':name' => $inputData['name'], ':parent_id' => $inputData['parent_id']]);
                        $newId = $pdo->lastInsertId();
                        $pdo->commit();
                        // Ghi log
                        write_user_log($pdo, (int)$_SESSION['user_id'], 'category_add', 'Thêm danh mục: ' . $inputData['name']);

                        // Return data of the newly added category for potential DOM update
                        echo json_encode([
                            'success' => true,
                            'message' => $lang['category_added_success'] ?? 'Category added successfully.',
                            'data' => ['id' => $newId, 'name' => $inputData['name'], 'parent_id' => $inputData['parent_id']]
                        ]);

                    } else { // edit
                        if ($inputData['category_id'] === null) {
                             throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid request for edit.');
                        }
                        // Prevent setting a category as its own parent or child of its descendants (more complex check needed for full hierarchy protection)
                        if ($inputData['category_id'] === $inputData['parent_id']) {
                             throw new InvalidArgumentException("Không thể đặt danh mục làm cha của chính nó.");
                        }
                        // Add check for descendant loop if needed

                        $sql = "UPDATE categories SET name = :name, parent_id = :parent_id, updated_at = NOW() WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':name' => $inputData['name'], ':parent_id' => $inputData['parent_id'], ':id' => $inputData['category_id']]);
                        $pdo->commit();
                        // Ghi log
                        write_user_log($pdo, (int)$_SESSION['user_id'], 'category_edit', 'Cập nhật danh mục ID=' . $inputData['category_id'] . ', tên mới: ' . $inputData['name']); // Return updated data
                         echo json_encode([
                            'success' => true,
                            'message' => $lang['category_updated_success'] ?? 'Category updated successfully.',
                             'data' => ['id' => $inputData['category_id'], 'name' => $inputData['name'], 'parent_id' => $inputData['parent_id']]
                        ]);
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e; // Re-throw to be caught by the outer try-catch
                }
                break; // End case 'add', 'edit'

            case 'delete':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                 if (!$id) {
                    throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid category ID.');
                 }

                 $pdo->beginTransaction();
                 try {
                    // Check for sub-categories
                    $stmt_check_children = $pdo->prepare("SELECT 1 FROM categories WHERE parent_id = :id LIMIT 1");
                    $stmt_check_children->execute([':id' => $id]);
                    if ($stmt_check_children->fetchColumn()) {
                         throw new RuntimeException($lang['cannot_delete_category_has_children'] ?? 'Cannot delete category because it has sub-categories.');
                    }

                    // Check for products (assuming products link to child categories only, check if this category IS a child category first)
                    // This check might be redundant if FK constraint on products is set correctly (ON DELETE RESTRICT/CASCADE)
                    // $stmt_check_products = $pdo->prepare("SELECT 1 FROM products WHERE category_id = :id LIMIT 1");
                    // $stmt_check_products->execute([':id' => $id]);
                    // if ($stmt_check_products->fetchColumn()) {
                    //     throw new RuntimeException($lang['cannot_delete_category_has_products'] ?? 'Cannot delete category because it has products.');
                    // }

                    // Perform deletion
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
                    $stmt->execute([':id' => $id]);

                    if ($stmt->rowCount() > 0) {
                        $pdo->commit();
                        // Ghi log
                        write_user_log($pdo, (int)$_SESSION['user_id'], 'category_delete', 'Xóa danh mục ID=' . $id);
                            
                       echo json_encode(['success' => true, 'message' => $lang['category_deleted_success'] ?? 'Category deleted successfully.']);
                    } else {
                         // This might happen if the ID doesn't exist or due to FK constraints not handled above
                         throw new RuntimeException($lang['category_not_found_or_cannot_delete'] ?? 'Category not found or cannot be deleted.');
                    }
                 } catch (Exception $e) {
                     $pdo->rollBack();
                     throw $e;
                 }
                 break; // End case 'delete'

            default:
                throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action specified.');
        } // End switch ($action) for POST

    }
    // --- Handle GET requests (Fetch data) ---
    elseif ($method === 'GET') {
         switch ($action) {
             case 'get': // Get single category details for editing
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid category ID.');
                }
                // Join with parent category to get parent name
                $stmt = $pdo->prepare("
                    SELECT c.id, c.name, c.parent_id, p.name as parent_name
                    FROM categories c
                    LEFT JOIN categories p ON c.parent_id = p.id
                    WHERE c.id = :id
                ");
                $stmt->execute([':id' => $id]);
                $category = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($category) {
                    // Convert parent_id null to 'null' string for consistency if needed by JS, or keep as null
                    // $category['parent_id'] = $category['parent_id'] ?? 'null';
                    echo json_encode(['success' => true, 'data' => $category]);
                } else {
                     throw new RuntimeException($lang['category_not_found'] ?? 'Category not found.');
                }
                break; // End case 'get'

             case 'check_duplicate': // Check duplicate name via GET for real-time validation
                $name = trim($_GET['name'] ?? '');
                $parentId = $_GET['parent_id'] ?? null; // Can be 'null' string
                $currentId = filter_input(INPUT_GET, 'current_id', FILTER_VALIDATE_INT) ?: null;

                // Convert 'null' string to actual null
                $parentId = ($parentId === 'null' || $parentId === '') ? null : filter_var($parentId, FILTER_VALIDATE_INT);

                if (empty($name)) {
                     echo json_encode(['exists' => false]); // Cannot check empty name
                     exit;
                }

                 $exists = checkCategoryDuplicate($pdo, $name, $parentId, $currentId);
                 echo json_encode(['exists' => $exists]);
                 break; // End case 'check_duplicate'

             case 'get_child_categories': // Get list of child categories (for product form dropdown)
                $stmt = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NOT NULL ORDER BY name ASC");
                $child_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $child_categories]);
                 break; // End case 'get_child_categories'

            default:
                throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action specified.');
        } // End switch ($action) for GET
    }
    // --- Handle other request methods ---
    else {
        // Method Not Allowed
        http_response_code(405);
        throw new RuntimeException($lang['invalid_request_method'] ?? 'Invalid request method.');
    }

} catch (PDOException $e) {
    error_log("Database Error in category_handler.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => $lang['database_error'] ?? 'A database error occurred. Please try again.']);

} catch (InvalidArgumentException $e) {
     http_response_code(400); // Bad Request
     echo json_encode(['success' => false, 'message' => $e->getMessage()]);

} catch (RuntimeException $e) {
     // Runtime exceptions could be various things (file errors, logic errors not caught elsewhere)
     // Use 500 for general runtime issues unless a specific code is more appropriate (e.g., 404 for not found)
     http_response_code(500);
     error_log("Runtime Error in category_handler.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
     echo json_encode(['success' => false, 'message' => $e->getMessage()]); // Show specific runtime message

} catch (Exception $e) {
    // Catch-all for any other unexpected exceptions
    error_log("Unexpected Error in category_handler.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => $lang['server_error'] ?? 'An unexpected server error occurred.']);
}

exit; // Ensure script termination after response
?>
