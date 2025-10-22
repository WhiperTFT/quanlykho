<?php
// File: process/sales_quote_handler.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
if (!headers_sent()) {
    header('Content-Type: application/json');
}

$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'] ?? null;

// --- Khai báo các hàm tiện ích (nếu chưa có trong init.php) ---
if (!function_exists('generateQuoteNumber')) {
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

if (!function_exists('calculateTotals')) {
    function calculateTotals(array $validItems, float $vatRate): array {
        $totalSub = 0.00;
        foreach ($validItems as $item) {
            $quantity = floatval($item['quantity']); // Đảm bảo là số
            $unitPrice = floatval($item['unit_price']); // Đảm bảo là số
            $lineSubTotal = round($quantity * $unitPrice, 2);
            $totalSub += $lineSubTotal;
        }
        $totalVat = round($totalSub * ($vatRate / 100.0), 2);
        $totalGrand = round($totalSub + $totalVat, 2);
        return ['sub_total' => $totalSub, 'vat_total' => $totalVat, 'grand_total' => $totalGrand];
    }
}
// --- Kết thúc khai báo hàm tiện ích ---

$response = ['success' => false, 'message' => ($lang['invalid_request'] ?? 'Invalid request or action not specified.')];
$http_status_code = 400;

try {
    if (!$pdo) {
        throw new PDOException($lang['database_connection_failed'] ?? "Database connection not established.");
    }

    // Kiểm tra CSRF Token nếu bạn có cơ chế này
    // if (!verifyCsrfToken($_REQUEST['security_token'] ?? '')) {
    // throw new RuntimeException($lang['csrf_token_invalid'] ?? 'CSRF token không hợp lệ.', 403);
    // }

    if ($action) {
        switch ($action) {
            case 'get_details': // Action bạn đã có
                if ($method === 'GET' || $method === 'POST') {
                    $quoteId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
                    if ($quoteId <= 0) {
                        $response['message'] = $lang['invalid_quote_id'] ?? 'Mã báo giá không hợp lệ.';
                        $http_status_code = 400;
                    } else {
                        $stmt = $pdo->prepare("SELECT * FROM sales_quotes WHERE id = ?");
                        $stmt->execute([$quoteId]);
                        $quote = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$quote) {
                            $response['message'] = $lang['quote_not_found'] ?? 'Không tìm thấy báo giá.';
                            $http_status_code = 404;
                        } else {
                            // Lấy chi tiết từ sales_quote_details
                            $stmtDetails = $pdo->prepare("
                                SELECT id, quote_id, product_id, product_name_snapshot, 
                                       category_snapshot, unit_snapshot, quantity, unit_price 
                                FROM sales_quote_details 
                                WHERE quote_id = ? ORDER BY id ASC
                            ");
                            $stmtDetails->execute([$quoteId]);
                            $items = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

                            $response = ['success' => true, 'data' => ['quote' => $quote, 'items' => $items]];
                            $http_status_code = 200;
                        }
                    }
                } else {
                    $response['message'] = $lang['method_not_allowed'] ?? 'Phương thức không được phép.';
                    $http_status_code = 405;
                }
                break;

            case 'get_quote_details_for_order': // ACTION MỚI
                if ($method === 'POST') {
                    if (!isset($_POST['quote_id']) || !filter_var($_POST['quote_id'], FILTER_VALIDATE_INT)) {
                        $response['message'] = $lang['missing_quote_id'] ?? 'Thiếu ID báo giá hoặc ID không hợp lệ.';
                        $http_status_code = 400;
                    } else {
                        $quote_id_for_order = intval($_POST['quote_id']);
                        $stmt_check_status = $pdo->prepare("SELECT status FROM sales_quotes WHERE id = ?");
                        $stmt_check_status->execute([$quote_id_for_order]);
                        $quote_status_row = $stmt_check_status->fetch(PDO::FETCH_ASSOC);

                        if (!$quote_status_row) {
                            $response['message'] = $lang['quote_not_found'] ?? 'Báo giá không tồn tại.';
                            $http_status_code = 404;
                        } elseif ($quote_status_row['status'] !== 'accepted') {
                            $response['message'] = $lang['quote_not_accepted_cannot_create_order'] ?? 'Báo giá chưa được chấp nhận, không thể tạo đơn hàng.';
                            $http_status_code = 403;
                        } else {
                            $items_data_for_order = [];
                            $sql_details = "SELECT
                                               sqd.product_id,
                                               sqd.product_name_snapshot,
                                               sqd.category_snapshot,
                                               sqd.unit_snapshot,
                                               sqd.quantity,
                                               sqd.unit_price,
                                               cat.id as category_id,
                                               u.id as unit_id
                                           FROM sales_quote_details sqd
                                           LEFT JOIN categories cat ON sqd.category_snapshot = cat.name COLLATE utf8mb4_general_ci
                                           LEFT JOIN units u ON sqd.unit_snapshot = u.name COLLATE utf8mb4_general_ci
                                           WHERE sqd.quote_id = ?
                                           ORDER BY sqd.id ASC";
                            $stmt_items_for_order = $pdo->prepare($sql_details);
                            $stmt_items_for_order->execute([$quote_id_for_order]);
                            $details_from_db = $stmt_items_for_order->fetchAll(PDO::FETCH_ASSOC);

                            foreach ($details_from_db as $item) {
                                $items_data_for_order[] = [
                                    'product_id' => $item['product_id'],
                                    'item_name' => $item['product_name_snapshot'],
                                    'category_id' => $item['category_id'],
                                    'category_snapshot' => $item['category_snapshot'],
                                    'unit_id' => $item['unit_id'],
                                    'unit_snapshot' => $item['unit_snapshot'],
                                    'quantity' => $item['quantity'],
                                    'unit_price' => $item['unit_price']
                                ];
                            }
                            $response = ['success' => true, 'details' => $items_data_for_order, 'message' => ($lang['quote_details_fetched'] ?? 'Quote details fetched.')];
                            if (empty($details_from_db)) {
                                $response['message'] = $lang['quote_has_no_items'] ?? 'Báo giá không có sản phẩm nào.';
                            }
                            $http_status_code = 200;
                        }
                    }
                } else {
                    $response['message'] = $lang['method_not_allowed'] ?? 'Phương thức không được phép.';
                    $http_status_code = 405;
                }
                break;

            case 'add':
            case 'edit':
                if ($method === 'POST') {
                    $input_data = $_POST; // Mặc định lấy từ POST form-data
                    // Kiểm tra nếu client gửi JSON (như trong sales_orders.js của bạn)
                    if (isset($_SERVER["CONTENT_TYPE"]) && stripos($_SERVER["CONTENT_TYPE"], 'application/json') !== false) {
                        $json_input = json_decode(file_get_contents('php://input'), true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($json_input)) {
                            $input_data = $json_input; // Sử dụng dữ liệu JSON nếu hợp lệ
                        } else {
                             throw new InvalidArgumentException($lang['invalid_request_data_format_json'] ?? 'Invalid JSON data format.');
                        }
                    }

                    // ---- Lấy và Validate dữ liệu cho Add/Edit ----
                    $quoteId = filter_var($input_data['id'] ?? ($input_data['quote_id'] ?? null), FILTER_VALIDATE_INT) ?: null;
                    $customerId = filter_var($input_data['partner_id'] ?? null, FILTER_VALIDATE_INT);
                    $quoteDateStr = $input_data['quote_date'] ?? ($input_data['document_date_display'] ?? null); // Lấy từ document_date_display nếu có
                    $quoteNumber = trim($input_data['quote_number'] ?? ($input_data['document_number_display'] ?? ''));
                    $currency = trim($input_data['currency'] ?? 'VND');
                    $notes = trim($input_data['notes'] ?? '') ?: null;
                    $vatRateStr = $input_data['vat_rate'] ?? ($input_data['summary-vat-rate'] ?? '10'); // Lấy từ summary-vat-rate nếu có
                    $vatRate = filter_var($vatRateStr, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                    $items_input = $input_data['items'] ?? [];
                    $errors = [];

                    if (!$customerId) $errors['partner_autocomplete'] = $lang['customer_required'] ?? 'Customer is required.';
                    if (empty($quoteDateStr)) {
                        $errors['document_date_display'] = $lang['quote_date_required'] ?? 'Quote date is required.';
                        $quoteDateSql = null;
                    } else {
                        $dateObj = DateTime::createFromFormat($user_date_format_php ?? 'd/m/Y', $quoteDateStr);
                        if (!$dateObj || $dateObj->format($user_date_format_php ?? 'd/m/Y') !== $quoteDateStr) {
                            $errors['document_date_display'] = sprintf($lang['invalid_date_format_expected'] ?? 'Invalid date format. Expected: %s.', ($user_date_format_display ?? 'dd/mm/yyyy'));
                            $quoteDateSql = null;
                        } else {
                            $quoteDateSql = $dateObj->format('Y-m-d');
                        }
                    }
                    if (empty($quoteNumber)) $errors['document_number_display'] = $lang['quote_number_required'] ?? 'Quote number is required.';
                    if ($vatRate === null || $vatRate < 0 || $vatRate > 100) {
                         $errors['summary-vat-rate'] = $lang['invalid_vat_rate'] ?? 'Invalid VAT rate (0-100).';
                    }
                    if (empty($items_input) || !is_array($items_input)) {
                        $errors['items_general'] = $lang['quote_must_have_items'] ?? 'Quote must have at least one item.';
                    }

                    $validItems = [];
                    if (is_array($items_input)) {
                        foreach ($items_input as $index => $item) {
                            $item_productName = trim($item['item_name'] ?? ($item['product_name_snapshot'] ?? ''));
                            $item_quantity = filter_var($item['quantity'] ?? '0', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                            $item_unitPrice = filter_var($item['unit_price'] ?? '0', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                            $item_productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                            $item_detailId = filter_var($item['detail_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

                            if (empty($item_productName) && !$item_productId) $errors["items"][$index]['item_name'] = $lang['product_name_required'] ?? 'Product name required.';
                            if ($item_quantity === null || $item_quantity <= 0) $errors["items"][$index]['quantity'] = $lang['invalid_quantity'] ?? 'Invalid quantity.';
                            if ($item_unitPrice === null || $item_unitPrice < 0) $errors["items"][$index]['unit_price'] = $lang['invalid_unit_price'] ?? 'Invalid unit price.';

                            if (empty($errors["items"][$index])) {
                                $validItems[] = [
                                    'detail_id' => $item_detailId,
                                    'product_id' => $item_productId,
                                    'product_name_snapshot' => $item_productName,
                                    'category_snapshot' => trim($item['category_snapshot'] ?? ($item['category_id'] ?? '')), // Ưu tiên category_snapshot nếu có
                                    'unit_snapshot' => trim($item['unit_snapshot'] ?? ($item['unit_id'] ?? '')), // Ưu tiên unit_snapshot
                                    'quantity' => $item_quantity,
                                    'unit_price' => $item_unitPrice,
                                ];
                            }
                        }
                    }
                     if (empty($validItems) && !isset($errors['items_general'])) {
                        $errors['items_general'] = $lang['quote_must_have_valid_items'] ?? 'Quote must have at least one valid item.';
                    }


                    if (!empty($errors)) {
                        $response = ['success' => false, 'message' => $lang['validation_failed'] ?? 'Validation failed.', 'errors' => $errors];
                        $http_status_code = 422; // Unprocessable Entity
                        break; // Thoát khỏi switch
                    }
                    // ---- Kết thúc Validation ----

                    $calculatedTotals = calculateTotals($validItems, $vatRate ?? 0.0);
                    // ... (Phần snapshot company_info, customer_info của bạn) ...
                    $stmt_comp = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1"); // Giả sử ID công ty luôn là 1
                    $companyInfoData = $stmt_comp->fetch(PDO::FETCH_ASSOC) ?: [];
                    $companyInfoSnapshot = json_encode($companyInfoData, JSON_UNESCAPED_UNICODE);

                    $stmt_cust = $pdo->prepare("SELECT * FROM partners WHERE id = :id AND type = 'customer'");
                    $stmt_cust->execute([':id' => $customerId]);
                    $customerInfoData = $stmt_cust->fetch(PDO::FETCH_ASSOC) ?: [];
                    if (empty($customerInfoData)) {
                        throw new InvalidArgumentException($lang['invalid_customer_not_found'] ?? 'Customer not found or not a customer type.');
                    }
                    $customerInfoSnapshot = json_encode($customerInfoData, JSON_UNESCAPED_UNICODE);


                    $pdo->beginTransaction();
                    if ($action === 'add') {
                        // ... (Kiểm tra trùng quote_number của bạn) ...
                        if (!($input_data['force_create'] ?? false)) { // Kiểm tra force_create
                            $stmt_check_num = $pdo->prepare("SELECT 1 FROM sales_quotes WHERE quote_number = :num LIMIT 1");
                            $stmt_check_num->execute([':num' => $quoteNumber]);
                            if ($stmt_check_num->fetchColumn()) {
                                $newSuggNumber = generateQuoteNumber($pdo); // Sử dụng hàm đã khai báo
                                throw new RuntimeException(sprintf($lang['quote_number_exists_suggest'] ?? 'Quote number %s already exists. Suggestion: %s.', $quoteNumber, $newSuggNumber), 409); // 409 Conflict
                            }
                        }

                        $sql_quote = "INSERT INTO sales_quotes (quote_number, quote_date, customer_id, currency, vat_rate, company_info_snapshot, customer_info_snapshot, notes, sub_total, vat_total, grand_total, status, created_by, created_at, updated_at) VALUES (:qn, :qd, :cid, :cur, :vr, :compinfo, :custinfo, :notes, :sub, :vat, :grand, 'draft', :uid, NOW(), NOW())";
                        $stmt_quote = $pdo->prepare($sql_quote);
                        $stmt_quote->execute([
                            ':qn' => $quoteNumber, ':qd' => $quoteDateSql, ':cid' => $customerId,
                            ':cur' => $currency, ':vr' => $vatRate,
                            ':compinfo' => $companyInfoSnapshot, ':custinfo' => $customerInfoSnapshot,
                            ':notes' => $notes,
                            ':sub' => $calculatedTotals['sub_total'], ':vat' => $calculatedTotals['vat_total'],
                            ':grand' => $calculatedTotals['grand_total'], ':uid' => $userId
                        ]);
                        $newQuoteId = (int)$pdo->lastInsertId();

                        $sql_item_insert = "INSERT INTO sales_quote_details (quote_id, product_id, product_name_snapshot, category_snapshot, unit_snapshot, quantity, unit_price) VALUES (:qid, :pid, :pname, :pcat, :punit, :pqty, :price)";
                        $stmt_item_insert = $pdo->prepare($sql_item_insert);
                        foreach ($validItems as $itemData) {
                            $stmt_item_insert->execute([
                                ':qid' => $newQuoteId,
                                ':pid' => $itemData['product_id'], ':pname' => $itemData['product_name_snapshot'],
                                ':pcat' => $itemData['category_snapshot'], ':punit' => $itemData['unit_snapshot'],
                                ':pqty' => $itemData['quantity'], ':price' => $itemData['unit_price']
                            ]);
                        }
                        $pdo->commit();
                        $response = ['success' => true, 'message' => $lang['quote_created_success'] ?? 'Quote created successfully.', 'quote_id' => $newQuoteId];
                        write_user_log($pdo, $userId, 'add_quote', "Tạo báo giá #$newQuoteId - $quoteNumber cho khách hàng ID $customerId");

                        $http_status_code = 201; // Created
                    } else { // action === 'edit'
                        if (!$quoteId) throw new InvalidArgumentException($lang['invalid_quote_id_for_edit'] ?? 'Invalid quote ID for edit.');
                        // ... (Kiểm tra status 'draft' trước khi sửa) ...
                        $stmt_status_check = $pdo->prepare("SELECT status FROM sales_quotes WHERE id = :id");
                        $stmt_status_check->execute([':id' => $quoteId]);
                        $currentDBStatus_edit = $stmt_status_check->fetchColumn();
                        if (!$currentDBStatus_edit) throw new RuntimeException($lang['quote_not_found_for_edit'] ?? 'Quote not found for edit.');
                        if ($currentDBStatus_edit !== 'draft') {
                             throw new RuntimeException(sprintf($lang['cannot_edit_quote_status_not_draft'] ?? 'Cannot edit quote. Status is "%s", not "draft".', $currentDBStatus_edit), 403); // Forbidden
                        }

                        $sql_quote_update = "UPDATE sales_quotes SET quote_number = :qn, quote_date = :qd, customer_id = :cid, currency = :cur, vat_rate = :vr, company_info_snapshot = :compinfo, customer_info_snapshot = :custinfo, notes = :notes, sub_total = :sub, vat_total = :vat, grand_total = :grand, status = :status, updated_at = NOW() WHERE id = :id AND status = 'draft'";
                        $stmt_quote_update = $pdo->prepare($sql_quote_update);
                        $stmt_quote_update->execute([
                            ':qn' => $quoteNumber, ':qd' => $quoteDateSql, ':cid' => $customerId,
                            ':cur' => $currency, ':vr' => $vatRate,
                            ':compinfo' => $companyInfoSnapshot, ':custinfo' => $customerInfoSnapshot,
                            ':notes' => $notes,
                            ':sub' => $calculatedTotals['sub_total'], ':vat' => $calculatedTotals['vat_total'],
                            ':grand' => $calculatedTotals['grand_total'],
                            ':status' => $input_data['status'] ?? $currentDBStatus_edit, // Giữ nguyên 'draft' hoặc cho phép sửa nếu logic hỗ trợ
                            ':id' => $quoteId
                        ]);

                        // Synchronize items (update, insert, delete)
                        $stmt_existing = $pdo->prepare("SELECT id FROM sales_quote_details WHERE quote_id = :quote_id");
                        $stmt_existing->execute([':quote_id' => $quoteId]);
                        $existingDbItemIds = $stmt_existing->fetchAll(PDO::FETCH_COLUMN);
                        $submittedItemDetailIds = [];

                        $sql_item_upd = "UPDATE sales_quote_details SET product_id = :pid, product_name_snapshot=:pname, category_snapshot=:pcat, unit_snapshot=:punit, quantity=:pqty, unit_price=:pprice WHERE id = :detail_id AND quote_id = :qid";
                        $stmt_item_upd = $pdo->prepare($sql_item_upd);
                        $sql_item_ins = "INSERT INTO sales_quote_details (quote_id, product_id, product_name_snapshot, category_snapshot, unit_snapshot, quantity, unit_price) VALUES (:qid, :pid, :pname, :pcat, :punit, :pqty, :pprice)";
                        $stmt_item_ins = $pdo->prepare($sql_item_ins);

                        foreach ($validItems as $itemData) {
                            $detail_id = $itemData['detail_id'];
                            $item_params_sync = [
                                ':qid' => $quoteId, ':pid' => $itemData['product_id'],
                                ':pname' => $itemData['product_name_snapshot'],
                                ':pcat' => $itemData['category_snapshot'], ':punit' => $itemData['unit_snapshot'],
                                ':pqty' => $itemData['quantity'], ':pprice' => $itemData['unit_price']
                            ];
                            if ($detail_id && in_array($detail_id, $existingDbItemIds)) {
                                $item_params_sync[':detail_id'] = $detail_id;
                                $stmt_item_upd->execute($item_params_sync);
                                $submittedItemDetailIds[] = $detail_id;
                            } else {
                                $stmt_item_ins->execute($item_params_sync);
                                // $submittedItemDetailIds[] = (int)$pdo->lastInsertId(); // Lấy ID mới nếu cần
                            }
                        }
                        $itemsToDelete = array_diff($existingDbItemIds, $submittedItemDetailIds);
                        if (!empty($itemsToDelete)) {
                            $placeholders_del = implode(',', array_fill(0, count($itemsToDelete), '?'));
                            $sql_item_del = "DELETE FROM sales_quote_details WHERE id IN ($placeholders_del) AND quote_id = ?";
                            $stmt_item_del = $pdo->prepare($sql_item_del);
                            $stmt_item_del->execute(array_merge($itemsToDelete, [$quoteId]));
                        }
                        $pdo->commit();
                        $response = ['success' => true, 'message' => $lang['quote_updated_success'] ?? 'Quote updated successfully.', 'quote_id' => $quoteId];
                        write_user_log($pdo, $userId, 'edit_quote', "Cập nhật báo giá #$quoteId - $quoteNumber cho khách hàng ID $customerId");

                        $http_status_code = 200;
                    }
                } else {
                    $response['message'] = $lang['method_not_allowed'] ?? 'Phương thức không được phép.';
                    $http_status_code = 405;
                }
                break;

            case 'update_status':
                if ($method === 'POST') {
                    $quoteId_us = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    $newStatus_us = trim((string)filter_input(INPUT_POST, 'new_status'));
                    if (!$quoteId_us) throw new InvalidArgumentException($lang['invalid_quote_id'] ?? 'Invalid Quote ID.');
                    $allowed_statuses_us = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'invoiced', 'cancelled'];
                    if (empty($newStatus_us) || !in_array($newStatus_us, $allowed_statuses_us)) {
                        throw new InvalidArgumentException(sprintf($lang['invalid_status_value'] ?? 'Invalid status value: %s.', htmlspecialchars($newStatus_us)));
                    }
                    // ... (Logic update status của bạn, bao gồm kiểm tra transition hợp lệ) ...
                    // Ví dụ:
                    $stmt_current_status_us = $pdo->prepare("SELECT status FROM sales_quotes WHERE id = :id");
                    $stmt_current_status_us->execute([':id' => $quoteId_us]);
                    $currentDBStatus_us = $stmt_current_status_us->fetchColumn();
                    if (!$currentDBStatus_us) throw new RuntimeException($lang['quote_not_found_for_status_update'] ?? 'Quote not found for status update.');

                    // Thêm logic kiểm tra transition (ví dụ: từ 'draft' chỉ có thể sang 'sent')
                    // ...

                    $sql_update_status_us = "UPDATE sales_quotes SET status = :new_status, updated_at = NOW() WHERE id = :id";
                    $stmt_update_us = $pdo->prepare($sql_update_status_us);
                    $stmt_update_us->execute([':new_status' => $newStatus_us, ':id' => $quoteId_us]);

                    if ($stmt_update_us->rowCount() > 0) {
                        $response = ['success' => true, 'message' => sprintf($lang['quote_status_updated_success_to'] ?? 'Quote status updated to %s.', $newStatus_us)];
                        write_user_log($pdo, $userId, 'update_quote_status', "Cập nhật trạng thái báo giá #$quoteId_us từ $currentDBStatus_us → $newStatus_us");

                        $http_status_code = 200;
                    } else {
                        $response = ['success' => false, 'message' => $lang['quote_status_update_failed_or_no_change'] ?? 'Failed to update status or status was already the same.'];
                        // $http_status_code = 400; // Hoặc 200 nếu không thay đổi không phải lỗi
                    }
                } else {
                    $response['message'] = $lang['method_not_allowed'] ?? 'Phương thức không được phép.';
                    $http_status_code = 405;
                }
                break;

            case 'delete':
                if ($method === 'POST') {
                    $quoteId_del = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    if (!$quoteId_del) throw new InvalidArgumentException($lang['invalid_quote_id_for_delete'] ?? 'Invalid Quote ID for deletion.');
                    // ... (Logic delete của bạn, bao gồm kiểm tra status 'draft', xóa items, rồi xóa quote)
                    $pdo->beginTransaction();
                    // Ví dụ:
                    $stmt_check = $pdo->prepare("SELECT status FROM sales_quotes WHERE id = ?");
                    $stmt_check->execute([$quoteId_del]);
                    $status_del = $stmt_check->fetchColumn();
                    if ($status_del !== 'draft') {
                         $pdo->rollBack();
                         throw new RuntimeException(sprintf($lang['cannot_delete_quote_status_not_draft'] ?? 'Cannot delete quote. Status is "%s", not "draft".', $status_del), 403);
                    }
                    $stmt_del_items = $pdo->prepare("DELETE FROM sales_quote_details WHERE quote_id = ?");
                    $stmt_del_items->execute([$quoteId_del]);
                    $stmt_del_quote = $pdo->prepare("DELETE FROM sales_quotes WHERE id = ? AND status = 'draft'");
                    $deleted_rows = $stmt_del_quote->execute([$quoteId_del]);
                    if ($deleted_rows) {
                        $pdo->commit();
                        $response = ['success' => true, 'message' => $lang['quote_deleted_success'] ?? 'Quote deleted success.'];
                        write_user_log($pdo, $userId, 'delete_quote', "Xóa báo giá #$quoteId_del");

                        $http_status_code = 200;
                    } else {
                        $pdo->rollBack();
                        $response = ['success' => false, 'message' => $lang['quote_delete_failed_or_not_found'] ?? 'Failed to delete quote or quote not found/not in draft status.'];
                        $http_status_code = 404; // Hoặc 400
                    }
                } else {
                    $response['message'] = $lang['method_not_allowed'] ?? 'Phương thức không được phép.';
                    $http_status_code = 405;
                }
                break;

            case 'generate_quote_number': // Có thể là GET hoặc POST tùy bạn gọi từ JS
                if ($method === 'GET' || $method === 'POST') {
                     $newNumber = generateQuoteNumber($pdo); // Sử dụng hàm đã khai báo
                     $response = ['success' => true, 'quote_number' => $newNumber];
                     $http_status_code = 200;
                } else {
                    $response['message'] = $lang['method_not_allowed'] ?? 'Phương thức không được phép.';
                    $http_status_code = 405;
                }
                break;

            default:
                // $response và $http_status_code giữ nguyên giá trị mặc định (invalid_request)
                // không cần gán lại ở đây nếu đã khởi tạo ở đầu.
                // Hoặc có thể throw new InvalidArgumentException để catch block xử lý
                // throw new InvalidArgumentException($lang['unknown_action_specified'] ?? 'Unknown action specified.');
                break;
        }
    } else {
        // Nếu không có action nào được cung cấp, $response và $http_status_code giữ nguyên giá trị mặc định.
    }

} catch (PDOException $e) {
    error_log("Database Error in " . basename(__FILE__) . " (Action: $action): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $response = ['success' => false, 'message' => $lang['database_error_details'] ?? 'Lỗi cơ sở dữ liệu. Vui lòng kiểm tra log.'];
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $http_status_code = 500;
} catch (InvalidArgumentException $e) { // Lỗi dữ liệu đầu vào từ client
    $response = ['success' => false, 'message' => $e->getMessage()];
    $http_status_code = $e->getCode() ?: 400; // Mặc định 400 cho lỗi này
    if ($http_status_code < 400 || $http_status_code >= 600) $http_status_code = 400;
    error_log("InvalidArgumentException in " . basename(__FILE__) . " (Action: $action, HTTP: $http_status_code): " . $e->getMessage());
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
} catch (RuntimeException $e) { // Lỗi logic nghiệp vụ hoặc lỗi không mong muốn trong quá trình xử lý
    $response = ['success' => false, 'message' => $e->getMessage()];
    $http_status_code = $e->getCode() ?: 500; // Mặc định 500 cho lỗi này
    if ($http_status_code < 400 || $http_status_code >= 600) $http_status_code = 500;
    // Đặc biệt cho lỗi 409 Conflict (ví dụ: số báo giá trùng)
    if ($http_status_code === 409 && method_exists($e, 'getSuggestion')) {
        $response['suggestion'] = $e->getSuggestion();
    }
    error_log("RuntimeException in " . basename(__FILE__) . " (Action: $action, HTTP: $http_status_code): " . $e->getMessage());
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
} catch (Exception $e) { // Bắt tất cả các lỗi khác
    error_log("Unexpected Error in " . basename(__FILE__) . " (Action: $action): " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $response = ['success' => false, 'message' => $lang['server_error_please_retry'] ?? 'Lỗi máy chủ không mong muốn. Vui lòng thử lại hoặc liên hệ hỗ trợ.'];
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $http_status_code = 500;
} finally {
    if (!headers_sent()) {
        http_response_code($http_status_code);
        // echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // Dễ debug
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
exit;
?>