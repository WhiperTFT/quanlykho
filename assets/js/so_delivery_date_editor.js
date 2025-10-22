// ===== Editor "Ngày giao" cho BẢNG — Bootstrap Datepicker (tách biệt Flatpickr) =====
(function(){
  function toIso(dmy){ const p=(dmy||'').split('/'); return (p.length===3)?`${p[2]}-${p[1].padStart(2,'0')}-${p[0].padStart(2,'0')}`:''; }

  function attachDeliveryDateEditor_BSDP(tableElOrSelector){
    const $table=$(tableElOrSelector);
    const dt=$table.DataTable();
    if ($table.data('hasDeliveryEditorBSDP')) return;
    $table.data('hasDeliveryEditorBSDP', true);

    // Khởi tạo datepicker khi user focus/click vào input (lazy-init)
    $table.on('focus click', 'input.delivery-date', function(){
      const $inp=$(this);
      if ($inp.data('bsdp-init')) return;

      $inp.datepicker({
        format:'dd/mm/yyyy',
        language:'vi',
        autoclose:true,
        todayHighlight:true,
        clearBtn:true
      })
      .on('changeDate', ()=> $inp.trigger('change'))
      .on('hide', ()=> $inp.trigger('change'));

      $inp.data('bsdp-init', true);
      // mở ngay lần đầu để user chọn luôn
      $inp.datepicker('show');
    });

    // Lưu khi change
    $table.on('change', 'input.delivery-date', function(){
      const $inp=$(this);
      const $td=$inp.closest('td');
      const row=dt.row($td.closest('tr'));
      const rowData=row.data()||{};
      const id=rowData.id || rowData.order_id; // sửa key ID nếu khác
      const val=($inp.val()||'').trim();
      const iso=toIso(val);

      $.ajax({
        url: AJAX_URL.sales_order, // đang trỏ process/sales_order_serverside.php
        type:'POST', dataType:'json',
        data:{ action:'update_delivery_date', id, expected_delivery_date: iso },
        success: (res)=>{
          if(res && res.success){
            // giữ nguyên input + giá trị; không cần redraw là vẫn đẹp
            $inp.val(val);
          }else{
            if (typeof toast==='function') toast(res?.message || 'Không lưu được ngày giao','error');
            else alert(res?.message || 'Không lưu được ngày giao');
          }
        },
        error: ()=>{
          if (typeof toast==='function') toast('Lỗi máy chủ khi lưu ngày giao','error');
          else alert('Lỗi máy chủ khi lưu ngày giao');
        }
      });
    });
  }

  // export
  window.attachDeliveryDateEditor_BSDP=attachDeliveryDateEditor_BSDP;
})();
