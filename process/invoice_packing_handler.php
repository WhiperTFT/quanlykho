<?php
// process/invoice_packing_handler.php

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/number_to_text_vn.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'generate_number':
        $prefix = $_GET['prefix'] ?? '';
        $year = $_GET['year'] ?? date('Y');
        
        if (empty($prefix)) {
            // Try to find the latest prefix from the table if none provided
            $stmt = $pdo->query("SELECT invoice_prefix FROM invoices ORDER BY id DESC LIMIT 1");
            $row = $stmt->fetch();
            $prefix = $row['invoice_prefix'] ?? '';
            
            if (empty($prefix)) {
                echo json_encode(['success' => false, 'message' => 'Prefix is required']);
                exit();
            }
        }

        try {
            $stmt = $pdo->prepare("SELECT MAX(invoice_seq) as max_seq FROM invoices WHERE invoice_prefix = ? AND invoice_year = ?");
            $stmt->execute([$prefix, $year]);
            $row = $stmt->fetch();
            $next_seq = ($row['max_seq'] ?? 0) + 1;
            
            $formatted_no = sprintf("%s/%s-%02d", $prefix, $year, $next_seq);
            
            echo json_encode([
                'success' => true, 
                'next_seq' => $next_seq,
                'formatted_no' => $formatted_no,
                'year' => $year
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'save':
        $id = $_POST['id'] ?? null;
        $invoice_prefix = $_POST['invoice_prefix'] ?? '';
        $invoice_year = $_POST['invoice_year'] ?? date('Y');
        $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
        $partner_bill_id = $_POST['partner_bill_id'] ?? null;
        $partner_ship_id = $_POST['partner_ship_id'] ?? null;
        $items = $_POST['items'] ?? []; // Array of items
        $total_amount = $_POST['total_amount'] ?? 0;
        $total_remark = $_POST['total_remark'] ?? '';
        $packing = $_POST['packing'] ?? '';
        $net_weight = $_POST['net_weight'] ?? '';

        if (empty($invoice_prefix) || empty($partner_bill_id) || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }

        // Generate Text for Total
        $total_text = read_number_vn($total_amount);

        try {
            $pdo->beginTransaction();

            if ($id) {
                // Update
                $stmt = $pdo->prepare("UPDATE invoices SET 
                    invoice_date = ?, partner_bill_id = ?, partner_ship_id = ?, 
                    items = ?, total_amount = ?, total_text = ?, total_remark = ?, 
                    packing = ?, net_weight = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $invoice_date, $partner_bill_id, $partner_ship_id, 
                    json_encode($items), $total_amount, $total_text, $total_remark,
                    $packing, $net_weight, $id
                ]);
                $message = "Invoice updated successfully";
                log_user_safe("UPDATE_INVOICE", "Updated invoice ID: $id");
            } else {
                // Create
                // Re-calculate sequence to avoid collisions
                $stmt_seq = $pdo->prepare("SELECT MAX(invoice_seq) as max_seq FROM invoices WHERE invoice_prefix = ? AND invoice_year = ?");
                $stmt_seq->execute([$invoice_prefix, $invoice_year]);
                $row_seq = $stmt_seq->fetch();
                $invoice_seq = ($row_seq['max_seq'] ?? 0) + 1;
                $invoice_no = sprintf("%s/%s-%02d", $invoice_prefix, $invoice_year, $invoice_seq);

                $stmt = $pdo->prepare("INSERT INTO invoices 
                    (invoice_no, invoice_prefix, invoice_year, invoice_seq, invoice_date, 
                     partner_bill_id, partner_ship_id, items, total_amount, total_text, total_remark, 
                     packing, net_weight) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $invoice_no, $invoice_prefix, $invoice_year, $invoice_seq, $invoice_date,
                    $partner_bill_id, $partner_ship_id, json_encode($items), 
                    $total_amount, $total_text, $total_remark, $packing, $net_weight
                ]);
                $id = $pdo->lastInsertId();
                $message = "Invoice created successfully";
                log_user_safe("CREATE_INVOICE", "Created invoice: $invoice_no");
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $message, 'id' => $id]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'list':
        try {
            $stmt = $pdo->query("SELECT i.*, pb.name as bill_to_name, ps.name as ship_to_name 
                                FROM invoices i 
                                LEFT JOIN partners pb ON i.partner_bill_id = pb.id 
                                LEFT JOIN partners ps ON i.partner_ship_id = ps.id 
                                ORDER BY i.id DESC");
            $data = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        try {
            $stmt = $pdo->prepare("SELECT i.*, 
                pb.name as bill_to_name, pb.address as bill_to_address, pb.phone as bill_to_phone, pb.tax_id as bill_to_tax,
                ps.name as ship_to_name, ps.address as ship_to_address, ps.phone as ship_to_phone, ps.tax_id as ship_to_tax
                FROM invoices i 
                LEFT JOIN partners pb ON i.partner_bill_id = pb.id 
                LEFT JOIN partners ps ON i.partner_ship_id = ps.id 
                WHERE i.id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            if ($data) {
                $data['items'] = json_decode($data['items'], true);
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invoice not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            exit();
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
            $stmt->execute([$id]);
            log_user_safe("DELETE_INVOICE", "Deleted invoice ID: $id");
            echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_last_prefix':
        try {
            $stmt = $pdo->query("SELECT invoice_prefix FROM invoices ORDER BY id DESC LIMIT 1");
            $row = $stmt->fetch();
            echo json_encode(['success' => true, 'prefix' => $row['invoice_prefix'] ?? '']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_partner_last_prefix':
        $partner_id = $_GET['partner_id'] ?? null;
        if (!$partner_id) {
            echo json_encode(['success' => false, 'message' => 'Partner ID is required']);
            exit();
        }
        try {
            $stmt = $pdo->prepare("SELECT invoice_prefix FROM invoices WHERE partner_bill_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$partner_id]);
            $row = $stmt->fetch();
            echo json_encode(['success' => true, 'prefix' => $row['invoice_prefix'] ?? '']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
