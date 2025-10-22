<?php
declare(strict_types=1);
// --- Include necessary files ---
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/file_handler.php'; // Include file processing functions

// --- Set response header to JSON ---
header('Content-Type: application/json');

// --- Get action and request method ---
// Đọc dữ liệu JSON từ AJAX
$method = $_SERVER['REQUEST_METHOD'];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if ($method === 'GET') {
    $parsed_input = $_GET;
    $action = $_GET['action'] ?? null;
} elseif ($method === 'POST') {
    if (str_starts_with($contentType, 'application/json')) {
        $raw_input = file_get_contents("php://input");
        $parsed_input = json_decode($raw_input, true) ?? [];
    } else {
        $parsed_input = $_POST;
    }
    $action = $parsed_input['action'] ?? null;
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không hỗ trợ']);
    exit;
}

$uploadBaseDir = __DIR__ . '/../uploads/products/'; // Base directory for product uploads
$uploadDirImages = $uploadBaseDir . 'images/';
$uploadDirDocs = $uploadBaseDir . 'documents/';

// --- Input validation helper (reuse or adapt from category_handler) ---
function validateProductInput(array $input, array $rules): array {
    $errors = [];
    // Basic required check (can be expanded)
    foreach ($rules as $field => $ruleSet) {
         $value = $input[$field] ?? null;
         if (strpos($ruleSet, 'required') !== false && (empty($value) && $value !== '0')) {
             $errors[$field][] = "Trường này là bắt buộc."; // Use $lang['field_required']
         }
         // Add other rules: integer, max length etc.
          if (strpos($ruleSet, 'integer') !== false && !empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
             $errors[$field][] = "Giá trị phải là số nguyên.";
         }
          if (preg_match('/max:(\d+)/', $ruleSet, $matches) && is_string($value) && mb_strlen($value) > (int)$matches[1]) {
             $errors[$field][] = "Không được vượt quá {$matches[1]} ký tự.";
          }
    }
    return $errors;
}

// --- Helper function to check product duplicates (for warning) ---
/**
 * Checks if a product with the same name, unit, and category exists.
 *
 * @param PDO $pdo
 * @param string $name
 * @param int $unitId
 * @param int $categoryId
 * @param int|null $currentId Product ID to exclude during edit check.
 * @return bool True if duplicate found, false otherwise.
 */
