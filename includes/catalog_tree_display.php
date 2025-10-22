<?php
// File: includes/catalog_tree_display.php (Cập nhật hiển thị ảnh/pdf và giao diện)

if (!isset($categoriesById) || !isset($productsByCategory) || !isset($lang)) {
    error_log("Required data not provided for catalog tree display.");
    echo '<li class="list-group-item text-danger">Error loading catalog data.</li>';
    return;
}

/**
 * Hiển thị cây danh mục và sản phẩm (hàm đệ quy).
 * Cập nhật để cải thiện giao diện và hiển thị ảnh/pdf.
 */
function displayCatalogTree(array $categories, array $products, array $lang, ?int $parentId = null, int $level = 0): bool
{
    $hasDirectChildren = false;
    $indentClass = 'ps-' . min($level * 2 + 1, 5); // Tăng thụt lề một chút, max ps-5

    foreach ($categories as $catId => $category) {
        if ($category['parent_id'] == $parentId) {
            $hasDirectChildren = true;
            $isParentCategory = ($category['parent_id'] === null);

            // Thêm class list-group-item-action để có hiệu ứng hover nhẹ
            echo '<li class="list-group-item category-item list-group-item-action border-0 ' . $indentClass . '" data-id="' . $catId . '" data-type="category" data-parent-id="' . ($parentId ?? 'null') . '">';
            echo '<div class="d-flex justify-content-between align-items-center">';

            // Tên danh mục và icon
            echo '<span class="category-name-wrapper d-flex align-items-center">'; // Wrapper cho tên và icon
            echo '<i class="bi bi-folder' . ($isParentCategory ? '-fill text-primary' : ' text-secondary') . ' me-2 fs-5"></i>'; // Tăng kích thước icon
            echo '<strong class="fs-6">' . htmlspecialchars($category['name']) . '</strong>'; // Tăng kích thước chữ
            echo '</span>';

            // Các nút chức năng (sẽ được ẩn/hiện bằng CSS)
            echo '<span class="item-actions ms-auto">'; // ms-auto đẩy nút sang phải
            if ($isParentCategory) {
                echo '<button class="btn btn-sm btn-outline-success me-1 btn-add-child-category" data-parent-id="' . $catId . '" title="' . ($lang['add_child_category'] ?? 'Add Child') . '"><i class="bi bi-folder-plus"></i></button>';
            } else {
                echo '<button class="btn btn-sm btn-outline-primary me-1 btn-add-product" data-category-id="' . $catId . '" title="' . ($lang['add_product'] ?? 'Add Product') . '"><i class="bi bi-plus-circle"></i></button>';
            }
            echo '<button class="btn btn-sm btn-outline-warning me-1 btn-edit-category" data-id="' . $catId . '" title="' . ($lang['edit'] ?? 'Edit') . '"><i class="bi bi-pencil-square"></i></button>';
            echo '<button class="btn btn-sm btn-outline-danger btn-delete-category" data-id="' . $catId . '" data-name="' . htmlspecialchars($category['name']) . '" title="' . ($lang['delete'] ?? 'Delete') . '"><i class="bi bi-trash"></i></button>';
            echo '</span>'; // End item-actions

            echo '</div>'; // End flex container

            // --- Hiển thị danh mục con hoặc sản phẩm ---
            echo '<ul class="list-group list-group-flush mt-1 sub-items" style="list-style-type: none;">'; // Thêm class sub-items

            // Gọi đệ quy để hiển thị các danh mục con
            $hasSubCategories = displayCatalogTree($categories, $products, $lang, $catId, $level + 1);

            // Hiển thị sản phẩm thuộc danh mục con này
            $hasProducts = false;
            if (!$isParentCategory && isset($products[$catId]) && !empty($products[$catId])) {
                 // Truyền thêm thông tin ảnh/pdf vào hàm displayProducts
                 displayProducts($products[$catId], $lang, $level + 1);
                 $hasProducts = true;
            }

             // Thông báo nếu danh mục con trống
             if (!$isParentCategory && !$hasSubCategories && !$hasProducts) {
                  $childIndentClass = 'ps-' . min(($level + 1) * 2 + 1, 5);
                  echo '<li class="list-group-item list-group-item-light fst-italic small border-0 py-1 ' . $childIndentClass . '">' . ($lang['no_products_in_category'] ?? 'No products') . '</li>';
             }

            echo '</ul>'; // End ul chứa các mục con
            echo '</li>'; // End category-item
        }
    }
    return $hasDirectChildren;
}

