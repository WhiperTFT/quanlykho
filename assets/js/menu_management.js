// File: assets/js/menu_management.js
// Yêu cầu: jQuery, jQuery UI Sortable, Bootstrap 5, Bootstrap Icons
// Backend giữ nguyên: process/menu_handler.php

$(document).ready(function () {
  const menuTreeContainer = $('#menu-tree');
  const modal = new bootstrap.Modal(document.getElementById('menu-modal'));
  const menuForm = $('#menu-form');
  const menuNameInput = $('#menu-name');
  const menuNameEnInput = $('#menu-name-en');
  const menuIconInput = $('#menu-icon-input');
  const iconPreview = $('#icon-preview i');

  // ---- Bẫy lỗi để không còn "Loading..." vô hạn ----
  (function(){
    const $box = $('#menu-tree');
    window.__menuDebug = function(title, detail){
      try {
        const safe = $('<div>').text(String(detail || '')).html();
        $box.html(`
          <div class="alert alert-danger">
            <div class="fw-semibold mb-1">${title}</div>
            <div class="small text-break">${safe}</div>
          </div>
        `);
      } catch(_) { console.error(title, detail); }
    };
    window.onerror = function(msg, src, line, col, err){
      console.error('[GLOBAL ERROR]', msg, src, line, col, err);
      window.__menuDebug('Lỗi JS toàn cục', `${msg} @ ${src}:${line}:${col}\n${err && err.stack || ''}`);
      return false;
    };
    window.addEventListener('unhandledrejection', function(ev){
      console.error('[PROMISE REJECTION]', ev.reason);
      window.__menuDebug('Lỗi Promise', `${ev.reason && (ev.reason.stack || ev.reason.message) || ev.reason}`);
    });
  })();

  // Nếu hàm log chưa có thì tạo no-op để tránh lỗi
  if (typeof window.sendUserLog !== 'function') {
    window.sendUserLog = function(){ return Promise.resolve(); };
  }

  /* ========= (A) DỊCH VI -> EN (rule-based + TitleCase) ========= */
  const VI_EN_DICT = [
    ['quản lý', 'Management'], ['thao tác', 'Actions'], ['báo cáo', 'Reports'],
    ['báo cáo bán hàng','Sales Reports'], ['cài đặt','Settings'], ['hệ thống','System'],
    ['người dùng','Users'], ['tài khoản','Accounts'], ['phân quyền','Permissions'], ['vai trò','Roles'],
    ['đơn hàng','Orders'], ['báo giá','Quotes'], ['khách hàng','Customers'], ['đối tác','Partners'],
    ['sản phẩm','Products'], ['danh mục','Categories'], ['đơn vị tính','Units'],
    ['tồn kho','Inventory'], ['xuất kho','Outbound'], ['nhập kho','Inbound'],
    ['vận chuyển','Shipping'], ['tài xế','Drivers'], ['lương','Payroll'],
    ['công nợ','Receivables'], ['mua hàng','Purchasing'], ['bán hàng','Sales'], ['thiết lập','Configuration'],
  ];
  function removeVietnameseTones(str) {
    return String(str||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/đ/g,'d').replace(/Đ/g,'D');
  }
  function titleCase(s){ return String(s||'').replace(/\w\S*/g, t => t[0].toUpperCase()+t.slice(1).toLowerCase()); }
  function viToEn(vi){
    const raw = String(vi||'').trim().toLowerCase();
    if(!raw) return '';
    for (const [k,v] of [...VI_EN_DICT].sort((a,b)=>b[0].length-a[0].length)) if (raw===k) return v;
    for (const [k,v] of VI_EN_DICT) if (raw.includes(k)) return v;
    return titleCase(removeVietnameseTones(vi));
  }

  /* ========= (B) GỢI Ý ICON THEO TIÊU ĐỀ ========= */
  const ICON_KEYWORDS = {
    users:['bi-people','bi-people-fill','bi-person-gear','bi-person-check'],
    orders:['bi-receipt','bi-cart-check','bi-bag-check','bi-clipboard-check'],
    quotes:['bi-file-earmark-text','bi-file-earmark-ruled','bi-file-text'],
    settings:['bi-gear','bi-gear-fill','bi-sliders','bi-sliders2'],
    inventory:['bi-box-seam','bi-boxes','bi-archive','bi-clipboard-data'],
    reports:['bi-graph-up','bi-bar-chart','bi-pie-chart','bi-graph-up-arrow'],
    categories:['bi-tags','bi-collection','bi-grid','bi-diagram-3'],
    products:['bi-cube','bi-grid-3x3-gap','bi-basket'],
    partners:['bi-building','bi-briefcase','bi-person-badge'],
    customers:['bi-person-lines-fill','bi-emoji-smile','bi-people'],
    shipping:['bi-truck','bi-geo-alt','bi-signpost-split'],
    drivers:['bi-steering-wheel','bi-person-workspace','bi-geo'],
    finance:['bi-cash-coin','bi-wallet2','bi-currency-dollar'],
    actions:['bi-lightning','bi-lightning-fill','bi-magic'],
    management:['bi-ui-checks-grid','bi-diagram-3','bi-kanban'],
    system:['bi-cpu','bi-hdd','bi-shield-lock'],
  };
  function suggestIconsByTitle(enTitle){
    const key = String(enTitle||'').toLowerCase();
    const tests = [
      ['users', /user|account|staff|role|permission|people/],
      ['orders', /order|receipt|invoice|cart|bag|clipboard/],
      ['quotes', /quote|quotation|proposal|offer/],
      ['settings', /setting|config|preference|option|slider/],
      ['inventory', /inventory|warehouse|stock|box|archive/],
      ['reports', /report|chart|graph|stat|analytics|insight/],
      ['categories', /category|tag|grid|collection|taxonomy/],
      ['products', /product|item|sku|basket|cube/],
      ['partners', /partner|supplier|vendor|company|building|briefcase/],
      ['customers', /customer|client|contact/],
      ['shipping', /ship|deliver|truck|geo|map|route/],
      ['drivers', /driver|steer|vehicle/],
      ['finance', /cash|wallet|currency|money|payment/],
      ['actions', /action|quick|magic|flash|lightning/],
      ['management', /manage|management|admin|kanban|diagram/],
      ['system', /system|cpu|server|security|shield/],
    ];
    let pool=[];
    for(const [k,re] of tests){ if(re.test(key)){ pool=ICON_KEYWORDS[k]||[]; break; } }
    if(!pool.length) pool=['bi-ui-checks-grid','bi-grid','bi-menu-button-wide','bi-three-dots'];
    return [...new Set(pool)].slice(0,6);
  }

  // Khu vực gợi ý icon (nếu HTML chưa có, sẽ tự thêm)
  let $iconSugWrap = $('#icon-suggestion-wrap');
  let $iconSuggestions = $('#icon-suggestions');
  if (!$iconSugWrap.length){
    $iconSugWrap = $(`
      <div id="icon-suggestion-wrap" class="mt-2" style="display:none;">
        <div class="small text-muted mb-1">Gợi ý biểu tượng:</div>
        <div id="icon-suggestions" class="d-flex flex-wrap gap-2"></div>
      </div>
    `);
    menuIconInput.closest('.mb-3').append($iconSugWrap);
    $iconSuggestions = $('#icon-suggestions');
  }

  function renderIconSuggestions(list){
    $iconSuggestions.empty();
    if(!list || !list.length){ $iconSugWrap.hide(); return; }
    list.forEach(cls=>{
      const btn=$(`
        <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center">
          <i class="bi ${cls} me-1"></i><span>${cls}</span>
        </button>
      `);
      btn.on('click', ()=>{
        menuIconInput.val(cls).trigger('keyup');
        sendUserLog('menu_icon_pick', `Picked icon: ${cls}`, 'info');
      });
      $iconSuggestions.append(btn);
    });
    $iconSugWrap.show();
  }

  // Preview icon khi gõ
  menuIconInput.on('keyup', function(){
    const iconClass = $(this).val().trim();
    iconPreview.attr('class', `bi ${iconClass || 'bi-circle'}`);
  });

  // ========= (C) RENDER CÂY: MỖI LI LUÔN CÓ UL CON =========
  function escapeHtml(str){
    return String(str||'')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function buildMenuItemNode(menu){
    const $li = $(`
      <li class="list-group-item" data-id="${menu.id}">
        <div class="d-flex align-items-center">
          <i class="bi bi-grip-vertical me-2 drag-handle" style="cursor: move;"></i>
          <i class="bi ${menu.icon || 'bi-circle'} me-3"></i>
          <span class="flex-grow-1">${escapeHtml(menu.name)} (${escapeHtml(menu.name_en || '')})</span>
          <small class="text-muted me-3">(${escapeHtml(menu.url || '#')})</small>
          <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm edit-btn" title="Sửa"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-outline-danger btn-sm delete-btn" title="Xóa"><i class="bi bi-trash"></i></button>
          </div>
        </div>
        <ul class="list-group sortable-list ms-4 mt-2"></ul>
      </li>
    `);
    const $childUL = $li.children('ul.sortable-list');
    if (Array.isArray(menu.children) && menu.children.length){
      menu.children.forEach(ch => $childUL.append(buildMenuItemNode(ch)));
    }
    return $li;
  }

  function renderMenuTree(menus, parent){
    const $root = $('<ul class="list-group sortable-list"></ul>');
    (menus||[]).forEach(m => $root.append(buildMenuItemNode(m)));
    parent.empty().append($root); // ← luôn clear spinner
  }

  // ========= (D) SORTABLE: connectWith cho TẤT CẢ danh sách =========
  function initSortable(){
  try {
    if (!$.fn.sortable) return;

    $('.sortable-list').sortable({
      items: '> li',                // chỉ kéo các li trực tiếp
      handle: '.drag-handle',
      connectWith: '.sortable-list',

      helper: 'clone',              // bóng kéo mượt, không xô layout
      appendTo: 'body',             // helper nằm trên cùng -> ít bị cấn
      cursor: 'grabbing',
      cursorAt: { top: 20, left: 20 }, // con trỏ lệch khỏi góc -> dễ canh

      axis: 'y',                    // kéo theo trục dọc (tránh đứng sai hàng)
      distance: 5,                  // kéo cách 5px mới kích hoạt (tránh click nhầm)
      delay: 50,                    // giữ 50ms mới bắt đầu kéo (đỡ rung)
      revert: 120,                  // bật nhẹ khi thả
      opacity: 0.95,

      placeholder: 'menu-drop-slot',   // class ô thả (đã CSS bên dưới)
      forcePlaceholderSize: true,      // placeholder luôn đủ lớn
      tolerance: 'pointer',            // theo vị trí con trỏ -> chính xác hơn intersect
      // Mẹo: nếu item có layout phức tạp, có thể dùng:
      // toleranceElement: '> .d-flex', // xác định vùng tính toán "chạm"

      dropOnEmpty: true,               // thả vào danh sách rỗng/ít item dễ hơn
      scroll: true,
      scrollSensitivity: 60,
      scrollSpeed: 20,

      start: (e,ui)=> sendUserLog('menu_drag_start', `id=${ui.item.data('id')||''}`, 'debug'),
      stop:  (e,ui)=> sendUserLog('menu_drag_stop',  `id=${ui.item.data('id')||''}`, 'debug'),
      over: function(e, ui){
        // Khi con trỏ vào 1 UL, nới padding để dễ thả
        $(this).addClass('is-hovering');
      },
      out: function(e, ui){
        $(this).removeClass('is-hovering');
      }
    }).disableSelection();
  } catch(e) {
    console.error('[Menu] initSortable error', e);
  }
}



  // ========= (E) LOAD / CRUD / REORDER =========
  function loadMenus(){
    const spinner = `
      <div class="text-center p-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <div class="small text-muted mt-2">Đang tải menu...</div>
      </div>`;
    menuTreeContainer.html(spinner);

    $.ajax({
      url: 'process/menu_handler.php',
      type: 'GET',
      data: { action: 'fetch' },
      dataType: 'json',
      timeout: 10000
    })
    .done(function(res){
      if(!res || res.status!=='success'){
        const msg = (res && res.message) ? res.message : 'Phản hồi không hợp lệ.';
        window.__menuDebug('Lỗi khi tải menu', msg);
        sendUserLog('menu_list_error', `bad_response: ${msg}`, 'error');
        return;
      }
      try {
        renderMenuTree(res.menus, menuTreeContainer);
      } catch(e){
        console.error('[Menu] renderMenuTree error', e);
        menuTreeContainer
          .html(`<div class="alert alert-warning mb-2">Render nâng cao lỗi, tạm hiển thị JSON thô.</div>`)
          .append($('<pre class="small mb-0 text-break">').text(JSON.stringify(res.menus, null, 2)));
        return;
      }
      initSortable();
      sendUserLog('menu_list_loaded', `count=${(res.menus||[]).length}`, 'info');
    })
    .fail(function(jqXHR, textStatus, errorThrown){
      const detail = `status=${jqXHR.status} | ${textStatus} | ${errorThrown||''} | ${(jqXHR.responseText||'').slice(0,1000)}`;
      window.__menuDebug('Không thể tải menu', detail);
      sendUserLog('menu_list_ajax_fail', detail, 'error');
    });
  }

  function openCreateModal(){
    menuForm[0].reset();
    $('#menu-id').val('');
    menuNameEnInput.val('').removeAttr('data-modified');
    $('#menuModalLabel').text('Thêm Menu Mới');
    iconPreview.attr('class','bi bi-circle');
    $('#icon-suggestion-wrap').hide();
    modal.show();
    sendUserLog('menu_modal_open', 'create', 'info');
  }

  function openEditModalByData(data){
    $('#menu-id').val(data.id);
    menuNameInput.val(data.name);
    menuNameEnInput.val(data.name_en || '').removeAttr('data-modified');
    $('#menu-url').val(data.url);
    menuIconInput.val(data.icon);
    $('#menu-permission').val(data.permission_key);
    iconPreview.attr('class', `bi ${data.icon || 'bi-circle'}`);
    $('#menuModalLabel').text('Sửa Menu');
    const enBase = data.name_en || viToEn(data.name || '');
    renderIconSuggestions(suggestIconsByTitle(enBase));
    modal.show();
    sendUserLog('menu_modal_open', `edit id=${data.id}`, 'info');
  }

  function saveMenu(){
    if (!menuNameEnInput.val().trim() && !menuNameEnInput.attr('data-modified')) {
      menuNameEnInput.val(viToEn(menuNameInput.val()));
    }
    $.ajax({
      url: 'process/menu_handler.php',
      type: 'POST',
      data: menuForm.serialize() + '&name_en=' + encodeURIComponent(menuNameEnInput.val()) + '&action=save',
      dataType: 'json',
      success: function (response) {
        if (response.status === 'success') {
          modal.hide();
          loadMenus();
          sendUserLog('menu_save', `id=${response.id||$('#menu-id').val()||''}; name="${menuNameInput.val()}"`, 'info');
          alert('✅ Đã lưu menu thành công!');
        } else {
          alert('❌ Lỗi: ' + response.message);
        }
      }
    });
  }

  function deleteMenu(id){
    if(!confirm('Bạn có chắc chắn muốn xóa menu này?')) return;
    $.ajax({
      url: 'process/menu_handler.php',
      type: 'POST',
      data: { action: 'delete', id },
      dataType: 'json',
      success: function (response) {
        if (response.status === 'success') {
          loadMenus();
          sendUserLog('menu_delete', `id=${id}`, 'warning');
          alert('🗑️ Đã xóa menu thành công!');
        } else {
          alert('❌ Lỗi: ' + response.message);
        }
      }
    });
  }

  function serializeMenu($ul){
    return $ul.children('li').map(function(){
      const $li = $(this);
      const item = { id: $li.data('id') };
      const $child = $li.children('ul.sortable-list');
      if($child.length) item.children = serializeMenu($child);
      return item;
    }).get();
  }

  function saveOrder($btn){
    const $rootList = menuTreeContainer.children('.sortable-list');
    const order = serializeMenu($rootList);

    $btn.prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang lưu...');
    sendUserLog('menu_save_order_start', `payload_size=${JSON.stringify(order).length}`, 'debug');

    $.ajax({
      url: 'process/menu_handler.php',
      type: 'POST',
      data: { action: 'reorder', order: JSON.stringify(order) },
      dataType: 'json'
    })
    .done(function(response){
      if (response.status !== 'success') {
        alert('Lỗi khi lưu thứ tự: ' + response.message);
        sendUserLog('menu_save_order_done', 'server_error', 'error');
      } else {
        sendUserLog('menu_save_order_done', `ok; nodes=${order.length}`, 'info');
      }
    })
    .fail(function(){
      alert('Lỗi nghiêm trọng khi kết nối server để lưu thứ tự.');
      sendUserLog('menu_save_order_done', 'ajax_fail', 'error');
    })
    .always(function(){
      $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Lưu Thứ Tự');
      loadMenus();
    });
  }

  // ========= (F) SỰ KIỆN =========
  $('#add-menu-btn').on('click', openCreateModal);

  // Auto dịch + gợi ý icon khi gõ tên VI
  menuNameInput.on('input', function(){
    const viText = $(this).val().trim();
    if (!viText){ $('#icon-suggestion-wrap').hide(); return; }

    if (!menuNameEnInput.data('modified')) {
      const en = viToEn(viText);
      menuNameEnInput.val(en);
      sendUserLog('menu_name_autotranslate', `VI="${viText}" -> EN="${en}"`, 'info');

      // Tuỳ chọn: refine qua API cũ (không bắt buộc)
      try{
        fetch('process/translate_vi_to_en.php',{
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ text: viText })
        }).then(r=>r.json()).then(d=>{
          if (d && d.translated) {
            const prev = menuNameEnInput.val();
            if (Math.abs(d.translated.length - prev.length) > 3) {
              menuNameEnInput.val(d.translated);
              sendUserLog('menu_name_autotranslate_api', `override EN="${d.translated}"`, 'debug');
            }
          }
        }).catch(()=>{});
      }catch(_){}
    }

    renderIconSuggestions(suggestIconsByTitle(menuNameEnInput.val() || viToEn(viText)));
  });

  menuNameEnInput.on('input', function(){
    $(this).attr('data-modified', 'true');
    renderIconSuggestions(suggestIconsByTitle($(this).val()));
  });

  // Edit
  menuTreeContainer.on('click', '.edit-btn', function(e){
    e.stopPropagation();
    const id = $(this).closest('li').data('id');
    $.ajax({
      url: `process/menu_handler.php?action=get_one&id=${id}`,
      type: 'GET',
      dataType: 'json',
      success: function (response) {
        if (response.status === 'success') openEditModalByData(response.data);
        else alert('Lỗi: ' + response.message);
      }
    });
  });

  // Save
  $('#save-menu-btn').on('click', saveMenu);

  // Delete
  menuTreeContainer.on('click', '.delete-btn', function(e){
    e.stopPropagation();
    deleteMenu($(this).closest('li').data('id'));
  });

  // Save order
  $('#save-order-btn').on('click', function(){ saveOrder($(this)); });

  // Load lần đầu
  loadMenus();
});