function checkProductDuplicate(PDO $pdo, string $name, int $unitId, int $categoryId, ?int $currentId = null): bool {
    $sql = "SELECT 1 FROM products
            WHERE LOWER(name) = LOWER(:name)
            AND unit_id = :unit_id
            AND category_id = :category_id";
     $params = [
        ':name' => $name,
        ':unit_id' => $unitId,
        ':category_id' => $categoryId
    ];
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
         error_log("Error checking product duplicate: " . $e->getMessage());
         throw new RuntimeException("Lỗi kiểm tra trùng lặp sản phẩm.");
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
                    'product_id'   => isset($parsed_input['product_id']) ? (int)$parsed_input['product_id'] : null,
                    'name'         => trim($parsed_input['name'] ?? ''),
                    'category_id'  => isset($parsed_input['category_id']) ? (int)$parsed_input['category_id'] : null,
                    'unit_id'      => isset($parsed_input['unit_id']) ? (int)$parsed_input['unit_id'] : null,
                    'description'  => trim($parsed_input['description'] ?? '')
                ];

                // --- Validation ---
                $rules = [
                    'name'        => 'required|string|max:255',
                    'category_id' => 'required|integer',
                    'unit_id'     => 'required|integer',
                    'description' => 'nullable|string|max:65535'
                ];
                $validationErrors = validateProductInput($inputData, $rules);
                if (!empty($validationErrors)) {
                    echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                    exit;
                }

                // --- Kiểm tra danh mục con ---
                $stmt = $pdo->prepare("SELECT 1 FROM categories WHERE id = :id AND parent_id IS NOT NULL LIMIT 1");
                $stmt->execute([':id' => $inputData['category_id']]);
                if (!$stmt->fetchColumn()) {
                    $validationErrors['category_id'][] = $lang['product_must_be_in_child_category'] ?? 'Products must be in a child category.';
                    echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                    exit;
                }

                // --- Kiểm tra đơn vị tính ---
                $stmt = $pdo->prepare("SELECT 1 FROM units WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $inputData['unit_id']]);
                if (!$stmt->fetchColumn()) {
                    $validationErrors['unit_id'][] = $lang['invalid_unit'] ?? 'Invalid unit selected.';
                    echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                    exit;
                }

                $pdo->beginTransaction();
                try {
                    $productId = $inputData['product_id'];

                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO products (name, category_id, unit_id, description, created_at, updated_at)
                                               VALUES (:name, :category_id, :unit_id, :description, NOW(), NOW())");
                        $stmt->execute([
                            ':name' => $inputData['name'],
                            ':category_id' => $inputData['category_id'],
                            ':unit_id' => $inputData['unit_id'],
                            ':description' => $inputData['description']
                        ]);
                        $productId = (int)$pdo->lastInsertId();
                        if (!$productId) {
                            throw new RuntimeException("Không thể tạo sản phẩm mới.");
                        }
                        $message = $lang['product_added_success'] ?? 'Product added successfully.';
                    } else {
                        if ($productId === null) {
                            throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid request for edit.');
                        }
                        $stmt = $pdo->prepare("UPDATE products SET name = :name, category_id = :category_id, unit_id = :unit_id, description = :description, updated_at = NOW()
                                               WHERE id = :id");
                        $stmt->execute([
                            ':name' => $inputData['name'],
                            ':category_id' => $inputData['category_id'],
                            ':unit_id' => $inputData['unit_id'],
                            ':description' => $inputData['description'],
                            ':id' => $productId
                        ]);
                        $message = $lang['product_updated_success'] ?? 'Product updated successfully.';
                    }

                    // --- File Upload ---
                    $fileUploadErrors = [];

                    if (!empty($_FILES['product_images']['name'][0])) {
                        try {
                            handleFileUploads($_FILES['product_images'], $productId, 'image', $pdo, $uploadDirImages);
                        } catch (Exception $e) {
                            $fileUploadErrors['product_images'][] = $e->getMessage();
                        }
                    }

                    if (!empty($_FILES['product_documents']['name'][0])) {
                        try {
                            handleFileUploads($_FILES['product_documents'], $productId, 'pdf', $pdo, $uploadDirDocs);
                        } catch (Exception $e) {
                            $fileUploadErrors['product_documents'][] = $e->getMessage();
                        }
                    }

                    if (!empty($fileUploadErrors)) {
                        $pdo->rollBack();
                        $allErrors = array_merge($validationErrors ?? [], $fileUploadErrors);
                        echo json_encode(['success' => false, 'message' => "Lỗi upload file.", 'errors' => $allErrors]);
                        exit;
                    }

                    $pdo->commit();

                    // ✅ Ghi log
                    $logAction = $action === 'add' ? 'product_add' : 'product_edit';
                    $logDesc = ($action === 'add' ? 'Thêm' : 'Cập nhật') . " sản phẩm ID=$productId, tên={$inputData['name']}";
                    write_user_log($pdo, (int)$_SESSION['user_id'], $logAction, $logDesc);

                    echo json_encode(['success' => true, 'message' => $message, 'data' => ['id' => $productId]]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;

            case 'delete':
                $id = isset($parsed_input['id']) ? (int)$parsed_input['id'] : 0;
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => "ID không hợp lệ: $id"]);
                    exit;
                }

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $id]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {
                        throw new RuntimeException($lang['product_not_found_or_cannot_delete'] ?? 'Product not found or cannot be deleted.');
                    }

                    $stmt = $pdo->prepare("SELECT file_path FROM product_files WHERE product_id = :id");
                    $stmt->execute([':id' => $id]);
                    $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
                    $stmt->execute([':id' => $id]);

                    if ($stmt->rowCount() === 0) {
                        throw new RuntimeException("Không thể xóa sản phẩm ID $id.");
                    }

                    foreach ($files as $relPath) {
                        if (!$relPath) continue;
                        $abs = realpath(__DIR__ . '/../' . $relPath);
                        $base = realpath(__DIR__ . '/../uploads');
                        if ($abs && $base && strpos($abs, $base) === 0 && is_file($abs)) {
                            @unlink($abs);
                        }
                    }

                    $pdo->commit();

                    // ✅ Ghi log
                    write_user_log($pdo, (int)$_SESSION['user_id'], 'product_delete', 'Đã xóa sản phẩm ID=' . $id . ', tên: ' . $product['name']);

                    echo json_encode(['success' => true, 'message' => $lang['product_deleted_success'] ?? 'Product deleted successfully.']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

            default:
                throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action specified.');
        } // end switch
    } // end POST

    // --- Handle GET requests (Fetch data) ---
    elseif ($method === 'GET') {
         switch ($action) {
            case 'search': // Action mới cho product autocomplete
                $term = trim($_GET['term'] ?? '');

                if (empty($term)) {
                    echo json_encode(['success' => true, 'data' => []]);
                    exit;
                }

                // Join với categories và units để lấy tên đầy đủ hơn
                $sql = "SELECT p.id, p.name, p.description,
                               c.name as category_name, u.name as unit_name
                        FROM products p
                        JOIN categories c ON p.category_id = c.id
                        JOIN units u ON p.unit_id = u.id
                        WHERE LOWER(p.name) LIKE :term
                        AND c.parent_id IS NOT NULL -- Chỉ tìm sản phẩm trong danh mục con
                        ORDER BY p.name ASC
                        LIMIT 15"; // Giới hạn kết quả

                $params = [':term' => '%' . strtolower($term) . '%'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $products]);
                break; // End case 'search'
                
            case 'get': // Get single product details + files
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid product ID.');
                }

                // Get product info + category name + unit name
                $stmt_prod = $pdo->prepare("
                    SELECT p.id, p.name, p.category_id, p.unit_id, p.description,
                           c.name as category_name, u.name as unit_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN units u ON p.unit_id = u.id
                    WHERE p.id = :id
                ");
                $stmt_prod->execute([':id' => $id]);
                $product = $stmt_prod->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new RuntimeException($lang['product_not_found'] ?? 'Product not found.');
                }

                // Get associated files
                $stmt_files = $pdo->prepare("
                    SELECT id, product_id, file_path, original_filename, file_type
                    FROM product_files
                    WHERE product_id = :id
                    ORDER BY file_type, uploaded_at ASC
                ");
                 $stmt_files->execute([':id' => $id]);
                 $files = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

                 echo json_encode(['success' => true, 'data' => ['product' => $product, 'files' => $files]]);
                 break; // End case 'get'

             case 'check_duplicate': // Check duplicate name+unit+category via GET
                $name = trim($_GET['name'] ?? '');
                $unitId = filter_input(INPUT_GET, 'unit_id', FILTER_VALIDATE_INT);
                $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
                $currentId = filter_input(INPUT_GET, 'current_id', FILTER_VALIDATE_INT) ?: null;

                 if (empty($name) || !$unitId || !$categoryId) {
                     echo json_encode(['exists' => false]); // Not enough info
                     exit;
                 }

                 $exists = checkProductDuplicate($pdo, $name, $unitId, $categoryId, $currentId);
                 echo json_encode(['exists' => $exists]);
                 break; // End case 'check_duplicate'

             default:
                throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action specified.');
        } // End switch ($action) for GET
    }
     // --- Handle other request methods ---
    else {
        http_response_code(405); // Method Not Allowed
        throw new RuntimeException($lang['invalid_request_method'] ?? 'Invalid request method.');
    }

} catch (PDOException $e) {
    error_log("Database Error in product_handler.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $lang['database_error'] ?? 'A database error occurred.']);

} catch (InvalidArgumentException $e) {
     http_response_code(400); // Bad Request for invalid arguments/actions
     echo json_encode(['success' => false, 'message' => $e->getMessage()]);

} catch (RuntimeException $e) {
     http_response_code(500);
     error_log("Runtime Error in product_handler.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
     echo json_encode(['success' => false, 'message' => $e->getMessage()]);

} catch (Exception $e) {
    error_log("Unexpected Error in product_handler.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $lang['server_error'] ?? 'An unexpected server error occurred.']);
}

exit; // Terminate script
?>