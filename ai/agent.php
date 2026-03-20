<?php

require_once __DIR__.'/tools/catalog_tools.php';
require_once __DIR__.'/tools/catalog_search.php';

/**
 * Parse câu nhanh: "thêm sản phẩm A trong Nhựa ABS"
 */
function parse_product_command($text){

    $result = [
        "name" => null,
        "category" => null
    ];

    // thêm sản phẩm A trong Nhựa ABS
    if(preg_match('/thêm sản phẩm (.+?) trong (.+)/u', $text, $m)){
        $result['name'] = trim($m[1]);
        $result['category'] = trim($m[2]);
        return $result;
    }

    return null;
}


/**
 * AI Agent chính
 */
function ai_agent($text){

    global $pdo;

    $text_raw = trim($text);
    $text = mb_strtolower($text_raw, 'UTF-8');

    if(!isset($_SESSION['ai_context'])){
        $_SESSION['ai_context'] = null;
    }

    // =========================
    // 🚀 QUICK MODE
    // =========================
    if(strpos($text,"thêm sản phẩm") !== false){

        $parsed = parse_product_command($text_raw);

        if($parsed && $parsed['name'] && $parsed['category']){

            $cat = find_category_smart($parsed['category']);

            if(!$cat){

                $suggest = suggest_categories($parsed['category']);

                $msg = "❌ Không tìm thấy danh mục\n";

                if($suggest){

                    $_SESSION['ai_context'] = [
                        "step"=>"choose_category",
                        "data"=>[
                            "name"=>$parsed['name']
                        ],
                        "category_suggest"=>$suggest
                    ];

                    $msg .= "👉 Bạn có thể chọn:\n";

                    foreach($suggest as $i=>$s){
                        $msg .= ($i+1).". ".$s['name']."\n";
                    }

                    $msg .= "👉 Nhập số để chọn";

                }

                return ["message"=>$msg];
            }

            // mặc định unit
            $unit = find_unit_by_name("kg");

            $res = ai_add_product(
                $parsed['name'],
                $cat['name'],
                $unit ? $unit['name'] : "kg",
                ""
            );

            if(!$res['success']){
                return ["message"=>"❌ Lỗi tạo sản phẩm"];
            }

            $_SESSION['ai_context'] = [
                "step"=>"ask_description",
                "product_id"=>$res['product_id']
            ];

            return [
                "message"=>"✔ Đã tạo sản phẩm ".$parsed['name']." trong ".$cat['name']."\n👉 Nhập mô tả",
                "product_id"=>$res['product_id']
            ];
        }

        // fallback → step mode
        $_SESSION['ai_context'] = [
            "step"=>"ask_name",
            "data"=>[]
        ];

        return ["message"=>"👉 Nhập tên sản phẩm"];
    }

    // =========================
    // 📌 STEP MODE
    // =========================
    if(!$_SESSION['ai_context']){
        return ["message"=>"❓ Gõ 'thêm sản phẩm' để bắt đầu"];
    }

    $ctx = $_SESSION['ai_context'];

    // ===== STEP: NAME =====
    if($ctx['step'] == "ask_name"){

        $_SESSION['ai_context']['data']['name'] = $text_raw;
        $_SESSION['ai_context']['step'] = "ask_category";

        return ["message"=>"👉 Nhập danh mục"];
    }

    // ===== STEP: CATEGORY =====
    if($ctx['step'] == "ask_category"){

        // chọn bằng số
        if(is_numeric($text) && isset($ctx['category_suggest'])){

            $index = (int)$text - 1;
            $list = $ctx['category_suggest'];

            if(isset($list[$index])){

                $_SESSION['ai_context']['data']['category'] = $list[$index];
                unset($_SESSION['ai_context']['category_suggest']);
                $_SESSION['ai_context']['step'] = "ask_unit";

                return [
                    "message"=>"✔ Đã chọn: ".$list[$index]['name']."\n👉 Nhập đơn vị"
                ];
            }
        }

        $cat = find_category_smart($text_raw);

        if(!$cat){

            $suggest = suggest_categories($text_raw);

            $msg = "❌ Không tìm thấy danh mục\n";

            if($suggest){

                $_SESSION['ai_context']['category_suggest'] = $suggest;

                $msg .= "👉 Bạn có thể chọn:\n";

                foreach($suggest as $i=>$s){
                    $msg .= ($i+1).". ".$s['name']."\n";
                }

                $msg .= "👉 Nhập số để chọn";
            }

            return ["message"=>$msg];
        }

        $_SESSION['ai_context']['data']['category'] = $cat;
        $_SESSION['ai_context']['step'] = "ask_unit";

        return ["message"=>"👉 Nhập đơn vị"];
    }

    // ===== STEP: UNIT =====
    if($ctx['step'] == "ask_unit"){

        $unit = find_unit_by_name($text_raw);

        if(!$unit){
            return ["message"=>"❌ Không tìm thấy đơn vị"];
        }

        $_SESSION['ai_context']['data']['unit'] = $unit;
        $_SESSION['ai_context']['step'] = "ask_description";

        return ["message"=>"👉 Nhập mô tả"];
    }

    // ===== STEP: DESCRIPTION =====
    if($ctx['step'] == "ask_description"){

        $data = $ctx['data'] ?? [];

        $name = $data['name'] ?? null;
        $category = $data['category']['name'] ?? null;
        $unit = $data['unit']['name'] ?? "kg";

        // nếu chưa tạo (step mode)
        if(empty($ctx['product_id'])){

            $res = ai_add_product($name,$category,$unit,$text_raw);

            if(!$res['success']){
                return ["message"=>"❌ Lỗi tạo sản phẩm"];
            }

            $product_id = $res['product_id'];

        }else{
            // update mô tả
            $product_id = $ctx['product_id'];

            $pdo->prepare("
                UPDATE products SET description=?
                WHERE id=?
            ")->execute([$text_raw,$product_id]);
        }

        $_SESSION['ai_context'] = [
            "step"=>"ask_upload",
            "product_id"=>$product_id
        ];

        return [
            "message"=>"✔ Đã lưu mô tả\n👉 Upload file?\n1. Có\n2. Không",
            "product_id"=>$product_id
        ];
    }

    // ===== STEP: UPLOAD =====
    if($ctx['step'] == "ask_upload"){

        $product_id = $ctx['product_id'];

        if($text == "1" || str_contains($text,"có")){

            $_SESSION['ai_context'] = null;

            return [
                "message"=>"👉 Đang mở form upload...",
                "open_modal"=>true,
                "product_id"=>$product_id
            ];
        }

        if($text == "2" || str_contains($text,"không")){

            $_SESSION['ai_context'] = null;

            return ["message"=>"✔ Hoàn tất"];
        }
    }

    return ["message"=>"❓ Không hiểu lệnh"];
}