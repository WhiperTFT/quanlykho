<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/init.php';
require_login();

require_once __DIR__ . '/includes/gmail_client.php';

$service = null;
$gmailError = '';

$mode = 'new'; // new / reply / forward

$to = '';
$cc = '';
$subject = '';
$body = '';
$success = '';
$error = '';
$origIdForForward = ''; // lưu message gốc khi forward
$suggestedEmails = [];  // dùng cho autocomplete

// Helper thêm email vào danh sách (lọc trùng)
function add_email_suggestion(array &$list, string $value): void {
    $value = trim($value);
    if ($value === '') return;

    // tách theo dấu phẩy (trường hợp nhiều email trong 1 header)
    $parts = preg_split('/[,;]/', $value);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;

        // Tìm email dạng xxx@yyy.zzz
        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $p, $m)) {
            foreach ($m[0] as $email) {
                $email = strtolower($email);
                if (!in_array($email, $list, true)) {
                    $list[] = $email;
                    if (count($list) >= 100) {
                        return; // giới hạn 100 gợi ý cho nhẹ
                    }
                }
            }
        }
    }
}

try {
    $service = get_gmail_service();

    // Lấy một ít email gần đây để tạo danh sách gợi ý địa chỉ
    try {
        $list = $service->users_messages->listUsersMessages('me', [
            'maxResults' => 50,
        ]);
        if ($list->getMessages()) {
            foreach ($list->getMessages() as $m) {
                $msg = $service->users_messages->get('me', $m->getId(), [
                    'format'          => 'metadata',
                    'metadataHeaders' => ['From', 'To'],
                ]);
                $headers = $msg->getPayload()->getHeaders();
                foreach ($headers as $h) {
                    $name = $h->getName();
                    if ($name === 'From' || $name === 'To') {
                        add_email_suggestion($suggestedEmails, $h->getValue());
                    }
                }
                if (count($suggestedEmails) >= 100) {
                    break;
                }
            }
        }
        sort($suggestedEmails);
    } catch (Throwable $e) {
        // không critical, lỗi thì bỏ qua autocomplete
    }

} catch (Throwable $e) {
    $gmailError = $e->getMessage();
}

// Chuẩn bị dữ liệu nếu là reply hoặc forward (GET)
if (!$gmailError && $_SERVER['REQUEST_METHOD'] !== 'POST') {

    if (!empty($_GET['reply_to'])) {
        $mode = 'reply';
        $origIdForForward = ''; // không dùng
        $origId = $_GET['reply_to'];

        try {
            $orig = $service->users_messages->get('me', $origId, ['format' => 'full']);
            $h = gmail_get_basic_headers($orig);
            $origFrom    = $h['From'] ?? '';
            $origSubject = $h['Subject'] ?? '';
            $origDate    = $h['Date'] ?? '';

            $to      = $origFrom;
            $subject = preg_match('/^Re:/i', $origSubject) ? $origSubject : 'Re: ' . $origSubject;

            $body = "\n\n-------------------------\n"
                  . "On $origDate, $origFrom wrote:\n";

        } catch (Throwable $e) {
            $error = 'Không đọc được email gốc để trả lời: ' . $e->getMessage();
        }

    } elseif (!empty($_GET['forward'])) {
        $mode = 'forward';
        $origIdForForward = $_GET['forward'];

        try {
            $orig = $service->users_messages->get('me', $origIdForForward, ['format' => 'full']);
            $h = gmail_get_basic_headers($orig);
            $origFrom    = $h['From'] ?? '';
            $origSubject = $h['Subject'] ?? '';
            $origDate    = $h['Date'] ?? '';

            $subject = preg_match('/^Fwd:/i', $origSubject) ? $origSubject : 'Fwd: ' . $origSubject;

            $body = "\n\n---------- Forwarded message ---------\n"
                  . "From: $origFrom\n"
                  . "Date: $origDate\n"
                  . "Subject: $origSubject\n\n"
                  . "[Original content & attachments will be forwarded. / Nội dung & file gốc sẽ được chuyển tiếp.]\n";

        } catch (Throwable $e) {
            $error = 'Không đọc được email gốc để chuyển tiếp: ' . $e->getMessage();
        }
    }
}

