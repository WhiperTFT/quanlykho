<?php
// File: process/delivery_handler.php
require_once '../includes/init.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_stats':
            $month = date('m');
            $year = date('Y');
            
            // Trips this month
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM dispatcher_trips WHERE MONTH(trip_date) = ? AND YEAR(trip_date) = ?");
            $stmt->execute([$month, $year]);
            $trips_count = $stmt->fetchColumn();
            
            // Total freight this month
            $stmt = $pdo->prepare("SELECT SUM(base_freight_cost + extra_costs) FROM dispatcher_trips WHERE MONTH(trip_date) = ? AND YEAR(trip_date) = ? AND status != 'cancelled'");
            $stmt->execute([$month, $year]);
            $total_freight = $stmt->fetchColumn() ?: 0;
            
            // Pending orders (not delivered and not in a completed trip)
            $stmt = $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status IN ('draft', 'sent', 'ordered', 'partially_received')");
            $pending_orders = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'trips_month' => $trips_count,
                    'total_freight' => number_format($total_freight, 0, ',', '.') . ' ₫',
                    'pending_orders' => $pending_orders
                ]
            ]);
            break;

        case 'get_available_orders':
            // Get orders that are not fully delivered
            // We consider 'ordered' and 'partially_received' as available
            // User requested to show orders even if they are already in a trip, to show a warning instead.
            $stmt = $pdo->query("
                SELECT so.id, so.order_number, so.order_date, p_sup.name as supplier_name, p_cus.name as customer_name, so.status,
                       t.trip_number as assigned_trip_number,
                       (SELECT GROUP_CONCAT(CONCAT(sod.product_name_snapshot, ' (', sod.quantity, ' ', IFNULL(sod.unit_snapshot, ''), ')') SEPARATOR '; ') 
                        FROM sales_order_details sod WHERE sod.order_id = so.id) as items_summary
                FROM sales_orders so
                JOIN partners p_sup ON so.supplier_id = p_sup.id
                LEFT JOIN sales_quotes sq ON so.quote_id = sq.id
                LEFT JOIN partners p_cus ON sq.customer_id = p_cus.id
                LEFT JOIN dispatcher_trip_orders dto ON so.id = dto.order_id
                LEFT JOIN dispatcher_trips t ON dto.trip_id = t.id AND t.status != 'cancelled'
                WHERE so.status IN ('draft', 'sent', 'ordered', 'partially_received')
                GROUP BY so.id
                ORDER BY so.order_date DESC
            ");
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'orders' => $orders]);
            break;

        case 'get_order_details_for_trip':
            $ids = $_GET['ids'] ?? '';
            if (empty($ids)) throw new Exception("No order IDs provided.");
            $id_array = array_map('trim', explode(',', $ids));
            $id_array = array_filter($id_array, function($v) { return $v !== ''; });
            if (empty($id_array)) throw new Exception("No valid order IDs provided.");
            
            $placeholders = implode(',', array_fill(0, count($id_array), '?'));
            
            $stmt = $pdo->prepare("
                SELECT so.id, so.order_number, so.status, p_sup.name as supplier_name, p_cus.name as customer_name,
                       (SELECT GROUP_CONCAT(CONCAT(sod.product_name_snapshot, ' (', sod.quantity, ' ', IFNULL(sod.unit_snapshot, ''), ')') SEPARATOR '; ') 
                        FROM sales_order_details sod WHERE sod.order_id = so.id) as items_summary
                FROM sales_orders so
                LEFT JOIN partners p_sup ON so.supplier_id = p_sup.id
                LEFT JOIN sales_quotes sq ON so.quote_id = sq.id
                LEFT JOIN partners p_cus ON sq.customer_id = p_cus.id
                WHERE so.id IN ($placeholders)
            ");
            $stmt->execute(array_values($id_array));
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'orders' => $orders]);
            break;

        case 'save_trip':
            $id = $_POST['trip_id'] ?? null;
            $trip_date = $_POST['trip_date'] ?? '';
            $driver_id = $_POST['driver_id'] ?? '';
            $vehicle_plate = $_POST['vehicle_plate'] ?? '';
            $base_freight_cost = (float)str_replace('.', '', $_POST['base_freight_cost'] ?? '0');
            $extra_costs = (float)str_replace('.', '', $_POST['extra_costs'] ?? '0');
            $notes = $_POST['notes'] ?? '';
            $status = $_POST['status'] ?? 'scheduled';
            $order_ids = $_POST['order_ids'] ?? []; // Array of order IDs to link

            if (empty($trip_date) || empty($driver_id)) {
                throw new Exception("Missing required fields (Date, Driver).");
            }

            $pdo->beginTransaction();

            if ($id) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE dispatcher_trips 
                    SET trip_date = ?, driver_id = ?, vehicle_plate = ?, base_freight_cost = ?, extra_costs = ?, notes = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$trip_date, $driver_id, $vehicle_plate, $base_freight_cost, $extra_costs, $notes, $status, $id]);
                $trip_id = $id;
                
                // Clear old links
                $pdo->prepare("DELETE FROM dispatcher_trip_orders WHERE trip_id = ?")->execute([$trip_id]);
            } else {
                // Create
                // Generate trip number: T-YYMM-XXX
                $prefix = 'T' . date('ym', strtotime($trip_date));
                $stmt = $pdo->prepare("SELECT trip_number FROM dispatcher_trips WHERE trip_number LIKE ? ORDER BY trip_number DESC LIMIT 1");
                $stmt->execute([$prefix . '-%']);
                $last = $stmt->fetchColumn();
                $seq = 1;
                if ($last) {
                    $seq = (int)substr($last, -3) + 1;
                }
                $trip_number = $prefix . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO dispatcher_trips (trip_number, trip_date, driver_id, vehicle_plate, base_freight_cost, extra_costs, notes, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$trip_number, $trip_date, $driver_id, $vehicle_plate, $base_freight_cost, $extra_costs, $notes, $status]);
                $trip_id = $pdo->lastInsertId();
            }

            // Link orders
            if (!empty($order_ids)) {
                $link_stmt = $pdo->prepare("INSERT INTO dispatcher_trip_orders (trip_id, order_id) VALUES (?, ?)");
                foreach ($order_ids as $oid) {
                    $link_stmt->execute([$trip_id, $oid]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Trip saved successfully', 'trip_id' => $trip_id, 'is_update' => !empty($id)]);
            break;

        case 'get_trip_details':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("
                SELECT t.*, d.ten as driver_name 
                FROM dispatcher_trips t 
                JOIN drivers d ON t.driver_id = d.id 
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trip) throw new Exception("Trip not found.");

            // Get linked orders
            $stmt = $pdo->prepare("
                SELECT dto.*, so.order_number, so.status as order_status, p_sup.name as supplier_name, p_cus.name as customer_name,
                       GROUP_CONCAT(CONCAT(sod.product_name_snapshot, ' (', sod.quantity, ' ', IFNULL(sod.unit_snapshot, ''), ')') SEPARATOR '; ') as items_summary
                FROM dispatcher_trip_orders dto
                JOIN sales_orders so ON dto.order_id = so.id
                JOIN partners p_sup ON so.supplier_id = p_sup.id
                LEFT JOIN sales_quotes sq ON so.quote_id = sq.id
                LEFT JOIN partners p_cus ON sq.customer_id = p_cus.id
                JOIN sales_order_details sod ON so.id = sod.order_id
                WHERE dto.trip_id = ?
                GROUP BY so.id
            ");
            $stmt->execute([$id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'trip' => $trip,
                'orders' => $orders
            ]);
            break;

        case 'get_order_details_for_trip':
            $ids = $_GET['ids'] ?? '';
            if (empty($ids)) throw new Exception("No order IDs provided.");
            $id_array = explode(',', $ids);
            $placeholders = implode(',', array_fill(0, count($id_array), '?'));
            
            $stmt = $pdo->prepare("
                SELECT so.id, so.order_number, so.status, p_sup.name as supplier_name, p_cus.name as customer_name,
                       (SELECT GROUP_CONCAT(CONCAT(sod.product_name_snapshot, ' (', sod.quantity, ' ', IFNULL(sod.unit_snapshot, ''), ')') SEPARATOR '; ') 
                        FROM sales_order_details sod WHERE sod.order_id = so.id) as items_summary
                FROM sales_orders so
                JOIN partners p_sup ON so.supplier_id = p_sup.id
                LEFT JOIN sales_quotes sq ON so.quote_id = sq.id
                LEFT JOIN partners p_cus ON sq.customer_id = p_cus.id
                WHERE so.id IN ($placeholders)
            ");
            $stmt->execute($id_array);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'orders' => $orders]);
            break;

        case 'get_trip_items_details':
            // Products
            $stmt = $pdo->prepare("
                SELECT 
                    p.id as product_code, p.name as product_name, u.name as unit_name,
                    SUM(sod.quantity) as total_qty
                FROM dispatcher_trip_orders dto
                JOIN sales_orders so ON dto.order_id = so.id
                JOIN sales_order_details sod ON so.id = sod.order_id
                JOIN products p ON sod.product_id = p.id
                LEFT JOIN units u ON p.unit_id = u.id
                WHERE dto.trip_id = ?
                GROUP BY p.id
            ");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Orders (for attachments/details)
            $stmt = $pdo->prepare("
                SELECT so.id, so.order_number, p_sup.name as supplier_name, p_cus.name as customer_name
                FROM dispatcher_trip_orders dto
                JOIN sales_orders so ON dto.order_id = so.id
                JOIN partners p_sup ON so.supplier_id = p_sup.id
                LEFT JOIN sales_quotes sq ON so.quote_id = sq.id
                LEFT JOIN partners p_cus ON sq.customer_id = p_cus.id
                WHERE dto.trip_id = ?
            ");
            $stmt->execute([$id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'items' => $items, 'orders' => $orders]);
            break;

        case 'delete_trip':
            $id = $_POST['id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM dispatcher_trips WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Trip deleted successfully']);
            break;

        case 'search_trips':
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? 'all';
            
            $query = "
                SELECT t.*, d.ten as driver_name 
                FROM dispatcher_trips t 
                JOIN drivers d ON t.driver_id = d.id 
                WHERE 1=1
            ";
            $params = [];
            
            if ($status !== 'all') {
                $query .= " AND t.status = ?";
                $params[] = $status;
            }
            
            if (!empty($search)) {
                $query .= " AND (t.trip_number LIKE ? OR d.ten LIKE ? OR t.notes LIKE ?)";
                $search_param = '%' . $search . '%';
                $params[] = $search_param;
                $params[] = $search_param;
                $params[] = $search_param;
            } else {
                // Limit to recent trips if no search term, unless filtered by status
                if ($status === 'all') {
                    $query .= " AND t.trip_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                }
            }
            
            $query .= " ORDER BY t.trip_date DESC, t.created_at DESC LIMIT 50";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'trips' => $trips]);
            break;

        default:
            throw new Exception("Invalid action: $action");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
