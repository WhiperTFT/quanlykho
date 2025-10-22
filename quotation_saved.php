<?php
// File: quotation_saved.php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
require_login();

// Khởi tạo kết nối mysqli
$mysqli = new mysqli("localhost", "root", "", "db_quanlykho");
if ($mysqli->connect_errno) {
    die("Lỗi kết nối: " . $mysqli->connect_error);
}

// Khởi tạo kết nối PDO cho logging
try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_quanlykho', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Lỗi kết nối PDO: " . $e->getMessage());
}

// Lấy user_id từ session
$user_id = $_SESSION['user_id'] ?? 0;

$editData = null;

// Hàm tạo HTML cho bảng danh sách báo giá
function getQuotationTable($mysqli, $search_partner = '', $search_title = '') {
    $sql = "SELECT q.*, p.name FROM quotations q JOIN partners p ON q.partner_id = p.id WHERE 1=1";
    if (!empty($search_partner)) {
        $sql .= " AND p.name LIKE '%" . $mysqli->real_escape_string($search_partner) . "%'";
    }
    if (!empty($search_title)) {
        $sql .= " AND q.title LIKE '%" . $mysqli->real_escape_string($search_title) . "%'";
    }
    $sql .= " ORDER BY q.quotation_date DESC";
    $result = $mysqli->query($sql);
    ob_start();
    ?>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-archive"></i> Danh sách báo giá đã lưu</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped" id="quotation_list">
                <thead>
                    <tr>
                        <th>Tên đối tác</th>
                        <th>Ngày</th>
                        <th>Tiêu đề</th>
                        <th>File</th>
                        <th class="text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()):
                        $files = json_decode($row['files_json'], true);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= date('d-m-Y', strtotime($row['quotation_date'])) ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td>
                                <?php foreach ($files as $f): ?>
                                    <a href="<?= htmlspecialchars($f) ?>" target="_blank" title="Xem tệp">
                                        <i class="bi bi-file-earmark-text" style="font-size: 1.2em;"></i>
                                    </a>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-center">
                                <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil-square"></i> Sửa
                                </a>
                                <button class="btn btn-sm btn-info text-white btn-view" data-id="<?= $row['id'] ?>">
                                    <i class="bi bi-eye"></i> Xem
                                </button>
                                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Xác nhận xóa?')" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i> Xóa
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Xử lý yêu cầu AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo getQuotationTable($mysqli, $_GET['search_partner'] ?? '', $_GET['search_title'] ?? '');
    exit;
}

// Xóa báo giá
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("DELETE FROM quotations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã xóa bảng báo giá thành công!'];
    write_user_log($pdo, $user_id, "Xóa bảng báo giá đã lưu", "Xóa danh sách báo giá đã lưu ID $id");
    echo "<script>location.href='quotation_saved.php';</script>";
    exit;
}

