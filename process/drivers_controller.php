<?php
// process/drivers_controller.php

ob_start(); // Bắt đầu output buffering

header('Content-Type: application/json');

// Sử dụng file init.php của bạn
// File này sẽ khởi tạo $pdo, session, $lang,...
require_once __DIR__ . '/../includes/init.php';

// --- Hàm tiện ích để gửi phản hồi JSON ---
function send_json_response($success, $message, $data = null) {
    $response = ['success' => (bool)$success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if (ob_get_length()) ob_end_clean(); // Xóa buffer nếu có nội dung
    echo json_encode($response);
    exit;
}

// Lấy hành động từ request (GET hoặc POST)
$action = $_REQUEST['action'] ?? '';

// Kiểm tra biến $pdo từ init.php
if (!isset($pdo) || !$pdo) { // $pdo được kỳ vọng khởi tạo từ init.php
    send_json_response(false, 'Lỗi kết nối cơ sở dữ liệu nghiêm trọng. Biến $pdo không được thiết lập.');
}

// --- Xử lý các hành động ---
switch ($action) {
    case 'list':
        handle_list_drivers($pdo);
        break;
    case 'get':
        handle_get_driver($pdo);
        break;
    case 'add':
        handle_add_driver($pdo);
        break;
    case 'update':
        handle_update_driver($pdo);
        break;
    case 'delete':
        handle_delete_driver($pdo);
        break;
    default:
        send_json_response(false, 'Hành động không hợp lệ.');
        break;
}

// --- Hàm xử lý lấy danh sách tài xế ---
function handle_list_drivers(PDO $pdo) {
    $search_term = $_GET['search'] ?? ''; // Lấy từ khóa tìm kiếm từ URL
    $drivers = [];
    // Câu SQL cơ bản
    $sql = "SELECT id, ten, cccd, ngay_cap, noi_cap, sdt, bien_so_xe, ghi_chu FROM drivers";
    $params = []; // Mảng chứa các tham số cho câu lệnh prepared

    if (!empty($search_term)) {
        // Nếu có từ khóa, thêm điều kiện WHERE
        // Tìm kiếm trong các trường: ten, cccd, sdt, bien_so_xe
        $sql .= " WHERE ten LIKE :search_term_ten OR cccd LIKE :search_term_cccd OR sdt LIKE :search_term_sdt OR bien_so_xe LIKE :search_term_bien_so";
        // Gán giá trị cho các placeholder, thêm dấu % để tìm kiếm gần đúng (LIKE)
        $params[':search_term_ten'] = "%" . $search_term . "%";
        $params[':search_term_cccd'] = "%" . $search_term . "%";
        $params[':search_term_sdt'] = "%" . $search_term . "%";
        $params[':search_term_bien_so'] = "%" . $search_term . "%";
    }
    $sql .= " ORDER BY ten ASC"; // Sắp xếp theo tên

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params); // Truyền mảng params vào execute
        $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        send_json_response(true, 'Tải danh sách tài xế thành công.', $drivers);
    } catch (PDOException $e) {
        // Ghi log lỗi $e->getMessage() trong môi trường production
        send_json_response(false, 'Lỗi truy vấn CSDL khi tải danh sách: ' . $e->getMessage()); // Hiển thị lỗi để debug
    }
}

// --- Hàm xử lý lấy thông tin một tài xế ---
function handle_get_driver(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        send_json_response(false, 'ID tài xế không hợp lệ.');
    }

    $sql = "SELECT id, ten, cccd, ngay_cap, noi_cap, sdt, bien_so_xe, ghi_chu FROM drivers WHERE id = :id";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($driver) {
            send_json_response(true, 'Lấy thông tin tài xế thành công.', $driver);
        } else {
            send_json_response(false, 'Không tìm thấy tài xế.');
        }
    } catch (PDOException $e) {
        send_json_response(false, 'Lỗi truy vấn CSDL khi lấy thông tin tài xế.');
    }
}

// --- Hàm kiểm tra CCCD đã tồn tại (trừ ID hiện tại nếu có) ---
function is_cccd_exists(PDO $pdo, $cccd, $current_id = 0) {
    $sql = "SELECT id FROM drivers WHERE cccd = :cccd";
    $params = [':cccd' => $cccd];

    if ($current_id > 0) {
        $sql .= " AND id != :current_id";
        $params[':current_id'] = $current_id;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        // Lỗi thì nên coi như không tìm thấy để tránh chặn oan, hoặc log lỗi
        return false;
    }
}

