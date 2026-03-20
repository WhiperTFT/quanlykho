<?php
// process/fetch_logs.php
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json');

try {
    // 1. Base Query Components
    $selectQuery = "SELECT l.id, l.user_id, u.username, l.action, l.module, l.log_type, l.description, l.data, l.ip_address, l.user_agent, l.created_at FROM user_logs l LEFT JOIN users u ON l.user_id = u.id";
    $countQuery = "SELECT COUNT(*) FROM user_logs l LEFT JOIN users u ON l.user_id = u.id";
    
    $whereConditions = [];
    $params = [];

    // 2. Custom Filters
    if (!empty($_POST['f_user'])) {
        $whereConditions[] = "l.user_id = :f_user";
        $params[':f_user'] = (int)$_POST['f_user'];
    }
    if (!empty($_POST['f_module'])) {
        $whereConditions[] = "l.module = :f_module";
        $params[':f_module'] = $_POST['f_module'];
    }
    if (!empty($_POST['f_action'])) {
        $whereConditions[] = "l.action = :f_action";
        $params[':f_action'] = $_POST['f_action'];
    }
    if (!empty($_POST['f_date_start'])) {
        $whereConditions[] = "DATE(l.created_at) >= :f_date_start";
        $params[':f_date_start'] = $_POST['f_date_start'];
    }
    if (!empty($_POST['f_date_end'])) {
        $whereConditions[] = "DATE(l.created_at) <= :f_date_end";
        $params[':f_date_end'] = $_POST['f_date_end'];
    }

    // 3. Global Search (DataTables)
    $searchValue = $_POST['search']['value'] ?? '';
    if (!empty($searchValue)) {
        $whereConditions[] = "(l.description LIKE :search OR u.username LIKE :search OR l.action LIKE :search OR l.module LIKE :search)";
        $params[':search'] = '%' . $searchValue . '%';
    }

    // Apply WHERE
    $whereSql = '';
    if (count($whereConditions) > 0) {
        $whereSql = " WHERE " . implode(" AND ", $whereConditions);
    }

    $selectQuery .= $whereSql;
    $countQuery .= $whereSql;

    // 4. Sorting
    $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
    $orderDir = $_POST['order'][0]['dir'] ?? 'desc';
    $columns = ['l.created_at', 'u.username', 'l.module', 'l.action', 'l.log_type', 'l.ip_address'];
    $orderBy = $columns[$orderColumnIndex] ?? 'l.created_at';
    $orderDir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';
    $selectQuery .= " ORDER BY $orderBy $orderDir";

    // 5. Pagination
    $start = (int)($_POST['start'] ?? 0);
    $length = (int)($_POST['length'] ?? 50);
    if ($length > 0) {
        $selectQuery .= " LIMIT $start, $length";
    }

    // Execute Records Filtered Count
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->execute($params);
    $recordsFiltered = $stmtCount->fetchColumn();

    // Execute Total Records
    $totalRecords = $pdo->query("SELECT COUNT(*) FROM user_logs")->fetchColumn();

    // Execute Main Data Query
    $stmtData = $pdo->prepare($selectQuery);
    $stmtData->execute($params);
    $data = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // Format Data for DataTables
    foreach ($data as &$row) {
        $row['data'] = $row['data'] ? json_decode($row['data'], true) : [];
        $row['created_at_fmt'] = date('d/m/Y H:i:s', strtotime($row['created_at']));
    }

    echo json_encode([
        "draw" => intval($_POST['draw'] ?? 1),
        "recordsTotal" => intval($totalRecords),
        "recordsFiltered" => intval($recordsFiltered),
        "data" => $data
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => "Database error: " . $e->getMessage()]);
}
