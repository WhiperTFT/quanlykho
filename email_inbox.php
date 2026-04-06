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

<div class="container-fluid mt-3">

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
    <h4 class="mb-2 mb-md-0">
      <i class="fa fa-envelope"></i>
      Gmail Inbox / Hộp thư đến
    </h4>

    <div class="d-flex flex-wrap align-items-center">
      <form class="form-inline mr-2 mb-2 mb-md-0" method="get">
        <input type="text"
               class="form-control form-control-sm mr-2"
               name="q"
               placeholder="Search / Tìm kiếm..."
               value="<?= htmlspecialchars($searchQuery ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button class="btn btn-outline-secondary btn-sm" type="submit">
          <i class="fa fa-search"></i>
        </button>
      </form>

      <a href="email_compose.php" class="btn btn-primary btn-sm mb-2 mb-md-0">
        <i class="fa fa-pencil"></i> New Email / Soạn mới
      </a>
    </div>
  </div>

  <?php if ($gmailError): ?>
    <div class="alert alert-danger mt-2">
      Gmail error / Lỗi kết nối Gmail: <?= htmlspecialchars($gmailError, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0" style="font-size: 0.85rem;">
            <thead class="thead-light">
              <tr>
                <th style="min-width: 220px;">From / Từ</th>
                <th style="min-width: 260px;">Subject / Tiêu đề</th>
                <th style="width: 140px;">Date / Ngày</th>
                <th style="width: 220px;">Actions / Hành động</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($messages)): ?>
              <tr><td colspan="4" class="text-center">No emails / Không có email nào.</td></tr>
            <?php else: ?>
              <?php foreach ($messages as $m): ?>
                <tr>
                  <td style="white-space: normal;">
                    <?= nl2br(htmlspecialchars($m['from'], ENT_QUOTES, 'UTF-8')) ?>
                  </td>
                  <td style="white-space: normal;">
                    <?= nl2br(htmlspecialchars($m['subject'], ENT_QUOTES, 'UTF-8')) ?>
                  </td>
                  <td>
                    <?= htmlspecialchars($m['date'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td>
                    <?php
                    $id      = urlencode($m['id']);
                    $thread  = urlencode($m['threadId']);
                    ?>
                    <a class="btn btn-sm btn-outline-secondary mb-1"
                       href="email_view.php?id=<?= $id ?>&thread=<?= $thread ?>">
                       View / Xem
                    </a>
                    <a class="btn btn-sm btn-outline-primary mb-1"
                       href="email_compose.php?reply_to=<?= $id ?>">
                       Reply / Trả lời
                    </a>
                    <a class="btn btn-sm btn-outline-info mb-1"
                       href="email_compose.php?forward=<?= $id ?>">
                       Forward / Chuyển tiếp
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
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
