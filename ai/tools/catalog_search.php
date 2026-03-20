<?php

require_once __DIR__.'/../../includes/init.php';

/**
 * Chuẩn hóa text:
 * - lowercase
 * - trim
 * - bỏ dấu tiếng Việt (an toàn, không dùng iconv)
 */
function normalize_text($text){

    // đảm bảo là string
    $text = (string)$text;

    // fix encoding lỗi
    if (!mb_detect_encoding($text, 'UTF-8', true)) {
        $text = mb_convert_encoding($text, 'UTF-8');
    }

    $text = mb_strtolower($text,'UTF-8');
    $text = trim($text);

    // bỏ dấu tiếng Việt
    $text = remove_vietnamese_accents($text);

    return $text;
}


/**
 * Bỏ dấu tiếng Việt (ổn định 100%)
 */
function remove_vietnamese_accents($str){

    $unicode = [
        'a'=>'àáạảãâầấậẩẫăằắặẳẵ',
        'e'=>'èéẹẻẽêềếệểễ',
        'i'=>'ìíịỉĩ',
        'o'=>'òóọỏõôồốộổỗơờớợởỡ',
        'u'=>'ùúụủũưừứựửữ',
        'y'=>'ỳýỵỷỹ',
        'd'=>'đ'
    ];

    foreach($unicode as $nonUnicode => $uni){
        $str = preg_replace("/[".$uni."]/u", $nonUnicode, $str);
    }

    return $str;
}


/**
 * Tìm category thông minh (fuzzy match)
 */
function find_category_smart($name){

    global $pdo;

    $input = normalize_text($name);

    $sql = "SELECT id,name,parent_id FROM categories";
    $stmt = $pdo->query($sql);

    $bestMatch = null;
    $bestScore = 0;

    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

        $dbName = normalize_text($row['name']);

        similar_text($input, $dbName, $percent);

        // boost nếu chứa chuỗi
        if(strpos($dbName, $input) !== false){
            $percent += 20;
        }

        if($percent > $bestScore){
            $bestScore = $percent;
            $bestMatch = $row;
        }
    }

    // ngưỡng match
    if($bestScore >= 60){
        return $bestMatch;
    }

    return null;
}


/**
 * Gợi ý danh mục gần đúng (top 5)
 */
function suggest_categories($name){

    global $pdo;

    $input = normalize_text($name);

    $sql = "SELECT id,name FROM categories";
    $stmt = $pdo->query($sql);

    $results = [];

    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

        $dbName = normalize_text($row['name']);

        similar_text($input, $dbName, $percent);

        // boost nếu chứa chuỗi
        if(strpos($dbName, $input) !== false){
            $percent += 20;
        }

        if($percent >= 40){
            $results[] = [
                "id" => $row['id'],
                "name" => $row['name'],
                "score" => $percent
            ];
        }
    }

    // sort giảm dần theo độ giống
    usort($results, function($a,$b){
        return $b['score'] <=> $a['score'];
    });

    return array_slice($results, 0, 5);
}