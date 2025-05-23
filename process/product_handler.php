<?php
// File: process/product_handler.php

declare(strict_types=1);

// --- Include necessary files ---
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth_check.php';
// require_once __DIR__ . '/../includes/admin_check.php'; // Uncomment if needed
require_once __DIR__ . '/file_handler.php'; // Include file processing functions

// --- Set response header to JSON ---
header('Content-Type: application/json');

// --- Get action and request method ---
$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

// --- Define Upload Directories (Consistent with file_handler.php usage) ---
// These paths are relative to the project root (where init.php likely is)
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

     // Custom validation for files (can be done here or in handleFileUploads)
     // Example: Check total file count before calling handleFileUploads
     $maxImages = 3;
     $maxDocs = 3;
     $currentImageCount = $input['current_image_count'] ?? 0; // Need to pass this if checking here
     $currentDocCount = $input['current_doc_count'] ?? 0;
     $newImageCount = isset($_FILES['product_images']['name']) ? count(array_filter($_FILES['product_images']['name'])) : 0;
     $newDocCount = isset($_FILES['product_documents']['name']) ? count(array_filter($_FILES['product_documents']['name'])) : 0;

     if (($currentImageCount + $newImageCount) > $maxImages) {
         $errors['product_images'][] = "Số lượng ảnh vượt quá giới hạn ($maxImages).";
     }
      if (($currentDocCount + $newDocCount) > $maxDocs) {
         $errors['product_documents'][] = "Số lượng chứng từ PDF vượt quá giới hạn ($maxDocs).";
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
         // Decide: throw or return false? Returning false might hide DB issues.
         throw new RuntimeException("Lỗi kiểm tra trùng lặp sản phẩm.");
    }
}