// Xử lý gửi mail (POST)
if (!$gmailError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $to      = trim($_POST['to'] ?? '');
    $cc      = trim($_POST['cc'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body    = $_POST['body'] ?? '';

    $mode = $_POST['mode'] ?? 'new';
    $origIdForForward = $_POST['orig_message_id'] ?? '';

    // Đọc file đính kèm người dùng upload
    $uploadedAttachments = [];
    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $names = $_FILES['attachments']['name'];
        $types = $_FILES['attachments']['type'];
        $tmp   = $_FILES['attachments']['tmp_name'];
        $errs  = $_FILES['attachments']['error'];
        $sizes = $_FILES['attachments']['size'];

        foreach ($names as $idx => $name) {
            if ($errs[$idx] === UPLOAD_ERR_OK && $sizes[$idx] > 0 && is_uploaded_file($tmp[$idx])) {
                $content = file_get_contents($tmp[$idx]);
                if ($content === false) {
                    continue;
                }
                $uploadedAttachments[] = [
                    'filename' => $name,
                    'mimeType' => $types[$idx] ?: 'application/octet-stream',
                    'data'     => $content,
                ];
            }
        }
    }

    if ($to === '' || $subject === '') {
        $error = 'Vui lòng nhập To và Subject. / Please enter To and Subject.';
    } else {

        try {
            $rawMessage = '';

            // Xác định có cần multipart/mixed không?
            $hasOrigAttachments = false;
            $origAttachments = [];

            if ($mode === 'forward' && $origIdForForward !== '') {
                $orig = $service->users_messages->get('me', $origIdForForward, ['format' => 'full']);
                $origAttachments = gmail_extract_attachments($orig);
                $hasOrigAttachments = !empty($origAttachments);
            }

            $hasUploaded = !empty($uploadedAttachments);

            if ($hasOrigAttachments || $hasUploaded) {
                // multipart/mixed
                $boundary = 'b_' . uniqid();

                $rawMessage =
                    "To: $to\r\n" .
                    ($cc !== '' ? "Cc: $cc\r\n" : '') .
                    "Subject: $subject\r\n" .
                    "MIME-Version: 1.0\r\n" .
                    "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";

                // Part 1: text
                $rawMessage .= "--$boundary\r\n";
                $rawMessage .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
                $rawMessage .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $rawMessage .= $body . "\r\n\r\n";

                // Đính kèm từ email gốc (nếu forward)
                if ($hasOrigAttachments) {
                    foreach ($origAttachments as $att) {
                        $aid      = $att['attachmentId'];
                        $filename = $att['filename'];
                        $mimeType = $att['mimeType'] ?: 'application/octet-stream';

                        $attDataObj = $service->users_messages_attachments->get('me', $origIdForForward, $aid);
                        $fileData   = gmail_decode_body($attDataObj->getData());
                        if ($fileData === '') {
                            continue;
                        }

                        $fileDataB64 = base64_encode($fileData);
                        $fileDataB64 = chunk_split($fileDataB64, 76, "\r\n");

                        $rawMessage .= "--$boundary\r\n";
                        $rawMessage .= "Content-Type: $mimeType; name=\"" . addslashes($filename) . "\"\r\n";
                        $rawMessage .= "Content-Transfer-Encoding: base64\r\n";
                        $rawMessage .= "Content-Disposition: attachment; filename=\"" . addslashes($filename) . "\"\r\n\r\n";
                        $rawMessage .= $fileDataB64 . "\r\n";
                    }
                }

                // Đính kèm file người dùng upload
                foreach ($uploadedAttachments as $ua) {
                    $filename = $ua['filename'];
                    $mimeType = $ua['mimeType'] ?: 'application/octet-stream';
                    $fileData = $ua['data'];

                    $fileDataB64 = base64_encode($fileData);
                    $fileDataB64 = chunk_split($fileDataB64, 76, "\r\n");

                    $rawMessage .= "--$boundary\r\n";
                    $rawMessage .= "Content-Type: $mimeType; name=\"" . addslashes($filename) . "\"\r\n";
                    $rawMessage .= "Content-Transfer-Encoding: base64\r\n";
                    $rawMessage .= "Content-Disposition: attachment; filename=\"" . addslashes($filename) . "\"\r\n\r\n";
                    $rawMessage .= $fileDataB64 . "\r\n";
                }

                $rawMessage .= "--$boundary--";

            } else {
                // new / reply không có file: gửi text-only
                $rawMessage =
                    "To: $to\r\n" .
                    ($cc !== '' ? "Cc: $cc\r\n" : '') .
                    "Subject: $subject\r\n" .
                    "Content-Type: text/plain; charset=utf-8\r\n" .
                    "MIME-Version: 1.0\r\n\r\n" .
                    $body;
            }

            // Encode URL-safe cho Gmail API
            $raw = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

            $gmailMessage = new Google_Service_Gmail_Message();
            $gmailMessage->setRaw($raw);
            $service->users_messages->send('me', $gmailMessage);

            $success = 'Email sent successfully. / Đã gửi email thành công.';
            $to = $cc = $subject = $body = '';
            $mode = 'new';
            $origIdForForward = '';

        } catch (Throwable $e) {
            $error = 'Lỗi gửi email / Send error: ' . $e->getMessage();
        }
    }
}

?>

<div class="container-fluid mt-3">
  <div class="row mb-2">
    <div class="col-md-8">
      <h4 class="mb-0">
        <i class="fa fa-pencil"></i>
        <?php
          if     ($mode === 'reply')   echo 'Compose (Reply) / Soạn email (Trả lời)';
          elseif ($mode === 'forward') echo 'Compose (Forward) / Soạn email (Chuyển tiếp)';
          else                         echo 'Compose / Soạn email';
        ?>
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
    <?php if ($success): ?>
      <div class="alert alert-success mt-2">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger mt-2">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="mode" value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="orig_message_id" value="<?= htmlspecialchars($origIdForForward, ENT_QUOTES, 'UTF-8') ?>">

          <div class="form-group">
            <label for="to">To / Đến:</label>
            <input list="emailSuggestions"
                   type="text"
                   class="form-control form-control-sm"
                   id="to"
                   name="to"
                   value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="example@domain.com">
          </div>

          <div class="form-group">
            <label for="cc">Cc / Đồng gửi:</label>
            <input list="emailSuggestions"
                   type="text"
                   class="form-control form-control-sm"
                   id="cc"
                   name="cc"
                   value="<?= htmlspecialchars($cc, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="cc@example.com (optional)">
          </div>

          <datalist id="emailSuggestions">
            <?php foreach ($suggestedEmails as $email): ?>
              <option value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"></option>
            <?php endforeach; ?>
          </datalist>

          <div class="form-group">
            <label for="subject">Subject / Tiêu đề:</label>
            <input type="text" class="form-control form-control-sm" id="subject" name="subject"
                   value="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div class="form-group">
            <label for="body">Body / Nội dung:</label>
            <textarea class="form-control" id="body" name="body" rows="10"
                      placeholder="Type your message here / Nhập nội dung email tại đây"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <div class="form-group">
            <label for="attachments">Attachments / File đính kèm:</label>
            <input type="file" class="form-control-file" id="attachments" name="attachments[]" multiple>
            <small class="form-text text-muted">
              You can select multiple files. / Có thể chọn nhiều file cùng lúc.
            </small>
          </div>

          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa fa-send"></i> Send / Gửi
          </button>
          <a href="email_inbox.php" class="btn btn-light btn-sm">
            <i class="fa fa-times"></i> Cancel / Hủy
          </a>
        </form>
      </div>
    </div>

  <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
