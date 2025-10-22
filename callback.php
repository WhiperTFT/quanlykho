<?php
// /quanlykho/callback.php
require __DIR__ . '/vendor/autoload.php';

function write_json_atomic(string $path, array $data): void {
  $dir = dirname($path);
  if (!is_dir($dir)) {
    if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
      exit("❌ Không thể tạo thư mục: " . htmlspecialchars($dir));
    }
  }
  $tmp = $path . '.tmp';
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json === false) exit("❌ Không thể encode JSON.");
  if (@file_put_contents($tmp, $json, LOCK_EX) === false) exit("❌ Không thể ghi file tạm.");
  if (!@rename($tmp, $path)) {
    if (!@copy($tmp, $path)) { @unlink($tmp); exit("❌ Không thể thay thế token.json."); }
    @unlink($tmp);
  }
}

$client = new Google_Client();
$client->setApplicationName("Gmail OAuth App");
$client->setScopes('https://www.googleapis.com/auth/gmail.send'); // chỉ gửi mail
$client->setAuthConfig(__DIR__ . '/config/client_secret.json');
$client->setAccessType('offline');
$client->setIncludeGrantedScopes(true);
$client->setPrompt('consent select_account');

// Khớp tuyệt đối với URI đã đăng ký
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
  $client->setRedirectUri('http://localhost/quanlykho/callback.php');
} else {
  $client->setRedirectUri('https://quanlykho.ddns.net/quanlykho/callback.php');
}

if (!isset($_GET['code'])) {
  exit('❌ Lỗi: Không nhận được mã ủy quyền (code).');
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) {
  echo "❌ Lỗi khi lấy token: " . htmlspecialchars($token['error_description'] ?? $token['error']);
  exit;
}

// Lưu token (ghi đè nếu có)
$tokenPath = __DIR__ . '/config/token.json';
write_json_atomic($tokenPath, $token);

if (empty($token['refresh_token'])) {
  echo "✅ Đã lưu access token <em>nhưng thiếu</em> <code>refresh_token</code>.<br>"
     . "👉 Hãy xóa <code>config/token.json</code> và ủy quyền lại (giữ <code>prompt=consent select_account</code>, chọn tài khoản khác nếu cần).";
} else {
  echo "✅ Ủy quyền thành công! Đã lưu token (kèm <code>refresh_token</code>) vào <code>config/token.json</code>.";
}
