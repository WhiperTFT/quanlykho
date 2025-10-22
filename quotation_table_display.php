<?php
$result = $mysqli->query("SELECT q.*, p.name FROM quotations q JOIN partners p ON q.partner_id = p.id ORDER BY q.quotation_date DESC");
?>
<form method="GET" class="mb-3">
    <div class="row">
        <div class="col-md-4">
            <input type="text" name="search_partner" class="form-control" placeholder="Tên đối tác" value="<?= htmlspecialchars($_GET['search_partner'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <input type="text" name="search_title" class="form-control" placeholder="Tiêu đề" value="<?= htmlspecialchars($_GET['search_title'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
            <a href="quotation_table_display.php" class="btn btn-secondary">Xóa bộ lọc</a>
        </div>
    </div>
</form>
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
                        <td><?= htmlspecialchars($row['quotation_date']) ?></td>
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
                            <button class="btn btn-sm btn-info text-white btn-view"
                                    data-id="<?= $row['id'] ?>">
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
