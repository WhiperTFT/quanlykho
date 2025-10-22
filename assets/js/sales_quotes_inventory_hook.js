// assets/js/sales_quotes_inventory_hook.js
$(function(){
  // Giả sử mỗi dòng chi tiết có select .detail-product và input .detail-qty
  // và có chỗ hiển thị badge .inventory-badge
  async function refreshInventoryBadge($row){
    const pid = parseInt($row.find('.detail-product').val(), 10);
    if (!pid) { $row.find('.inventory-badge').html(''); return; }
    try {
      const res = await $.getJSON('process/get_inventory_info.php', { product_id: pid });
      const onhand = Number(res.on_hand||0);
      const atp    = Number(res.atp||0);
      $row.data('onhand', onhand).data('atp', atp);
      const html = `
        <span class="badge bg-secondary me-1">On hand: ${onhand}</span>
        <span class="badge ${atp>=0?'bg-success':'bg-danger'}">ATP: ${atp}</span>
      `;
      $row.find('.inventory-badge').html(html);
    } catch (e) {
      $row.find('.inventory-badge').html('<span class="text-danger">Không lấy được tồn</span>');
    }
  }

  // Khi chọn sản phẩm
  $(document).on('change', '.detail-product', function(){
    refreshInventoryBadge($(this).closest('tr'));
  });

  // Khi nhập số lượng → cảnh báo nếu > ATP
  $(document).on('input', '.detail-qty', function(){
    const $row = $(this).closest('tr');
    const qty = Number($(this).val() || 0);
    const atp = Number($row.data('atp') || 0);
    const $field = $(this);
    if (qty > atp && atp >= 0) {
      $field.addClass('is-invalid');
      if (!$row.find('.qty-warn').length) {
        $field.after('<div class="invalid-feedback qty-warn">Số lượng > ATP hiện có!</div>');
      }
    } else {
      $field.removeClass('is-invalid');
      $row.find('.qty-warn').remove();
    }
  });

  // Trước khi submit
  $('#salesQuotesForm').on('submit', function(e){
    let violation = false;
    $('.detail-qty').each(function(){
      const $row = $(this).closest('tr');
      const qty = Number($(this).val() || 0);
      const atp = Number($row.data('atp') || 0);
      if (qty > atp && atp >= 0) { violation = true; }
    });
    if (violation) {
      e.preventDefault();
      alert('Có dòng vượt quá ATP. Vui lòng điều chỉnh số lượng hoặc nhập hàng.');
    }
  });

  // Lúc load ban đầu, quét tất cả dòng đã có
  $('.detail-product').each(function(){
    refreshInventoryBadge($(this).closest('tr'));
  });
});
