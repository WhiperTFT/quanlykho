<?php
// File: process/sales_quote_handler.php

// Dòng 1: BẮT BUỘC LÀ DÒNG ĐẦU TIÊN NẾU DÙNG
declare(strict_types=1); 

// Dòng 2, 3, 4: Cài đặt error reporting (CHỈ CHO DEVELOPMENT - XÓA KHI LÊN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Dòng 6, 7: Includes
require_once __DIR__ . '/../includes/init.php'; // init.php phải cung cấp $pdo, $lang, và các hàm cần thiết
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/admin_check.php'; // Bỏ comment nếu cần

// Dòng 10: QUAN TRỌNG - Set header Content-Type SỚM NHẤT CÓ THỂ và CHỈ MỘT LẦN
if (!headers_sent()) { 
    header('Content-Type: application/json');
}

// Dòng 12, 13, 14: Lấy action, method, userId
$action = $_REQUEST['action'] ?? null; 
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'] ?? null; // Đảm bảo session đã được start trong init.php

// Dòng 17-31: function generateQuoteNumber(...) - GIỮ NGUYÊN TỪ FILE CỦA BẠN
if (!function_exists('generateQuoteNumber')) { // Tránh redefine nếu init.php đã có
    function generateQuoteNumber(PDO $pdo, string $prefix = 'BG-STV'): string {
        $datePart = date('dmY');
        $prefixWithDate = $prefix . '-' . $datePart . '-';
        $sql = "SELECT quote_number FROM sales_quotes WHERE quote_number LIKE :prefix ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':prefix' => $prefixWithDate . '%']);
        $lastNumber = $stmt->fetchColumn();
        $nextSeq = 1;
        if (is_string($lastNumber) && $lastNumber !== '') {
            if (preg_match('/-(\d+)$/', $lastNumber, $matches)) {
                $lastSeq = (int)$matches[1];
                $nextSeq = $lastSeq + 1;
            }
        }
        return $prefixWithDate . str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
    }
}
// *** THÊM HÀM NÀY ĐỂ TÍNH TOÁN LẠI TỔNG ***
function calculateTotals(array $validItems, float $vatRate): array {
    $totalSub = 0.00;
    foreach ($validItems as $item) {
        // Chỉ cần số lượng và đơn giá từ item đã validate
        $quantity = $item['quantity'];
        $unitPrice = $item['unit_price'];
        $lineSubTotal = round($quantity * $unitPrice, 2);
        $totalSub += $lineSubTotal;
    }

    $totalVat = round($totalSub * ($vatRate / 100.0), 2);
    $totalGrand = round($totalSub + $totalVat, 2);

    return [
        'sub_total' => $totalSub,
        'vat_total' => $totalVat,
        'grand_total' => $totalGrand
    ];
}
// *** KẾT THÚC THÊM HÀM ***


// Khởi tạo $response mặc định ở đầu, trước khối try chính
$response = ['success' => false, 'message' => ($lang['invalid_request'] ?? 'Invalid request or action not specified.')];
$http_status_code = 400; // Mặc định Bad Request

