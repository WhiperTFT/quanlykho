<?php
// File: pxk_print.php (match layout "PXK mẫu ok.pdf")
// - A4 chứa 2 bản A5 xếp dọc, không tách trang
// - Header text block: Đơn vị / ĐC / Tel / Website / Email
// - Tiêu đề: PHIẾU XUẤT KHO + "Ngày dd tháng mm năm yyyy"
// - Dòng "Số: ..."
// - Khối: Tên đơn vị nhận hàng / Người liên hệ / Địa chỉ
// - Bảng hàng hóa (có cột ĐƠN GIÁ, THÀNH TIỀN theo form mẫu; nếu DB chưa có thì để trống)
// - Dòng lưu ý cố định
// - Chữ ký: 1 dòng tiêu đề / 1 dòng "(Ký, họ tên)" (3 cột chia đều)

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php'; // $pdo có sẵn

$pxk_id = isset($_GET['pxk_id']) ? (int)$_GET['pxk_id'] : (int)($_GET['id'] ?? 0);
if ($pxk_id <= 0) { die('Thiếu pxk_id'); }

// ---- Company info (mở rộng thêm website/email nếu có) ----
$company = [
    'company_name' => '',
    'address'      => '',
    'phone'        => '',
    'tax_code'     => '',
    'logo_path'    => '',
    'website'      => '',
    'email'        => '',
];
try {
    // Nếu bảng của bạn chưa có website/email thì SELECT vẫn ok, field rỗng
    $rs = $pdo->query("SELECT company_name, address, phone, tax_code, logo_path, website, email FROM company_settings LIMIT 1");
    if ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
        $company = array_merge($company, $row);
    }
} catch (Throwable $e) {
    // fallback
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- PXK master + items ----
// Gợi ý các cột ở pxk_master: id, pxk_number, pxk_date, receiver_name, receiver_address, contact_name, note, created_by
$pxk = null; 
$items = [];
try {
    $st = $pdo->prepare("
        SELECT m.*, u.username AS created_by_name
        FROM pxk_master m
        LEFT JOIN users u ON u.id = m.created_by
        WHERE m.id = ? LIMIT 1
    ");
    $st->execute([$pxk_id]);
    $pxk = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pxk) die('Không tìm thấy PXK');

    // Gợi ý các cột ở pxk_items: product_name, unit_name, qty, unit_price(optional), line_total(optional), note
    $st2 = $pdo->prepare("
        SELECT i.*, p.code AS product_code
        FROM pxk_items i
        LEFT JOIN products p ON p.id = i.product_id
        WHERE i.pxk_id = ?
        ORDER BY i.id ASC
    ");
    $st2->execute([$pxk_id]);
    $items = $st2->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('Lỗi dữ liệu PXK');
}

$pxk_number = $pxk['pxk_number'] ?? ('PXK-' . $pxk_id);

// Định dạng "Ngày dd tháng mm năm yyyy" theo file mẫu
$ts = !empty($pxk['pxk_date']) ? strtotime($pxk['pxk_date']) : time();
$ngay = (int)date('d', $ts);
$thang = (int)date('m', $ts);
$nam = (int)date('Y', $ts);
$date_text = "Ngày {$ngay} tháng {$thang} năm {$nam}";

// Render 1 bản A5 (dùng 2 lần)
function render_copy($company, $pxk, $items, $pxk_number, $date_text) {
?>
    <section class="a5">
        <!-- BLOCK THÔNG TIN CÔNG TY (text), giống form "PXK mẫu ok.pdf" -->
        <div class="company-block">
            <div><strong>Đơn vị:</strong> <?= h($company['company_name']) ?></div>
            <div><strong>ĐC:</strong> <?= h($company['address']) ?></div>
            <div class="two-cols">
                <div><strong>Tel:</strong> <?= h($company['phone']) ?></div>
                <div><strong>Website:</strong> <?= h($company['website']) ?></div>
                <div><strong>Email:</strong> <?= h($company['email']) ?></div>
            </div>
        </div>

        <!-- TIÊU ĐỀ & NGÀY -->
        <div class="title-row">
            <div class="title">PHIẾU XUẤT KHO</div>
            <div class="date"><?= h($date_text) ?></div>
        </div>

        <!-- SỐ PHIẾU -->
        <div class="no-row">
            <span><strong>Số:</strong> <?= h($pxk_number) ?></span>
        </div>

        <!-- BÊN NHẬN & THÔNG TIN LIÊN QUAN -->
        <div class="info-rows">
            <div><strong>Tên đơn vị nhận hàng:</strong> <?= h($pxk['receiver_name'] ?? '') ?></div>
            <div><strong>Người liên hệ:</strong> <?= h($pxk['contact_name'] ?? '') ?></div>
            <div><strong>Địa chỉ:</strong> <?= h($pxk['receiver_address'] ?? '') ?></div>
        </div>

        <!-- BẢNG HÀNG HÓA (theo mẫu: có ĐƠN GIÁ / THÀNH TIỀN; nếu thiếu dữ liệu thì để trống) -->
        <table class="items">
            <thead>
                <tr>
                    <th style="width:38px">STT</th>
                    <th>TÊN HÀNG HOÁ</th>
                    <th style="width:70px">ĐVT</th>
                    <th style="width:100px">SỐ LƯỢNG</th>
                    <th style="width:110px">ĐƠN GIÁ</th>
                    <th style="width:120px">THÀNH TIỀN</th>
                    <th>GHI CHÚ</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($items)): $i=1; foreach ($items as $row): 
                $qty  = $row['qty'] ?? null;
                $price = $row['unit_price'] ?? null;       // nếu DB không có thì = null
                $line  = $row['line_total'] ?? null;       // nếu DB không có thì = null
            ?>
                <tr>
                    <td class="center"><?= $i++ ?></td>
                    <td><?= h($row['product_name'] ?? '') ?></td>
                    <td class="center"><?= h($row['unit_name'] ?? '') ?></td>
                    <td class="num"><?= $qty !== null ? h((string)$qty) : '' ?></td>
                    <td class="num"><?= $price !== null ? h((string)$price) : '' ?></td>
                    <td class="num"><?= $line !== null ? h((string)$line) : '' ?></td>
                    <td><?= h($row['note'] ?? '') ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" class="center">Không có dữ liệu hàng hoá.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- DÒNG LƯU Ý CỐ ĐỊNH (giống file mẫu) -->
        <div class="foot-note">
            <em>Ghi chú: Khi đại diện bên mua đã ký nhận hàng, bên mua sẽ không khiếu nại về số lượng và mã hàng đã được giao</em>
        </div>

        <!-- CHỮ KÝ: 1 dòng tiêu đề + 1 dòng "(Ký, họ tên)" -->
        <div class="signatures">
            <div class="sign-row titles">
                <div class="cell">Người lập phiếu</div>
                <div class="cell">Người nhận hàng</div>
                <div class="cell">Thủ kho</div>
            </div>
            <div class="sign-row note">
                <div class="cell">(Ký, họ tên)</div>
                <div class="cell">(Ký, họ tên)</div>
                <div class="cell">(Ký, họ tên)</div>
            </div>
            <div class="sign-space"></div>
        </div>
    </section>
<?php
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>In PXK - <?= h($pxk_number) ?></title>
<style>
    /* ===== KÍCH THƯỚC IN =====
       - A4 cao 297mm; margin trên + dưới = 10mm -> vùng in 277mm.
       - GAP giữa 2 A5 = 8mm. Mỗi A5 cao = (277mm - 8mm)/2 = 134.5mm.
    */
    @page { size: A4 portrait; margin: 10mm; }
    html, body { margin:0; padding:0; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color:#000; font-size:12px; }
    * { box-sizing: border-box; }

    .a4 {
        height: 277mm;
        display: grid;
        grid-template-rows: 1fr 1fr;
        row-gap: 8mm;
        break-inside: avoid;
        page-break-inside: avoid;
        overflow: hidden;
    }
    .a5 {
        height: calc((277mm - 8mm) / 2);
        padding: 6mm 7mm;
        border: 1px dashed #999;
        break-inside: avoid;
        page-break-inside: avoid;
        display: block;
    }

    /* ===== COMPANY BLOCK (theo mẫu) ===== */
    .company-block { margin-bottom: 6px; line-height: 1.35; }
    .company-block .two-cols {
        display: grid;
        grid-template-columns: auto auto auto; /* Tel | Website | Email */
        column-gap: 16px;
        row-gap: 2px;
        margin-top: 2px;
    }

    /* ===== TITLE ROW ===== */
    .title-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        align-items: end;
        margin: 4px 0 6px 0;
    }
    .title-row .title {
        font-weight: 700;
        font-size: 16px;
        text-transform: uppercase;
        text-align: left;
    }
    .title-row .date { text-align: right; }

    /* ===== SỐ PHIẾU ===== */
    .no-row { margin: 2px 0 6px 0; }

    /* ===== INFO ROWS ===== */
    .info-rows { margin: 6px 0 8px 0; line-height: 1.4; }
    .info-rows strong { min-width: 160px; display: inline-block; }

    /* ===== BẢNG HÀNG HOÁ ===== */
    table.items { width: 100%; border-collapse: collapse; }
    table.items th, table.items td { border: 1px solid #000; padding: 4px 6px; font-size: 12px; }
    table.items th { text-align: center; font-weight: 700; background: #f4f4f4; white-space: nowrap; }
    td.center, th.center { text-align: center; white-space: nowrap; }
    td.num, th.num { text-align: right; white-space: nowrap; }

    /* ===== FOOT NOTE ===== */
    .foot-note { margin-top: 6px; }

    /* ===== CHỮ KÝ ===== */
    .signatures { margin-top: 8px; }
    .sign-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        column-gap: 12px;
        text-align: center;
        white-space: nowrap;
        align-items: center;
    }
    .sign-row.titles .cell { font-weight: 600; }
    .sign-row.note .cell { font-style: italic; }
    .sign-space { height: 55px; }

    @media print {
        .a4, .a5 { break-inside: avoid; page-break-inside: avoid; }
        .a5 { border-color: #ccc; }
    }
</style>
</head>
<body>
    <div class="a4">
        <?php render_copy($company, $pxk, $items, $pxk_number, $date_text); ?>
        <?php render_copy($company, $pxk, $items, $pxk_number, $date_text); ?>
    </div>
</body>
</html>
