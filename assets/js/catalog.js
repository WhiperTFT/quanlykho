// cleaned: console logs optimized, debug system applied
// File: assets/js/catalog.js
// Cần jQuery và Bootstrap JS đã được load trước

$(document).ready(function() {
    // Khởi tạo các đối tượng Modal của Bootstrap
    const categoryModalElement = document.getElementById('categoryModal');
    const productModalElement = document.getElementById('productModal');
    const viewProductModalElement = document.getElementById('viewProductModal');

    // Kiểm tra xem element có tồn tại không trước khi tạo modal instance
    window.categoryModal = categoryModalElement ? new bootstrap.Modal(categoryModalElement) : null;
    window.productModal = productModalElement ? new bootstrap.Modal(productModalElement) : null;
    window.viewProductModal = viewProductModalElement ? new bootstrap.Modal(viewProductModalElement) : null;

    // Kiểm tra xem các modal instance đã được tạo thành công chưa
    if (!window.categoryModal || !window.productModal || !window.viewProductModal) {
        console.error("One or more modal elements not found. Check HTML IDs.");

    }
//    const MAX_IMAGES = 3;
//    const MAX_DOCS = 3;

    // --- Helper Functions ---
    function showUserMessage(message, type = 'success') {
        alert(type.toUpperCase() + ": " + message);
        if (type === 'success') {
            location.reload();
        }
    }

    // Hàm reset form danh mục
    function resetCategoryForm() {
        const form = $('#categoryForm');
        if (form.length) {
            form[0].reset();
            form.find('input[type="hidden"]').val(''); // Xóa các hidden input
            form.find('.is-invalid').removeClass('is-invalid'); // Xóa class lỗi validation
            form.find('.invalid-feedback').text(''); // Xóa text lỗi
            $('#categoryNameError').text(''); // Xóa lỗi trùng tên riêng
            $('#categoryModalLabel').text(LANG['add_category'] || 'Add Category');
            $('#saveCategoryBtn').text(LANG['save'] || 'Save').prop('disabled', false);
            $('#parentCategoryInfo').hide();
        } else {
             console.error("#categoryForm not found");
        }
    }

    // Hàm reset form sản phẩm
    function resetProductForm() {
        const form = $('#productForm');
         if (form.length) {
            form[0].reset();
            form.find('input[type="hidden"]').val('');
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.invalid-feedback').text('');
            form.find('.text-warning').text(''); // Xóa cảnh báo
            $('#productNameWarning').text('');
            $('#productModalLabel').text(LANG['add_product'] || 'Add Product');
            $('#saveProductBtn').text(LANG['save'] || 'Save').prop('disabled', false);
            $('#imagePreview, #documentList, #currentImages, #currentDocuments').empty();
            $('#imageUploadError, #documentUploadError').text('');
            $('#productCategoryInfo').hide();
            $('#productCategory').show().prop('disabled', false); // Hiện lại dropdown danh mục
            // Reset file counts và kích hoạt lại input file
            $('#productImages, #productDocuments').data('current-files', 0).prop('disabled', false).val('');
            $('#currentImagesLabel').text(LANG['current_images'] || 'Current Images:');
            $('#currentDocumentsLabel').text(LANG['current_documents'] || 'Current Documents:');
         } else {
             console.error("#productForm not found");
         }
    }

    // Hàm xử lý lỗi validation từ AJAX response
    function handleValidationErrors(errors, formId) {
        const form = $(`#${formId}`);
        // Xóa lỗi cũ
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.invalid-feedback').text('');

        if (typeof errors === 'object' && errors !== null) {
            $.each(errors, function(fieldName, messages) {
                const input = form.find(`[name="${fieldName}"]`);
                if (input.length) {
                    input.addClass('is-invalid');
                    // Hiển thị lỗi đầu tiên cho field đó
                    input.closest('.mb-3').find('.invalid-feedback').text(messages[0]);
                } else {
                    // Lỗi không gắn với field cụ thể? Hiển thị ở đâu đó chung
                    devLog(`Validation error for unknown field: ${fieldName}`);
                }
            });
        }
    }
    // --- Event Handlers ---

    // Mở Modal Thêm Danh mục Cha
    $('.btn-add-parent-category').on('click', function() {
        if (!categoryModal) return;
        resetCategoryForm();
        $('#categoryModalLabel').text(LANG['add_parent_category'] || 'Add Parent Category');
        $('#parentId').val('null'); // Backend sẽ hiểu là cha
        categoryModal.show();
    });

    // Mở Modal Thêm Danh mục Con (Delegated event)
    $('#catalogTree').on('click', '.btn-add-child-category', function() {
        if (!categoryModal) return;
        const parentId = $(this).data('parent-id');
        // Tìm tên từ thẻ strong trong cùng thẻ div chứa nút
        const parentName = $(this).closest('.d-flex').find('strong').first().text();
        resetCategoryForm();
        $('#categoryModalLabel').text(LANG['add_child_category'] || 'Add Child Category');
        $('#parentId').val(parentId);
        $('#parentCategoryName').text(parentName);
        $('#parentCategoryInfo').show();
        categoryModal.show();
    });

    // Mở Modal Sửa Danh mục (Delegated event)
    $('#catalogTree').on('click', '.btn-edit-category', function() {
        if (!categoryModal) return;
        const categoryId = $(this).data('id');
        resetCategoryForm();
        $('#categoryModalLabel').text(LANG['edit_category'] || 'Edit Category');
        $('#saveCategoryBtn').text(LANG['update'] || 'Update');
        $('#categoryId').val(categoryId);

        // AJAX call to get category data
        $.ajax({
            url: 'process/category_handler.php',
            type: 'GET',
            data: { action: 'get', id: categoryId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    $('#categoryName').val(response.data.name);
                    $('#parentId').val(response.data.parent_id || 'null');
                     if(response.data.parent_id && response.data.parent_name) { // Chỉ hiển thị nếu có parent_id và parent_name
                        $('#parentCategoryName').text(response.data.parent_name);
                        $('#parentCategoryInfo').show();
                    } else {
                         $('#parentCategoryInfo').hide();
                    }
                    categoryModal.show();
                } else {
                    showUserMessage(response.message || LANG['error_fetching_data'] || 'Error fetching data', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                 showUserMessage(LANG['server_error'] || 'Server error', 'error');
            }
        });
    });

     // Kiểm tra trùng tên Danh mục khi nhập liệu (debounced để tránh gọi AJAX liên tục)
    let debounceTimerCat;
    $('#categoryName').on('input', function() {
        clearTimeout(debounceTimerCat);
        const input = $(this);
        const name = input.val().trim();
        const categoryId = $('#categoryId').val();
        const parentId = $('#parentId').val();
        const errorDiv = $('#categoryNameError'); // Div riêng cho lỗi trùng tên

        // Xóa lỗi cũ ngay lập tức
        input.removeClass('is-invalid');
        errorDiv.text(''); // Xóa lỗi trùng

        if (name.length < 1) { // Hoặc > 0 nếu tên không được rỗng
            return;
        }

        debounceTimerCat = setTimeout(function() {
            $.ajax({
                url: 'process/category_handler.php',
                type: 'GET',
                data: {
                    action: 'check_duplicate',
                    name: name,
                    parent_id: parentId === 'null' ? null : parentId,
                    current_id: categoryId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.exists) {
                        // Hiển thị lỗi trùng tên vào div riêng, không dùng is-invalid
                        errorDiv.text(LANG['category_name_exists'] || 'Category name already exists at this level.');
                        // input.addClass('is-invalid'); // Không nên set is-invalid chỉ vì trùng
                    } else {
                        errorDiv.text('');
                    }
                },
                error: function() {
                     console.error("Error checking category duplicate name.");
                     // Không hiển thị lỗi cho user khi check duplicate thất bại
                }
            });
        }, 500); // Chờ 500ms sau khi ngừng gõ
    });

    // Submit Form Danh mục (Add/Edit)
    $('#categoryForm').on('submit', function(e) {
        e.preventDefault();
        if (!categoryModal) return;

        const form = $(this);
        const categoryNameInput = $('#categoryName');
        const categoryName = categoryNameInput.val().trim();
        const parentId = $('#parentId').val();
        const categoryId = $('#categoryId').val();
        let action = categoryId ? 'edit' : 'add';
        const saveButton = $('#saveCategoryBtn');

        // --- Client-side Validation (Đơn giản) ---
        let isValid = true;
        form.find('.is-invalid').removeClass('is-invalid'); // Xóa lỗi cũ
        form.find('.invalid-feedback').text('');
        $('#categoryNameError').text(''); // Xóa lỗi trùng

        if (categoryName === '') {
            categoryNameInput.addClass('is-invalid');
            categoryNameInput.closest('.mb-3').find('.invalid-feedback').text(LANG['category_name_required'] || 'Category name is required.');
            isValid = false;
        }

        // Kiểm tra lỗi trùng tên đã hiển thị chưa
        if ($('#categoryNameError').text() !== '') {
             // Không cần báo lỗi thêm, chỉ cần không cho submit
             isValid = false;
        }
        if (!isValid) {
            return; // Dừng nếu validation client-side thất bại
        }

        // Vô hiệu hóa nút submit và hiển thị spinner
        saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + (LANG['saving'] || 'Saving...'));
        // AJAX submit
        $.ajax({
            url: 'process/category_handler.php',
            type: 'POST',
            data: form.serialize() + '&action=' + action,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    categoryModal.hide();
                    showUserMessage(response.message || LANG['save_success'] || 'Saved successfully!');
                    // Cân nhắc: Thay vì reload, cập nhật cây DOM bằng JS
                    // updateTreeAfterCategorySave(action, response.data); // Ví dụ
                } else {
                    // Xử lý lỗi từ server (bao gồm cả validation server-side)
                    if (response.errors) {
                         handleValidationErrors(response.errors, 'categoryForm');
                    } else if (response.field === 'name' && response.message) {
                        // Lỗi trùng tên từ server (dự phòng)
                        $('#categoryNameError').text(response.message);
                    }
                     else {
                        // Lỗi chung khác
                        showUserMessage(response.message || LANG['save_error'] || 'Error saving data', 'error');
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showUserMessage(LANG['server_error'] || 'Server error', 'error');
            },
            complete: function() {
                // Kích hoạt lại nút submit
                 saveButton.prop('disabled', false).html(action === 'edit' ? (LANG['update'] || 'Update') : (LANG['save'] || 'Save'));
            }
        });
    });

     // Xóa Danh mục (Delegated event)
    $('#catalogTree').on('click', '.btn-delete-category', function() {
        const button = $(this);
        const categoryId = button.data('id');
        const categoryName = button.data('name');

        const confirmMessage = (LANG['confirm_delete_category'] || 'Are you sure you want to delete category "%s"? This might also affect child categories and products.').replace('%s', categoryName);

        if (confirm(confirmMessage)) {
             // Hiển thị loading trên nút hoặc toàn bộ item (tùy chọn)
             button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

             $.ajax({
                url: 'process/category_handler.php',
                type: 'POST',
                data: { action: 'delete', id: categoryId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showUserMessage(response.message || LANG['delete_success'] || 'Deleted successfully!');
                       // Cập nhật cây DOM thay vì reload
                       // button.closest('.category-item').remove();
                    } else {
                        // Hiển thị lỗi cụ thể từ server (ví dụ: không xóa được do có con)
                        showUserMessage(response.message || LANG['delete_error'] || 'Error deleting data', 'error');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                    showUserMessage(LANG['server_error'] || 'Server error', 'error');
                },
                complete: function() {
                    // Khôi phục lại nút xóa
                    button.prop('disabled', false).html('<i class="bi bi-trash"></i>');
                }
            });
        }
    });
    // --- Xử lý Sản phẩm ---
    // Mở Modal Thêm Sản phẩm (Delegated event)
    $('#catalogTree').on('click', '.btn-add-product', function() {
        if (!productModal) return;
        const categoryId = $(this).data('category-id');
         // Tìm tên danh mục cha của nút này
         const categoryName = $(this).closest('.category-item').find('.d-flex > span > strong').first().text();
        resetProductForm();
         $('#productModalLabel').text(LANG['add_product'] || 'Add Product');
        // Chọn sẵn danh mục con và hiển thị tên (nhưng không disable dropdown)
         $('#productCategory').val(categoryId);
         if (categoryId && categoryName) {
            $('#selectedCategoryName').text(categoryName);
            $('#productCategoryInfo').show();
            // $('#productCategory').hide(); // Không ẩn, cho phép đổi nếu muốn
         } else {
             $('#productCategoryInfo').hide();
             // $('#productCategory').show();
         }
        productModal.show();
    });

     // Mở Modal Sửa Sản phẩm (Delegated event)
    $('#catalogTree').on('click', '.btn-edit-product', function() {
        if (!productModal) return;
        const productId = $(this).data('id');
        resetProductForm();
        $('#productModalLabel').text(LANG['edit_product'] || 'Edit Product');
        $('#saveProductBtn').text(LANG['update'] || 'Update');
        $('#productId').val(productId);

        // AJAX get product data + files
        $.ajax({
            url: 'process/product_handler.php',
            type: 'GET',
            data: { action: 'get', id: productId },
            dataType: 'json',
            beforeSend: function() {
                // Có thể hiển thị loading trong modal body
                $('#productForm .modal-body').addClass('opacity-50');
            },
            success: function(response) {
                if (response.success && response.data) {
                    const product = response.data.product;
                    const files = response.data.files || []; // Đảm bảo files là mảng

                    $('#productName').val(product.name);
                    $('#productCategory').val(product.category_id);
                     // Hiển thị tên category đã chọn
                     if (product.category_id && product.category_name) {
                        $('#selectedCategoryName').text(product.category_name);
                        $('#productCategoryInfo').show();
                        // $('#productCategory').hide(); // Cân nhắc có cho sửa category không
                     } else {
                          $('#productCategoryInfo').hide();
                          // $('#productCategory').show();
                     }
                    $('#productUnit').val(product.unit_id);
                    $('#productDescription').val(product.description || ''); // Đảm bảo là chuỗi

                    // Hiển thị file hiện tại
                    let imageCount = 0;
                    let docCount = 0;
                    $('#currentImages, #currentDocuments').empty(); // Xóa file cũ

                    files.forEach(file => {
                        const fileElement = createFileElement(file, true); // true = existing file
                        if (file.file_type === 'image') {
                            $('#currentImages').append(fileElement);
                            imageCount++;
                        } else if (file.file_type === 'pdf') {
                            $('#currentDocuments').append(fileElement);
                             docCount++;
                        }
                    });

                    // Cập nhật số lượng file hiện tại và kiểm tra giới hạn
                    $('#productImages').data('current-files', imageCount);
                     $('#productDocuments').data('current-files', docCount);


                    productModal.show();
                } else {
                    showUserMessage(response.message || LANG['error_fetching_data'] || 'Error fetching data', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showUserMessage(LANG['server_error'] || 'Server error', 'error');
            },
             complete: function() {
                 $('#productForm .modal-body').removeClass('opacity-50');
             }
        });
    });

    // Kiểm tra trùng tên sản phẩm + đơn vị + category (chỉ cảnh báo, debounced)
    let debounceTimerProd;
    $('#productName, #productUnit, #productCategory').on('change input', function() {
         clearTimeout(debounceTimerProd);
         const name = $('#productName').val().trim();
         const unitId = $('#productUnit').val();
         const categoryId = $('#productCategory').val();
         const productId = $('#productId').val();
         const warningDiv = $('#productNameWarning');

         warningDiv.text(''); // Xóa cảnh báo cũ

         if (name.length < 1 || !unitId || !categoryId) {
             return; // Chưa đủ thông tin để kiểm tra
         }

         debounceTimerProd = setTimeout(function() {
              $.ajax({
                url: 'process/product_handler.php',
                type: 'GET',
                data: {
                    action: 'check_duplicate',
                    name: name,
                    unit_id: unitId,
                    category_id: categoryId,
                    current_id: productId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.exists) {
                        warningDiv.text(LANG['product_name_unit_exists_warning'] || 'Warning: A product with the same name and unit already exists in this category.');
                    } else {
                         warningDiv.text('');
                    }
                },
                error: function() {
                    console.error("Error checking product duplicate.");
                }
            });
         }, 500); // Chờ 500ms
    });

    // Submit Form Sản phẩm (Add/Edit)
    $('#productForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const imageFiles = $('#productImages')[0].files;
    const docFiles = $('#productDocuments')[0].files;
    const productId = $('#productId').val();
    const action = productId ? 'edit' : 'add'; // Định nghĩa action
    devLog('Image files in input:', imageFiles.length, Array.from(imageFiles).map(f => f.name));
    devLog('Document files in input:', docFiles.length, Array.from(docFiles).map(f => f.name));
    devLog('FormData entries:', Array.from(formData.entries()));
    formData.append('action', action); // Đảm bảo action được gửi
    $.ajax({
        url: 'process/product_handler.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            devLog('Server response:', response);
            if (response.success) {
                productModal.hide();
                showUserMessage(response.message || LANG['save_success'] || 'Saved successfully!');
            } else {
                let errorMsg = response.message || LANG['save_error'] || 'Error saving data';
                if (response.errors) {
                    handleValidationErrors(response.errors, 'productForm');
                    if (response.errors.product_images) {
                        $('#imageUploadError').text(response.errors.product_images.join('; '));
                        errorMsg = response.errors.product_images.join('; ');
                    }
                    if (response.errors.product_documents) {
                        $('#documentUploadError').text(response.errors.product_documents.join('; '));
                        errorMsg = response.errors.product_documents.join('; ');
                    }
                }
                showUserMessage(errorMsg, 'error');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
            showUserMessage(LANG['server_error'] || 'Server error.', 'error');
        },
        complete: function() {
            // Sử dụng action từ scope ngoài
            $('#saveProductBtn').prop('disabled', false).html(action === 'edit' ? (LANG['update'] || 'Update') : (LANG['save'] || 'Save'));
        }
    });
});

            // Xóa Sản phẩm (Delegated event)
        $('#catalogTree').on('click', '.btn-delete-product', function() {
            const button = $(this);
            const productId = button.data('id');
            const productName = button.data('name');

            const confirmMessage = (LANG['confirm_delete_product'] || 'Are you sure you want to delete product "%s"? Associated files will also be deleted.').replace('%s', productName);

            if (confirm(confirmMessage)) {
                button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

                // ✅ Khai báo biến formData trước khi dùng
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', productId);

                $.ajax({
            url: 'process/product_handler.php',
            type: 'POST',
            contentType: 'application/json', // 🔥 Quan trọng: định dạng JSON
            data: JSON.stringify({
                action: 'delete',
                id: productId
            }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showUserMessage(response.message || 'Deleted successfully!');
                } else {
                    showUserMessage(response.message || 'Delete error', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                showUserMessage('Server error', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html('<i class="bi bi-trash"></i>');
            }
        });
            }
        });
      


    // Xử lý khi chọn file ảnh
    $('#productImages').on('change', function(e) {
        const files = e.target.files;
        const fileInput = $(this);
        const previewContainer = $('#imagePreview');
        const errorContainer = $('#imageUploadError');
        const currentFileCount = parseInt(fileInput.data('current-files') || 0);
        previewContainer.empty(); // Xóa preview cũ
        errorContainer.text(''); // Xóa lỗi cũ

        if (!files) return;


        // Hiển thị preview cho các file mới chọn
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
             // Kiểm tra loại file cơ bản phía client (không thay thế validation server)
             if (!file.type.startsWith('image/')) {
                 errorContainer.text(LANG['invalid_file_type_client'] || 'One or more files are not valid images.');
                 fileInput.val('');
                 previewContainer.empty();
                 return;
             }

            const reader = new FileReader();
            reader.onload = function(event) {
                 // Tạo element preview cho file MỚI (không có nút xóa)
                 const imgElement = `
                    <div class="position-relative d-inline-block border p-1 me-2 mb-2">
                        <img src="${event.target.result}" alt="Preview" class="img-thumbnail" style="max-width: 80px; max-height: 80px;">
                        <small class="d-block text-muted text-truncate" style="max-width: 80px;">${escapeHtml(file.name)}</small>
                    </div>`;
                previewContainer.append(imgElement);
            }
            reader.readAsDataURL(file);
        }

    });

     // Xử lý khi chọn file tài liệu
     $('#productDocuments').on('change', function(e) {
        const files = e.target.files;
        const fileInput = $(this);
        const listContainer = $('#documentList');
        const errorContainer = $('#documentUploadError');
        const currentFileCount = parseInt(fileInput.data('current-files') || 0);
        listContainer.empty();
        errorContainer.text('');

         if (!files) return;

         

         // Hiển thị danh sách file mới chọn
         for (let i = 0; i < files.length; i++) {
            const file = files[i];
             // Kiểm tra loại file PDF phía client
             if (file.type !== 'application/pdf') {
                 errorContainer.text(LANG['invalid_file_type_pdf_client'] || 'One or more files are not valid PDFs.');
                 fileInput.val('');
                 listContainer.empty();

                 return;
             }
             const fileSizeKB = (file.size / 1024).toFixed(1);
             const listItem = `
                <div class="d-flex align-items-center mb-1 border-bottom pb-1">
                    <i class="bi bi-file-earmark-pdf me-2"></i>
                    <span class="text-truncate" style="max-width: 80%;">${escapeHtml(file.name)}</span>
                    <small class="text-muted ms-auto">(${fileSizeKB} KB)</small>
                </div>`;
            listContainer.append(listItem);
         }
    });

     // Hàm tạo element hiển thị file (chủ yếu cho file ĐÃ TỒN TẠI)
     function createFileElement(file, isExisting = false) {
    const fileName = escapeHtml(file.original_filename || file.name);
    const fileId = file.id;
    const filePath = file.file_path;
    let element = '';

    if (!filePath) return '';

    if (file.file_type === 'image') {
        element = `
            <div class="existing-file-container position-relative d-inline-block border p-1 me-2 mb-2 text-center" data-file-id="${fileId}">
                <a href="#" class="image-link" data-bs-toggle="modal" data-bs-target="#imageModal" data-image-src="${filePath}">
                    <img src="${filePath}" alt="${fileName}" class="img-thumbnail" style="max-width: 150px; max-height: 150px;" onerror="this.src='https://placehold.co/150x150/eee/ccc?text=Error'; this.onerror=null;">
                </a>
                <small class="d-block text-muted text-truncate" style="max-width: 150px;">${fileName}</small>
                ${isExisting ? `
                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0 m-0 lh-1 btn-delete-file" title="${LANG['delete']}" data-file-id="${fileId}" data-file-type="image" style="width: 18px; height: 18px; font-size: 0.6rem;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <a href="${filePath}" download="${fileName}" class="btn btn-sm btn-outline-secondary p-0 m-0 position-absolute bottom-0 end-0 lh-1" title="${LANG['download']}" style="width: 18px; height: 18px; font-size: 0.6rem;">
                        <i class="bi bi-download"></i>
                    </a>
                    ` : ''}
            </div>`;
    } else if (file.file_type === 'pdf') {
        element = `
            <div class="existing-file-container d-flex align-items-center justify-content-between border-bottom pb-1 mb-1" data-file-id="${fileId}">
                <span class="d-flex align-items-center text-truncate" style="max c-width: 85%;">
                    <i class="bi bi-file-earmark-pdf me-2"></i>
                    <a href="${filePath}" target="_blank" title="${LANG['view'] || 'View'} ${fileName}">${fileName}</a>
                </span>
                ${isExisting ? `
                    <span class="ms-auto ps-2">
                        <a href="${filePath}" download="${fileName}" class="btn btn-sm btn-outline-secondary p-0 m-0 me-1 lh-1" title="${LANG['download']}" style="width: 18px; height: 18px; font-size: 0.6rem;">
                            <i class="bi bi-download"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger p-0 m-0 lh-1 btn-delete-file" title="${LANG['delete']}" data-file-id="${fileId}" data-file-type="pdf" style="width: 18px; height: 18px; font-size: 0.6rem;">
                            <i class="bi bi-trash"></i>
                        </button>
                    </span>
                    ` : ''}
            </div>`;
    }
    return element;
}

// Hàm hiển thị ảnh lớn trong modal
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.image-link').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const imageSrc = this.getAttribute('data-image-src');
            document.getElementById('largeImage').src = imageSrc;
        });
    });
});

    // Xóa file đính kèm ĐÃ TỒN TẠI (AJAX) - Delegated event trong modal sản phẩm
    $('#productModal').on('click', '.btn-delete-file', function() {
         const button = $(this);
         const fileId = button.data('file-id');
         const fileType = button.data('file-type');
         const fileElement = button.closest('.existing-file-container');
         const productId = $('#productId').val(); // Cần productId để xác thực

         if (!fileId || !productId) {
             console.error("Missing fileId or productId for deletion.");
             return;
         }

        if (confirm(LANG['confirm_delete_file'] || 'Are you sure you want to delete this file? This action cannot be undone.')) {
             // Vô hiệu hóa nút xóa và hiển thị spinner nhỏ
             button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" style="width: 0.6rem; height: 0.6rem;" role="status" aria-hidden="true"></span>');

             $.ajax({
                 url: 'process/file_handler.php', // Endpoint xử lý xóa file
                 type: 'POST',
                 data: { action: 'delete', file_id: fileId, product_id: productId },
                 dataType: 'json',
                 success: function(response) {
                     if (response.success) {
                         // Thông báo thành công (có thể dùng toast nhẹ nhàng hơn)
                         // showUserMessage(response.message || LANG['file_deleted_success'] || 'File deleted successfully.', 'info');

                         // Xóa element khỏi DOM
                         fileElement.fadeOut(300, function() { $(this).remove(); });

                         // Cập nhật lại số lượng file hiện tại và kiểm tra giới hạn
                         const fileInputId = (fileType === 'image') ? '#productImages' : '#productDocuments';
                         const currentCount = parseInt($(fileInputId).data('current-files') || 0);

                     } else {
                         showUserMessage(response.message || LANG['delete_error'] || 'Error deleting file.', 'error');
                          // Kích hoạt lại nút nếu xóa thất bại
                          button.prop('disabled', false).html(fileType === 'image' ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-trash"></i>');
                     }
                 },
                 error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                    showUserMessage(LANG['server_error'] || 'Server error', 'error');
                     // Kích hoạt lại nút nếu xóa thất bại
                    button.prop('disabled', false).html(fileType === 'image' ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-trash"></i>');
                 }
                 // Không cần complete vì nút đã bị xóa nếu thành công
             });
         }
    });

      // Mở Modal Xem Chi tiết Sản phẩm (Delegated event)
    $('#catalogTree').on('click', '.btn-view-product', function() {
        if (!viewProductModal) return;
        const productId = $(this).data('id');
        const modalBody = $('#viewProductDetails');
        // Hiển thị loading spinner
        modalBody.html('<div class="text-center p-5"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        viewProductModal.show();

        // AJAX lấy chi tiết sản phẩm
        $.ajax({
            url: 'process/product_handler.php',
            type: 'GET',
            data: { action: 'get', id: productId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const product = response.data.product;
                    const files = response.data.files || [];
                    let detailsHtml = `
                        <h4>${escapeHtml(product.name)}</h4>
                        <table class="table table-sm table-borderless mb-3">
                            <tbody>
                                <tr>
                                    <th scope="row" style="width: 120px;">${LANG['category'] || 'Category'}</th>
                                    <td>${escapeHtml(product.category_name || 'N/A')}</td>
                                </tr>
                                <tr>
                                    <th scope="row">${LANG['unit'] || 'Unit'}</th>
                                    <td>${escapeHtml(product.unit_name || 'N/A')}</td>
                                </tr>
                                <tr>
                                    <th scope="row">${LANG['description'] || 'Description'}</th>
                                    <td>${escapeHtml(product.description || '-')}</td>
                                </tr>
                            </tbody>
                        </table>
                        <hr>
                    `;

                                        // Hiển thị ảnh
                    detailsHtml += `<h6><i class="bi bi-images me-2"></i>${LANG['product_images'] || 'Images'}:</h6>`;
                    const images = files.filter(f => f.file_type === 'image');
                    if (images.length > 0) {
                        detailsHtml += '<div class="d-flex flex-wrap gap-3 mb-3">';
                        images.forEach(img => {
                            detailsHtml += `
                                <div class="text-center border rounded p-1 position-relative">
                                    <div class="position-absolute top-0 start-0 m-1">
                                        <input type="checkbox" class="form-check-input share-checkbox" style="transform: scale(1.3); cursor: pointer;" value="${img.file_path}">
                                    </div>
                                    <img src="${img.file_path}" alt="${escapeHtml(img.original_filename)}" class="img-fluid img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: contain; cursor: pointer;" onclick="showLargeImage('${img.file_path}')" onerror="this.src='https://placehold.co/150x150/eee/ccc?text=Error'; this.onerror=null;">
                                    <a href="${img.file_path}" download="${escapeHtml(img.original_filename)}" class="d-block small mt-1"><i class="bi bi-download"></i> ${LANG['download'] || 'Download'}</a>
                                </div>`;
                        });
                        detailsHtml += '</div>';
                    } else {
                        detailsHtml += `<p class="text-muted"><em>${LANG['no_images_available'] || 'No images available.'}</em></p>`;
                    }

                    // Hiển thị tài liệu
                    detailsHtml += `<hr><h6><i class="bi bi-file-earmark-text me-2"></i>${LANG['product_documents'] || 'Documents'}:</h6>`;
                     const documents = files.filter(f => f.file_type === 'pdf');
                     if (documents.length > 0) {
                         detailsHtml += '<ul class="list-unstyled">';
                         documents.forEach(doc => {
                             detailsHtml += `
                                 <li class="mb-2 border-bottom pb-1 d-flex align-items-center">
                                     <input type="checkbox" class="form-check-input me-2 share-checkbox" style="transform: scale(1.1); cursor: pointer;" value="${doc.file_path}">
                                     <i class="bi bi-file-earmark-pdf me-1"></i>
                                     <a href="${doc.file_path}" target="_blank" title="${LANG['view'] || 'View'} ${escapeHtml(doc.original_filename)}">${escapeHtml(doc.original_filename)}</a>
                                     <a href="${doc.file_path}" download="${escapeHtml(doc.original_filename)}" class="ms-auto small float-end" title="${LANG['download'] || 'Download'}"><i class="bi bi-download"></i></a>
                                 </li>`;
                         });
                         detailsHtml += '</ul>';
                     } else {
                         detailsHtml += `<p class="text-muted"><em>${LANG['no_documents_available'] || 'No documents available.'}</em></p>`;
                     }

                    if (files.length > 0) {
                        detailsHtml += `
                            <div class="mt-4 pt-3 border-top text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('.share-checkbox').prop('checked', true)"><i class="bi bi-check-all"></i> Chọn tất cả</button>
                                <button type="button" class="btn btn-sm btn-primary ms-2" onclick="shareSelectedToZalo()"><i class="bi bi-chat-dots"></i> Chia sẻ Zalo</button>
                            </div>
                        `;
                    }
                    modalBody.html(detailsHtml);
                     // Khởi tạo lại tooltips trong modal vừa load xong
                    const tooltipTriggerList = [].slice.call(modalBody[0].querySelectorAll('[data-bs-toggle="tooltip"]'))
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                      return new bootstrap.Tooltip(tooltipTriggerEl)
                    })
                } else {
                    modalBody.html(`<div class="alert alert-danger">${response.message || LANG['error_loading_details'] || 'Error loading details.'}</div>`);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                modalBody.html(`<div class="alert alert-danger">${LANG['server_error'] || 'Server error.'}</div>`);
            }
        });
    });


    // --- Filter ---
    $('#filterInput').on('keyup', function() {
        const filterText = $(this).val().toLowerCase().trim();

        // Lặp qua tất cả các item (cả category và product)
        $('#catalogTree .list-group-item').each(function() {
            const item = $(this);
            const itemTextElement = item.find('.d-flex > span > strong').first(); // Tìm tên category
            let itemText = '';
            if (itemTextElement.length) {
                 itemText = itemTextElement.text().toLowerCase();
            } else {
                // Nếu không phải category, tìm tên product (trong span đầu tiên)
                 const productTextElement = item.find('.d-flex > span').first();
                 if(productTextElement.length) {
                    // Lấy text của span, loại bỏ phần text của thẻ small bên trong
                    itemText = productTextElement.clone().find('small').remove().end().text().trim().toLowerCase();
                 }
            }


            if (itemText.includes(filterText)) {
                item.show();
                 // Hiển thị tất cả cha của item này (nếu đang bị ẩn)
                 item.parentsUntil('#catalogTree', '.list-group-item').show();
            } else {
                item.hide();
            }
        });
    });

    // Utility function to escape HTML special characters
    function escapeHtml(unsafe) {
        if (unsafe === null || typeof unsafe === 'undefined') return '';
        return unsafe
             .toString()
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

     // Khởi tạo tooltip của Bootstrap (nếu dùng)
     var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
     var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
       return new bootstrap.Tooltip(tooltipTriggerEl)
     })

     devLog("catalog.js loaded and ready."); // Log để xác nhận script chạy
