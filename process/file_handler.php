<?php
// File: process/file_handler.php

declare(strict_types=1);

// This file contains the file upload function and the AJAX handler for deleting files.
// The handleFileUploads function is included by product_handler.php.
// The AJAX delete handler needs its own includes if accessed directly.

/**
 * Handles file uploads for a specific product.
 * Validates files and moves them to the specified directory.
 * Inserts file records into the database.
 *
 * @param array $filesData Data from $_FILES['input_name']. Should be normalized first.
 * @param int $productId The ID of the product.
 * @param string $fileType 'image' or 'pdf'.
 * @param PDO $pdo PDO database connection object.
 * @param string $uploadDir Absolute path to the destination directory (e.g., /path/to/htdocs/quanlykho/uploads/products/images/).
 * @param int $maxFiles Maximum allowed total files of this type for the product.
 * @param int $currentFileCount Number of files of this type already associated with the product.
 * @throws InvalidArgumentException For invalid parameters or file types/sizes.
 * @throws RuntimeException For file system errors or database errors.
 */
function handleFileUploads(array $filesData, int $productId, string $fileType, PDO $pdo, string $uploadDir, int $maxFiles, int $currentFileCount): void
{
    // Access $lang if available globally, otherwise use default messages
    global $lang;
    $lang = $lang ?? []; // Ensure $lang is an array even if not loaded

    // Define allowed types and max size
    $allowedMimeTypes = [
        'image' => ['image/jpeg', 'image/png', 'image/gif'],
        'pdf' => ['application/pdf']
    ];
    $maxFileSize = 5 * 1024 * 1024; // 5 MB - Make this configurable if needed

    // --- Basic Parameter Validation ---
    if (!isset($allowedMimeTypes[$fileType])) {
        throw new InvalidArgumentException("Invalid file type specified: $fileType");
    }
    if ($productId <= 0) {
         throw new InvalidArgumentException("Invalid product ID for file upload.");
    }

    // --- Directory Checks ---
    if (!is_dir($uploadDir)) {
        // Attempt to create the directory recursively
        if (!mkdir($uploadDir, 0775, true)) {
            $errorMsg = sprintf("Upload directory does not exist and could not be created: %s", $uploadDir);
            error_log($errorMsg);
            throw new RuntimeException($lang['upload_directory_error'] ?? 'Upload directory configuration error.');
        }
    } elseif (!is_writable($uploadDir)) {
        $errorMsg = sprintf("Upload directory is not writable: %s", $uploadDir);
        error_log($errorMsg);
        throw new RuntimeException($lang['upload_directory_error'] ?? 'Upload directory configuration error.');
    }

    // --- Normalize $_FILES array ---
    // (Assumes multiple files uploaded with name="input_name[]")
    $normalizedFiles = [];
    if (isset($filesData['name']) && is_array($filesData['name'])) {
        foreach ($filesData['name'] as $key => $name) {
            // Skip empty file inputs often submitted by browsers
            if ($filesData['error'][$key] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            // Check for other upload errors immediately
            if ($filesData['error'][$key] !== UPLOAD_ERR_OK) {
                 $uploadError = match ($filesData['error'][$key]) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => ($lang['file_too_large'] ?? 'File is too large (%s).'),
                    UPLOAD_ERR_PARTIAL => ($lang['file_partially_uploaded'] ?? 'File was only partially uploaded (%s).'),
                    UPLOAD_ERR_NO_TMP_DIR => ($lang['missing_temp_folder'] ?? 'Missing temporary folder for %s.'),
                    UPLOAD_ERR_CANT_WRITE => ($lang['failed_to_write_file'] ?? 'Failed to write file to disk for %s.'),
                    UPLOAD_ERR_EXTENSION => ($lang['upload_stopped_by_extension'] ?? 'File upload stopped by extension for %s.'),
                    default => ($lang['unknown_upload_error'] ?? 'Unknown upload error for %s.'),
                 };
                 // Throw immediately for upload errors
                 throw new RuntimeException(sprintf($uploadError, basename($name)));
            }

            $normalizedFiles[] = [
                'name' => $name,
                'type' => $filesData['type'][$key], // Use reported type initially
                'tmp_name' => $filesData['tmp_name'][$key],
                'error' => $filesData['error'][$key], // Should be UPLOAD_ERR_OK here
                'size' => $filesData['size'][$key],
            ];
        }
    }
    // Optional: Handle single file upload case if form allows it

    // --- Process Each Validated File ---
    $uploadedCount = 0;
    foreach ($normalizedFiles as $file) {
        // --- Check File Limit ---
        if (($currentFileCount + $uploadedCount) >= $maxFiles) {
            // Stop processing further files if limit reached
            // Throwing an exception here might be too harsh if some files were already processed.
            // Maybe collect errors and return them? Or just log a warning.
            $limitError = sprintf(
                $lang['file_limit_exceeded_on_upload'] ?? 'Cannot upload "%s". Maximum %d files of type %s allowed.',
                basename($file['name']), $maxFiles, $fileType
            );
             // Throwing for now, assumes transaction rollback in caller
             throw new InvalidArgumentException($limitError);
            // break; // Or just stop processing more files for this request
        }

        // --- Validate File Size ---
        if ($file['size'] <= 0) {
             // Skip zero-byte files silently or throw error?
             // throw new InvalidArgumentException(sprintf("File '%s' is empty.", basename($file['name'])));
             continue; // Skip empty file
        }
        if ($file['size'] > $maxFileSize) {
            throw new InvalidArgumentException(sprintf(
                $lang['file_size_exceeds_limit'] ?? 'File "%s" is too large (Max: %s MB).',
                basename($file['name']), round($maxFileSize / 1024 / 1024, 1)
            ));
        }

        // --- Validate MIME Type (More reliable than $_FILES['type']) ---
        if (!file_exists($file['tmp_name']) || !is_readable($file['tmp_name'])) {
             throw new RuntimeException(sprintf("Cannot read temporary file: %s", $file['tmp_name']));
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedMimeTypes[$fileType])) {
            throw new InvalidArgumentException(sprintf(
                $lang['invalid_file_type'] ?? 'Invalid file type: %s. Only %s allowed.',
                $mimeType, strtoupper(implode('/', explode('/', $allowedMimeTypes[$fileType][0]))) // e.g., IMAGE/JPEG
            ) . " (" . basename($file['name']) . ")");
        }

        // --- Generate Secure and Unique Filename ---
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        // Sanitize base name: remove special chars, limit length
        $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $safeBaseName = mb_substr($safeBaseName, 0, 100); // Limit base name length
        // Create unique name: base_timestamp_random.ext
        $uniqueFilename = $safeBaseName . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destinationPath = $uploadDir . $uniqueFilename; // Absolute path for moving

        // --- Move Uploaded File ---
        if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
            // Log error details if possible
            error_log("Failed to move uploaded file from {$file['tmp_name']} to $destinationPath");
            throw new RuntimeException(sprintf($lang['error_moving_uploaded_file'] ?? 'Could not save uploaded file: %s', basename($file['name'])));
        }

        // --- Construct Relative Path for Database ---
        // This path should be relative to the web root (htdocs/quanlykho)
        // Assumes 'uploads' directory is directly under the web root. Adjust if needed.
        $relativePath = 'uploads/products/' . ($fileType === 'image' ? 'images' : 'documents') . '/' . $uniqueFilename;

        // --- Save File Info to Database ---
        try {
            $sql = "INSERT INTO product_files (product_id, file_path, original_filename, file_type, uploaded_at)
                    VALUES (:product_id, :file_path, :original_filename, :file_type, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':product_id' => $productId,
                ':file_path' => $relativePath,
                ':original_filename' => $originalName, // Store original name for user display/download
                ':file_type' => $fileType
            ]);
            $uploadedCount++;
        } catch (PDOException $e) {
            // If DB insert fails, attempt to delete the already moved file
            if (file_exists($destinationPath)) {
                @unlink($destinationPath);
            }
            error_log("Database error saving file record: " . $e->getMessage());
            // Re-throw exception to be handled by the caller (likely causing transaction rollback)
            throw new RuntimeException("Lỗi lưu thông tin file vào cơ sở dữ liệu.");
        }
    } // End foreach loop over files
} // End handleFileUploads function


