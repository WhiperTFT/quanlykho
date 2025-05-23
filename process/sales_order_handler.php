<?php
// File: process/sales_order_handler.php (ĐÃ SỬA ĐỔI THEO YÊU CẦU)

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/admin_check.php'; // Bỏ comment nếu cần check admin

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'] ?? null;

// --- Function to generate a unique order number ---
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
        if ($lastSeq <= 0 && count($parts) > 1) {
            $potentialSeq = (int)$parts[count($parts) - 2];
            if ($potentialSeq > 0) $lastSeq = $potentialSeq;
        }
        $nextSeq = $lastSeq + 1;
    }
    return $prefixWithDate . str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
}

// --- Function to calculate totals ---
function calculateTotals(array $validItems, float $vatRate): array {
    $totalSub = 0.00;
    foreach ($validItems as $item) {
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

try {
    if ($method === 'POST') {
        switch ($action) {
            case 'add':
            case 'edit':
                $input = json_decode(file_get_contents('php://input'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new InvalidArgumentException($lang['invalid_request_data'] ?? 'Invalid request data format.');
                }

                // Lấy dữ liệu từ input
                $orderId = filter_var($input['order_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $supplierId = filter_var($input['partner_id'] ?? null, FILTER_VALIDATE_INT);
                $orderDateStr = $input['order_date'] ?? null;
                $orderNumber = trim($input['order_number'] ?? '');
                $currency = trim($input['currency'] ?? 'VND');
                $notes = trim($input['notes'] ?? '') ?: null;
                $vatRateStr = $input['vat_rate'] ?? '10';
                $vatRate = filter_var($vatRateStr, FILTER_VALIDATE_FLOAT);
                $items = $input['items'] ?? [];
                $quote_id_raw = $input['quote_id'] ?? null;
                $quote_id = (!empty($quote_id_raw) && is_numeric($quote_id_raw)) ? (int)$quote_id_raw : null;

                // --- Validation ---
                $errors = [];
                if (!$supplierId) $errors['partner_autocomplete'][] = $lang['supplier_required'] ?? 'Supplier is required.';
                if (empty($orderDateStr)) {
                    $errors['order_date'][] = $lang['order_date_required'] ?? 'Order date is required.';
                    $orderDateSql = null;
                } else {
                    $dateObj = DateTime::createFromFormat('d/m/Y', $orderDateStr);
                    if (!$dateObj || $dateObj->format('d/m/Y') !== $orderDateStr) {
                        $errors['order_date'][] = $lang['invalid_date_format'] ?? 'Invalid date format (dd/mm/yyyy).';
                        $orderDateSql = null;
                    } else {
                        $orderDateSql = $dateObj->format('Y-m-d');
                    }
                }
                if (empty($orderNumber)) $errors['order_number'][] = $lang['order_number_required'] ?? 'Order number is required.';
                if (!in_array($currency, ['VND', 'USD'])) $errors['currency_select'][] = $lang['invalid_currency'] ?? 'Invalid currency selected.';
                if ($vatRate === false || $vatRate < 0 || $vatRate > 100) {
                    $errors['summary-vat-rate'][] = $lang['invalid_vat_rate'] ?? 'Invalid VAT rate (0-100).';
                }
                if (empty($items) || !is_array($items)) $errors['items_general'][] = $lang['order_must_have_items'] ?? 'Order must have at least one item.';

                // --- Validate items ---
                $validItems = [];
                foreach ($items as $index => $item) {
                    $itemErrors = [];
                    $detailId = filter_var($item['detail_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                    $productName = trim($item['product_name_snapshot'] ?? '');
                    $quantityStr = $item['quantity'] ?? '0';
                    $unitPriceStr = $item['unit_price'] ?? '0';

                    $quantity = filter_var($quantityStr, FILTER_VALIDATE_FLOAT);
                    $unitPrice = filter_var($unitPriceStr, FILTER_VALIDATE_FLOAT);

                    if (empty($productName)) $itemErrors[] = $lang['product_name_required'] ?? 'Product name required.';
                    if ($quantity === false || $quantity <= 0) $itemErrors[] = $lang['invalid_quantity'] ?? 'Invalid or zero quantity.';
                    if ($unitPrice === false || $unitPrice < 0) $itemErrors[] = $lang['invalid_unit_price'] ?? 'Invalid unit price.';

                    if (!empty($itemErrors)) {
                        $errors["items"][$index] = $itemErrors;
                    } else {
                        $validItems[] = [
                            'detail_id' => $detailId,
                            'product_id' => $productId,
                            'product_name_snapshot' => $productName,
                            'category_snapshot' => trim($item['category_snapshot'] ?? ''),
                            'unit_snapshot' => trim($item['unit_snapshot'] ?? ''),
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice
                        ];
                    }
                }

                if (empty($validItems) && empty($errors['items_general'])) {
                    $errors['items_general'][] = $lang['order_must_have_valid_items'] ?? 'Order must have at least one valid item.';
                }

                if (!empty($errors)) {
                    http_response_code(422);
                    echo json_encode(['success' => false, 'message' => $lang['validation_failed'] ?? 'Validation failed.', 'errors' => $errors]);
                    exit;
                }

                // Tính toán tổng
                $calculatedTotals = ['sub_total' => 0, 'vat_total' => 0, 'grand_total' => 0];
                if ($vatRate !== false && $vatRate >= 0 && $vatRate <= 100) {
                    $calculatedTotals = calculateTotals($validItems, $vatRate);
                }
                $totalSub = $calculatedTotals['sub_total'];
                $totalVat = $calculatedTotals['vat_total'];
                $totalGrand = $calculatedTotals['grand_total'];

                // Snapshotting Info
                $stmt_comp = $pdo->query("SELECT * FROM company_info WHERE id = 1 LIMIT 1");
                $companyInfoData = $stmt_comp->fetch(PDO::FETCH_ASSOC) ?: [];
                $companyInfoSnapshot = json_encode($companyInfoData, JSON_UNESCAPED_UNICODE);

                $stmt_supp = $pdo->prepare("SELECT * FROM partners WHERE id = :id AND type = 'supplier'");
                $stmt_supp->execute([':id' => $supplierId]);
                $supplierInfoData = $stmt_supp->fetch(PDO::FETCH_ASSOC) ?: [];
                if (empty($supplierInfoData)) {
                    throw new InvalidArgumentException($lang['invalid_supplier'] ?? 'Invalid supplier selected.');
                }
                $supplierInfoSnapshot = json_encode($supplierInfoData, JSON_UNESCAPED_UNICODE);

                // Database Operations
                $pdo->beginTransaction();
                try {
                    if ($action === 'add') {
                        $stmt_check_num = $pdo->prepare("SELECT 1 FROM sales_orders WHERE order_number = :num LIMIT 1");
                        $stmt_check_num->execute([':num' => $orderNumber]);
                        if ($stmt_check_num->fetchColumn()) {
                            $newNumber = generateOrderNumber($pdo);
                            throw new RuntimeException(sprintf($lang['order_number_exists_suggest'] ?? 'Order number %s already exists. Suggestion: %s. Please generate again or confirm.', $orderNumber, $newNumber), 409);
                        }

                        $sql_order = "INSERT INTO sales_orders (
                            order_number, order_date, supplier_id, currency, vat_rate, 
                            company_info_snapshot, supplier_info_snapshot, notes, 
                            sub_total, vat_total, grand_total, 
                            status, created_by, created_at, updated_at, 
                            quote_id
                        ) VALUES (
                            :order_number, :order_date, :supplier_id, :currency, :vat_rate, 
                            :company_info, :supplier_info, :notes, 
                            :sub_total, :vat_total, :grand_total, 
                            :status, :created_by, NOW(), NOW(), 
                            :quote_id
                        )";
                        $stmt_order = $pdo->prepare($sql_order);
                        $stmt_order->execute([
                            ':order_number' => $orderNumber,
                            ':order_date' => $orderDateSql,
                            ':supplier_id' => $supplierId,
                            ':currency' => $currency,
                            ':vat_rate' => $vatRate,
                            ':company_info' => $companyInfoSnapshot,
                            ':supplier_info' => $supplierInfoSnapshot,
                            ':notes' => $notes,
                            ':sub_total' => $totalSub,
                            ':vat_total' => $totalVat,
                            ':grand_total' => $totalGrand,
                            ':status' => 'draft',
                            ':created_by' => $userId,
                            ':quote_id' => $quote_id
                        ]);
                        $orderId = (int)$pdo->lastInsertId();
                        $message = $lang['order_created_success'] ?? 'Order created successfully.';
                    } else { // edit action
                        if (!$orderId) throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid order ID for edit.');
                        $stmt_status = $pdo->prepare("SELECT status FROM sales_orders WHERE id = :id");
                        $stmt_status->execute([':id' => $orderId]);
                        $currentStatus = $stmt_status->fetchColumn();
                        if (!$currentStatus) throw new RuntimeException($lang['order_not_found'] ?? 'Order not found.');
                        if ($currentStatus !== 'draft') {
                            throw new RuntimeException(sprintf($lang['cannot_edit_order_status'] ?? 'Cannot edit order with status "%s". Only draft orders can be edited.', $currentStatus), 403);
                        }

                        $current_status_for_edit = $input['status'] ?? $currentStatus;
                        $sql_order_update = "UPDATE sales_orders SET
                            order_number = :order_number, order_date = :order_date, supplier_id = :supplier_id, currency = :currency, vat_rate = :vat_rate, status = :status,
                            notes = :notes, sub_total = :sub_total, vat_total = :vat_total, grand_total = :grand_total,
                            company_info_snapshot = :company_info, supplier_info_snapshot = :supplier_info, quote_id = :quote_id,
                            updated_at = NOW()
                        WHERE id = :id AND status = 'draft'";
                        $stmt_order_update = $pdo->prepare($sql_order_update);
                        $stmt_order_update->execute([
                            ':order_number' => $orderNumber,
                            ':order_date' => $orderDateSql,
                            ':supplier_id' => $supplierId,
                            ':currency' => $currency,
                            ':vat_rate' => $vatRate,
                            ':notes' => $notes,
                            ':sub_total' => $totalSub,
                            ':vat_total' => $totalVat,
                            ':grand_total' => $totalGrand,
                            ':company_info' => $companyInfoSnapshot,
                            ':supplier_info' => $supplierInfoSnapshot,
                            ':status' => $current_status_for_edit,
                            ':quote_id' => $quote_id,
                            ':id' => $orderId
                        ]);
                        $message = $lang['order_updated_success'] ?? 'Order updated successfully.';
                    }

                    // Synchronize Items (cập nhật, thêm, xóa details)
                    $stmt_existing_items = $pdo->prepare("SELECT id FROM sales_order_details WHERE order_id = :order_id");
                    $stmt_existing_items->execute([':order_id' => $orderId]);
                    $existingDbItemIds = $stmt_existing_items->fetchAll(PDO::FETCH_COLUMN);

                    error_log("Valid Items: " . json_encode($validItems));
                    error_log("Existing Item IDs: " . json_encode($existingDbItemIds));

                    $submittedItemDetailIds = [];
                    $sql_item_update = "UPDATE sales_order_details SET product_id = :product_id, product_name_snapshot = :name, category_snapshot = :category, unit_snapshot = :unit, quantity = :quantity, unit_price = :price WHERE id = :detail_id AND order_id = :order_id";
                    $stmt_item_update = $pdo->prepare($sql_item_update);

                    $sql_item_insert = "INSERT INTO sales_order_details (order_id, product_id, product_name_snapshot, category_snapshot, unit_snapshot, quantity, unit_price) VALUES (:order_id, :product_id, :name, :category, :unit, :quantity, :price)";
                    $stmt_item_insert = $pdo->prepare($sql_item_insert);

                    foreach ($validItems as $itemData) {
                        $detailId = $itemData['detail_id'];
                        if ($detailId && in_array($detailId, $existingDbItemIds)) {
                            $stmt_item_update->execute([
                                ':product_id' => $itemData['product_id'],
                                ':name' => $itemData['product_name_snapshot'],
                                ':category' => $itemData['category_snapshot'],
                                ':unit' => $itemData['unit_snapshot'],
                                ':quantity' => $itemData['quantity'],
                                ':price' => $itemData['unit_price'],
                                ':detail_id' => $detailId,
                                ':order_id' => $orderId
                            ]);
                            $submittedItemDetailIds[] = $detailId;
                        } else {
                            $stmt_item_insert->execute([
                                ':order_id' => $orderId,
                                ':product_id' => $itemData['product_id'],
                                ':name' => $itemData['product_name_snapshot'],
                                ':category' => $itemData['category_snapshot'],
                                ':unit' => $itemData['unit_snapshot'],
                                ':quantity' => $itemData['quantity'],
                                ':price' => $itemData['unit_price']
                            ]);
                        }
                    }
                    
                    $itemsToDelete = array_diff($existingDbItemIds, $submittedItemDetailIds);
                    error_log("Items to Delete: " . json_encode($itemsToDelete));
                    if (!empty($itemsToDelete)) {
                        $placeholders_del = implode(',', array_fill(0, count($itemsToDelete), '?'));
                        $sql_item_del = "DELETE FROM sales_order_details WHERE id IN ($placeholders_del) AND order_id = ?";
                        $stmt_item_del = $pdo->prepare($sql_item_del);
                        $stmt_item_del->execute(array_merge($itemsToDelete, [$orderId]));
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => $message, 'order_id' => $orderId]);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    if ($e->getCode() === 409) {
                        http_response_code(409);
                        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'suggestion' => explode(': ', $e->getMessage())[1] ?? null]);
                        exit;
                    } elseif ($e->getCode() === 403) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                        exit;
                    }
                    throw $e;
                }
                break;

            case 'delete':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$id) throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid order ID.');

                $pdo->beginTransaction();
                try {
                    $stmt_get = $pdo->prepare("SELECT order_number, status FROM sales_orders WHERE id = :id");
                    $stmt_get->execute([':id' => $id]);
                    $orderInfo = $stmt_get->fetch(PDO::FETCH_ASSOC);

                    if (!$orderInfo) throw new RuntimeException($lang['order_not_found'] ?? 'Order not found.');
                    if ($orderInfo['status'] !== 'draft') {
                        throw new RuntimeException(sprintf($lang['cannot_delete_order_status'] ?? 'Cannot delete order with status "%s". Only draft orders can be deleted.', $orderInfo['status']), 403);
                    }

                    $stmt_delete_details = $pdo->prepare("DELETE FROM sales_order_details WHERE order_id = :id");
                    $stmt_delete_details->execute([':id' => $id]);

                    $stmt_delete_order = $pdo->prepare("DELETE FROM sales_orders WHERE id = :id AND status = 'draft'");
                    $stmt_delete_order->execute([':id' => $id]);

                    if ($stmt_delete_order->rowCount() > 0) {
                        $pdo->commit();
                        echo json_encode(['success' => true, 'message' => $lang['order_deleted_success'] ?? 'Order deleted successfully.']);
                        exit;
                    } else {
                        throw new RuntimeException($lang['order_not_found_or_cannot_delete'] ?? 'Order not found or cannot be deleted (check status).');
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
                throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action.');
        }
    } elseif ($method === 'GET') {
        switch ($action) {
            case 'get_list':
                $sql = "SELECT so.id, so.order_number, so.order_date, so.currency, p.name as supplier_name, so.grand_total, so.status
                        FROM sales_orders so
                        JOIN partners p ON so.supplier_id = p.id
                        WHERE p.type = 'supplier'
                        ORDER BY so.order_date DESC, so.id DESC";
                $stmt = $pdo->query($sql);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($orders as &$order) {
                    if (!empty($order['order_date'])) {
                        $dateObj = DateTime::createFromFormat('Y-m-d', $order['order_date']);
                        $order['order_date_formatted'] = $dateObj ? $dateObj->format('d/m/Y') : $order['order_date'];
                    } else {
                        $order['order_date_formatted'] = '-';
                    }
                    $currency = $order['currency'] ?? 'VND';
                    if (isset($order['grand_total'])) {
                        $order['grand_total_formatted'] = number_format((float)$order['grand_total'], ($currency === 'VND' ? 0 : 2), ',', '.');
                    } else {
                        $order['grand_total_formatted'] = '0';
                    }
                    if (isset($order['status'])) {
                        $status_key = 'status_' . strtolower($order['status']);
                        $order['status_text'] = $lang[$status_key] ?? ucfirst($order['status']);
                        $order['status_badge'] = match (strtolower($order['status'])) {
                            'draft' => 'bg-secondary',
                            'ordered', 'confirmed' => 'bg-info text-dark',
                            'partially_received' => 'bg-warning text-dark',
                            'fully_received', 'completed', 'shipped' => 'bg-success',
                            'cancelled', 'rejected' => 'bg-danger',
                            default => 'bg-light text-dark',
                        };
                    } else {
                        $order['status_text'] = '-';
                        $order['status_badge'] = 'bg-light text-dark';
                    }
                }
                unset($order);
                echo json_encode(['success' => true, 'data' => $orders]);
                exit;

            case 'get_details':
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if (!$id) throw new InvalidArgumentException($lang['invalid_request'] ?? 'Invalid order ID.');

                $stmt_header = $pdo->prepare("SELECT * FROM sales_orders WHERE id = :id");
                $stmt_header->execute([':id' => $id]);
                $orderHeader = $stmt_header->fetch(PDO::FETCH_ASSOC);

                if (!$orderHeader) throw new RuntimeException($lang['order_not_found'] ?? 'Order not found.');

                $sql_details = "SELECT sod.id, sod.order_id, sod.product_id, sod.product_name_snapshot, sod.category_snapshot, sod.unit_snapshot, sod.quantity, sod.unit_price, p.name as product_master_name
                                FROM sales_order_details sod
                                LEFT JOIN products p ON sod.product_id = p.id
                                WHERE sod.order_id = :id ORDER BY sod.id ASC";
                $stmt_details = $pdo->prepare($sql_details);
                $stmt_details->execute([':id' => $id]);
                $orderDetails = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

                $orderHeader['company_info_data'] = @json_decode($orderHeader['company_info_snapshot'] ?? '[]', true) ?: [];
                $orderHeader['supplier_info_data'] = @json_decode($orderHeader['supplier_info_snapshot'] ?? '[]', true) ?: [];
                if (isset($orderHeader['order_date']) && $orderHeader['order_date']) {
                    $dateObj = DateTime::createFromFormat('Y-m-d', $orderHeader['order_date']);
                    $orderHeader['order_date_formatted'] = $dateObj ? $dateObj->format('d/m/Y') : $orderHeader['order_date'];
                }

                echo json_encode(['success' => true, 'data' => ['header' => $orderHeader, 'details' => $orderDetails]]);
                exit;

            case 'generate_order_number':
                $newNumber = generateOrderNumber($pdo);
                echo json_encode(['success' => true, 'order_number' => $newNumber]);
                exit;

            default:
                throw new InvalidArgumentException($lang['invalid_action'] ?? 'Invalid action.');
        }
    } else {
        http_response_code(405);
        throw new RuntimeException($lang['invalid_request_method'] ?? 'Invalid request method.');
    }
} catch (PDOException $e) {
    error_log("Database Error in sales_order_handler.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $lang['database_error'] ?? 'Database error.']);
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} catch (RuntimeException $e) {
    $errorCode = $e->getCode() ?: 500;
    if ($errorCode < 400 || $errorCode >= 600) {
        $errorCode = 500;
    }
    http_response_code($errorCode);
    error_log("Runtime Error in sales_order_handler.php: (Code: {$errorCode}) " . $e->getMessage());
    $responseData = ['success' => false, 'message' => $e->getMessage()];
    if ($errorCode === 409 && isset(explode(': ', $e->getMessage())[1])) {
        $responseData['suggestion'] = explode(': ', $e->getMessage())[1];
    }
    echo json_encode($responseData);
    exit;
} catch (Exception $e) {
    error_log("Unexpected Error in sales_order_handler.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $lang['server_error'] ?? 'Unexpected server error.']);
    exit;
}
?>