function showLargeImage(imagePath) {
    const largeImage = document.getElementById('largeImage');
    if (largeImage) {
        largeImage.src = imagePath;
        const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        imageModal.show();
    } else {
        console.error("Element #largeImage not found.");
    }
}
// GLOBAL function (đặt ngoài document.ready nếu muốn gọi từ file khác)
window.openProductEditModal = function(productId){

    if (!productModal) {
        console.error("productModal not initialized");
        return;
    }

    // reset form
    resetProductForm();

    $('#productModalLabel').text(LANG['edit_product'] || 'Edit Product');
    $('#saveProductBtn').text(LANG['update'] || 'Update');
    $('#productId').val(productId);

    // loading effect
    $('#productForm .modal-body').addClass('opacity-50');

    $.ajax({
        url: 'process/product_handler.php',
        type: 'GET',
        data: { action: 'get', id: productId },
        dataType: 'json',

        success: function(response) {

            if (response.success && response.data) {

                const product = response.data.product;
                const files = response.data.files || [];

                $('#productName').val(product.name);
                $('#productCategory').val(product.category_id);
                $('#productUnit').val(product.unit_id);
                $('#productDescription').val(product.description || '');

                // render file giống logic cũ
                let imageCount = 0;
                let docCount = 0;

                $('#currentImages, #currentDocuments').empty();

                files.forEach(file => {
                    const el = createFileElement(file, true);

                    if (file.file_type === 'image') {
                        $('#currentImages').append(el);
                        imageCount++;
                    } else if (file.file_type === 'pdf') {
                        $('#currentDocuments').append(el);
                        docCount++;
                    }
                });

                $('#productImages').data('current-files', imageCount);
                $('#productDocuments').data('current-files', docCount);

                productModal.show();

            } else {
                alert("Load product failed");
            }

        },

        error: function(xhr) {
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: function(){
            $('#productForm .modal-body').removeClass('opacity-50');
        }
    });
};
}); // End document ready

