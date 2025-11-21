<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/init.php';
require_login();

require_once __DIR__ . '/includes/gmail_client.php';

$id       = $_GET['id']      ?? null;   // id message hiện tại (lấy từ inbox)
$threadId = $_GET['thread']  ?? null;   // id thread (cuộc hội thoại)

$gmailError = '';
$threadMessages = [];   // Mảng các message trong thread
$singleMessage = null;  // fallback nếu không có thread

try {
    $service = get_gmail_service();

    if ($threadId) {
        // Lấy cả cuộc hội thoại
        $thread = $service->users_threads->get('me', $threadId, ['format' => 'full']);
        $threadMessages = $thread->getMessages() ?: [];
    } elseif ($id) {
        // Fallback: chỉ lấy 1 message (như version cũ)
        $singleMessage = $service->users_messages->get('me', $id, ['format' => 'full']);
    } else {
        $gmailError = 'Thiếu tham số id hoặc thread.';
    }
} catch (Throwable $e) {
    $gmailError = $e->getMessage();
}
?>

<div class="container-fluid mt-3">
  <div class="row mb-2">
    <div class="col-md-8">
      <h4 class="mb-0">
        <i class="fa fa-envelope-open"></i>
        Email Conversation / Cuộc hội thoại
      </h4>
    </div>
    <div class="col-md-4 text-md-right mt-2 mt-md-0">
      <a href="email_inbox.php" class="btn btn-secondary btn-sm">
        <i class="fa fa-arrow-left"></i> Back to Inbox / Về hộp thư
      </a>
    </div>
  </div>

  <?php if ($gmailError): ?>
    <div class="alert alert-danger mt-2">
      Gmail error / Lỗi Gmail: <?= htmlspecialchars($gmailError, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php else: ?>

    <?php
    // Helper để render 1 message (dùng cho cả thread & single)
    function render_message_card($msg, $index, $total) {
        $headers = gmail_get_basic_headers($msg);
        $bodyHtml = gmail_get_body_html($msg->getPayload());
        $attachments = gmail_extract_attachments($msg);

        $isLatest = ($index === $total - 1);
        ?>
        <div class="card shadow-sm mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <strong>
                <?= $isLatest ? 'Latest / Mới nhất' : 'Message / Email' ?>
              </strong>
              <?php if (!empty($headers['Subject'])): ?>
                <span class="badge badge-light ml-2">
                  <?= htmlspecialchars($headers['Subject'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
            </div>
            <span class="badge badge-secondary">
              #<?= $index + 1 ?> / <?= $total ?>
            </span>
          </div>
          <div class="card-body">
            <div class="mb-2">
              <div><strong>From / Từ:</strong> <?= htmlspecialchars($headers['From'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
              <div><strong>To / Đến:</strong> <?= htmlspecialchars($headers['To'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
              <div><strong>Date / Ngày:</strong> <?= htmlspecialchars($headers['Date'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <?php if (!empty($attachments)): ?>
              <div class="mb-2">
                <strong>Attachments / Tệp đính kèm:</strong>
                <ul class="mb-0">
                  <?php foreach ($attachments as $att): ?>
                    <?php
                      $mid  = urlencode($att['messageId']);
                      $aid  = urlencode($att['attachmentId']);
                      $fn   = $att['filename'];
                      $mime = $att['mimeType'] ?: 'application/octet-stream';
                      $url  = "email_attachment.php?mid={$mid}&aid={$aid}&filename=" . urlencode($fn) . "&mime=" . urlencode($mime);
                    ?>
                    <li>
                      <a href="<?= $url ?>" target="_blank">
                        <i class="fa fa-paperclip"></i>
                        <?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?>
                        <small class="text-muted">
                          (<?= htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') ?>)
                        </small>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <hr>
            <div style="max-height: 60vh; overflow:auto; font-size: 0.9rem;">
              <?= $bodyHtml ?>
            </div>
          </div>
        </div>
        <?php
    }
    ?>

    <?php if ($threadMessages): ?>
      <?php
        $total = count($threadMessages);
        // Hiển thị theo thứ tự từ cũ đến mới
        foreach ($threadMessages as $idx => $m) {
            render_message_card($m, $idx, $total);
        }
        // Lấy id message mới nhất để reply/forward
        $latestMessage = $threadMessages[$total - 1];
        $latestId = urlencode($latestMessage->getId());
      ?>
      <div class="mb-3">
        <a href="email_compose.php?reply_to=<?= $latestId ?>" class="btn btn-primary btn-sm">
          <i class="fa fa-reply"></i> Reply / Trả lời
        </a>
        <a href="email_compose.php?forward=<?= $latestId ?>" class="btn btn-info btn-sm">
          <i class="fa fa-share"></i> Forward / Chuyển tiếp
        </a>
      </div>

    <?php elseif ($singleMessage): ?>
      <?php render_message_card($singleMessage, 0, 1); ?>
      <?php $sid = urlencode($singleMessage->getId()); ?>
      <div class="mb-3">
        <a href="email_compose.php?reply_to=<?= $sid ?>" class="btn btn-primary btn-sm">
          <i class="fa fa-reply"></i> Reply / Trả lời
        </a>
        <a href="email_compose.php?forward=<?= $sid ?>" class="btn btn-info btn-sm">
          <i class="fa fa-share"></i> Forward / Chuyển tiếp
        </a>
      </div>

    <?php else: ?>
      <div class="alert alert-warning mt-2">
        No email data / Không có dữ liệu email.
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
