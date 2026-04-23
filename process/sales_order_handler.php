<?php
// File: process/sales_order_handler.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php'; // Chứa $pdo, $lang, các hàm helper chung
function send_json_response($data) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}
$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'] ?? null;

// Khởi tạo $response và $http_status_code mặc định
$response = ['success' => false, 'message' => ($lang['invalid_request'] ?? 'Invalid request or action not specified.')];
$http_status_code = 400; // Bad Request

// --- Các hàm tiện ích ---
if (!function_exists('generateOrderNumber')) {
    function generateOrderNumber(PDO $pdo, string $prefix = 'PO-STV'): string {
        $datePart = date('dmY');
        $prefixWithDate = $prefix . '-' . $datePart . '-';
        $sql = "SELECT order_number FROM sales_orders WHERE order_number LIKE :prefix ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':prefix' => $prefixWithDate . '%']);
        $lastNumber = $stmt->fetchColumn();
        $nextSeq = 1;
        if ($lastNumber) {
            $parts = explode('-', $lastNumber);
            $lastSeq = (int)end($parts);
            if ($lastSeq <= 0 && count($parts) > 1) { // Xử lý trường hợp như PO-STV-27052025-ABC-1 (ít gặp)
                $potentialSeq = (int)$parts[count($parts) - 2];
                if ($potentialSeq > 0) $lastSeq = $potentialSeq;
            }
            $nextSeq = $lastSeq + 1;
        }
        return $prefixWithDate . str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('calculateOrderTotals')) { // Đổi tên để tránh xung đột nếu có hàm calculateTotals ở nơi khác
    function calculateOrderTotals(array $validItems, float $vatRate): array {
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
// --- Kết thúc các hàm tiện ích ---

try {
    if (!$pdo) {
        throw new PDOException($lang['database_connection_failed'] ?? "Database connection not established.");
    }

    if ($method === 'POST') {
        switch ($action) {
            case 'update_status':
        // Kiểm tra các tham số cần thiết
        if (!isset($_POST['id']) || !isset($_POST['status'])) {
            send_json_response(['success' => false, 'message' => 'Lỗi: Thiếu ID đơn hàng hoặc trạng thái mới.']);
        }

        $order_id = $_POST['id'];
        $new_status = $_POST['status'];
        
        // Danh sách các trạng thái hợp lệ từ CSDL của bạn
        $allowed_statuses = ['draft', 'ordered', 'partially_received', 'fully_received', 'cancelled'];

        // Kiểm tra trạng thái mới có hợp lệ không
        if (!in_array($new_status, $allowed_statuses)) {
            send_json_response(['success' => false, 'message' => 'Trạng thái mới không hợp lệ: ' . htmlspecialchars($new_status)]);
        }

        try {
            $stmt = $pdo->prepare("UPDATE sales_orders SET status = ? WHERE id = ?");
            $success = $stmt->execute([$new_status, $order_id]);

            if ($success) {
                // Ghi log hoạt động (nếu có)
                write_user_log('UPDATE', 'sales_order', "Cập nhật trạng thái đơn hàng #$order_id sang '$new_status'", ['order_id' => $order_id, 'new_status' => $new_status], 'info');

                send_json_response(['success' => true, 'message' => 'Cập nhật trạng thái thành công!']);
            } else {
                send_json_response(['success' => false, 'message' => 'Lỗi khi cập nhật cơ sở dữ liệu.']);
            }
        } catch (PDOException $e) {
            error_log("PDOException in update_status: " . $e->getMessage()); // Ghi lỗi ra file log của server
            send_json_response(['success' => false, 'message' => 'Lỗi máy chủ khi cập nhật trạng thái.']);
        }
        break; // Kết thúc case 'update_status'
            case 'add':
            case 'edit':
                $input = json_decode(file_get_contents('php://input'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new InvalidArgumentException($lang['invalid_request_data_format_json'] ?? 'Invalid JSON data format.');
                }

                $orderId = filter_var($input['order_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $supplierId = filter_var($input['partner_id'] ?? null, FILTER_VALIDATE_INT);
                $orderDateStr = $input['order_date'] ?? null;
                $orderNumber = trim($input['order_number'] ?? '');
                $currency = trim($input['currency'] ?? 'VND');
                $notes = trim($input['notes'] ?? '') ?: null;
                $vatRateStr = $input['vat_rate'] ?? '10'; // Mặc định 10% nếu không có
                $vatRate = filter_var($vatRateStr, FILTER_VALIDATE_FLOAT);
                $items_input = $input['items'] ?? [];
                $quote_id_raw = $input['quote_id'] ?? ($input['quote_id_fk'] ?? null); // Chấp nhận cả quote_id_fk
                $quote_id = (!empty($quote_id_raw) && is_numeric($quote_id_raw)) ? (int)$quote_id_raw : null;


                $errors = [];
                if (!$supplierId) $errors['partner_autocomplete'] = $lang['supplier_required'] ?? 'Supplier is required.';
                
                $user_date_format_php = $user_settings['date_format_php'] ?? 'd/m/Y'; // Lấy từ user_settings hoặc config
                if (empty($orderDateStr)) {
                    $errors['order_date'] = $lang['order_date_required'] ?? 'Order date is required.';
                    $orderDateSql = null;
                } else {
                    $dateObj = DateTime::createFromFormat($user_date_format_php, $orderDateStr);
                    if (!$dateObj || $dateObj->format($user_date_format_php) !== $orderDateStr) {
                        $errors['order_date'] = sprintf($lang['invalid_date_format_expected'] ?? 'Invalid date format. Expected: %s.', ($user_settings['date_format_display'] ?? 'dd/mm/yyyy'));
                        $orderDateSql = null;
                    } else {
                        $orderDateSql = $dateObj->format('Y-m-d');
                    }
                }
                if (empty($orderNumber)) $errors['order_number'] = $lang['order_number_required'] ?? 'Order number is required.';
                if (!in_array($currency, ['VND', 'USD'])) $errors['currency_select'] = $lang['invalid_currency'] ?? 'Invalid currency selected.';
                
                if ($vatRate === false || $vatRate < 0 || $vatRate > 100) { // false khi filter_var thất bại
                    $errors['summary-vat-rate'] = $lang['invalid_vat_rate'] ?? 'Invalid VAT rate (0-100).';
                }

                if (empty($items_input) || !is_array($items_input)) {
                     $errors['items_general'] = $lang['order_must_have_items'] ?? 'Order must have at least one item.';
                }

                $validItems = [];
                if (is_array($items_input)) {
                    foreach ($items_input as $index => $item) {
                        $item_errors_detail = []; // Đổi tên để tránh trùng với $errors
                        $detailId = filter_var($item['detail_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                        $quoteDetailId = filter_var($item['quote_detail_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                        $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                        $productName = trim($item['product_name_snapshot'] ?? '');
                        $quantity_raw = $item['quantity'] ?? '0';
                        $unitPrice_raw = $item['unit_price'] ?? '0';

                        $quantity = filter_var($quantity_raw, FILTER_VALIDATE_FLOAT);
                        $unitPrice = filter_var($unitPrice_raw, FILTER_VALIDATE_FLOAT);

                        if (empty($productName) && !$productId) $item_errors_detail['product_name_snapshot'] = $lang['product_name_required'] ?? 'Product name required.';
                        if ($quantity === false || $quantity <= 0) $item_errors_detail['quantity'] = $lang['invalid_quantity'] ?? 'Invalid quantity (must be > 0).';
                        if ($unitPrice === false || $unitPrice < 0) $item_errors_detail['unit_price'] = $lang['invalid_unit_price'] ?? 'Invalid unit price (must be >= 0).';
                        
                        if (!empty($item_errors_detail)) {
                            $errors["items"][$index] = $item_errors_detail;
                        } else {
                            $validItems[] = [
                                'detail_id' => $detailId,
                                'quote_detail_id' => $quoteDetailId,
                                'product_id' => $productId,
                                'product_name_snapshot' => $productName,
                                'category_snapshot' => trim($item['category_snapshot'] ?? ''),
                                'unit_snapshot' => trim($item['unit_snapshot'] ?? ''),
                                'quantity' => $quantity,
                                'unit_price' => $unitPrice
                            ];
                        }
                    }
                }
                 if (empty($validItems) && !isset($errors['items_general'])) {
                    $errors['items_general'] = $lang['order_must_have_valid_items'] ?? 'Order must have at least one valid item.';
                }


                if (!empty($errors)) {
                    $response = ['success' => false, 'message' => $lang['validation_failed'] ?? 'Validation failed.', 'errors' => $errors];
                    $http_status_code = 422; // Unprocessable Entity
                    break; 
                }
                
                $calculatedTotals = calculateOrderTotals($validItems, $vatRate ?? 0.0);

                $stmt_comp = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
                $companyInfoData = $stmt_comp->fetch(PDO::FETCH_ASSOC) ?: [];
                $companyInfoSnapshot = json_encode($companyInfoData, JSON_UNESCAPED_UNICODE);

                $stmt_supp = $pdo->prepare("SELECT * FROM partners WHERE id = :id AND type = 'supplier'");
                $stmt_supp->execute([':id' => $supplierId]);
                $supplierInfoData = $stmt_supp->fetch(PDO::FETCH_ASSOC) ?: [];
                if (empty($supplierInfoData)) {
                    throw new InvalidArgumentException($lang['invalid_supplier_not_found'] ?? 'Supplier not found or not a supplier type.');
                }
                $supplierInfoSnapshot = json_encode($supplierInfoData, JSON_UNESCAPED_UNICODE);

                $pdo->beginTransaction();
                if ($action === 'add') {
                    if (!($input['force_create'] ?? false)) {
                        $stmt_check_num = $pdo->prepare("SELECT 1 FROM sales_orders WHERE order_number = :num LIMIT 1");
                        $stmt_check_num->execute([':num' => $orderNumber]);
                        if ($stmt_check_num->fetchColumn()) {
                            $newSuggNumber = generateOrderNumber($pdo);
                            throw new RuntimeException(sprintf($lang['order_number_exists_suggest'] ?? 'Order number %s already exists. Suggestion: %s.', $orderNumber, $newSuggNumber), 409);
                        }
                    }

                    $sql_order = "INSERT INTO sales_orders (order_number, order_date, supplier_id, currency, vat_rate, company_info_snapshot, supplier_info_snapshot, notes, sub_total, vat_total, grand_total, status, created_by, created_at, updated_at, quote_id) VALUES (:on, :od, :sid, :cur, :vr, :compinfo, :suppinfo, :notes, :sub, :vat, :grand, 'draft', :uid, NOW(), NOW(), :qid)";
                    $stmt_order = $pdo->prepare($sql_order);
                    $stmt_order->execute([
                        ':on' => $orderNumber, ':od' => $orderDateSql, ':sid' => $supplierId,
                        ':cur' => $currency, ':vr' => $vatRate,
                        ':compinfo' => $companyInfoSnapshot, ':suppinfo' => $supplierInfoSnapshot,
                        ':notes' => $notes,
                        ':sub' => $calculatedTotals['sub_total'], ':vat' => $calculatedTotals['vat_total'],
                        ':grand' => $calculatedTotals['grand_total'], ':uid' => $userId, ':qid' => $quote_id
                    ]);
                    $newOrderId = (int)$pdo->lastInsertId();
                    $orderId = $newOrderId; // Gán $orderId để đồng bộ item

                    $message = $lang['order_created_success'] ?? 'Order created successfully.';
                    write_user_log('CREATE', 'sales_order', "Tạo đơn hàng mới #$orderId - Số đơn: $orderNumber, NCC ID: $supplierId", ['order_id' => $orderId, 'order_number' => $orderNumber], 'success');

                    $http_status_code = 201;
                } else { // action === 'edit'
                    if (!$orderId) throw new InvalidArgumentException($lang['invalid_order_id_for_edit'] ?? 'Invalid order ID for edit.');
                    
                    $stmt_status_check = $pdo->prepare("SELECT status, quote_id FROM sales_orders WHERE id = :id");
                    $stmt_status_check->execute([':id' => $orderId]);
                    $order_row_edit = $stmt_status_check->fetch(PDO::FETCH_ASSOC);
                    $currentDBStatus_edit = $order_row_edit['status'] ?? null;
                    $old_quote_id = $order_row_edit['quote_id'] ?? null;

                    if (!$currentDBStatus_edit) throw new RuntimeException($lang['order_not_found_for_edit'] ?? 'Order not found for edit.');
                    if ($currentDBStatus_edit !== 'draft') {
                         throw new RuntimeException(sprintf($lang['cannot_edit_order_status_not_draft'] ?? 'Cannot edit order. Status is "%s", not "draft".', $currentDBStatus_edit), 403);
                    }

                    $sql_order_update = "UPDATE sales_orders SET order_number = :on, order_date = :od, supplier_id = :sid, currency = :cur, vat_rate = :vr, company_info_snapshot = :compinfo, supplier_info_snapshot = :suppinfo, notes = :notes, sub_total = :sub, vat_total = :vat, grand_total = :grand, status = :status, quote_id = :qid, updated_at = NOW() WHERE id = :id AND status = 'draft'";
                    $stmt_order_update = $pdo->prepare($sql_order_update);
                    $stmt_order_update->execute([
                        ':on' => $orderNumber, ':od' => $orderDateSql, ':sid' => $supplierId,
                        ':cur' => $currency, ':vr' => $vatRate,
                        ':compinfo' => $companyInfoSnapshot, ':suppinfo' => $supplierInfoSnapshot,
                        ':notes' => $notes,
                        ':sub' => $calculatedTotals['sub_total'], ':vat' => $calculatedTotals['vat_total'],
                        ':grand' => $calculatedTotals['grand_total'],
                        ':status' => $input['status'] ?? $currentDBStatus_edit,
                        ':qid' => $quote_id, ':id' => $orderId
                    ]);
                    $message = $lang['order_updated_success'] ?? 'Order updated successfully.';
                    write_user_log('UPDATE', 'sales_order', "Cập nhật đơn hàng #$orderId - Số đơn: $orderNumber", ['order_id' => $orderId], 'info');

                    $http_status_code = 200;
                }

                // Synchronize Items
                $stmt_existing = $pdo->prepare("SELECT id FROM sales_order_details WHERE order_id = :order_id");
                $stmt_existing->execute([':order_id' => $orderId]);
                $existingDbItemIds = $stmt_existing->fetchAll(PDO::FETCH_COLUMN);
                $submittedItemDetailIds = [];

                $sql_item_upd = "UPDATE sales_order_details SET product_id = :pid, product_name_snapshot=:pname, category_snapshot=:pcat, unit_snapshot=:punit, quantity=:pqty, unit_price=:price, quote_detail_id=:qdid WHERE id = :detail_id AND order_id = :oid";
                $stmt_item_upd = $pdo->prepare($sql_item_upd);
                $sql_item_ins = "INSERT INTO sales_order_details (order_id, product_id, product_name_snapshot, category_snapshot, unit_snapshot, quantity, unit_price, quote_detail_id) VALUES (:oid, :pid, :pname, :pcat, :punit, :pqty, :price, :qdid)";
                $stmt_item_ins = $pdo->prepare($sql_item_ins);

                foreach ($validItems as $itemData) {
                    $detail_id_sync = $itemData['detail_id'];
                    $item_params_sync = [
                        ':oid' => $orderId, ':pid' => $itemData['product_id'],
                        ':pname' => $itemData['product_name_snapshot'],
                        ':pcat' => $itemData['category_snapshot'], ':punit' => $itemData['unit_snapshot'],
                        ':pqty' => $itemData['quantity'], ':price' => $itemData['unit_price'],
                        ':qdid' => $itemData['quote_detail_id']
                    ];
                    if ($detail_id_sync && in_array($detail_id_sync, $existingDbItemIds)) {
                        $item_params_sync[':detail_id'] = $detail_id_sync;
                        $stmt_item_upd->execute($item_params_sync);
                        $submittedItemDetailIds[] = $detail_id_sync;
                    } else {
                        $stmt_item_ins->execute($item_params_sync);
                    }
                }
                $itemsToDelete = array_diff($existingDbItemIds, $submittedItemDetailIds);
                if (!empty($itemsToDelete)) {
                    $placeholders_del = implode(',', array_fill(0, count($itemsToDelete), '?'));
                    $sql_item_del = "DELETE FROM sales_order_details WHERE id IN ($placeholders_del) AND order_id = ?";
                    $stmt_item_del = $pdo->prepare($sql_item_del);
                    $stmt_item_del->execute(array_merge($itemsToDelete, [$orderId]));
                }
                // Update ordered_quantity for NEW quote_id
                if (!empty($quote_id)) {
                    $sql_update_ordered_qty = "
                        UPDATE sales_quote_details sqd
                        SET ordered_quantity = (
                            SELECT COALESCE(SUM(sod.quantity), 0)
                            FROM sales_order_details sod
                            JOIN sales_orders so ON sod.order_id = so.id
                            WHERE sod.quote_detail_id = sqd.id
                              AND so.status != 'cancelled'
                        )
                        WHERE sqd.quote_id = :qid
                    ";
                    $stmt_update_qty = $pdo->prepare($sql_update_ordered_qty);
                    $stmt_update_qty->execute([':qid' => $quote_id]);
                }
                
                // Update ordered_quantity for OLD quote_id if it was changed
                if (!empty($old_quote_id) && $old_quote_id != $quote_id) {
                    $stmt_update_old_qty = $pdo->prepare($sql_update_ordered_qty);
                    $stmt_update_old_qty->execute([':qid' => $old_quote_id]);
                }
                $pdo->commit();
                $response = ['success' => true, 'message' => $message, 'order_id' => $orderId];
                // $http_status_code đã được set ở trên (201 hoặc 200)
                break;


            case 'get_available_quotes_for_order_linking':
                $available_quotes_data = [];
                $current_sales_order_id = filter_input(INPUT_POST, 'current_so_id', FILTER_VALIDATE_INT) ?: null; // JS nên gửi current_so_id khi edit

                $stmt_accepted_quotes = $pdo->prepare("
                    SELECT sq.id, sq.quote_number, p.name as customer_name, sq.customer_id
                    FROM sales_quotes sq
                    LEFT JOIN partners p ON sq.customer_id = p.id -- AND p.type = 'customer' (Thêm nếu bảng partners dùng chung)
                    WHERE sq.status = 'accepted'
                    ORDER BY sq.quote_date DESC, sq.id DESC
                ");
                $stmt_accepted_quotes->execute();
                $accepted_quotes_list = $stmt_accepted_quotes->fetchAll(PDO::FETCH_ASSOC);

                $sql_linked_ids = "SELECT DISTINCT so.quote_id FROM sales_orders so WHERE so.quote_id IS NOT NULL";
                $params_linked_ids = [];
                if ($current_sales_order_id) {
                    // Khi sửa một SO, không coi BG của chính SO đó là "đã link với SO khác"
                    $sql_linked_ids .= " AND so.id != :current_so_id";
                    $params_linked_ids[':current_so_id'] = $current_sales_order_id;
                }
                $stmt_linked_quote_ids = $pdo->prepare($sql_linked_ids);
                $stmt_linked_quote_ids->execute($params_linked_ids);
                $linked_quote_ids_in_other_orders = $stmt_linked_quote_ids->fetchAll(PDO::FETCH_COLUMN);

                foreach ($accepted_quotes_list as $quote) {
                    $quote_to_add = $quote;
                    $quote_to_add['is_already_linked_to_another_order'] = in_array($quote['id'], $linked_quote_ids_in_other_orders);
                    $available_quotes_data[] = $quote_to_add;
                }

                $response = ['success' => true, 'quotes' => $available_quotes_data];
                $http_status_code = 200;
                break;

                

            case 'delete':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) throw new InvalidArgumentException($lang['invalid_order_id_for_delete'] ?? 'Invalid order ID for deletion.');
                
                $pdo->beginTransaction();
                // Lấy thông tin quote_id trước khi xóa
                $stmt_check = $pdo->prepare("SELECT status, quote_id FROM sales_orders WHERE id = ?");
                $stmt_check->execute([$id]);
                $order_data_del = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if (!$order_data_del) {
                    $pdo->rollBack();
                    throw new RuntimeException($lang['order_not_found'] ?? 'Order not found.', 404);
                }

                $status_del = $order_data_del['status'];
                $quote_id_del = $order_data_del['quote_id'];

                if ($status_del !== 'draft') {
                     $pdo->rollBack();
                     throw new RuntimeException(sprintf($lang['cannot_delete_order_status_not_draft'] ?? 'Cannot delete order. Status is "%s", not "draft".', $status_del), 403);
                }

                $stmt_del_items = $pdo->prepare("DELETE FROM sales_order_details WHERE order_id = ?");
                $stmt_del_items->execute([$id]);

                $stmt_del_order = $pdo->prepare("DELETE FROM sales_orders WHERE id = ? AND status = 'draft'");
                $deleted_rows = $stmt_del_order->execute([$id]);

                if ($deleted_rows) {
                    // Cập nhật lại ordered_quantity cho Báo giá liên kết (nếu có)
                    if (!empty($quote_id_del)) {
                        $sql_update_ordered_qty = "
                            UPDATE sales_quote_details sqd
                            SET ordered_quantity = (
                                SELECT COALESCE(SUM(sod.quantity), 0)
                                FROM sales_order_details sod
                                JOIN sales_orders so ON sod.order_id = so.id
                                WHERE sod.quote_detail_id = sqd.id
                                  AND so.status != 'cancelled'
                            )
                            WHERE sqd.quote_id = :qid
                        ";
                        $stmt_update_qty = $pdo->prepare($sql_update_ordered_qty);
                        $stmt_update_qty->execute([':qid' => $quote_id_del]);
                    }

                    $pdo->commit();
                    $response = ['success' => true, 'message' => $lang['order_deleted_success'] ?? 'Order deleted success.'];
                    write_user_log('DELETE', 'sales_order', "Xóa đơn hàng #$id", ['id' => $id], 'danger');

                    $http_status_code = 200;
                } else {
                    $pdo->rollBack(); 
                    throw new RuntimeException($lang['order_delete_failed_or_not_found'] ?? 'Failed to delete order or order not found/not in draft status.', 404);
                }
                break;

                
            case 'update_status':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $newStatus = trim($_POST['status'] ?? '');
                
                if (!$id || empty($newStatus)) {
                    throw new InvalidArgumentException($lang['invalid_data'] ?? 'Dữ liệu không hợp lệ.');
                }
                
                $pdo->beginTransaction();
                
                // Lấy quote_id trước khi update để tính toán lại ordered_quantity
                $stmt_get = $pdo->prepare("SELECT quote_id FROM sales_orders WHERE id = ?");
                $stmt_get->execute([$id]);
                $quote_id = $stmt_get->fetchColumn();
                
                $stmt_upd = $pdo->prepare("UPDATE sales_orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt_upd->execute([$newStatus, $id]);
                
                if (!empty($quote_id)) {
                    $sql_update_ordered_qty = "
                        UPDATE sales_quote_details sqd
                        SET ordered_quantity = (
                            SELECT COALESCE(SUM(sod.quantity), 0)
                            FROM sales_order_details sod
                            JOIN sales_orders so ON sod.order_id = so.id
                            WHERE sod.quote_detail_id = sqd.id
                              AND so.status != 'cancelled'
                        )
                        WHERE sqd.quote_id = :qid
                    ";
                    $stmt_update_qty = $pdo->prepare($sql_update_ordered_qty);
                    $stmt_update_qty->execute([':qid' => $quote_id]);
                }
                
                $pdo->commit();
                $response = ['success' => true, 'message' => $lang['status_updated_success'] ?? 'Trạng thái đã được cập nhật thành công.'];
                write_user_log('UPDATE_STATUS', 'sales_order', "Cập nhật trạng thái đơn hàng #$id thành $newStatus", ['id' => $id, 'status' => $newStatus], 'info');
                $http_status_code = 200;
                break;

            default:
                $response = ['success' => false, 'message' => ($lang['invalid_action_specified_post'] ?? 'Invalid action specified for POST.') . " Action: " . htmlspecialchars($action)];
                $http_status_code = 400;
                break;
        }
    } elseif ($method === 'GET') {
        switch ($action) {
            case 'get_list': // Logic này thường cho DataTables server-side, đây là ví dụ đơn giản
                $sql = "SELECT so.id, so.order_number, so.order_date, so.currency, p.name as supplier_name, so.grand_total, so.status
                        FROM sales_orders so
                        LEFT JOIN partners p ON so.supplier_id = p.id -- AND p.type = 'supplier' (Nếu cần)
                        ORDER BY so.order_date DESC, so.id DESC";
                $stmt = $pdo->query($sql);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // ... (xử lý định dạng ngày, tiền tệ, status text cho orders nếu cần) ...
                $response = ['success' => true, 'data' => $orders]; // Cho DataTables client-side
                $http_status_code = 200;
                break;

            case 'get_details':
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$id) throw new InvalidArgumentException($lang['invalid_order_id_for_details'] ?? 'Invalid order ID for details.');

                $stmt_header = $pdo->prepare("SELECT * FROM sales_orders WHERE id = :id");
                $stmt_header->execute([':id' => $id]);
                $orderHeader = $stmt_header->fetch(PDO::FETCH_ASSOC);

                if (!$orderHeader) throw new RuntimeException($lang['order_not_found'] ?? 'Order not found.', 404);

                $sql_details = "SELECT sod.* FROM sales_order_details sod WHERE sod.order_id = :id ORDER BY sod.id ASC";
                $stmt_details = $pdo->prepare($sql_details);
                $stmt_details->execute([':id' => $id]);
                $orderDetails = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
                
                $user_date_format_php = $user_settings['date_format_php'] ?? 'd/m/Y';
                if (isset($orderHeader['order_date']) && $orderHeader['order_date']) {
                    $dateObj = DateTime::createFromFormat('Y-m-d', $orderHeader['order_date']);
                    $orderHeader['order_date_formatted'] = $dateObj ? $dateObj->format($user_date_format_php) : $orderHeader['order_date'];
                }
                if ($orderHeader && !empty($orderHeader['quote_id'])) { // quote_id là FK trong sales_orders
                    $stmt_linked_quote = $pdo->prepare("SELECT quote_number FROM sales_quotes WHERE id = :linked_quote_id_param");
                    $stmt_linked_quote->execute([':linked_quote_id_param' => $orderHeader['quote_id']]);
                    $linked_quote_data = $stmt_linked_quote->fetch(PDO::FETCH_ASSOC);
                    if ($linked_quote_data) {
                        $orderHeader['linked_quote_number'] = $linked_quote_data['quote_number'];
                    } else {
                        $orderHeader['linked_quote_number'] = null; // Hoặc một giá trị báo không tìm thấy
                    }
                } else {
                    $orderHeader['linked_quote_number'] = null;
                }
                $response = ['success' => true, 'data' => ['header' => $orderHeader, 'details' => $orderDetails]];
                $http_status_code = 200;
                break;

            case 'generate_order_number':
                $newNumber = generateOrderNumber($pdo);
                $response = ['success' => true, 'order_number' => $newNumber];
                $http_status_code = 200;
                break;
            
            default:
                $response = ['success' => false, 'message' => ($lang['invalid_action_specified_get'] ?? 'Invalid action specified for GET.') . " Action: " . htmlspecialchars($action)];
                $http_status_code = 400;
                break;
        }
    } else {
        $response['message'] = $lang['invalid_request_method'] ?? 'Invalid request method.';
        $http_status_code = 405; // Method Not Allowed
    }

} catch (PDOException $e) {
    error_log("Database Error in " . basename(__FILE__) . " (Action: $action): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $response = ['success' => false, 'message' => $lang['database_error_details'] ?? 'Lỗi cơ sở dữ liệu.'];
    $http_status_code = 500;
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
} catch (InvalidArgumentException $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
    $code = $e->getCode();
    $http_status_code = ($code && is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
    error_log("InvalidArgumentException in " . basename(__FILE__) . " (Action: $action, HTTP: $http_status_code): " . $e->getMessage());
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
} catch (RuntimeException $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
    $code = $e->getCode();
    $http_status_code = ($code && is_int($code) && $code >= 400 && $code < 600) ? $code : 500;
    if ($http_status_code === 409 && isset(explode(': ', $e->getMessage())[1])) {
        $response['suggestion'] = explode(': ', $e->getMessage())[1];
    }
    error_log("RuntimeException in " . basename(__FILE__) . " (Action: $action, HTTP: $http_status_code): " . $e->getMessage());
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
} catch (Throwable $e) { // Bắt tất cả các lỗi khác (bao gồm Error)
    error_log("Unexpected Error/Throwable in " . basename(__FILE__) . " (Action: $action): " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $response = ['success' => false, 'message' => $lang['server_error_please_retry'] ?? 'Lỗi máy chủ không mong muốn.'];
    $http_status_code = 500;
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
} finally {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($http_status_code);
    }
    // Đảm bảo $response luôn được định nghĩa trước khi encode
    if (!isset($response) || !is_array($response)) {
         // Điều này không nên xảy ra nếu $response được khởi tạo ở đầu
        $response = ['success' => false, 'message' => 'Server response was not properly formed.'];
        if ($http_status_code < 400) { // Nếu không có lỗi nào được set, nhưng response vẫn không đúng
            // http_response_code(500); // Có thể set lại mã lỗi ở đây
        }
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
exit; // Thêm exit ở đây để chắc chắn không có output nào khác sau JSON
?>