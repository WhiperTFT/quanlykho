<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/init.php';
require_login();

require_once __DIR__ . '/includes/gmail_client.php';

$gmailError = '';
$messages = [];

try {
    $service = get_gmail_service();

    // Nhận từ ô tìm kiếm (nếu có)
    $searchQuery = trim($_GET['q'] ?? '');

    $params = [
        'labelIds'   => ['INBOX'],
        'maxResults' => 50,
    ];
    if ($searchQuery !== '') {
        $params['q'] = $searchQuery;
    }

    // Lấy mail mới nhất trong INBOX (hoặc theo search)
    $list = $service->users_messages->listUsersMessages('me', $params);

    if ($list->getMessages()) {
        $client = $service->getClient();
        $tokenArray = $client->getAccessToken();
        $accessToken = $tokenArray['access_token'] ?? '';

        $mh = curl_multi_init();
        $curls = [];

        foreach ($list->getMessages() as $m) {
            $id = $m->getId();
            $url = "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$id}?format=metadata&metadataHeaders=From&metadataHeaders=Subject&metadataHeaders=Date";
            $curls[$id] = curl_init($url);
            curl_setopt($curls[$id], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curls[$id], CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$accessToken}",
                "Accept: application/json"
            ]);
            // SSL settings to avoid local XAMPP CA issues if any
            curl_setopt($curls[$id], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curls[$id], CURLOPT_SSL_VERIFYHOST, false);
            curl_multi_add_handle($mh, $curls[$id]);
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        foreach ($list->getMessages() as $m) {
            $id = $m->getId();
            $c = $curls[$id];
            $response = curl_multi_getcontent($c);
            curl_multi_remove_handle($mh, $c);

            $data = json_decode($response, true);
            if (!isset($data['payload'])) {
                continue;
            }

            $headersArray = $data['payload']['headers'] ?? [];
            $from = $subject = $dateRaw = '';

            foreach ($headersArray as $h) {
                switch ($h['name']) {
                    case 'From':
                        $from = $h['value'];
                        break;
                    case 'Subject':
                        $subject = $h['value'];
                        break;
                    case 'Date':
                        $dateRaw = $h['value'];
                        break;
                }
            }

            // Chuẩn hóa Date: 21/11/2025 15:00 (giờ VN)
            $dateDisplay = $dateRaw;
            if ($dateRaw) {
                try {
                    $dt = new DateTime($dateRaw);
                    $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
                    $dateDisplay = $dt->format('d/m/Y H:i');
                } catch (Exception $e) {
                    // giữ nguyên $dateRaw nếu parse fail
                }
            }

            $messages[] = [
                'id'       => $id,
                'threadId' => $data['threadId'] ?? '',
                'from'     => $from,
                'subject'  => $subject,
                'date'     => $dateDisplay,
            ];
        }
        curl_multi_close($mh);
    }
} catch (Throwable $e) {
    $gmailError = $e->getMessage();
}
?>

<div class="page-header">
  <div>
    <h1 class="h3 fw-bold mb-1"><i class="bi bi-envelope-fill me-2 text-primary"></i>Hộp thư đến (Gmail Inbox)</h1>
    <p class="text-muted mb-0 small">Xem và quản lý email từ tài khoản Gmail được liên kết</p>
  </div>
  <div class="page-header-actions">
    <form class="d-flex gap-2" method="get">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" class="form-control border-start-0" name="q"
               placeholder="Tìm kiếm email..."
               value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <button class="btn btn-outline-secondary btn-sm" type="submit">
        <i class="bi bi-search me-1"></i>Tìm
      </button>
    </form>
    <a href="email_compose.php" class="btn btn-primary btn-sm">
      <i class="bi bi-pencil-square me-1"></i>Soạn email mới
    </a>
  </div>
</div>

<?php if ($gmailError): ?>
  <div class="alert alert-danger alert-modern mt-2">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Lỗi kết nối Gmail:</strong> <?= htmlspecialchars($gmailError, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php else: ?>
  <div class="content-card shadow-sm">
    <div class="content-card-header">
      <span><i class="bi bi-list-ul me-2 text-primary"></i>Danh sách email</span>
      <span class="badge bg-light text-dark border"><?= count($messages) ?> email</span>
    </div>
    <div class="content-card-body-flush">
      <div class="table-responsive">
        <table class="table table-hover table-custom mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width:220px;">Người gửi</th>
              <th style="min-width:260px;">Tiêu đề</th>
              <th style="width:140px;">Ngày nhận</th>
              <th style="width:220px;" class="text-center">Hành động</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($messages)): ?>
            <tr><td colspan="4" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>Không có email nào.
            </td></tr>
          <?php else: ?>
            <?php foreach ($messages as $m): ?>
              <tr>
                <td style="white-space:normal;" class="fw-semibold">
                  <i class="bi bi-person-circle me-1 text-muted"></i>
                  <?= nl2br(htmlspecialchars($m['from'], ENT_QUOTES, 'UTF-8')) ?>
                </td>
                <td style="white-space:normal;">
                  <?= nl2br(htmlspecialchars($m['subject'], ENT_QUOTES, 'UTF-8')) ?>
                </td>
                <td class="text-muted small">
                  <i class="bi bi-clock me-1"></i>
                  <?= htmlspecialchars($m['date'], ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td class="text-center text-nowrap">
                  <?php
                  $id     = urlencode($m['id']);
                  $thread = urlencode($m['threadId']);
                  ?>
                  <a class="btn btn-sm btn-outline-secondary" href="email_view.php?id=<?= $id ?>&thread=<?= $thread ?>" title="Xem">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a class="btn btn-sm btn-outline-primary" href="email_compose.php?reply_to=<?= $id ?>" title="Trả lời">
                    <i class="bi bi-reply-fill"></i>
                  </a>
                  <a class="btn btn-sm btn-outline-info" href="email_compose.php?forward=<?= $id ?>" title="Chuyển tiếp">
                    <i class="bi bi-forward-fill"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
