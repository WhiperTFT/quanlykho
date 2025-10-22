<?php
require_once __DIR__ . '/includes/header.php'; // Bao gồm header.php (đã include init.php)
require_login();
// Đảm bảo biến $available_permissions đã được định nghĩa trong init.php

$available_permissions = $available_permissions ?? [];
// --- Code xử lý logic của trang Quản lý Người dùng ---

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$message = '';

$current_user_id = $_SESSION['user_id'] ?? null;
$is_admin_user = is_admin(); // Lấy trạng thái admin của người dùng hiện tại

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);

    // Kiểm tra username đã tồn tại
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        $message = "Lỗi: Username đã tồn tại.";
    } else {
        // Tiếp tục xử lý thêm user...
    }
}
if ($stmt->fetch()) {
    $message = "Lỗi: Username đã tồn tại.";
} else {
    // tiếp tục thêm user
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        // Kiểm tra quyền thêm người dùng
        if (!has_permission('users_add')) { // Chỉ admin mới có quyền users_add theo logic hiện tại
            $message = $lang['error_permission_denied'] ?? "Lỗi: Chỉ ADMIN mới có quyền thêm người dùng mới.";
            $action = 'list';
        } else {
            // ... (Giữ nguyên code xử lý thêm người dùng nếu có quyền)
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $email = trim($_POST['email']);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role']; // Admin có thể chọn role khi thêm user
            $is_active = isset($_POST['is_active']) ? 1 : 0; // Admin có thể chọn active khi thêm user
            $selected_permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
            $assigned_permissions = array_intersect($selected_permissions, $available_permissions);
            $permissions_json = json_encode($assigned_permissions);

            if (empty($username) || empty($password) || empty($role)) {
                 $message = $lang['error_required_fields'] ?? "Lỗi: Các trường có dấu (*) là bắt buộc.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $sql = "INSERT INTO users (username, password_hash, email, full_name, role, is_active, permissions)
                        VALUES (:username, :password_hash, :email, :full_name, :role, :is_active, :permissions)";
                $stmt = $pdo->prepare($sql);

                try {
                    $stmt->execute([
                        ':username' => $username,
                        ':password_hash' => $password_hash,
                        ':email' => empty($email) ? null : $email,
                        ':full_name' => empty($full_name) ? null : $full_name,
                        ':role' => $role,
                        ':is_active' => $is_active,
                        ':permissions' => $permissions_json
                    ]);
                    $message = $lang['user_add_success'] ?? "Thêm người dùng thành công!";
                    
                    $action = 'list';
                } catch (PDOException $e) {
                     if ($e->getCode() == 23000) {
                         $message = $lang['error_user_email_exists'] ?? "Lỗi: Username hoặc Email đã tồn tại.";
                     } else {
                        $message = $lang['error_db_insert'] ?? "Lỗi khi thêm người dùng: " . $e->getMessage();
                     }
                    error_log("Add User Error: " . $e->getMessage());
                    
                }
            }
        }


    } elseif (isset($_POST['edit_user'])) {
         $edited_user_id = (int)$_POST['user_id'];

         // --- KIỂM TRA QUYỀN SỬA NGƯỜI DÙNG: Chỉ ADMIN hoặc Sửa CHÍNH MÌNH ---
         $can_edit_this_user = $is_admin_user || ($current_user_id && $edited_user_id === $current_user_id);

         if (!$can_edit_this_user) {
              $message = $lang['error_permission_denied'] ?? "Lỗi: Bạn không có quyền sửa thông tin người dùng này.";
              $action = 'list'; // Chuyển về trang danh sách nếu không có quyền
         } else {
             // TIẾP TỤC XỬ LÝ SỬA NẾU CÓ QUYỀN CHUNG (Admin hoặc Sửa chính mình)

             // Lấy thông tin người dùng đang được sửa từ database để giữ lại các giá trị không được phép sửa
             $sql_fetch_user = "SELECT role, is_active, permissions FROM users WHERE id = :id";
             $stmt_fetch_user = $pdo->prepare($sql_fetch_user);
             $stmt_fetch_user->execute([':id' => $edited_user_id]);
             $editing_user_data_before_save = $stmt_fetch_user->fetch(PDO::FETCH_ASSOC); // Sử dụng FETCH_ASSOC

             if (!$editing_user_data_before_save) {
                 $message = $lang['user_not_found'] ?? "Không tìm thấy người dùng để cập nhật.";
                 $action = 'list';
             } else {
                // Lấy dữ liệu từ form
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $full_name = trim($_POST['full_name']);
                $password = $_POST['password'];

                // Các trường Role và Is Active chỉ được cập nhật nếu người hiện tại là ADMIN
                // Nếu không phải Admin, giữ nguyên giá trị cũ từ DB
                $role = $editing_user_data_before_save['role'];
                $is_active = $editing_user_data_before_save['is_active'];

                if ($is_admin_user) { // Nếu người hiện tại là ADMIN
                     $role = $_POST['role'];
                     $is_active = isset($_POST['is_active']) ? 1 : 0;
                }

                 // Cột permissions chỉ được cập nhật nếu người hiện tại là ADMIN
                 // Nếu không phải Admin, giữ nguyên giá trị cũ từ DB
                 $permissions_json = $editing_user_data_before_save['permissions'];

                 if ($is_admin_user) { // Nếu người hiện tại là ADMIN
                      $selected_permissions = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
                      $assigned_permissions = array_intersect($selected_permissions, $available_permissions);
                      $permissions_json = json_encode($assigned_permissions);
                 }


                // Kiểm tra các trường bắt buộc (username, role - role đã được đảm bảo có giá trị)
                if (empty($username)) {
                     $message = $lang['error_required_fields'] ?? "Lỗi: Các trường có dấu (*) là bắt buộc.";
                } else {
                     $sql = "UPDATE users SET username=:username, email=:email, full_name=:full_name, role=:role, is_active=:is_active, permissions=:permissions";
                    $params = [
                        ':username' => $username,
                        ':email' => empty($email) ? null : $email,
                        ':full_name' => empty($full_name) ? null : $full_name,
                        ':role' => $role,
                        ':is_active' => $is_active,
                        ':permissions' => $permissions_json,
                        ':id' => $edited_user_id
                    ];

                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                         $sql = "UPDATE users SET username=:username, password_hash=:password_hash, email=:email, full_name=:full_name, role=:role, is_active=:is_active, permissions=:permissions";
                         $params[':password_hash'] = $password_hash;
                    }

                    $sql .= " WHERE id=:id";

                    $stmt = $pdo->prepare($sql);

                    try {
                        $stmt->execute($params);
                         if ($stmt->rowCount() > 0) {
                            $message = $lang['user_edit_success'] ?? "Cập nhật người dùng thành công!";
                             
                             // Nếu đang sửa chính tài khoản của mình và thay đổi vai trò hoặc quyền, cần cập nhật lại session
                             if ($current_user_id && $edited_user_id === $current_user_id) {
                                 // Chỉ cập nhật session nếu các giá trị này CÓ THỂ bị sửa bởi non-admin
                                 // Hiện tại non-admin không sửa được role và permissions, nên chỉ cần cập nhật username, full_name, email nếu cần
                                 $_SESSION['username'] = $username;
                                 // $_SESSION['role'] = $role; // Chỉ admin sửa được role, nên không cần cập nhật ở đây cho non-admin
                                 // $_SESSION['user_permissions'] = $assigned_permissions; // Chỉ admin sửa được permissions
                             }
                         } else {
                             $message = $lang['user_edit_no_change'] ?? "Không có thông tin người dùng nào được thay đổi hoặc không tìm thấy người dùng.";
                         }
                        $action = 'list';
                    } catch (PDOException $e) {
                         if ($e->getCode() == 23000) {
                             $message = $lang['error_user_email_exists'] ?? "Lỗi: Username hoặc Email đã tồn tại.";
                         } else {
                            $message = $lang['error_db_update'] ?? "Lỗi khi cập nhật người dùng: " . $e->getMessage();
                         }
                         error_log("Edit User Error: " . $e->getMessage());
                         
                    }
                }
             }
         }


    } elseif (isset($_POST['delete_user'])) {
         // Kiểm tra quyền xóa người dùng: Chỉ ADMIN và không phải chính mình
         if (!has_permission('users_delete')) { // Chỉ admin mới có quyền users_delete
              $message = $lang['error_permission_denied'] ?? "Lỗi: Bạn không có quyền xóa người dùng.";
              $action = 'list';
         } else {
            $deleted_user_id = (int)$_POST['user_id'];

             // Ngăn admin tự xóa tài khoản của chính mình
             if ($current_user_id && $deleted_user_id === $current_user_id) {
                  $message = $lang['error_cannot_delete_self'] ?? "Lỗi: Bạn không thể tự xóa tài khoản của chính mình.";
                  $action = 'list';
             } else {
                 // ... (Giữ nguyên code xử lý xóa người dùng nếu có quyền)
                 $sql = "DELETE FROM users WHERE id=:id";
                 $stmt = $pdo->prepare($sql);

                 try {
                     $stmt->execute([':id' => $deleted_user_id]);
                      if ($stmt->rowCount() > 0) {
                          $message = $lang['user_delete_success'] ?? "Xóa người dùng thành công!";
                           
                      } else {
                          $message = $lang['user_not_found'] ?? "Không tìm thấy người dùng để xóa.";
                      }
                     $action = 'list';
                 } catch (PDOException $e) {
                     $message = $lang['error_db_delete'] ?? "Lỗi khi xóa người dùng: " . $e->getMessage();
                      error_log("Delete User Error: " . $e->getMessage());
                      
                 }
             }
         }
    }
}

