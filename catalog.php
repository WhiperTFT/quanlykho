<?php
// catalog.php - 3-Column Modern Redesign
$ajax_action = $_GET['ajax_action'] ?? $_POST['ajax_action'] ?? null;
if ($ajax_action) {
    require_once __DIR__ . '/includes/init.php';
    if (!is_logged_in()) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
    header('Content-Type: application/json');

    try {
        if ($ajax_action === 'get_parents') {
            $stmt = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($ajax_action === 'get_children') {
            $parent_id = $_GET['parent_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE parent_id = ? ORDER BY name ASC");
            $stmt->execute([$parent_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($ajax_action === 'get_products') {
            $category_id = $_GET['category_id'] ?? 0;
            $search = $_GET['search'] ?? '';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $sql = "SELECT p.id, p.name, p.description, u.name as unit_name, p.unit_id,
                           (SELECT pf.file_path FROM product_files pf WHERE pf.product_id = p.id AND pf.file_type = 'image' ORDER BY pf.uploaded_at ASC LIMIT 1) as image_path
                    FROM products p
                    LEFT JOIN units u ON p.unit_id = u.id
                    WHERE p.category_id = ?";
            
            $params = [$category_id];
            
            if ($search !== '') {
                $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $sql .= " ORDER BY p.name ASC LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count total
            $countSql = "SELECT COUNT(*) FROM products p WHERE p.category_id = ?";
            $countParams = [$category_id];
            if ($search !== '') {
                $countSql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
                $countParams[] = "%$search%";
                $countParams[] = "%$search%";
            }
            $stmtCount = $pdo->prepare($countSql);
            $stmtCount->execute($countParams);
            $total = $stmtCount->fetchColumn();

            echo json_encode(['success' => true, 'data' => $products, 'total' => $total, 'page' => $page, 'has_more' => ($offset + $limit) < $total]);
            exit;
        }

        if ($ajax_action === 'inline_edit_product') {
            $id = $_POST['id'] ?? 0;
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            
            if ($field === 'name') {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$value, $id]);
            } elseif ($field === 'unit_id') {
                $stmt = $pdo->prepare("UPDATE products SET unit_id = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$value, $id]);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if ($ajax_action === 'bulk_delete_products') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids) && is_array($ids)) {
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                // Note: File cleanup would normally happen here, but we mimic process/product_handler behavior.
                $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($inQuery)");
                $stmt->execute($ids);
            }
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($ajax_action === 'bulk_move_products') {
            $ids = $_POST['ids'] ?? [];
            $new_category_id = $_POST['new_category_id'] ?? 0;
            if (!empty($ids) && is_array($ids) && $new_category_id > 0) {
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge([$new_category_id], $ids);
                $stmt = $pdo->prepare("UPDATE products SET category_id = ?, updated_at = NOW() WHERE id IN ($inQuery)");
                $stmt->execute($params);
            }
            echo json_encode(['success' => true]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ----------------------------------------------------
// Frontend rendering
// ----------------------------------------------------
$page_title = "Quản lý Danh mục & Hàng hóa";
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
require_login();

// Units cache for JS
$units_stmt = $pdo->query("SELECT id, name FROM units ORDER BY name ASC");
$units = $units_stmt->fetchAll(PDO::FETCH_ASSOC);

// All Categories for forms
$allCatStmt = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NOT NULL ORDER BY name ASC");
$flatChildren = $allCatStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* Modern 3-column layout styling */
:root {
  --bg-sidebar: #fdfdfd;
  --border-color: #dee2e6;
  --active-bg: #eef0fd;
  --active-color: #4361ee;
}
.catalog-container { height: calc(100vh - 140px); background: #fff; border-radius: 12px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
.col-pane { height: 100%; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; }

@media (max-width: 767.98px) {
    .catalog-container { height: auto; min-height: 100vh; overflow: visible; background: transparent; border: none; box-shadow: none; }
    .col-pane { height: 50vh; min-height: 400px; border-right: none; border-bottom: 1px solid var(--border-color); border-radius: 12px; background: #fff; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
    .col-pane:last-child { border-bottom: none; height: 75vh; }
}
.col-pane:last-child { border-right: none; }
.pane-header { padding: 16px; background: #fff; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 1030; }
.pane-body { flex: 1; overflow-y: auto; background: var(--bg-sidebar); padding: 10px; }
.pane-title { font-weight: 700; font-size: 1rem; margin: 0; color: #343a40; text-transform: uppercase; letter-spacing: 0.5px; }

/* List group aesthetics */
.cat-item { cursor: pointer; border: none; border-radius: 8px; margin-bottom: 4px; padding: 12px 16px; font-weight: 500; color: #495057; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; }
.cat-item:hover { background: #f8f9fa; }
.cat-item.active { background: var(--active-bg); color: var(--active-color); font-weight: 600; box-shadow: inset 3px 0 0 var(--active-color); }
.cat-action-btn { opacity: 0; transition: opacity 0.2s; }
.cat-item:hover .cat-action-btn { opacity: 1; }

/* Table styling */
.prod-table { background: #fff; border-radius: 8px; overflow: hidden; }
.prod-table th { background: #f8f9fa; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; color: #6c757d; font-weight: 600; border-bottom: 2px solid var(--border-color); padding: 14px 16px; }
.prod-table td { padding: 14px 16px; vertical-align: middle; color: #212529; font-weight: 500; }
.inline-edit-input { border: 1px dashed #ccc; padding: 4px 8px; background: #fdfdfd; width: 100%; font-weight: 500; transition: all 0.2s; }
.inline-edit-input:focus { border-color: var(--active-color); outline: none; background: #fff; }
.inline-edit-select { border: 1px dashed #ccc; padding: 4px; background: transparent; }

/* Product thumbnail */
.prod-thumb { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; border: 1px solid #eee; background: #f8f9fa; }
</style>

<div class="page-header border-bottom pt-2 pb-3">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="bi bi-grid-fill me-2 text-primary"></i>Quản lý Catalog</h1>
        <p class="text-muted mb-0 small">Quản lý danh mục, hàng hóa và sản phẩm</p>
    </div>
</div>

<div class="catalog-container row g-0" id="catalogTree">
    <!-- COL 1: Parents -->
    <div class="col-12 col-md-3 col-pane">
        <div class="pane-header">
            <h2 class="pane-title">Danh mục Cha</h2>
            <button class="btn btn-sm btn-primary" onclick="openAddCategoryModal(null)"><i class="bi bi-plus-lg"></i></button>
        </div>
        <div class="pane-body">
            <div class="input-group input-group-sm mb-3 shadow-sm rounded">
                 <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                 <input type="text" id="searchParents" class="form-control border-start-0 focus-none" placeholder="Tìm danh mục...">
            </div>
            <div id="parentList">
                <div class="text-center mt-5 text-muted"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>
    </div>

    <!-- COL 2: Children -->
    <div class="col-12 col-md-3 col-pane" style="background:#f4f6f9;">
        <div class="pane-header">
            <h2 class="pane-title">Danh mục Con</h2>
            <button class="btn btn-sm btn-primary" id="btnAddChild" disabled onclick="openAddCategoryModal(currentParentId)"><i class="bi bi-plus-lg"></i></button>
        </div>
        <div class="pane-body">
            <div class="input-group input-group-sm mb-3 shadow-sm rounded">
                 <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                 <input type="text" id="searchChildren" class="form-control border-start-0 focus-none" placeholder="Tìm danh mục con..." disabled>
            </div>
            <div id="childList">
                <div class="text-center mt-5 text-muted small"><i class="bi bi-arrow-left-circle me-1"></i>Chọn một Danh mục Cha</div>
            </div>
        </div>
    </div>

    <!-- COL 3: Products -->
    <div class="col-12 col-md-6 col-pane bg-white">
        <div class="pane-header d-flex justify-content-between flex-wrap gap-2">
            <h2 class="pane-title d-flex align-items-center"><span id="productPaneTitle">Sản phẩm</span></h2>
            <div class="d-flex gap-2">
                <div class="input-group input-group-sm rounded shadow-sm" style="width:200px;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchProducts" class="form-control border-start-0" placeholder="Tìm sản phẩm..." disabled>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="bulkActionsBtn" data-bs-toggle="dropdown" disabled>
                        Tác vụ <span id="selectedCount" class="badge bg-secondary ms-1">0</span>
                    </button>
                    <ul class="dropdown-menu shadow">
                        <li><a class="dropdown-item text-danger" href="#" onclick="bulkDelete()"><i class="bi bi-trash me-2"></i>Xóa đã chọn</a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#bulkMoveModal"><i class="bi bi-arrows-move me-2"></i>Chuyển danh mục</a></li>
                    </ul>
                </div>
                <button class="btn btn-sm btn-success shadow-sm btn-add-product" id="btnAddProduct" disabled><i class="bi bi-plus-lg me-1"></i>Sản phẩm</button>
            </div>
        </div>
        <div class="pane-body p-0 position-relative" id="productPaneBody">
            <div id="productEmptyState" class="text-center mt-5 pt-5 text-muted small">
                <i class="bi bi-arrow-left-circle fs-3 d-block mb-2"></i>Chọn kết hợp Danh mục để xem Sản phẩm
            </div>
            <div class="table-responsive h-100 d-none" id="productTableWrapper">
                <table class="table prod-table table-hover mb-0">
                    <thead class="sticky-top shadow-sm">
                        <tr>
                            <th style="width: 40px;"><input class="form-check-input" type="checkbox" id="checkAll"></th>
                            <th style="width: 60px;">Ảnh</th>
                            <th>Tên Sản phẩm <i class="bi bi-pencil-fill text-muted ms-1 small" title="Double click to edit"></i></th>
                            <th style="width: 140px;">ĐVT <i class="bi bi-pencil-fill text-muted ms-1 small"></i></th>
                            <th style="width: 80px;" class="text-end">#</th>
                        </tr>
                    </thead>
                    <tbody id="productList"></tbody>
                </table>
                <div class="p-3 text-center d-none" id="loadMoreSection">
                    <button class="btn btn-sm btn-outline-primary rounded-pill px-4" id="btnLoadMore"><i class="bi bi-arrow-down me-2"></i>Tải thêm</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Quick Category Modal -->
<div class="modal fade" id="quickCatModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title fs-6 fw-bold" id="quickCatModalLabel">Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="qc_id">
        <input type="hidden" id="qc_parent_id">
        <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Tên danh mục</label>
            <input type="text" class="form-control" id="qc_name" required>
        </div>
      </div>
      <div class="modal-footer p-2 bg-light">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="button" class="btn btn-sm btn-primary px-3" onclick="saveCategory()">Lưu</button>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Move Modal -->
<div class="modal fade" id="bulkMoveModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light">
        <h5 class="modal-title fs-6 fw-bold">Chuyển danh mục</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <select class="form-select form-select-sm" id="bulkMoveCatId">
            <option value="">-- Chọn danh mục con đích --</option>
            <?php foreach($flatChildren as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer p-2 bg-light">
        <button type="button" class="btn btn-sm btn-primary" onclick="executeBulkMove()">Chuyển</button>
      </div>
    </div>
  </div>
</div>

<?php 
// Include standard modals if we still want to reuse the massive add product modal from includes
// For maximum compatibility we'll use includes/catalog_modals.php for adding an entire product with images.
$modal_data = ['lang' => $lang, 'units' => $units, 'child_categories' => $flatChildren];
require_once __DIR__ . '/includes/catalog_modals.php';

echo '<script>const LANG = ' . json_encode($lang) . ';</script>';

require_once __DIR__ . '/includes/footer.php'; 
?>

<script src="assets/js/catalog.js?v=<?= time() ?>"></script>
<script>
const UNITS = <?= json_encode($units) ?>;
let currentParentId = null;
let currentChildId = null;
let prodPage = 1;
let prodSearch = '';

$(document).ready(function() {
    loadParents();

    // Search Category
    $('#searchParents').on('input', function() {
        let keyword = $(this).val().toLowerCase();
        $('#parentList .cat-item').each(function() {
            let name = $(this).find('span.cat-name').text().toLowerCase();
            $(this).toggle(name.includes(keyword));
        });
    });
    $('#searchChildren').on('input', function() {
        let keyword = $(this).val().toLowerCase();
        $('#childList .cat-item').each(function() {
            let name = $(this).find('span.cat-name').text().toLowerCase();
            $(this).toggle(name.includes(keyword));
        });
    });

    // Search Products
    let timeout = null;
    $('#searchProducts').on('input', function() {
        clearTimeout(timeout);
        prodSearch = $(this).val();
        timeout = setTimeout(function() {
            loadProducts(true);
        }, 300);
    });

    $('#btnLoadMore').on('click', function() {
        prodPage++;
        loadProducts(false);
    });

    // Checkbox mapping
    $('#checkAll').on('change', function() {
        $('.prod-checkbox').prop('checked', $(this).is(':checked'));
        updateBulkActions();
    });
    $(document).on('change', '.prod-checkbox', function() {
        updateBulkActions();
    });
});

function updateBulkActions() {
    let count = $('.prod-checkbox:checked').length;
    $('#selectedCount').text(count);
    $('#bulkActionsBtn').prop('disabled', count === 0);
}

// ==== Categories AJAX ====
function loadParents() {
    $.getJSON('catalog.php', { ajax_action: 'get_parents' }, function(res) {
        if (!res.success) return;
        let html = '';
        res.data.forEach(c => {
            html += `<div class="cat-item parent-item" data-id="${c.id}" onclick="selectParent(${c.id}, this)">
                        <span class="cat-name text-truncate">${escapeHtml(c.name)}</span>
                        <div class="cat-action-btn">
                            <i class="bi bi-pencil ms-2 text-warning" onclick="event.stopPropagation(); editCategory(${c.id}, '${escapeHtml(c.name)}', null)"></i>
                            <i class="bi bi-trash ms-2 text-danger" onclick="event.stopPropagation(); deleteCategory(${c.id}, 'parent')"></i>
                        </div>
                     </div>`;
        });
        $('#parentList').html(html || '<div class="text-muted small text-center mt-3">Trống</div>');
    });
}

function selectParent(id, element) {
    $('.parent-item').removeClass('active');
    $(element).addClass('active');
    currentParentId = id;
    
    // Reset child context
    currentChildId = null;
    $('#btnAddChild, #searchChildren').prop('disabled', false);
    $('#searchChildren').val('');
    
    // Reset product context
    resetProductContext();
    
    $('#childList').html('<div class="text-center mt-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>');
    
    $.getJSON('catalog.php', { ajax_action: 'get_children', parent_id: id }, function(res) {
        if (!res.success) return;
        let html = '';
        res.data.forEach(c => {
            html += `<div class="cat-item child-item" data-id="${c.id}" onclick="selectChild(${c.id}, '${escapeHtml(c.name)}', this)">
                        <span class="cat-name text-truncate">${escapeHtml(c.name)}</span>
                        <div class="cat-action-btn">
                            <i class="bi bi-pencil ms-2 text-warning" onclick="event.stopPropagation(); editCategory(${c.id}, '${escapeHtml(c.name)}', ${id})"></i>
                            <i class="bi bi-trash ms-2 text-danger" onclick="event.stopPropagation(); deleteCategory(${c.id}, 'child')"></i>
                        </div>
                     </div>`;
        });
        $('#childList').html(html || '<div class="text-muted small text-center mt-3">Trống</div>');
    });
}

function selectChild(id, name, element) {
    $('.child-item').removeClass('active');
    $(element).addClass('active');
    currentChildId = id;
    
    $('#productPaneTitle').html(`Sản phẩm <i class="bi bi-chevron-right mx-1 small text-muted"></i> <span class="text-primary">${name}</span>`);
    $('#searchProducts, #btnAddProduct').prop('disabled', false);
    $('#btnAddProduct').data('category-id', id);
    $('#searchProducts').val('');
    prodSearch = '';
    
    loadProducts(true);
}

function resetProductContext() {
    $('#productPaneTitle').text('Sản phẩm');
    $('#productEmptyState').removeClass('d-none');
    $('#productTableWrapper').addClass('d-none');
    $('#searchProducts, #btnAddProduct, #bulkActionsBtn').prop('disabled', true);
    $('#productList').empty();
}

function loadProducts(resetFlag = false) {
    if (!currentChildId) return;
    if (resetFlag) {
        prodPage = 1;
        $('#productList').empty();
        $('#productEmptyState').addClass('d-none');
        $('#productTableWrapper').removeClass('d-none');
    }

    $.getJSON('catalog.php', { ajax_action: 'get_products', category_id: currentChildId, search: prodSearch, page: prodPage }, function(res) {
        if (!res.success) return;
        if (resetFlag && res.data.length === 0) {
            $('#productList').html(`<tr><td colspan="5" class="text-center text-muted py-5">Không có sản phẩm nào. Nhấn Thêm Sản Phẩm góc trên.</td></tr>`);
            $('#loadMoreSection').addClass('d-none');
            return;
        }

        let html = '';
        let unitOpts = UNITS.map(u => `<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('');

        res.data.forEach(p => {
            let img = p.image_path ? p.image_path : 'https://placehold.co/44?text=No+Img';
            // Selected unit pre-selection logic
            let uOptsSelected = unitOpts.replace(`value="${p.unit_id}"`, `value="${p.unit_id}" selected`);
            html += `<tr data-id="${p.id}">
                        <td><input class="form-check-input prod-checkbox" type="checkbox" value="${p.id}"></td>
                        <td><img src="${img}" class="prod-thumb"></td>
                        <td><input type="text" class="inline-edit-input border-0 bg-transparent rename-product" value="${escapeHtml(p.name)}" data-id="${p.id}"></td>
                        <td><select class="form-select form-select-sm inline-edit-select border-0 bg-transparent reunit-product" data-id="${p.id}">${uOptsSelected}</select></td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-info border-0 btn-view-product" data-id="${p.id}" title="Xem chi tiết / File đính kèm"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-sm btn-outline-secondary border-0 btn-edit-product" data-id="${p.id}" title="Sửa"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-sm btn-outline-danger border-0 btn-delete-product" data-id="${p.id}" data-name="${escapeHtml(p.name)}" title="Xóa"><i class="bi bi-trash"></i></button>
                        </td>
                     </tr>`;
        });
        
        $('#productList').append(html);
        $('#loadMoreSection').toggleClass('d-none', !res.has_more);
        updateBulkActions();
    });
}

// ==== Inline Edits ====
$(document).on('change', '.rename-product', function() {
    let id = $(this).data('id');
    let val = $(this).val();
    $.post('catalog.php', { ajax_action: 'inline_edit_product', field: 'name', id: id, value: val }, function(res) {
        if(res.success) console.log('Saved');
    });
});
$(document).on('change', '.reunit-product', function() {
    let id = $(this).data('id');
    let val = $(this).val();
    $.post('catalog.php', { ajax_action: 'inline_edit_product', field: 'unit_id', id: id, value: val }, function(res) {
        if(res.success) console.log('Saved');
    });
});

// ==== Category CRUD via Modal ====
let quickCatModalVar = new bootstrap.Modal(document.getElementById('quickCatModal'));

function openAddCategoryModal(parentId) {
    $('#qc_id').val('');
    $('#qc_parent_id').val(parentId || '');
    $('#qc_name').val('');
    $('#quickCatModalLabel').text(parentId ? 'Thêm Danh mục Con' : 'Thêm Danh mục Cha');
    quickCatModalVar.show();
}

function editCategory(id, name, parentId) {
    $('#qc_id').val(id);
    $('#qc_parent_id').val(parentId || '');
    $('#qc_name').val(name);
    $('#quickCatModalLabel').text('Sửa Danh mục');
    quickCatModalVar.show();
}

function saveCategory() {
    let id = $('#qc_id').val();
    let pId = $('#qc_parent_id').val();
    let name = $('#qc_name').val();
    
    // Call existing process/category_handler.php
    let data = { action: id ? 'edit' : 'add', name: name };
    if (id) data.category_id = id;
    if (pId) data.parent_id = pId; else data.parent_id = 'null';

    $.post('process/category_handler.php', data, function(res) {
        if (res.success) {
            if (document.activeElement) document.activeElement.blur();
            quickCatModalVar.hide();
            if (pId) {
                if(currentParentId === parseInt(pId)) selectParent(currentParentId, $('.parent-item.active'));
            } else {
                loadParents();
            }
        } else {
            alert(res.message);
        }
    });
}

function deleteCategory(id, type) {
    if (!confirm('Bạn có chắc xoá danh mục này?')) return;
    $.post('process/category_handler.php', { action: 'delete', id: id }, function(res) {
        if (res.success) {
            if (type === 'parent') loadParents();
            else if (type === 'child') selectParent(currentParentId, $('.parent-item.active'));
        } else alert(res.message);
    });
}

// ==== Bulk Actions ====
function bulkDelete() {
    let ids = $('.prod-checkbox:checked').map(function(){ return $(this).val(); }).get();
    if (ids.length === 0) return;
    if (!confirm(`Xoá ${ids.length} sản phẩm?`)) return;

    $.post('catalog.php', { ajax_action: 'bulk_delete_products', ids: ids }, function(res) {
        if (res.success) loadProducts(true);
    });
}

function executeBulkMove() {
    let ids = $('.prod-checkbox:checked').map(function(){ return $(this).val(); }).get();
    let newCatId = $('#bulkMoveCatId').val();
    if (ids.length === 0 || !newCatId) return;

    $.post('catalog.php', { ajax_action: 'bulk_move_products', ids: ids, new_category_id: newCatId }, function(res) {
         if (res.success) {
             bootstrap.Modal.getInstance(document.getElementById('bulkMoveModal')).hide();
             loadProducts(true);
         }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>