try {
    if (!$pdo) { // Kiểm tra $pdo từ init.php
        throw new PDOException($lang['database_connection_failed'] ?? "Database connection not established.");
    }

    if ($method === 'POST') {
        // File sales_quote_handler.php của bạn dùng $_POST cho add/edit.
        // Chúng ta sẽ giả định tất cả các action POST đều dùng $_POST.
        // Nếu một action nào đó (như add/edit phức tạp) dùng JSON body, bạn cần điều chỉnh cách lấy $input cho action đó.
        $input_data = $_POST; // Dùng $_POST làm nguồn dữ liệu chính cho các action POST
        if ($method === 'GET' && $action === 'get_details') {
    // Lấy ID báo giá từ request
    $quoteId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

    if ($quoteId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mã báo giá không hợp lệ.']);
        exit;
    }

    try {
        // Kết nối PDO và truy vấn dữ liệu chính
        $stmt = $pdo->prepare("SELECT * FROM sales_quotes WHERE id = ?");
        $stmt->execute([$quoteId]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quote) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy báo giá.']);
            exit;
        }

        // Lấy chi tiết sản phẩm đi kèm
        $stmtDetails = $pdo->prepare("SELECT * FROM sales_quote_items WHERE quote_id = ?");
        $stmtDetails->execute([$quoteId]);
        $items = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'quote' => $quote,
                'items' => $items
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        error_log("Lỗi khi lấy chi tiết báo giá: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống khi truy vấn báo giá.']);
        exit;
    }
}

        switch ($action) {
            case 'add':
            case 'edit': // Logic add và edit của bạn có vẻ dùng chung nhiều phần
                // Lấy dữ liệu từ $input_data (tức là $_POST)
                $quoteId = filter_var($input_data['id'] ?? ($input_data['quote_id'] ?? null), FILTER_VALIDATE_INT) ?: null;
                $customerId = filter_var($input_data['partner_id'] ?? null, FILTER_VALIDATE_INT);
                $quoteDateStr = $input_data['quote_date'] ?? null;
                $quoteNumber = trim($input_data['quote_number'] ?? '');
                $currency = trim($input_data['currency'] ?? 'VND');
                $notes = trim($input_data['notes'] ?? '') ?: null;
                $vatRateStr = $input_data['vat_rate'] ?? '10';
                $vatRate = filter_var($vatRateStr, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                $items = $input_data['items'] ?? []; // JS cần gửi items dạng mảng các object
                                                 // Nếu JS gửi items dạng JSON string, cần json_decode ở đây.
                                                 // File sales_quotes.js của bạn có getFormData() tạo object, sau đó JSON.stringify
                                                 // Vậy ở đây phải đọc từ php://input nếu JS gửi application/json
                
                // NẾU JS GỬI JSON CHO ADD/EDIT (như sales_quotes.js làm):
                if (isset($_SERVER["CONTENT_TYPE"]) && stripos($_SERVER["CONTENT_TYPE"], 'application/json') !== false) {
                    $json_input = json_decode(file_get_contents('php://input'), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new InvalidArgumentException($lang['invalid_request_data_format'] ?? 'Invalid request data format (JSON expected).');
                    }
                    // Ghi đè các biến đã lấy từ $_POST bằng dữ liệu từ JSON body
                    $quoteId = filter_var($json_input['quote_id'] ?? ($json_input['id'] ?? null), FILTER_VALIDATE_INT) ?: null;
                    $customerId = filter_var($json_input['partner_id'] ?? null, FILTER_VALIDATE_INT);
                    $quoteDateStr = $json_input['quote_date'] ?? null;
                    $quoteNumber = trim($json_input['quote_number'] ?? '');
                    $currency = trim($json_input['currency'] ?? 'VND');
                    $notes = trim($json_input['notes'] ?? '') ?: null;
                    $vatRateStr = $json_input['vat_rate'] ?? '10';
                    $vatRate = filter_var($vatRateStr, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                    $items = $json_input['items'] ?? [];
                }


                // --- Validation (chung cho add và edit) ---
                $errors = [];
                if (!$customerId) $errors['partner_autocomplete'][] = $lang['customer_required'] ?? 'Customer is required.';
                if (empty($quoteDateStr)) {
                    $errors['document_date_display'][] = $lang['quote_date_required'] ?? 'Quote date is required.';
                    $quoteDateSql = null;
                } else {
                    $dateObj = DateTime::createFromFormat($user_date_format_php ?? 'd/m/Y', $quoteDateStr); // $user_date_format_php từ init.php
                    if (!$dateObj || $dateObj->format($user_date_format_php ?? 'd/m/Y') !== $quoteDateStr) {
                        $errors['document_date_display'][] = sprintf($lang['invalid_date_format_expected'] ?? 'Invalid date format. Expected: %s.', ($user_date_format_php ?? 'dd/mm/yyyy'));
                        $quoteDateSql = null;
                    } else {
                        $quoteDateSql = $dateObj->format('Y-m-d');
                    }
                }
                if (empty($quoteNumber)) $errors['document_number_display'][] = $lang['quote_number_required'] ?? 'Quote number is required.';
                if (!in_array($currency, ['VND', 'USD'])) $errors['currency_select'][] = $lang['invalid_currency'] ?? 'Invalid currency selected.';
                if ($vatRate === null || $vatRate < 0 || $vatRate > 100) { // Sửa kiểm tra vatRate
                    $errors['summary-vat-rate'][] = $lang['invalid_vat_rate'] ?? 'Invalid VAT rate (0-100).';
                }
                if (empty($items) || !is_array($items)) $errors['items_general'][] = $lang['quote_must_have_items'] ?? 'Quote must have at least one item.';
                
                // --- Validate items (chung) ---
                $validItems = [];
                foreach ($items as $index => $item) { /* ... (logic validate item của bạn, bỏ VAT rate của item) ... */ 
                    $itemErrors = [];
                    $detailId = filter_var($item['detail_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $productName = trim($item['product_name_snapshot'] ?? '');
                    $quantity = filter_var($item['quantity'] ?? '0', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                    $unitPrice = filter_var($item['unit_price'] ?? '0', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

                    if (empty($productName) && !$productId) $itemErrors[] = $lang['product_name_required'] ?? 'Product name required.';
                    if ($quantity === null || $quantity <= 0) $itemErrors[] = $lang['invalid_quantity'] ?? 'Invalid or zero quantity.';
                    if ($unitPrice === null || $unitPrice < 0) $itemErrors[] = $lang['invalid_unit_price'] ?? 'Invalid unit price.';
                    
                    if (!empty($itemErrors)) {
                        $errors["items"][$index] = $itemErrors;
                    } else {
                        $validItems[] = [
                            'detail_id' => $detailId, 'product_id' => $productId,
                            'product_name_snapshot' => $productName,
                            'category_snapshot' => trim($item['category_snapshot'] ?? ''),
                            'unit_snapshot' => trim($item['unit_snapshot'] ?? ''),
                            'quantity' => $quantity, 'unit_price' => $unitPrice,
                        ];
                    }
                }
                if (empty($validItems) && empty($errors['items_general'])) {
                     $errors['items_general'][] = $lang['quote_must_have_valid_items'] ?? 'Quote must have at least one valid item.';
                }

                if (!empty($errors)) {
                    $http_status_code = 422; // Unprocessable Entity
                    $response = ['success' => false, 'message' => $lang['validation_failed'] ?? 'Validation failed.', 'errors' => $errors];
                    break; // Thoát khỏi switch, response sẽ được echo ở cuối
                }

                // Tính toán tổng (chung)
                $calculatedTotals = calculateTotals($validItems, $vatRate ?? 0.0); // Truyền $vatRate đã validate
                $totalSub = $calculatedTotals['sub_total'];
                $totalVat = $calculatedTotals['vat_total'];
                $totalGrand = $calculatedTotals['grand_total'];

                // Snapshot info (chung)
                $stmt_comp = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
                $companyInfoData = $stmt_comp->fetch(PDO::FETCH_ASSOC) ?: [];
                $companyInfoSnapshot = json_encode($companyInfoData, JSON_UNESCAPED_UNICODE);

                $stmt_cust = $pdo->prepare("SELECT * FROM partners WHERE id = :id AND type = 'customer'");
                $stmt_cust->execute([':id' => $customerId]);
                $customerInfoData = $stmt_cust->fetch(PDO::FETCH_ASSOC) ?: [];
                if (empty($customerInfoData)) {
                    throw new InvalidArgumentException($lang['invalid_customer'] ?? 'Invalid customer selected.');
                }
                $customerInfoSnapshot = json_encode($customerInfoData, JSON_UNESCAPED_UNICODE);
                
                // === THỰC THI ADD HOẶC EDIT ===
                $pdo->beginTransaction(); // Bắt đầu transaction cho DB operations
                if ($action === 'add') {
                    // ... (check_duplicate_quote_number của bạn) ...
                    if (isset($input['force_create']) && $input['force_create'] === 'true') {
                        // Bỏ qua kiểm tra trùng nếu force_create
                    } else {
                        $stmt_check_num = $pdo->prepare("SELECT 1 FROM sales_quotes WHERE quote_number = :num LIMIT 1");
                        $stmt_check_num->execute([':num' => $quoteNumber]);
                        if ($stmt_check_num->fetchColumn()) {
                            $newSuggNumber = generateQuoteNumber($pdo);
                            throw new RuntimeException(sprintf($lang['quote_number_exists_suggest'] ?? 'Quote number %s already exists. Suggestion: %s.', $quoteNumber, $newSuggNumber), 409);
                        }
                    }

                    $sql_quote = "INSERT INTO sales_quotes (
                                    quote_number, quote_date, customer_id, currency, vat_rate, 
                                    company_info_snapshot, customer_info_snapshot, notes, 
                                    sub_total, vat_total, grand_total, 
                                    status, created_by, created_at, updated_at
                                ) VALUES (
                                    :quote_number, :quote_date, :customer_id, :currency, :vat_rate, 
                                    :company_info, :customer_info, :notes, 
                                    :sub_total, :vat_total, :grand_total, 
                                    :status, :created_by, NOW(), NOW()
                                )";
                    $stmt_quote = $pdo->prepare($sql_quote);
                    $stmt_quote->execute([
                        ':quote_number' => $quoteNumber, ':quote_date' => $quoteDateSql,
                        ':customer_id' => $customerId, ':currency' => $currency, ':vat_rate' => $vatRate,
                        ':company_info' => $companyInfoSnapshot, ':customer_info' => $customerInfoSnapshot,
                        ':notes' => $notes, ':sub_total' => $totalSub, ':vat_total' => $totalVat,
                        ':grand_total' => $totalGrand, ':status' => 'draft', ':created_by' => $userId
                    ]);
                    $quoteId = (int)$pdo->lastInsertId();

                    // Insert items
                    $sql_item_insert = "INSERT INTO sales_quote_details (quote_id, product_id, product_name_snapshot, category_snapshot, unit_snapshot, quantity, unit_price) VALUES (:quote_id, :product_id, :name, :category, :unit, :quantity, :price)";
                    $stmt_item_insert = $pdo->prepare($sql_item_insert);
                    foreach ($validItems as $itemData) {
                        $stmt_item_insert->execute([
                            ':quote_id' => $quoteId, /* ... các bind khác cho itemData ... */
                            ':product_id' => $itemData['product_id'], ':name' => $itemData['product_name_snapshot'],
                            ':category' => $itemData['category_snapshot'], ':unit' => $itemData['unit_snapshot'],
                            ':quantity' => $itemData['quantity'], ':price' => $itemData['unit_price']
                        ]);
                    }
                    $message = $lang['quote_created_success'] ?? 'Quote created successfully.';
                    $response = ['success' => true, 'message' => $message, 'quote_id' => $quoteId];
                    $http_status_code = 201;

                } else { // $action === 'edit'
                    if (!$quoteId) throw new InvalidArgumentException($lang['invalid_request_edit_quote'] ?? 'Invalid quote ID for edit.');
                    // ... (check status 'draft' của bạn) ...
                    $stmt_status_check = $pdo->prepare("SELECT status FROM sales_quotes WHERE id = :id");
                    $stmt_status_check->execute([':id' => $quoteId]);
                    $currentDBStatus_edit = $stmt_status_check->fetchColumn();
                    if (!$currentDBStatus_edit) throw new RuntimeException($lang['quote_not_found'] ?? 'Quote not found.');
                    if ($currentDBStatus_edit !== 'draft') {
                         throw new RuntimeException(sprintf($lang['cannot_edit_quote_status'] ?? 'Cannot edit quote with status "%s".', $currentDBStatus_edit), 403);
                    }

                    $sql_quote_update = "UPDATE sales_quotes SET 
                                        quote_number = :quote_number, quote_date = :quote_date, customer_id = :customer_id, 
                                        currency = :currency, vat_rate = :vat_rate, notes = :notes, 
                                        sub_total = :sub_total, vat_total = :vat_total, grand_total = :grand_total,
                                        company_info_snapshot = :company_info, customer_info_snapshot = :customer_info,
                                        status = :status, -- Giữ nguyên status 'draft' hoặc cho phép cập nhật nếu cần
                                        updated_at = NOW()
                                     WHERE id = :id AND status = 'draft'";
                    $stmt_quote_update = $pdo->prepare($sql_quote_update);
                    $stmt_quote_update->execute([
                        /* ... các bind params tương tự như INSERT, thêm :id ... */
                        ':quote_number' => $quoteNumber, ':quote_date' => $quoteDateSql,
                        ':customer_id' => $customerId, ':currency' => $currency, ':vat_rate' => $vatRate,
                        ':notes' => $notes, ':sub_total' => $totalSub, ':vat_total' => $totalVat,
                        ':grand_total' => $totalGrand, ':company_info' => $companyInfoSnapshot,
                        ':customer_info' => $customerInfoSnapshot, 
                        ':status' => $input_data['status'] ?? $currentDBStatus_edit, // Giữ nguyên status là draft, hoặc lấy từ input nếu bạn cho phép sửa status ở form edit
                        ':id' => $quoteId
                    ]);
                    
                    // ... (logic synchronize items của bạn: update, insert, delete details) ...
                    // Ví dụ:
                    $stmt_existing = $pdo->prepare("SELECT id FROM sales_quote_details WHERE quote_id = :quote_id");
                    $stmt_existing->execute([':quote_id' => $quoteId]);
                    $existingDbItemIds = $stmt_existing->fetchAll(PDO::FETCH_COLUMN);
                    $submittedItemDetailIds = [];

                    $sql_item_upd = "UPDATE sales_quote_details SET product_id = :pid, product_name_snapshot=:pname, category_snapshot=:pcat, unit_snapshot=:punit, quantity=:pqty, unit_price=:pprice WHERE id = :detail_id AND quote_id = :quote_id";
                    $stmt_item_upd = $pdo->prepare($sql_item_upd);
                    $sql_item_ins = "INSERT INTO sales_quote_details (quote_id, product_id, product_name_snapshot, category_snapshot, unit_snapshot, quantity, unit_price) VALUES (:quote_id, :pid, :pname, :pcat, :punit, :pqty, :pprice)";
                    $stmt_item_ins = $pdo->prepare($sql_item_ins);

                    foreach ($validItems as $itemData) {
                        $detail_id = $itemData['detail_id'];
                        $item_params = [
                            ':quote_id' => $quoteId, ':pid' => $itemData['product_id'], ':pname' => $itemData['product_name_snapshot'],
                            ':pcat' => $itemData['category_snapshot'], ':punit' => $itemData['unit_snapshot'],
                            ':pqty' => $itemData['quantity'], ':pprice' => $itemData['unit_price']
                        ];
                        if ($detail_id && in_array($detail_id, $existingDbItemIds)) {
                            $item_params[':detail_id'] = $detail_id;
                            $stmt_item_upd->execute($item_params);
                            $submittedItemDetailIds[] = $detail_id;
                        } else {
                            $stmt_item_ins->execute($item_params);
                        }
                    }
                    $itemsToDelete = array_diff($existingDbItemIds, $submittedItemDetailIds);
                    if (!empty($itemsToDelete)) {
                        $placeholders_del = implode(',', array_fill(0, count($itemsToDelete), '?'));
                        $sql_item_del = "DELETE FROM sales_quote_details WHERE id IN ($placeholders_del) AND quote_id = ?";
                        $stmt_item_del = $pdo->prepare($sql_item_del);
                        $stmt_item_del->execute(array_merge($itemsToDelete, [$quoteId]));
                    }
                    // --- Kết thúc synchronize items ---
                    
                    $message = $lang['quote_updated_success'] ?? 'Quote updated successfully.';
                    $response = ['success' => true, 'message' => $message, 'quote_id' => $quoteId];
                    $http_status_code = 200;
                }
                $pdo->commit(); // Commit transaction cho add hoặc edit
                break; // Kết thúc case 'add': và case 'edit':

            // =======================================================
            // === CASE 'update_status' (Đã sửa để dùng $_POST và gán $response) ===
            // =======================================================
            case 'update_status':
                // $pdo->beginTransaction(); // Bắt đầu ở đây nếu chưa có transaction chung cho POST
                
                $quoteId_us = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $newStatus_us = trim((string)filter_input(INPUT_POST, 'new_status'));

                if (!$quoteId_us) {
                    throw new InvalidArgumentException($lang['invalid_quote_id'] ?? 'Invalid Quote ID.');
                }

                $allowed_statuses_us = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'invoiced', 'cancelled'];
                if (empty($newStatus_us) || !in_array($newStatus_us, $allowed_statuses_us)) {
                    throw new InvalidArgumentException(sprintf($lang['invalid_status_value'] ?? 'Invalid status value: %s.', htmlspecialchars($newStatus_us)));
                }
                
                $stmt_current_status_us = $pdo->prepare("SELECT status FROM sales_quotes WHERE id = :id");
                $stmt_current_status_us->execute([':id' => $quoteId_us]);
                $currentDBStatus_us = $stmt_current_status_us->fetchColumn();

                if (!$currentDBStatus_us) {
                    throw new RuntimeException($lang['quote_not_found'] ?? 'Quote not found.');
                }
                
                $valid_transitions_us = [ /* ... (như đã định nghĩa ở trên) ... */ 
                    'draft'    => ['sent', 'cancelled'], 'sent'     => ['accepted', 'rejected', 'expired', 'draft'], 
                    'accepted' => ['invoiced', 'sent', 'draft'], 'rejected' => ['draft'],    
                    'expired'  => ['draft', 'sent'], 'invoiced' => [], 'cancelled'=> ['draft']
                ];

                if (!isset($valid_transitions_us[$currentDBStatus_us]) || !in_array($newStatus_us, $valid_transitions_us[$currentDBStatus_us])) {
                     throw new RuntimeException(sprintf($lang['invalid_status_transition'] ?? 'Cannot change status from %s to %s.', $currentDBStatus_us, $newStatus_us), 403); // 403 Forbidden
                }
                
                $sql_update_status_us = "UPDATE sales_quotes SET status = :new_status, updated_at = NOW() WHERE id = :id";
                $stmt_update_us = $pdo->prepare($sql_update_status_us);
                $stmt_update_us->execute([':new_status' => $newStatus_us, ':id' => $quoteId_us]);

                if ($stmt_update_us->rowCount() > 0) {
                    // $pdo->commit(); // Commit ở đây nếu transaction bắt đầu ở đây
                    $response = ['success' => true, 'message' => sprintf($lang['quote_status_updated_success'] ?? 'Quote status updated to %s.', $newStatus_us)];
                    $http_status_code = 200;
                } else {
                    if ($currentDBStatus_us === $newStatus_us) {
                        // $pdo->commit(); 
                        $response = ['success' => true, 'message' => sprintf($lang['quote_status_already_is'] ?? 'Quote status is already %s.', $newStatus_us)];
                        $http_status_code = 200;
                    } else {
                        // $pdo->rollBack(); 
                        $response = ['success' => false, 'message' => $lang['quote_status_not_changed_or_unknown_issue'] ?? 'Quote status not changed or issue.'];
                        $http_status_code = 400; 
                    }
                }
                break; 
            // =======================================================
            // === KẾT THÚC CASE 'update_status' ===
            // =======================================================

            case 'delete':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid quote ID.');

                $pdo->beginTransaction();
                try {
                    $stmt_get = $pdo->prepare("SELECT quote_number, status FROM sales_quotes WHERE id = :id");
                    $stmt_get->execute([':id' => $id]);
                    $quoteInfo = $stmt_get->fetch(PDO::FETCH_ASSOC);

                    if (!$quoteInfo) throw new RuntimeException($lang['quote_not_found'] ?? 'Quote not found.');
                    if ($quoteInfo['status'] !== 'draft') {
                        throw new RuntimeException(sprintf($lang['cannot_delete_quote_status'] ?? 'Cannot delete quote with status "%s". Only draft quotes can be deleted.', $quoteInfo['status']), 403);
                    }

                    $stmt_delete_details = $pdo->prepare("DELETE FROM sales_quote_details WHERE quote_id = :id");
                    $stmt_delete_details->execute([':id' => $id]);

                    $stmt_delete_quote = $pdo->prepare("DELETE FROM sales_quotes WHERE id = :id AND status = 'draft'");
                    $stmt_delete_quote->execute([':id' => $id]);

                    if ($stmt_delete_quote->rowCount() > 0) {
                        $pdo->commit();
                        echo json_encode(['success' => true, 'message' => $lang['quote_deleted_success'] ?? 'Quote deleted successfully.']);
                        exit;
                    } else {
                        throw new RuntimeException($lang['quote_not_found_or_cannot_delete'] ?? 'Quote not found or cannot be deleted (check status).');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    if ($e->getCode() === 403) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                        exit;
                    }
                    throw $e;
                }
                break;

            default:
                throw new InvalidArgumentException($lang['invalid_action_post'] ?? 'Invalid POST action specified.');
        } // End POST switch

    } elseif ($method === 'GET') {
        switch ($action) {
            case 'get_details':
    $quoteId_get = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$quoteId_get) {
        throw new InvalidArgumentException($lang['invalid_quote_id_for_details'] ?? 'Invalid quote ID for details.');
    }

    // Lấy thông tin header của quote
    $stmt_header = $pdo->prepare("
        SELECT sq.*, p.name as customer_name 
        FROM sales_quotes sq 
        LEFT JOIN partners p ON sq.customer_id = p.id 
        WHERE sq.id = :id
    ");
    $stmt_header->execute([':id' => $quoteId_get]);
    $quoteHeader = $stmt_header->fetch(PDO::FETCH_ASSOC);

    if (!$quoteHeader) {
        throw new RuntimeException($lang['quote_not_found'] ?? 'Quote not found.');
    }

    // Format ngày nếu cần
    if (!empty($quoteHeader['quote_date'])) {
        $quoteHeader['quote_date_formatted'] = (new DateTime($quoteHeader['quote_date']))->format('d/m/Y');
    }

    // Decode snapshot nếu có
    if (!empty($quoteHeader['customer_info_snapshot'])) {
        $quoteHeader['customer_info_data'] = json_decode($quoteHeader['customer_info_snapshot'], true);
    }
    

    // Lấy chi tiết items
    $stmt_details = $pdo->prepare("SELECT * FROM sales_quote_details WHERE quote_id = :id");
    $stmt_details->execute([':id' => $quoteId_get]);
    $quoteDetails = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'success' => true,
        'data' => [
            'header' => $quoteHeader,
            'details' => $quoteDetails
        ]
    ];
    $http_status_code = 200;
    break;
            case 'generate_quote_number':
                $newNumber = generateQuoteNumber($pdo);
                $response = ['success' => true, 'quote_number' => $newNumber];
                $http_status_code = 200;
                break;
            default:
                throw new InvalidArgumentException($lang['invalid_action_get'] ?? 'Invalid GET action specified.');
        } // End GET switch
    } else {
        $http_status_code = 405; 
        throw new RuntimeException($lang['invalid_request_method'] ?? 'Invalid request method.');
    }

} catch (PDOException $e) { 
    error_log("Database Error in " . basename(__FILE__) . " (Action: $action): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString()); 
    $response['message'] = $lang['database_error_details'] ?? 'Database error. Please check logs.';
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); } // Rollback nếu lỗi DB trong transaction
    $http_status_code = 500;
} catch (InvalidArgumentException | RuntimeException $e) { 
    $response['message'] = $e->getMessage();
    $http_status_code = $e->getCode() ?: 400; 
    if ($http_status_code < 400 || $http_status_code >= 600) { $http_status_code = ($e instanceof InvalidArgumentException ? 400 : 500); }
    if ($e instanceof RuntimeException && $http_status_code === 409 && method_exists($e, 'getSuggestion')) { // Giả sử có hàm getSuggestion
         $response['suggestion'] = $e->getSuggestion();
    }
    error_log("Client/Runtime Error in " . basename(__FILE__) . " (Action: $action, HTTP: $http_status_code): " . $e->getMessage());
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
} catch (Exception $e) { 
    error_log("Unexpected Error in " . basename(__FILE__) . " (Action: $action): " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $response['message'] = $lang['server_error_please_retry'] ?? 'Unexpected server error. Please try again or contact support.';
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $http_status_code = 500;
}

// Gửi HTTP status code trước khi echo JSON (nếu chưa gửi)
if (!headers_sent()) {
    http_response_code($http_status_code);
}
echo json_encode($response);
exit;
?>