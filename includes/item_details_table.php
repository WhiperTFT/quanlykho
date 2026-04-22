<?php

if (!isset($lang)) { $lang = []; }
?>
<div class="table-responsive item-details-table-wrapper">
    <table class="table table-hover table-striped table-sm table-mobile-cards" id="item-details-table">
        <thead class="table-light align-middle">
            <tr>
                <th scope="col" class="text-center" data-lang-key="stt"><?= $lang['stt'] ?? 'STT' ?></th>
                <th scope="col" data-lang-key="category"><?= $lang['category'] ?? 'Danh mục' ?></th>
                <th scope="col"><span data-lang-key="product_name"><?= $lang['product_name'] ?? 'Tên sản phẩm' ?></span> <span class="text-danger">*</span></th>
                <th scope="col" class="text-center" data-lang-key="unit"><?= $lang['unit'] ?? 'ĐVT' ?></th>
                <th scope="col" class="text-center"><span data-lang-key="quantity"><?= $lang['quantity'] ?? 'Số lượng' ?></span> <span class="text-danger">*</span></th>
                <th scope="col" class="text-end"><span data-lang-key="unit_price"><?= $lang['unit_price'] ?? 'Đơn giá' ?></span> <span class="text-danger">*</span></th>
                <th scope="col" class="text-end" data-lang-key="line_total"><?= $lang['line_total'] ?? 'Thành tiền' ?></th>
                <th scope="col" class="text-center"></th>
            </tr>
        </thead>
        <tbody id="item-details-body">
        </tbody>
        <tfoot>
            <tr class="item-row-template align-middle" style="display: none;">
                 <td class="stt-col text-center" data-label="<?= $lang['stt'] ?? 'STT' ?>"></td>
                 <td data-label="<?= $lang['category'] ?? 'Danh mục' ?>"><input type="text" class="form-control form-control-sm category-display bg-light" readonly tabindex="-1"></td>
                 <td data-label="<?= $lang['product_name'] ?? 'Tên sản phẩm' ?>">
                     <input type="text" class="form-control form-control-sm product-autocomplete" name="items[999][product_name_snapshot]" placeholder="<?= $lang['product_placeholder'] ?? 'Nhập tên sản phẩm...' ?>" required>
                     <input type="hidden" class="product-id" name="items[999][product_id]">
                     <input type="hidden" name="items[999][category_snapshot]">
                     <div class="invalid-feedback"></div>
                 </td>
                 <td data-label="<?= $lang['unit'] ?? 'ĐVT' ?>">
                      <input type="text" class="form-control form-control-sm unit-display bg-light text-center" readonly tabindex="-1">
                      <input type="hidden" name="items[999][unit_snapshot]">
                 </td>
                 <td data-label="<?= $lang['quantity'] ?? 'Số lượng' ?>">
                      <input type="text" inputmode="decimal" class="form-control form-control-sm quantity text-end" name="items[999][quantity]" value="1" required>
                      <div class="invalid-feedback"></div>
                 </td>
                  <td data-label="<?= $lang['unit_price'] ?? 'Đơn giá' ?>">
                      <div class="input-group input-group-sm">
                          <input type="text" inputmode="decimal" class="form-control form-control-sm unit-price text-end" name="items[999][unit_price]" value="0" required>
                          <span class="input-group-text currency-symbol-unit">đ</span>
                      </div>
                      <div class="invalid-feedback"></div>
                  </td>
                  <td data-label="<?= $lang['line_total'] ?? 'Thành tiền' ?>">
                      <div class="input-group input-group-sm">
                          <input type="text" class="form-control form-control-sm line-total bg-light text-end" readonly tabindex="-1">
                          <span class="input-group-text currency-symbol-unit">đ</span>
                      </div>
                  </td>
                  <td class="text-center align-middle action-cell-item" data-label="<?= $lang['actions'] ?? 'Thao tác' ?>">
                       <button type="button" class="btn btn-sm btn-outline-danger remove-item-row" title="<?= $lang['delete_row'] ?? 'Xóa dòng' ?>"><i class="bi bi-trash"></i></button>
                  </td>
            </tr>
            <tr class="border-top">
                <td colspan="8" class="text-end border-0 pt-3">
                    <button type="button" class="btn btn-sm btn-success" id="add-item-row">
                        <i class="bi bi-plus-lg"></i> <?= $lang['add_item_row'] ?? 'Thêm dòng' ?>
                    </button>
                </td>
            </tr>
        </tfoot>
    </table>
</div>