/**
 * Hiển thị danh sách sản phẩm thuộc một danh mục con.
 * Cập nhật để hiển thị ảnh thumbnail và icon PDF.
 */
function displayProducts(array $productList, array $lang, int $level) {
    $indentClass = 'ps-' . min($level * 2 + 1, 5); // Thụt lề cho sản phẩm

    foreach ($productList as $product) {
        $firstImagePath = $product['first_image_path'] ?? null;
        $firstPdfPath = $product['first_pdf_path'] ?? null;

        // Thêm class list-group-item-action cho sản phẩm
        echo '<li class="list-group-item product-item list-group-item-action border-0 py-1 ' . $indentClass . '" data-id="' . $product['id'] . '" data-type="product" data-category-id="' . $product['category_id'] . '">';
         echo '<div class="d-flex justify-content-between align-items-center">'; // Flex container

         // Tên sản phẩm, ảnh, icon PDF và đơn vị
         echo '<span class="product-name-wrapper d-flex align-items-center text-truncate">'; // Wrapper
         echo '<i class="bi bi-box me-2"></i>'; // Icon sản phẩm chung

         // Hiển thị ảnh thumbnail nếu có
         if ($firstImagePath) {
             // Sử dụng ảnh placeholder nếu ảnh gốc lỗi
             $onErrorScript = "this.onerror=null; this.src='https://placehold.co/24x24/eee/ccc?text=Err';";
             echo '<img src="' . htmlspecialchars($firstImagePath) . '"
                      alt="' . ($lang['product_image_thumbnail'] ?? 'Image') . '"
                      class="img-thumbnail me-2"
                      style="width: 24px; height: 24px; object-fit: cover; padding: 1px;"
                      onerror="' . $onErrorScript . '"
                      loading="lazy">'; // Thêm lazy loading
         } else {
             // Placeholder hoặc khoảng trống nếu không có ảnh
             echo '<span class="me-2" style="width: 24px; display: inline-block;"></span>';
         }

         // Hiển thị icon PDF nếu có và link mở tab mới
         if ($firstPdfPath) {
             echo '<a href="' . htmlspecialchars($firstPdfPath) . '" target="_blank" class="me-2 text-danger" title="' . ($lang['view_pdf'] ?? 'View PDF') . '">
                      <i class="bi bi-file-earmark-pdf-fill"></i>
                   </a>';
         } else {
              // Placeholder hoặc khoảng trống nếu không có PDF
             echo '<span class="me-2" style="width: 16px; display: inline-block;"></span>'; // Chiều rộng nhỏ hơn icon PDF
         }


         // Tên sản phẩm
         echo '<span class="product-name text-truncate" title="' . htmlspecialchars($product['name']) . '">' . htmlspecialchars($product['name']) . '</span>';

         // Đơn vị tính
         echo '<small class="text-muted ms-2">(' . ($lang['unit'] ?? 'Unit') . ': ' . htmlspecialchars($product['unit_name']) . ')</small>';
         echo '</span>'; // End product-name-wrapper

        // Các nút chức năng (sẽ được ẩn/hiện bằng CSS)
        echo '<span class="item-actions ms-auto">';
        echo '<button class="btn btn-sm btn-outline-info me-1 btn-view-product" data-id="' . $product['id'] . '" title="' . ($lang['view_details'] ?? 'View') . '"><i class="bi bi-eye"></i></button>';
        echo '<button class="btn btn-sm btn-outline-warning me-1 btn-edit-product" data-id="' . $product['id'] . '" title="' . ($lang['edit'] ?? 'Edit') . '"><i class="bi bi-pencil-square"></i></button>';
        echo '<button class="btn btn-sm btn-outline-danger btn-delete-product" data-id="' . $product['id'] . '" data-name="' . htmlspecialchars($product['name']) . '" title="' . ($lang['delete'] ?? 'Delete') . '"><i class="bi bi-trash"></i></button>';
        echo '</span>'; // End item-actions

        echo '</div>'; // End flex container
        echo '</li>'; // End product-item
    }
}
?>
