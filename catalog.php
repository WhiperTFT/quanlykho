<?php
// File: catalog.php (Cập nhật để lấy thêm thông tin ảnh/pdf)

$page_title = "Quản lý Danh mục & Hàng hóa";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/init.php';
require_login();


// --- Lấy dữ liệu cần thiết cho trang ---
try {
    // Lấy tất cả danh mục
    $stmt_cat = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY parent_id ASC, name ASC");
    $all_categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
    $categoriesById = [];
    foreach ($all_categories as $cat) {
        $categoriesById[$cat['id']] = $cat;
    }

    // Lấy tất cả sản phẩm và join với đơn vị tính
    // **CẬP NHẬT:** Thêm subquery để lấy ảnh đầu tiên và pdf đầu tiên
    $sql_prod = "
        SELECT
            p.id, p.category_id, p.name, p.description, u.name as unit_name,
            (SELECT pf.file_path FROM product_files pf
             WHERE pf.product_id = p.id AND pf.file_type = 'image'
             ORDER BY pf.uploaded_at ASC LIMIT 1) as first_image_path,
            (SELECT pf.file_path FROM product_files pf
             WHERE pf.product_id = p.id AND pf.file_type = 'pdf'
             ORDER BY pf.uploaded_at ASC LIMIT 1) as first_pdf_path
        FROM products p
        JOIN units u ON p.unit_id = u.id
        ORDER BY p.name ASC
    ";
    $stmt_prod = $pdo->query($sql_prod);

    $productsByCategory = [];
    while ($row = $stmt_prod->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($productsByCategory[$row['category_id']])) {
            $productsByCategory[$row['category_id']] = [];
        }
        $productsByCategory[$row['category_id']][] = $row;
    }

    // Lấy danh sách đơn vị tính
    $units_stmt = $pdo->query("SELECT id, name FROM units ORDER BY name ASC");
    $units = $units_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách danh mục con
    $child_categories_stmt = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NOT NULL ORDER BY name ASC");
    $child_categories = $child_categories_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error on catalog.php: " . $e->getMessage());
    $errorMessage = isset($lang['database_error']) ? $lang['database_error'] : 'A database error occurred.';
    echo "<div class='alert alert-danger'>$errorMessage</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><i class="bi bi-journal-bookmark-fill me-2"></i><?= $lang['catalog_management'] ?? 'Catalog Management' ?></h1>
        <button class="btn btn-primary btn-add-parent-category">
            <i class="bi bi-folder-plus"></i> <?= $lang['add_parent_category'] ?? 'Add Parent Category' ?>
        </button>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                 <span class="input-group-text"><i class="bi bi-search"></i></span>
                 <input type="text" id="filterInput" class="form-control" placeholder="<?= $lang['filter_by_name'] ?? 'Filter by name...' ?>">
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0"> <ul class="list-group list-group-flush" id="catalogTree">
                <?php
                // Include file hiển thị cây, truyền dữ liệu vào
                require_once __DIR__ . '/includes/catalog_tree_display.php';
                if (!empty($categoriesById)) {
                    displayCatalogTree($categoriesById, $productsByCategory, $lang);
                } else {
                    echo '<li class="list-group-item">' . ($lang['no_categories_found'] ?? 'No categories found.') . '</li>';
                }
                ?>
            </ul>
        </div>
    </div>

</div>

<?php
// Include file chứa HTML của các Modals
$modal_data = [
    'lang' => $lang,
    'units' => $units,
    'child_categories' => $child_categories
];
require_once __DIR__ . '/includes/catalog_modals.php';
?>

<?php
// Truyền biến $lang sang Javascript
echo '<script>const LANG = ' . json_encode($lang) . ';</script>';

// Include footer
require_once __DIR__ . '/includes/footer.php';

// Nhúng file JS tùy chỉnh cho trang catalog
echo '<script src="assets/js/catalog.js?v=' . time() . '"></script>';
?>