window.shareSelectedToZalo = function() {
    let rawPaths = [];
    $('.share-checkbox:checked').each(function() {
        rawPaths.push($(this).val());
    });
    
    if (rawPaths.length === 0) {
        alert("Vui lòng tích chọn ít nhất 1 ảnh/file đính kèm để chia sẻ!");
        return;
    }

    $.post('process/generate_share.php', { files: rawPaths }, function(res) {
        if (res.success && res.links) {
            let textToShare = "Tài liệu sản phẩm đính kèm:\n" + res.links.join("\n");
            navigator.clipboard.writeText(textToShare).then(function() {
                alert("Đã copy toàn bộ link chia sẻ vào bộ nhớ tạm!\nCác link này không cần đăng nhập và có thời hạn 3 ngày.\n\nHệ thống sẽ tự động mở Zalo PC, bạn chỉ cần dán (Ctrl+V) vào khung chat.");
                window.location.href = "zalo://";
            }).catch(function() {
                alert("Không thể chuyển hướng/copy tự động, vui lòng copy thủ công dòng sau:\n\n" + textToShare);
            });
        } else {
            alert("Lỗi tạo link chia sẻ: " + (res.message || 'Lỗi không xác định'));
        }
    }, 'json').fail(function() {
        alert("Lỗi giao tiếp máy chủ khi tạo link chia sẻ.");
    });
};
