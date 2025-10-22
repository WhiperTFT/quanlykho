// assets/js/idle-logout.js
(function () {
  var TIMEOUT_MIN = window.IDLE_TIMEOUT_MIN || 30; // phút
  var TIMEOUT_MS  = TIMEOUT_MIN * 60 * 1000;
  var WARN_BEFORE_MS = 0; // đặt 60*1000 nếu muốn cảnh báo trước 1 phút

  var timer = null, warnTimer = null;

    var LOGOUT_URL = 'process/logout_process.php'; // đúng thư mục
  var LOGIN_URL  = 'login.php?message=timeout';  // trang login ở gốc

  function logout(reason) {
    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(LOGOUT_URL + '?reason=' + encodeURIComponent(reason || 'idle'));
      }
    } catch (e) {}

    fetch(LOGOUT_URL, {
      method: 'POST',
      body: new URLSearchParams({ reason: reason || 'idle' }),
      keepalive: true,
      headers: { 'Accept': 'application/json' }
    }).catch(function(){});

    window.location.href = LOGIN_URL;
  }


  function clearTimers() {
    if (timer)     { clearTimeout(timer); timer = null; }
    if (warnTimer) { clearTimeout(warnTimer); warnTimer = null; }
  }

  function scheduleTimers() {
    clearTimers();
    if (WARN_BEFORE_MS > 0 && WARN_BEFORE_MS < TIMEOUT_MS) {
      warnTimer = setTimeout(function () {
        // Có thể hiển thị cảnh báo (Swal/Modal) tại đây nếu muốn
      }, TIMEOUT_MS - WARN_BEFORE_MS);
    }
    timer = setTimeout(function () { logout('idle'); }, TIMEOUT_MS);
  }

  // Reset khi có hoạt động
  ['mousemove','keydown','scroll','touchstart','pointerdown','visibilitychange'].forEach(function (ev) {
    window.addEventListener(ev, scheduleTimers, { passive: true });
  });

  scheduleTimers();

  // (Tuỳ chọn – KHÔNG khuyến nghị): gửi tín hiệu khi đóng tab/trình duyệt
  /*
  window.addEventListener('pagehide', function () {
    if (navigator.sendBeacon) navigator.sendBeacon('logout_process.php?reason=tab_closed');
  });
  */
})();