// --- AJAX Handler for Deleting Files ---
// This part runs only if the file is accessed directly via POST with action=delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {

     // --- Include necessary files for standalone execution ---
     require_once __DIR__ . '/../includes/init.php';
     require_once __DIR__ . '/../includes/auth_check.php';
     // require_once __DIR__ . '/../includes/admin_check.php'; // Check if admin rights are needed

     // --- Set response header ---
     header('Content-Type: application/json');

     // --- Input Data ---
     $fileId = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
     $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT); // For ownership validation

     try {
        if (!$fileId || !$productId) {
             throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid request data.');
        }

        $pdo->beginTransaction(); // Use transaction for delete consistency

        // --- Get file path and verify ownership ---
        $stmt_get_file = $pdo->prepare("SELECT file_path FROM product_files WHERE id = :file_id AND product_id = :product_id");
        $stmt_get_file->execute([':file_id' => $fileId, ':product_id' => $productId]);
        $relativePath = $stmt_get_file->fetchColumn();

        if (!$relativePath) {
             // File not found or doesn't belong to the specified product
             throw new RuntimeException($lang['file_not_found_or_permission_denied'] ?? 'File not found or permission denied.');
        }

        // --- Delete database record ---
        $stmt_delete_db = $pdo->prepare("DELETE FROM product_files WHERE id = :id");
        $stmt_delete_db->execute([':id' => $fileId]);

        if ($stmt_delete_db->rowCount() > 0) {
             // --- Delete physical file ---
             if (!empty($relativePath)) {
                $absolutePath = realpath(__DIR__ . '/../' . $relativePath);
                // Security check: Ensure path is within uploads and is a file
                $allowedBase = realpath(__DIR__ . '/../uploads');
                if ($absolutePath && $allowedBase && strpos($absolutePath, $allowedBase) === 0 && is_file($absolutePath)) {
                    if (!@unlink($absolutePath)) {
                        // Log failure but don't necessarily fail the request as DB is clean
                        error_log("Failed to delete physical file after DB delete: " . $absolutePath);
                    }
                } else {
                     error_log("Physical file not found or invalid path for deletion after DB delete: " . $relativePath);
                }
             }
             $pdo->commit();
             echo json_encode(['success' => true, 'message' => $lang['file_deleted_success'] ?? 'File deleted successfully.']);

        } else {
             // Should not happen if fetchColumn succeeded, but as a safeguard
             throw new RuntimeException($lang['error_deleting_file_record'] ?? 'Error deleting file record.');
        }

     } catch (PDOException $e) {
         $pdo->rollBack();
         error_log("Database Error in file_handler.php (delete action): " . $e->getMessage());
         http_response_code(500);
         echo json_encode(['success' => false, 'message' => $lang['database_error'] ?? 'Database error during file deletion.']);
     } catch (InvalidArgumentException | RuntimeException $e) {
         // Handle specific logic errors (invalid ID, not found, cannot delete)
         $pdo->rollBack(); // Rollback if transaction started
         http_response_code(400); // Bad request or Not Found (404 might be better for 'not found')
         echo json_encode(['success' => false, 'message' => $e->getMessage()]);
     } catch (Exception $e) {
         // Catch-all for unexpected errors
          $pdo->rollBack();
         error_log("Unexpected Error in file_handler.php (delete action): " . $e->getMessage());
         http_response_code(500);
         echo json_encode(['success' => false, 'message' => $lang['server_error'] ?? 'An unexpected error occurred.']);
     }
     exit; // Terminate script after handling AJAX delete request
}
?>
