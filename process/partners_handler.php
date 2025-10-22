<?php
// File: process/partners_handler.php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth_check.php';
// require_once __DIR__ . '/../includes/admin_check.php'; // Quyền hạn tùy theo logic

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

// ... (Các hàm helper và logic khác của file này giữ nguyên) ...

try {
    if ($method === 'GET') {
        switch ($action) {
            // ... (Các case GET khác như get, check_duplicate...) ...

            case 'search': // Action mới cho autocomplete
                $term = trim($_GET['term'] ?? '');
                $type = trim($_GET['type'] ?? ''); // 'supplier' or 'customer'

                if (empty($term)) {
                    echo json_encode(['success' => true, 'data' => []]); // Trả về rỗng nếu không có từ khóa
                    exit;
                }

                $sql = "SELECT id, name, address, tax_id, phone, email, contact_person
                        FROM partners
                        WHERE LOWER(name) LIKE :term";

                $params = [':term' => '%' . strtolower($term) . '%'];

                if (!empty($type) && ($type === 'supplier' || $type === 'customer')) {
                    $sql .= " AND type = :type";
                    $params[':type'] = $type;
                }

                $sql .= " ORDER BY name ASC LIMIT 15"; // Giới hạn kết quả

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $partners]);
                break; // End search case


            default:
                 if ($action !== null) { // Chỉ báo lỗi nếu action được cung cấp nhưng không hợp lệ
                     throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action specified.');
                 }
                 // Nếu không có action, có thể là yêu cầu lấy toàn bộ danh sách (nếu có)
                 // Hoặc đơn giản là không làm gì cả / trả lỗi
                 break; // Hoặc throw exception nếu mọi request GET phải có action hợp lệ
        } // End GET switch

    } elseif ($method === 'POST') {
         // ... (Xử lý các action POST như add, edit, delete...) ...
         switch ($action) {
             // ... (Các case POST khác) ...
             default:
                 throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action specified.');
         }

    } else {
        http_response_code(405);
        throw new RuntimeException($lang['invalid_request_method'] ?? 'Invalid request method.');
    }

} catch (PDOException $e) {
    error_log("Database Error in partners_handler.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $lang['database_error'] ?? 'Database error.']);
} catch (InvalidArgumentException | RuntimeException $e) {
     http_response_code(400); // Bad Request or other client/logic error
     error_log("Error in partners_handler.php: " . $e->getMessage());
     echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Unexpected Error in partners_handler.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $lang['server_error'] ?? 'Unexpected server error.']);
}

exit;
?>
