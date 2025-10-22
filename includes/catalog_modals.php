<?php
// File: includes/catalog_modals.php
// Được include bởi catalog.php. Nó sử dụng biến $modal_data được truyền vào.

// Kiểm tra xem biến $modal_data có tồn tại và có các key cần thiết không
if (!isset($modal_data) || !isset($modal_data['lang']) || !isset($modal_data['units']) || !isset($modal_data['child_categories'])) {
     // Ghi log lỗi thay vì die để không làm dừng hoàn toàn trang chính
     error_log("Required data not provided for catalog modals include.");
     // Có thể hiển thị thông báo lỗi ẩn hoặc không hiển thị gì cả
     // echo "<p class='text-danger'>Error loading modal structures.</p>";
     return; // Không hiển thị modal nếu thiếu dữ liệu
}

// Giải nén các biến từ $modal_data để dễ sử dụng hơn
$lang = $modal_data['lang'];
$units = $modal_data['units'];
$child_categories = $modal_data['child_categories'];

?>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="categoryModalLabel"><?= $lang['add_category'] ?? 'Add Category' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $lang['close'] ?? 'Close' ?>"></button>
      </div>
      <form id="categoryForm" novalidate> <div class="modal-body">
            <input type="hidden" id="categoryId" name="category_id">
            <input type="hidden" id="parentId" name="parent_id">
            <div class="mb-3">
                <label for="categoryName" class="form-label"><?= $lang['category_name'] ?? 'Category Name' ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="categoryName" name="name" required>
                <div id="categoryNameError" class="invalid-feedback"></div>
            </div>
             <div class="mb-3" id="parentCategoryInfo" style="display: none;">
                <label class="form-label"><?= $lang['parent_category'] ?? 'Parent Category' ?>:</label>
                <span id="parentCategoryName" class="fw-bold"></span>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $lang['cancel'] ?? 'Cancel' ?></button>
          <button type="submit" class="btn btn-primary" id="saveCategoryBtn"><?= $lang['save'] ?? 'Save' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel"><?= $lang['add_product'] ?? 'Add Product' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $lang['close'] ?? 'Close' ?>"></button>
            </div>
            <form id="productForm" enctype="multipart/form-data" novalidate> <div class="modal-body">
                    <input type="hidden" id="productId" name="product_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="productName" class="form-label"><?= $lang['product_name'] ?? 'Product Name' ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="productName" name="name" required>
                             <div id="productNameWarning" class="text-warning mt-1 small"></div> <div class="invalid-feedback"></div> </div>
                        <div class="col-md-6 mb-3">
                             <label for="productCategory" class="form-label"><?= $lang['child_category'] ?? 'Child Category' ?> <span class="text-danger">*</span></label>
                             <select class="form-select" id="productCategory" name="category_id" required>
                                 <option value="" disabled selected><?= $lang['select_category'] ?? 'Select Category' ?></option>
                                 <?php if (!empty($child_categories)): ?>
                                     <?php foreach ($child_categories as $child_cat): ?>
                                         <option value="<?= $child_cat['id'] ?>"><?= htmlspecialchars($child_cat['name']) ?></option>
                                     <?php endforeach; ?>
                                 <?php endif; ?>
                             </select>
                              <div class="invalid-feedback"></div>
                             <div id="productCategoryInfo" class="mt-1" style="display: none;">
                                <small class="text-muted"><?= $lang['category'] ?? 'Category' ?>:</small>
                                <span id="selectedCategoryName" class="fw-bold"></span>
                             </div>
                        </div>
                    </div>
                     <div class="row">
                         <div class="col-md-6 mb-3">
                             <label for="productUnit" class="form-label"><?= $lang['unit'] ?? 'Unit' ?> <span class="text-danger">*</span></label>
                             <select class="form-select" id="productUnit" name="unit_id" required>
                                 <option value="" disabled selected><?= $lang['select_unit'] ?? 'Select Unit' ?></option>
                                  <?php if (!empty($units)): ?>
                                     <?php foreach ($units as $unit): ?>
                                         <option value="<?= $unit['id'] ?>"><?= htmlspecialchars($unit['name']) ?></option>
                                     <?php endforeach; ?>
                                 <?php endif; ?>
                             </select>
                              <div class="invalid-feedback"></div>
                         </div>
                         <div class="col-md-6 mb-3">
                             <label for="productDescription" class="form-label"><?= $lang['description'] ?? 'Description' ?></label>
                            <textarea class="form-control" id="productDescription" name="description" rows="1"></textarea>
                         </div>
                    </div>

                    <hr>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                             <h6><?= $lang['product_images'] ?? 'Images' ?> <small>(<?= ($lang['max_3_files'] ?? 'Max 3 files') . ', JPG/PNG/GIF' ?>)</small></h6>
                             <input class="form-control" type="file" id="productImages" name="product_images[]" multiple accept="image/jpeg,image/png,image/gif">
                             <div id="imagePreview" class="mt-2 d-flex flex-wrap gap-2"></div>
                             <small class="form-text text-muted mt-2" id="currentImagesLabel"><?= $lang['current_images'] ?? 'Current Images' ?>:</small>
                             <div id="currentImages" class="mt-1 d-flex flex-wrap gap-2"></div>
                             <div id="imageUploadError" class="text-danger mt-1 small"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                             <h6><?= $lang['product_documents'] ?? 'Documents' ?> <small>(<?= ($lang['max_3_files'] ?? 'Max 3 files') . ', PDF' ?>)</small></h6>
                             <input class="form-control" type="file" id="productDocuments" name="product_documents[]" multiple accept="application/pdf">
                             <div id="documentList" class="mt-2"></div>
                             <small class="form-text text-muted mt-2" id="currentDocumentsLabel"><?= $lang['current_documents'] ?? 'Current Documents' ?>:</small>
                             <div id="currentDocuments" class="mt-1"></div>
                             <div id="documentUploadError" class="text-danger mt-1 small"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $lang['cancel'] ?? 'Cancel' ?></button>
                    <button type="submit" class="btn btn-primary" id="saveProductBtn"><?= $lang['save'] ?? 'Save' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewProductModal" tabindex="-1" aria-labelledby="viewProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewProductModalLabel"><?= $lang['product_details'] ?? 'Product Details' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $lang['close'] ?? 'Close' ?>"></button>
            </div>
            <div class="modal-body" id="viewProductDetails">
                <div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $lang['close'] ?? 'Close' ?></button>
            </div>
        </div>
    </div>
</div>
<!-- Thêm modal để hiển thị ảnh lớn -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Xem ảnh lớn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" id="largeImage" class="img-fluid" alt="Ảnh lớn">
            </div>
        </div>
    </div>
</div>