// Xóa file đính kèm
if (isset($_GET['delete_file'], $_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $file_to_delete = urldecode($_GET['delete_file']);

    $stmt = $mysqli->prepare("SELECT files_json FROM quotations WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result && !empty($result['files_json'])) {
        $files = json_decode($result['files_json'], true);
        $updated_files = array_filter($files, fn($f) => $f !== $file_to_delete);

        if ($files !== $updated_files) {
            $stmt = $mysqli->prepare("UPDATE quotations SET files_json = ? WHERE id = ?");
            $new_json = json_encode(array_values($updated_files), JSON_UNESCAPED_UNICODE);
            $stmt->bind_param("si", $new_json, $edit_id);
            $stmt->execute();
            $stmt->close();

            if (file_exists($file_to_delete)) {
                unlink($file_to_delete);
            }

            $_SESSION['alert'] = ['type' => 'success', 'message' => 'Đã xoá tệp thành công!'];
            write_user_log($pdo, $user_id, "Xóa file đính kèm", "Xóa file đính kèm khỏi báo giá ID $edit_id: $file_to_delete");
        }
    }
    echo "<script>location.href='quotation_saved.php?edit=$edit_id';</script>";
    exit;
}

// Lấy dữ liệu để chỉnh sửa
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $mysqli->prepare("SELECT q.*, p.name AS partner_name FROM quotations q JOIN partners p ON q.partner_id = p.id WHERE q.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Xử lý form khi submit
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $partner_name = $_POST['partner_name'];
    $title = $_POST['title'];
    $quotation_date_input = $_POST['quotation_date'];
    $notes = $_POST['notes'] ?? '';
    $quantities = $_POST['quantity'];
    $units = $_POST['unit'];
    $prices = $_POST['price'];

    $date = DateTime::createFromFormat('d-m-Y', $quotation_date_input);
    if ($date) {
        $quotation_date = $date->format('Y-m-d');
    } else {
        die("Ngày không hợp lệ. Vui lòng nhập theo định dạng d-m-Y.");
    }

    $stmt = $mysqli->prepare("SELECT id FROM partners WHERE name = ?");
    $stmt->bind_param("s", $partner_name);
    $stmt->execute();
    $stmt->bind_result($partner_id);
    if (!$stmt->fetch()) {
        die("Không tìm thấy đối tác.");
    }
    $stmt->close();

    $detail_json = json_encode(array_map(function($q, $u, $p) {
        return ["qty" => $q, "unit" => $u, "price" => $p];
    }, $quantities, $units, $prices), JSON_UNESCAPED_UNICODE);

    $files_info = [];
    if (!empty($_FILES['files']['name'][0])) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        foreach ($_FILES['files']['tmp_name'] as $i => $tmp_path) {
            $name = basename($_FILES['files']['name'][$i]);
            $target_path = $upload_dir . time() . "_" . $name;
            move_uploaded_file($tmp_path, $target_path);
            $files_info[] = $target_path;
        }
    }

    if (!empty($_POST['edit_id'])) {
        $edit_id = intval($_POST['edit_id']);
        $sql = "UPDATE quotations SET partner_id=?, quotation_date=?, title=?, notes=?, details_json=?";
        if (!empty($files_info)) {
            $files_json = json_encode($files_info, JSON_UNESCAPED_UNICODE);
            $sql .= ", files_json=?";
        }
        $sql .= " WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        if (!empty($files_info)) {
            $stmt->bind_param("isssssi", $partner_id, $quotation_date, $title, $notes, $detail_json, $files_json, $edit_id);
        } else {
            $stmt->bind_param("issssi", $partner_id, $quotation_date, $title, $notes, $detail_json, $edit_id);
        }
        $stmt->execute();
        $stmt->close();
        write_user_log($pdo, $user_id, "Cập nhật báo giá", "Cập nhật báo giá ID $edit_id cho đối tác $partner_name.");
    } else {
        $files_json = json_encode($files_info, JSON_UNESCAPED_UNICODE);
        $stmt = $mysqli->prepare("INSERT INTO quotations (partner_id, quotation_date, title, notes, details_json, files_json) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $partner_id, $quotation_date, $title, $notes, $detail_json, $files_json);
        $stmt->execute();
        $stmt->close();
        write_user_log($pdo, $user_id, "Thêm báo giá", "Thêm mới báo giá cho đối tác $partner_name.");
    }

    $_SESSION['alert'] = ['type' => 'success', 'message' => 'Lưu báo giá thành công!'];
    echo "<script>location.href='quotation_saved.php';</script>";
    exit;
}

// Lấy danh sách đơn vị từ bảng units
$unitsQuery = $mysqli->query("SELECT id, name FROM units ORDER BY name ASC");
$units = [];
while ($unit = $unitsQuery->fetch_assoc()) {
    $units[] = $unit;
}
?>

