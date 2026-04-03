<?php
/**
 * Chuyển đổi số thành chữ (tiếng Việt) - Phiên bản chuẩn xác cao
 */
function read_number_vn($number) {
    if ($number == 0) return 'Không đồng';
    if ($number < 0) return 'Âm ' . read_number_vn(abs($number));

    $units = array('', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ');
    $digits = array('không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín');

    $number = strval($number);
    $len = strlen($number);
    if ($len == 0) return "";

    // Chia số thành các nhóm 3 chữ số từ phải sang trái
    $groups = array();
    for ($i = $len; $i > 0; $i -= 3) {
        $start = max(0, $i - 3);
        $groups[] = substr($number, $start, $i - $start);
    }

    $rs = "";
    for ($i = 0; $i < count($groups); $i++) {
        $group_val = intval($groups[$i]);
        if ($group_val > 0 || ($i == 0 && count($groups) == 1)) {
            $group_text = read_group_3($groups[$i], $i < count($groups) - 1);
            $rs = $group_text . ($units[$i] ? ' ' . $units[$i] : '') . ' ' . $rs;
        } else if ($i > 0 && $i < count($groups) - 1) {
            // Trường hợp 000 ở giữa (ví dụ 1,000,005)
        }
    }

    $rs = trim($rs);
    // Viết hoa chữ cái đầu
    $rs = mb_strtoupper(mb_substr($rs, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($rs, 1, null, 'UTF-8');
    return $rs . " đồng";
}

function read_group_3($group, $showZeroHundred = true) {
    $digits = array('không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín');
    $group = str_pad($group, 3, '0', STR_PAD_LEFT);
    $h = intval($group[0]);
    $t = intval($group[1]);
    $u = intval($group[2]);

    $res = "";
    if ($h > 0 || $showZeroHundred) {
        $res .= $digits[$h] . " trăm ";
    }

    if ($t == 0) {
        if ($u > 0 && ($h > 0 || $showZeroHundred)) $res .= "lẻ ";
    } else if ($t == 1) {
        $res .= "mười ";
    } else {
        $res .= $digits[$t] . " mươi ";
    }

    if ($u > 0) {
        if ($u == 1 && $t > 1) $res .= "mốt";
        else if ($u == 5 && $t > 0) $res .= "lăm";
        else $res .= $digits[$u];
    }

    return trim($res);
}
?>
