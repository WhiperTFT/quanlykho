<?php
// menu_handler.php
require_once '../includes/init.php';
require_once '../includes/auth_check.php';

global $pdo;
header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL trong menu_handler.']);
    exit;
}
if (!is_admin()) {
    echo json_encode(['status' => 'error', 'message' => 'Không có quyền truy cập.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Hàm đệ quy để xây dựng cây menu từ một danh sách phẳng.
 * Hàm này không thay đổi.
 */
function buildMenuTree(array $elements, $parentId = 0) {
    $branch = [];
    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $children = buildMenuTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}


switch ($action) {
    case 'fetch':
        try {
            // Sử dụng trực tiếp $pdo
            $stmt = $pdo->prepare("SELECT * FROM menus ORDER BY parent_id, sort_order ASC");
            $stmt->execute();
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $menuTree = buildMenuTree($menus);
            echo json_encode(['status' => 'success', 'menus' => $menuTree]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'save':
        try {
            $id = $_POST['id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $name_en = trim($_POST['name_en'] ?? '');
            $url = trim($_POST['url'] ?? '#');
            $icon = trim($_POST['icon'] ?? 'bi-circle');
            $permission_key = trim($_POST['permission_key'] ?? '');

            if (empty($name)) {
                throw new Exception("Tên menu không được để trống.");
            }

            if ($id) { // Cập nhật
                $stmt = $pdo->prepare("UPDATE menus SET name = ?, name_en = ?, url = ?, icon = ?, permission_key = ? WHERE id = ?");
                $stmt->execute([$name, $name_en, $url, $icon, $permission_key, $id]);
                $message = 'Cập nhật menu thành công.';
            } else { // Thêm mới
                $stmt = $pdo->prepare("INSERT INTO menus (name, name_en, url, icon, permission_key, parent_id, sort_order) VALUES (?, ?, ?, ?, ?, 0, 999)");
                $stmt->execute([$name, $name_en, $url, $icon, $permission_key]);
                $message = 'Thêm menu thành công.';
                // Ghi log
                write_user_log($pdo, (int)$_SESSION['user_id'], 'menu_add', 'Đã thêm menu: ' . $name);
            }
            echo json_encode(['status' => 'success', 'message' => $message]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'delete':
        try {
            $id = $_POST['id'];
            // Kiểm tra xem menu có con không
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE parent_id = ?");
            $stmt->execute([$id]);
            // ghi log
            write_user_log($pdo, (int)$_SESSION['user_id'], 'menu_delete', 'Đã xóa menu ID=' . $id);
            if ($stmt->fetchColumn() > 0) {
                 throw new Exception("Không thể xóa menu có chứa menu con. Vui lòng di chuyển hoặc xóa các menu con trước.");
            }
            $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Đã xóa menu.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'reorder':
        try {
            $orderData = json_decode($_POST['order'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Dữ liệu thứ tự không hợp lệ.');
            }

            $pdo->beginTransaction();

            function updateOrder($menuItems, $parentId = 0) {
                global $pdo; // Sử dụng biến pdo toàn cục trong hàm
                foreach ($menuItems as $index => $item) {
                    $stmt = $pdo->prepare("UPDATE menus SET sort_order = ?, parent_id = ? WHERE id = ?");
                    $stmt->execute([$index + 1, $parentId, $item['id']]);
                    if (isset($item['children']) && is_array($item['children'])) {
                        updateOrder($item['children'], $item['id']);
                    }
                }
            }

            updateOrder($orderData);

            $pdo->commit();
            // Ghi log
            write_user_log($pdo, (int)$_SESSION['user_id'], 'menu_reorder', 'Đã thay đổi thứ tự menu.');
            echo json_encode(['status' => 'success', 'message' => 'Đã lưu thứ tự menu.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Không thể lưu thứ tự: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_one':
        try {
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
            $stmt->execute([$id]);
            $menu = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($menu) {
                echo json_encode(['status' => 'success', 'data' => $menu]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy menu.']);
            }
        } catch(PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;
        

    default:
        echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
        break;
        
        
}