// --- Hàm xử lý thêm mới tài xế ---
function handle_add_driver(PDO $pdo) {
    $ten = trim($_POST['ten'] ?? '');
    $cccd = trim($_POST['cccd'] ?? '');
    $ngay_cap = !empty(trim($_POST['ngay_cap'] ?? '')) ? trim($_POST['ngay_cap']) : null;
    $noi_cap = !empty(trim($_POST['noi_cap'] ?? '')) ? trim($_POST['noi_cap']) : null;
    $sdt = !empty(trim($_POST['sdt'] ?? '')) ? trim($_POST['sdt']) : null;
    $bien_so_xe = !empty(trim($_POST['bien_so_xe'] ?? '')) ? trim($_POST['bien_so_xe']) : null;
    $ghi_chu = !empty(trim($_POST['ghi_chu'] ?? '')) ? trim($_POST['ghi_chu']) : null;

    if (empty($ten)) send_json_response(false, 'Tên tài xế không được để trống.');
    if (empty($cccd)) send_json_response(false, 'CCCD không được để trống.');
    if (is_cccd_exists($pdo, $cccd)) send_json_response(false, 'Số CCCD này đã tồn tại.');
    if ($ngay_cap !== null && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $ngay_cap)) {
        send_json_response(false, 'Ngày cấp CCCD không hợp lệ. Định dạng YYYY-MM-DD.');
    }

    $sql = "INSERT INTO drivers (ten, cccd, ngay_cap, noi_cap, sdt, bien_so_xe, ghi_chu)
            VALUES (:ten, :cccd, :ngay_cap, :noi_cap, :sdt, :bien_so_xe, :ghi_chu)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ten', $ten);
        $stmt->bindParam(':cccd', $cccd);
        $stmt->bindParam(':ngay_cap', $ngay_cap); // PDO tự xử lý NULL
        $stmt->bindParam(':noi_cap', $noi_cap);
        $stmt->bindParam(':sdt', $sdt);
        $stmt->bindParam(':bien_so_xe', $bien_so_xe);
        $stmt->bindParam(':ghi_chu', $ghi_chu);

        if ($stmt->execute()) {
            send_json_response(true, 'Thêm mới tài xế thành công.', ['id' => $pdo->lastInsertId()]);
        } else {
            send_json_response(false, 'Lỗi khi thêm tài xế.');
        }
    } catch (PDOException $e) {
        send_json_response(false, 'Lỗi CSDL khi thêm tài xế: ' . $e->getMessage()); // Cho debug, production thì ẩn đi
    }
}

// --- Hàm xử lý cập nhật thông tin tài xế ---
function handle_update_driver(PDO $pdo) {
    $id = (int)($_POST['driver_id'] ?? 0);
    if ($id <= 0) send_json_response(false, 'ID tài xế không hợp lệ.');

    $ten = trim($_POST['ten'] ?? '');
    $cccd = trim($_POST['cccd'] ?? '');
    $ngay_cap = !empty(trim($_POST['ngay_cap'] ?? '')) ? trim($_POST['ngay_cap']) : null;
    $noi_cap = !empty(trim($_POST['noi_cap'] ?? '')) ? trim($_POST['noi_cap']) : null;
    $sdt = !empty(trim($_POST['sdt'] ?? '')) ? trim($_POST['sdt']) : null;
    $bien_so_xe = !empty(trim($_POST['bien_so_xe'] ?? '')) ? trim($_POST['bien_so_xe']) : null;
    $ghi_chu = !empty(trim($_POST['ghi_chu'] ?? '')) ? trim($_POST['ghi_chu']) : null;

    if (empty($ten)) send_json_response(false, 'Tên tài xế không được để trống.');
    if (empty($cccd)) send_json_response(false, 'CCCD không được để trống.');
    if (is_cccd_exists($pdo, $cccd, $id)) send_json_response(false, 'Số CCCD này đã được sử dụng bởi tài xế khác.');
    if ($ngay_cap !== null && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $ngay_cap)) {
        send_json_response(false, 'Ngày cấp CCCD không hợp lệ. Định dạng YYYY-MM-DD.');
    }

    $sql = "UPDATE drivers SET ten = :ten, cccd = :cccd, ngay_cap = :ngay_cap, noi_cap = :noi_cap,
            sdt = :sdt, bien_so_xe = :bien_so_xe, ghi_chu = :ghi_chu
            WHERE id = :id";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ten', $ten);
        $stmt->bindParam(':cccd', $cccd);
        $stmt->bindParam(':ngay_cap', $ngay_cap);
        $stmt->bindParam(':noi_cap', $noi_cap);
        $stmt->bindParam(':sdt', $sdt);
        $stmt->bindParam(':bien_so_xe', $bien_so_xe);
        $stmt->bindParam(':ghi_chu', $ghi_chu);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                send_json_response(true, 'Cập nhật thông tin tài xế thành công.');
            } else {
                send_json_response(true, 'Không có thay đổi nào được thực hiện hoặc không tìm thấy tài xế.');
            }
        } else {
            send_json_response(false, 'Lỗi khi cập nhật tài xế.');
        }
    } catch (PDOException $e) {
        send_json_response(false, 'Lỗi CSDL khi cập nhật tài xế: ' . $e->getMessage());
    }
}

// --- Hàm xử lý xóa tài xế ---
function handle_delete_driver(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0); // JS gửi ID qua GET
    if ($id <= 0) send_json_response(false, 'ID tài xế không hợp lệ.');

    $sql = "DELETE FROM drivers WHERE id = :id";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                send_json_response(true, 'Xóa tài xế thành công.');
            } else {
                send_json_response(false, 'Không tìm thấy tài xế để xóa hoặc đã được xóa.');
            }
        } else {
            send_json_response(false, 'Lỗi khi xóa tài xế.');
        }
    } catch (PDOException $e) {
        send_json_response(false, 'Lỗi CSDL khi xóa tài xế: ' . $e->getMessage());
    }
}

if (ob_get_length()) ob_end_flush(); // Gửi output buffer (nếu có)
?>