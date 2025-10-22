<?php
// add_menu.php
session_start();
require_login();
$menu_config_path = __DIR__ . '/menu_config.php';

// Load menu hiện tại
$menu_items = [];
if (file_exists($menu_config_path)) {
    require $menu_config_path;
}

// Thêm hoặc cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $label = trim($_POST['label'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $icon = trim($_POST['icon'] ?? 'bi-link');
    $permission = trim($_POST['permission'] ?? '');
    $parent = trim($_POST['parent'] ?? '');
    $index = isset($_POST['edit_index']) ? (int)$_POST['edit_index'] : null;

    if ($label && $link) {
        $entry = [
            'label' => $label,
            'link' => $link,
            'icon' => $icon,
            'permission' => $permission,
            'parent' => $parent ?: null
        ];

        if ($index !== null && isset($menu_items[$index])) {
            $menu_items[$index] = $entry;
        } else {
            $menu_items[] = $entry;
        }

        $export = "<?php\n\n\$menu_items = " . var_export($menu_items, true) . ";\n";
        file_put_contents($menu_config_path, $export);

        header("Location: add_menu.php");
        exit;
    }
}

// Xóa
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_index = (int)$_GET['delete'];
    if (isset($menu_items[$del_index])) {
        unset($menu_items[$del_index]);
        $menu_items = array_values($menu_items);
        $export = "<?php\n\n\$menu_items = " . var_export($menu_items, true) . ";\n";
        file_put_contents($menu_config_path, $export);
        header("Location: add_menu.php");
        exit;
    }
}

// Gợi ý icon phổ biến
$icon_suggestions = ['bi-house', 'bi-box', 'bi-people', 'bi-cart', 'bi-bar-chart', 'bi-tools', 'bi-file-text', 'bi-list-task', 'bi-archive', 'bi-graph-up'];

$editing_index = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editing_item = $editing_index !== null && isset($menu_items[$editing_index]) ? $menu_items[$editing_index] : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container bg-white p-4 rounded shadow">
    <h2 class="mb-4">Quản lý Menu Navigation</h2>

    <form method="POST" class="row g-3 border-bottom pb-4 mb-4">
        <input type="hidden" name="edit_index" value="<?= $editing_index ?>">

        <div class="col-md-4">
            <label class="form-label">Tên menu</label>
            <input type="text" name="label" class="form-control" value="<?= $editing_item['label'] ?? '' ?>" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Đường dẫn (link)</label>
            <input type="text" name="link" class="form-control" value="<?= $editing_item['link'] ?? '' ?>" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Biểu tượng (Bootstrap Icon)</label>
            <select name="icon" class="form-select">
                <?php foreach ($icon_suggestions as $icon): ?>
                    <option value="<?= $icon ?>" <?= ($editing_item['icon'] ?? '') === $icon ? 'selected' : '' ?>>
                        <?= $icon ?>
                    </option>
                <?php endforeach; ?>
                <option value="custom">Khác (nhập tay)</option>
            </select>
            <input type="text" name="icon" class="form-control mt-2" placeholder="Tên icon tuỳ chọn" value="<?= $editing_item['icon'] ?? '' ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Menu cha (tuùy chọn)</label>
            <select name="parent" class="form-select">
                <option value="">-- KHÔNG --</option>
                <?php foreach ($menu_items as $index => $item): ?>
                    <option value="<?= $item['label'] ?>" <?= ($editing_item['parent'] ?? '') === $item['label'] ? 'selected' : '' ?>><?= $item['label'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Yêu cầu quyền (tuùy chọn)</label>
            <input type="text" name="permission" class="form-control" value="<?= $editing_item['permission'] ?? '' ?>">
        </div>

        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">Lưu menu</button>
            <a href="add_menu.php" class="btn btn-secondary">Làm mới</a>
        </div>
    </form>

    <h4 class="mb-3">Danh sách menu hiện tại</h4>
    <table class="table table-bordered table-hover">
        <thead class="table-light">
        <tr>
            <th>#</th>
            <th>Tên</th>
            <th>Link</th>
            <th>Icon</th>
            <th>Menu cha</th>
            <th>Quyền</th>
            <th>Hành động</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($menu_items as $i => $item): ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= htmlspecialchars($item['label']) ?></td>
                <td><?= htmlspecialchars($item['link']) ?></td>
                <td><i class="bi <?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['icon']) ?></td>
                <td><?= $item['parent'] ?? '' ?></td>
                <td><?= $item['permission'] ?? '' ?></td>
                <td>
                    <a href="?edit=<?= $i ?>" class="btn btn-sm btn-warning">Sửa</a>
                    <a href="?delete=<?= $i ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xác nhận xóa menu này?')">Xóa</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
