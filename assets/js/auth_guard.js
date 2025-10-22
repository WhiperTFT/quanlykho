(function () {
  // Nếu bạn đã có biến global PROJECT_BASE_URL thì dùng, không có thì fallback
  var base = (window.PROJECT_BASE_URL || '/quanlykho/');

  // jQuery global AJAX error handler
  if (window.jQuery) {
    jQuery(document).ajaxError(function (event, jqxhr) {
      if (jqxhr && (jqxhr.status === 401 || jqxhr.status === 403)) {
        var back = encodeURIComponent(location.pathname + location.search);
        window.location.href = base + 'login.php?message=login_required&redirect=' + back;
      }
    });
  }

  // Optional: nếu bạn có dùng fetch() thuần ở vài nơi, có thể bọc helper:
  window.authFetch = function (input, init) {
    return fetch(input, init).then(function (res) {
      if (res && (res.status === 401 || res.status === 403)) {
        var back = encodeURIComponent(location.pathname + location.search);
        window.location.href = base + 'login.php?message=login_required&redirect=' + back;
        // chặn các chain tiếp theo
        return Promise.reject(new Error('Unauthorized'));
      }
      return res;
    });
  };
})();