<div class="container mt-5">
    <div class="card shadow-lg">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="bi bi-file-earmark-plus"></i> Nhập báo giá mới</h4>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <?php if ($editData): ?>
                    <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">Tên đối tác</label>
                    <input type="text" name="partner_name" class="form-control" required placeholder="Nhập tên đối tác" value="<?= htmlspecialchars($editData['partner_name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tiêu đề báo giá</label>
                    <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editData['title'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ngày báo giá</label>
                    <input type="text" name="quotation_date" class="form-control flatpickr" required value="<?= isset($editData['quotation_date']) ? date('d-m-Y', strtotime($editData['quotation_date'])) : '' ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="notes" class="form-control ckeditor" rows="3"><?= htmlspecialchars($editData['notes'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Đơn vị</label>
                    <select name="unit_select" id="unit_select" class="form-control">
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= htmlspecialchars($unit['name']) ?>" 
                                <?= isset($editData) && $editData['details_json'] && json_decode($editData['details_json'], true)[0]['unit'] === $unit['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($unit['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Chi tiết báo giá</label>
                    <table class="table table-bordered align-middle text-center" id="quotation_table">
                        <thead class="table-light">
                            <tr><th>Số lượng</th><th>Đơn vị</th><th>Giá tiền</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $details = isset($editData) ? json_decode($editData['details_json'], true) : [['qty' => '', 'unit' => $units[0]['name'] ?? 'Cái', 'price' => '']];
                        foreach ($details as $d): ?>
                            <tr>
                                <td><input name="quantity[]" type="number" class="form-control" value="<?= $d['qty'] ?>" required></td>
                                <td><input name="unit[]" type="text" class="form-control unit-input" value="<?= $d['unit'] ?>" readonly></td>
                                <td><input name="price[]" type="number" class="form-control" value="<?= $d['price'] ?>" required></td>
                                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-secondary" onclick="addRow()"><i class="bi bi-plus-circle"></i> Thêm dòng</button>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tệp đính kèm</label>
                    <input type="file" name="files[]" class="form-control" multiple>
                    <?php if (!empty($editData['files_json'])): ?>
                        <div class="alert alert-secondary small mt-2">
                            <strong>File đã lưu:</strong><br>
                            <?php foreach (json_decode($editData['files_json'], true) as $i => $f): ?>
                            <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-1">
                                <a href="<?= $f ?>" target="_blank"><?= basename($f) ?></a>
                                <a href="?edit=<?= $editData['id'] ?>&delete_file=<?= urlencode($f) ?>" class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm('Bạn có chắc muốn xoá file này không?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Lưu báo giá</button>
            </form>
        </div>
    </div>

    <hr class="my-5">

    <form id="search-form" method="GET" class="mb-3">
        <div class="row">
            <div class="col-md-4">
                <input type="text" name="search_partner" class="form-control" placeholder="Tên đối tác" value="<?= htmlspecialchars($search_partner ?? '') ?>">
            </div>
            <div class="col-md-4">
                <input type="text" name="search_title" class="form-control" placeholder="Tiêu đề" value="<?= htmlspecialchars($search_title ?? '') ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                <button type="button" class="btn btn-secondary" id="clear-filter">Xóa bộ lọc</button>
            </div>
        </div>
    </form>
    <div id="quotation-list-container">
        <?php echo getQuotationTable($mysqli, $search_partner ?? '', $search_title ?? ''); ?>
    </div>

    <?php require_once __DIR__ . '/quotation_view_modal.php'; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/autocomplete_partner.js?v=1"></script>
<script src="assets/js/quotation_view.js?v=1"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    flatpickr(".flatpickr", {
        dateFormat: "d-m-Y",
        altInput: true,
        altFormat: "d-m-Y",
        allowInput: true
    });

    // Cập nhật đơn vị khi thay đổi select
    document.getElementById('unit_select').addEventListener('change', function() {
        const unit = this.value;
        const rows = document.querySelectorAll('#quotation_table tbody tr');
        rows.forEach(row => {
            const unitInput = row.querySelector('input[name="unit[]"]');
            if (unitInput) {
                unitInput.value = unit;
            }
        });
    });

    // Xử lý tìm kiếm AJAX
    $('#search-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
            type: 'GET',
            url: 'quotation_saved.php',
            data: formData,
            success: function(data) {
                $('#quotation-list-container').html(data);
            }
        });
    });

    $('#clear-filter').on('click', function() {
        $('#search-form input').val('');
        $('#search-form').submit();
    });
});

function addRow() {
    const table = document.getElementById('quotation_table').querySelector('tbody');
    const unit = document.getElementById('unit_select').value;
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input name="quantity[]" type="number" class="form-control" required></td>
        <td><input name="unit[]" type="text" class="form-control unit-input" value="${unit}" readonly></td>
        <td><input name="price[]" type="number" class="form-control" required></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
    `;
    table.appendChild(newRow);
}

function removeRow(button) {
    const row = button.closest('tr');
    row.remove();
}
</script>
<?php if (!empty($_SESSION['alert'])): ?>
<script>
Swal.fire({
    icon: '<?= $_SESSION['alert']['type'] ?>',
    title: '<?= $_SESSION['alert']['type'] === 'success' ? 'Thành công' : 'Lỗi' ?>',
    text: '<?= $_SESSION['alert']['message'] ?>',
    confirmButtonText: 'Đóng'
});
</script>
<?php unset($_SESSION['alert']); endif; ?>

<?php $mysqli->close(); ?>