// --- Hiển thị giao diện HTML ---
?>

    <div class="container mt-4 mb-5 main-content">
        <h1><?= $lang['user_management_title'] ?? 'Quản lý Người dùng' ?></h1>

        <?php if ($message): ?>
            <div class="alert <?php echo (strpos($message, 'Lỗi') !== false || strpos($message, 'không có quyền') !== false) ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
                 <?php if (strpos($message, 'Lỗi') !== false || strpos($message, 'không có quyền') !== false): ?>
                    <i class="fas fa-times-circle"></i>
                 <?php else: ?>
                    <i class="fas fa-check-circle"></i>
                 <?php endif; ?>
                <?php echo htmlspecialchars($message); ?>
                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($action == 'list'): ?>
            
            <?php
            // Hiển thị nút "Thêm Người dùng Mới" chỉ nếu có quyền 'users_add' (chỉ admin)
            if (has_permission('users_add')) {
                echo '<p><a href="?action=add" class="btn btn-success mb-3"><i class="fas fa-user-plus"></i> ' . ($lang['add_new_user'] ?? 'Thêm Người dùng Mới') . '</a></p>';
            }
            ?>
            <h5><?= $lang['user_list_title'] ?? 'Danh sách Người dùng' ?></h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Tên hiển thị</th>
                            <th>Role</th>
                            <th>Kích hoạt</th>
                            <th>Ngày tạo</th>
                            <th>Ngày cập nhật gần nhất</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT id, username, email, full_name, role, is_active, created_at, updated_at FROM users ORDER BY created_at DESC";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute();
                        $users = $stmt->fetchAll();

                        if ($users) {
                            foreach($users as $row) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row["id"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["full_name"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["role"]) . "</td>";
                                echo "<td>" . ($row["is_active"] ? ($lang['yes'] ?? 'Có') : ($lang['no'] ?? 'Không')) . "</td>";
                                echo "<td>" . htmlspecialchars($row["created_at"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["updated_at"]) . "</td>";
                                echo "<td class='action-links'>";
                                // --- KIỂM TRA QUYỀN HIỂN THỊ NÚT SỬA: Chỉ ADMIN hoặc Sửa CHÍNH MÌNH ---
                                 $can_view_edit_button = $is_admin_user || ($current_user_id && $row['id'] === $current_user_id);
                                 if ($can_view_edit_button) {
                                    echo "<a href='?action=edit&id=" . htmlspecialchars($row["id"]) . "' class='btn btn-sm btn-primary me-1'><i class='fas fa-edit'></i> " . ($lang['edit'] ?? 'Sửa') . "</a>";
                                 }
                                // --- KIỂM TRA QUYỀN HIỂN THỊ NÚT XÓA: Chỉ ADMIN VÀ không phải chính mình ---
                                if (has_permission('users_delete') && ($current_user_id && $row['id'] !== $current_user_id)) { // has_permission('users_delete') ngầm định chỉ admin
                                     echo "<form method='POST' action='' class='d-inline' onsubmit='return confirm(\"" . ($lang['confirm_delete_user'] ?? 'Bạn có chắc chắn muốn xóa người dùng') . " \\\"" . addslashes($row['username']) . "\\\" không?\");'>";
                                    echo "<input type='hidden' name='user_id' value='" . htmlspecialchars($row['id']) . "'>";
                                    echo "<button type='submit' name='delete_user' class='btn btn-sm btn-danger'><i class='fas fa-trash-alt'></i> " . ($lang['delete'] ?? 'Xóa') . "</button>";
                                    echo "</form>";
                                }
                                 // Hiển thị "Không có quyền" nếu không có nút nào được hiển thị
                                 if (!$can_view_edit_button && !(has_permission('users_delete') && ($current_user_id && $row['id'] !== $current_user_id))) {
                                      echo $lang['no_permission'] ?? "Không có quyền";
                                 }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9'>" . ($lang['no_users_found'] ?? 'Không có người dùng nào.') . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>


        <?php elseif ($action == 'add'): ?>
            <h2><?= $lang['add_new_user'] ?? 'Thêm Người dùng Mới' ?></h2>
            <?php
            // Chỉ hiển thị form thêm nếu có quyền 'users_add' (chỉ admin)
            if (has_permission('users_add')):
                 $user_permissions = [];
            ?>
                <form method="POST" action="">
                    <input type="hidden" name="add_user" value="1">
                    <div class="row">
                        <div class="col-md-6">
                             <div class="form-group mb-3">
                                <label for="username"><?= $lang['username'] ?? 'Username' ?>: <span style="color: red;">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="password"><?= $lang['password'] ?? 'Mật khẩu' ?>: <span style="color: red;">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                             <div class="form-group mb-3">
                                <label for="email"><?= $lang['email'] ?? 'Email' ?>:</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="form-group mb-3">
                                <label for="full_name"><?= $lang['full_name'] ?? 'Họ và Tên' ?>:</label>
                                <input type="text" class="form-control" id="full_name" name="full_name">
                            </div>
                            <div class="form-group mb-3">
                                <label for="role"><?= $lang['role'] ?? 'Vai trò' ?>: <span style="color: red;">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user"><?= $lang['role_user'] ?? 'User' ?></option>
                                    <option value="manager"><?= $lang['role_manager'] ?? 'Manager' ?></option>
                                    <option value="admin"><?= $lang['role_admin'] ?? 'Admin' ?></option>
                                </select>
                            </div>
                             <div class="form-group mb-3">
                                 <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                                    <label class="form-check-label" for="is_active"><?= $lang['is_active'] ?? 'Kích hoạt?' ?></label>
                                 </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label><?= $lang['permissions'] ?? 'Cấp quyền' ?>:</label>
                                <div class="permission-checkboxes">
                                    <?php
                                     // Khi thêm user, chỉ admin thấy và sửa được checkbox quyền
                                    $can_edit_permissions_on_add = $is_admin_user; // Chỉ admin thấy và sửa
                                    foreach ($available_permissions as $permission):
                                    ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($permission); ?>" id="perm_<?php echo htmlspecialchars($permission); ?>"
                                                <?php echo $can_edit_permissions_on_add ? '' : 'disabled'; ?> >
                                            <label class="form-check-label" for="perm_<?php echo htmlspecialchars($permission); ?>">
                                                <?php echo htmlspecialchars($lang['permission_' . $permission] ?? str_replace('_', ' ', $permission)); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                     <?php if (!$can_edit_permissions_on_add): ?>
                                        <small class="form-text text-danger"><?= $lang['error_no_permission_edit_permissions'] ?? 'Bạn không có quyền thiết lập quyền cho người dùng mới.' ?></small>
                                     <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success me-2"><i class="fas fa-save"></i> <?= $lang['save'] ?? 'Lưu' ?></button>
                    <a href="?action=list" class="btn btn-secondary"><i class="fas fa-times"></i> <?= $lang['cancel'] ?? 'Hủy' ?></a>
                </form>
            <?php else: ?>
                 <p class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= $lang['error_permission_denied'] ?? 'Bạn không có quyền truy cập chức năng này.' ?></p>
            <?php endif; ?>

        <?php elseif ($action == 'edit' && $user_id > 0): ?>
            <?php
            // Lấy thông tin người dùng cần sửa, bao gồm cả cột permissions
            $sql = "SELECT id, username, email, full_name, role, is_active, permissions FROM users WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // Sử dụng FETCH_ASSOC

             // --- KIỂM TRA QUYỀN HIỂN THỊ FORM SỬA: Chỉ ADMIN hoặc Sửa CHÍNH MÌNH ---
            $can_view_edit_form = $is_admin_user || ($current_user_id && $user && $user['id'] === $current_user_id);

            if ($user && $can_view_edit_form): // Chỉ hiển thị form nếu tìm thấy user VÀ có quyền xem/sửa form này
                // Giải mã JSON permissions từ DB
                $user_permissions = json_decode($user['permissions'], true) ?? [];
                if (!is_array($user_permissions)) {
                    $user_permissions = [];
                }

                // --- ĐỊNH NGHĨA QUYỀN SỬA TỪNG PHẦN TRONG FORM ---
                // Quyền sửa Role và Active chỉ dành cho Admin
                $can_edit_role_active = $is_admin_user;
                // Quyền sửa Permissions chỉ dành cho Admin
                $can_edit_permissions = $is_admin_user; // Giả định chỉ admin sửa được permissions
                // Quyền sửa các trường còn lại (username, email, full_name, password)
                // Chỉ cần có quyền xem/sửa form là được phép sửa các trường này
                $can_edit_other_fields = $can_view_edit_form; // Luôn đúng nếu form hiển thị
            ?>
            <h2><?= $lang['edit_user'] ?? 'Sửa Thông tin Người dùng' ?>: <?php echo htmlspecialchars($user['username']); ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                 <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="username"><?= $lang['username'] ?? 'Username' ?>: <span style="color: red;">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required <?php echo $can_edit_other_fields ? '' : 'disabled'; ?> > <?php if (!$can_edit_other_fields): ?><input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>"><?php endif; ?>
                        </div>
                        <div class="form-group mb-3">
                            <label for="password"><?= $lang['password'] ?? 'Mật khẩu' ?> (<?= $lang['leave_blank_if_no_change'] ?? 'Để trống nếu không đổi' ?>):</label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo $can_edit_other_fields ? '' : 'disabled'; ?> > <?php if (!$can_edit_other_fields): ?><input type="hidden" name="password" value=""><?php endif; // Mật khẩu không gửi disabled value ?>
                             <small class="form-text text-muted"><?= $lang['enter_new_password_hint'] ?? 'Nhập mật khẩu mới nếu bạn muốn thay đổi.' ?></small>
                        </div>
                         <div class="form-group mb-3">
                            <label for="email"><?= $lang['email'] ?? 'Email' ?>:</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" <?php echo $can_edit_other_fields ? '' : 'disabled'; ?> > <?php if (!$can_edit_other_fields): ?><input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"><?php endif; ?>
                        </div>
                        <div class="form-group mb-3">
                            <label for="full_name"><?= $lang['full_name'] ?? 'Họ và Tên' ?>:</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" <?php echo $can_edit_other_fields ? '' : 'disabled'; ?> > <?php if (!$can_edit_other_fields): ?><input type="hidden" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>"><?php endif; ?>
                        </div>
                        <div class="form-group mb-3">
                            <label for="role"><?= $lang['role'] ?? 'Vai trò' ?>: <span style="color: red;">*</span></label>
                            <select class="form-select" id="role" name="role" required <?php echo $can_edit_role_active ? '' : 'disabled'; ?> > <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>><?= $lang['role_user'] ?? 'User' ?></option>
                                <option value="manager" <?php echo ($user['role'] == 'manager') ? 'selected' : ''; ?>><?= $lang['role_manager'] ?? 'Manager' ?></option>
                                <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>><?= $lang['role_admin'] ?? 'Admin' ?></option>
                            </select>
                             <?php if (!$can_edit_role_active): ?>
                                <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>"> <small class="form-text text-danger"><?= $lang['error_no_permission_edit_role'] ?? 'Bạn không có quyền sửa Vai trò.' ?></small>
                             <?php endif; ?>
                        </div>
                         <div class="form-group mb-3">
                             <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?php echo ($user['is_active'] ? 'checked' : ''); ?> <?php echo $can_edit_role_active ? '' : 'disabled'; ?> > <label class="form-check-label" for="is_active"><?= $lang['is_active'] ?? 'Kích hoạt?' ?></label>
                                 <?php if (!$can_edit_role_active): ?>
                                    <input type="hidden" name="is_active" value="<?php echo htmlspecialchars($user['is_active']); ?>"> <small class="form-text text-danger"><?= $lang['error_no_permission_edit_active'] ?? 'Bạn không có quyền sửa trạng thái Kích hoạt.' ?></small>
                                 <?php endif; ?>
                             </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                         <div class="form-group mb-3">
                            <label><?= $lang['permissions'] ?? 'Cấp quyền' ?>:</label>
                            <div class="permission-checkboxes">
                                <?php
                                // Chỉ hiển thị và cho phép sửa checkbox quyền nếu là ADMIN
                                foreach ($available_permissions as $permission):
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($permission); ?>" id="perm_<?php echo htmlspecialchars($permission); ?>"
                                            <?php echo in_array($permission, $user_permissions) ? 'checked' : ''; ?>
                                            <?php echo $can_edit_permissions ? '' : 'disabled'; ?> > <label class="form-check-label" for="perm_<?php echo htmlspecialchars($permission); ?>">
                                            <?php echo htmlspecialchars($lang['permission_' . $permission] ?? str_replace('_', ' ', $permission)); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>

                                 <?php if (!$can_edit_permissions): ?>
                                    <small class="form-text text-danger"><?= $lang['error_no_permission_edit_permissions'] ?? 'Bạn không có quyền sửa các thiết lập quyền.' ?></small>
                                 <?php endif; ?>
                            </div>
                        </div>
                    </div>
                 </div>

                <?php
                // Hiển thị nút Lưu/Cập nhật chỉ khi được phép sửa bất kỳ trường nào
                // Tức là, có thể sửa các trường thông tin cơ bản (username, email,...) HOẶC sửa role/active/permissions
                // Logic đơn giản hơn: Chỉ hiển thị nút này nếu form được phép hiển thị (có quyền xem/sửa form)
                if ($can_view_edit_form):
                ?>
                    <button type="submit" class="btn btn-success me-2"><i class="fas fa-save"></i> <?= $lang['update'] ?? 'Cập nhật' ?></button>
                <?php endif; ?>
                 <a href="?action=list" class="btn btn-secondary"><i class="fas fa-times"></i> <?= $lang['cancel'] ?? 'Hủy' ?></a>
            </form>
            <?php elseif (!$user): ?>
                <p class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= $lang['user_not_found'] ?? 'Không tìm thấy người dùng.' ?></p>
            <?php else: // user tồn tại nhưng không có quyền xem/sửa form ?>
                 <p class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= $lang['error_permission_denied'] ?? 'Bạn không có quyền truy cập thông tin người dùng này.' ?></p>
            <?php endif; ?>

        <?php endif; ?>

    </div> <?php
require_once __DIR__ . '/includes/footer.php'; // Bao gồm footer.php
?>