// --- Main Logic ---
try {
    // --- Handle POST requests (Add, Edit, Delete) ---
    if ($method === 'POST') {
        // For add/edit, content type will likely be multipart/form-data
        // For delete, it might be application/x-www-form-urlencoded

        switch ($action) {
            case 'add':
            case 'edit':
                error_log("PRODUCT_HANDLER UPLOAD (3 files test) - RECEIVED FILES: " . print_r($_FILES, true));
                // --- Input Data ---
                $inputData = [
                    'product_id' => filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?: null,
                    'name' => trim($_POST['name'] ?? ''),
                    'category_id' => filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null,
                    'unit_id' => filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT) ?: null,
                    'description' => trim($_POST['description'] ?? '')
                ];

                 // --- Validation ---
                 $rules = [
                    'name' => 'required|string|max:255',
                    'category_id' => 'required|integer',
                    'unit_id' => 'required|integer',
                    'description' => 'nullable|string|max:65535' // Max length for TEXT type
                 ];
                 // Add file validation rules here if checking before calling handler
                 // $rules['product_images'] = 'file_count_max:3'; // Custom rule example
                 // $rules['product_documents'] = 'file_count_max:3';

                 // Need current file counts if validating here
                 // $inputData['current_image_count'] = ...; // Fetch if needed
                 // $inputData['current_doc_count'] = ...;

                 $validationErrors = validateProductInput($inputData, $rules);
                 if (!empty($validationErrors)) {
                     echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                     exit;
                 }

                 // --- Additional Server-Side Checks ---
                 // Check if category_id is actually a child category
                 $stmt_check_child = $pdo->prepare("SELECT 1 FROM categories WHERE id = :id AND parent_id IS NOT NULL LIMIT 1");
                 $stmt_check_child->execute([':id' => $inputData['category_id']]);
                 if (!$stmt_check_child->fetchColumn()) {
                     // Add error to validation errors array
                      $validationErrors['category_id'][] = $lang['product_must_be_in_child_category'] ?? 'Products must be in a child category.';
                      echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                      exit;
                 }
                 // Check if unit_id exists
                  $stmt_check_unit = $pdo->prepare("SELECT 1 FROM units WHERE id = :id LIMIT 1");
                  $stmt_check_unit->execute([':id' => $inputData['unit_id']]);
                  if (!$stmt_check_unit->fetchColumn()) {
                       $validationErrors['unit_id'][] = $lang['invalid_unit'] ?? 'Invalid unit selected.';
                       echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                       exit;
                  }


                // --- Database Operation with Transaction ---
                $pdo->beginTransaction();
                try {
                    $productId = $inputData['product_id']; // Use for both add (null) and edit

                    if ($action === 'add') {
                        $sql = "INSERT INTO products (name, category_id, unit_id, description, created_at, updated_at)
                                VALUES (:name, :category_id, :unit_id, :description, NOW(), NOW())";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':name' => $inputData['name'],
                            ':category_id' => $inputData['category_id'],
                            ':unit_id' => $inputData['unit_id'],
                            ':description' => $inputData['description']
                        ]);
                        $productId = (int)$pdo->lastInsertId(); // Get the new product ID
                        if (!$productId) {
                             throw new RuntimeException("Không thể tạo sản phẩm mới.");
                        }
                        $message = $lang['product_added_success'] ?? 'Product added successfully.';

                    } else { // edit
                        if ($productId === null) {
                            throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid request for edit.');
                        }
                        $sql = "UPDATE products SET
                                    name = :name,
                                    category_id = :category_id,
                                    unit_id = :unit_id,
                                    description = :description,
                                    updated_at = NOW()
                                WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':name' => $inputData['name'],
                            ':category_id' => $inputData['category_id'],
                            ':unit_id' => $inputData['unit_id'],
                            ':description' => $inputData['description'],
                            ':id' => $productId
                        ]);
                         $message = $lang['product_updated_success'] ?? 'Product updated successfully.';
                    }

                     // --- File Upload Handling ---
                     // Get current file counts for validation within the handler
                     $stmt_count_files = $pdo->prepare("SELECT file_type, COUNT(*) as count FROM product_files WHERE product_id = :product_id GROUP BY file_type");
                     $stmt_count_files->execute([':product_id' => $productId]);
                     $current_file_counts = $stmt_count_files->fetchAll(PDO::FETCH_KEY_PAIR);
                     $current_image_count = (int)($current_file_counts['image'] ?? 0);
                     $current_pdf_count = (int)($current_file_counts['pdf'] ?? 0);

                     $fileUploadErrors = []; // Collect file upload specific errors

                     // Process Images
                     if (isset($_FILES['product_images']) && $_FILES['product_images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                         try {
                             handleFileUploads($_FILES['product_images'], $productId, 'image', $pdo, $uploadDirImages, 3, $current_image_count);
                         } catch (Exception $e) {
                             $fileUploadErrors['product_images'][] = $e->getMessage();
                         }
                     }
                     // Process Documents
                     if (isset($_FILES['product_documents']) && $_FILES['product_documents']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                          try {
                             handleFileUploads($_FILES['product_documents'], $productId, 'pdf', $pdo, $uploadDirDocs, 3, $current_pdf_count);
                          } catch (Exception $e) {
                             $fileUploadErrors['product_documents'][] = $e->getMessage();
                          }
                     }

                     // If there were file upload errors, rollback and report
                     if (!empty($fileUploadErrors)) {
                         $pdo->rollBack();
                         // Merge file errors with potential other validation errors
                         $allErrors = array_merge($validationErrors ?? [], $fileUploadErrors);
                         echo json_encode(['success' => false, 'message' => "Lỗi upload file.", 'errors' => $allErrors]);
                         exit;
                     }

                    // --- Commit Transaction ---
                    $pdo->commit();
                    // Return data for potential DOM update
                    echo json_encode([
                        'success' => true,
                        'message' => $message,
                        'data' => ['id' => $productId] // Include ID
                    ]);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    // Check if it's a validation error already prepared
                    if (isset($validationErrors) && !empty($validationErrors)) {
                         echo json_encode(['success' => false, 'message' => "Dữ liệu không hợp lệ.", 'errors' => $validationErrors]);
                    } else {
                        // Otherwise, re-throw general exception
                        throw $e;
                    }
                }
                break; // End case 'add', 'edit'

            case 'delete':
                // Ensure content type is appropriate or just use $_POST
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid product ID.');
                }

                $pdo->beginTransaction();
                try {
                    // 1. Get list of physical files to delete *before* deleting DB records
                    $stmt_files = $pdo->prepare("SELECT file_path FROM product_files WHERE product_id = :id");
                    $stmt_files->execute([':id' => $id]);
                    $files_to_delete = $stmt_files->fetchAll(PDO::FETCH_COLUMN);

                    // 2. Delete product record (FK ON DELETE CASCADE should handle product_files records)
                    $stmt_delete_product = $pdo->prepare("DELETE FROM products WHERE id = :id");
                    $stmt_delete_product->execute([':id' => $id]);

                    if ($stmt_delete_product->rowCount() > 0) {
                         // 3. Delete physical files *after* successful DB deletion
                         foreach ($files_to_delete as $relativePath) {
                             if (empty($relativePath)) continue;
                             // Construct absolute path carefully
                             $absolutePath = realpath(__DIR__ . '/../' . $relativePath);
                              // Security check: ensure the path is within the uploads directory
                              $allowedBase = realpath(__DIR__ . '/../uploads');
                             if ($absolutePath && $allowedBase && strpos($absolutePath, $allowedBase) === 0 && is_file($absolutePath)) {
                                 if (!@unlink($absolutePath)) {
                                      error_log("Failed to delete physical file (but DB record deleted): " . $absolutePath);
                                 }
                             } else {
                                 error_log("Physical file not found, invalid path, or outside allowed directory: " . $relativePath . " (Absolute: " . $absolutePath . ")");
                             }
                         }
                         $pdo->commit();
                         echo json_encode(['success' => true, 'message' => $lang['product_deleted_success'] ?? 'Product deleted successfully.']);
                    } else {
                         // Product might have already been deleted or ID was invalid
                         throw new RuntimeException($lang['product_not_found_or_cannot_delete'] ?? 'Product not found or cannot be deleted.');
                    }

                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e; // Re-throw
                }
                break; // End case 'delete'

             default:
                throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action specified.');
        } // End switch ($action) for POST

    }
    // --- Handle GET requests (Fetch data) ---
    elseif ($method === 'GET') {
         switch ($action) {
            case 'search': // Action mới cho product autocomplete
                $term = trim($_GET['term'] ?? '');
                // $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT); // Lọc theo category nếu cần

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

                // if ($categoryId) { // Bỏ comment nếu muốn lọc theo category cụ thể
                //     $sql = str_replace("WHERE", "WHERE p.category_id = :category_id AND", $sql);
                //     $params[':category_id'] = $categoryId;
                // }

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
                    LEFT JOIN categories c ON p.category_id = c.id  -- Use LEFT JOIN in case category was deleted?
                    LEFT JOIN units u ON p.unit_id = u.id          -- Use LEFT JOIN in case unit was deleted?
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

                 // Note: file_path is already relative to web root as stored by file_handler

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
     // Runtime errors from logic (e.g., cannot delete, not found)
     // Use 500 or a more specific code if applicable (e.g., 404, 409 Conflict)
     http_response_code(500);
     error_log("Runtime Error in product_handler.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
     echo json_encode(['success' => false, 'message' => $e->getMessage()]);

} catch (Exception $e) {
    // Catch-all for unexpected errors
    error_log("Unexpected Error in product_handler.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $lang['server_error'] ?? 'An unexpected server error occurred.']);
}

exit; // Terminate script
?>
