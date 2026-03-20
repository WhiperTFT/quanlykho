<?php
session_start();

ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__.'/agent.php';

$text = $_POST['msg'] ?? '';

$res = ai_agent($text);

// tránh null
if(!$res){
    $res = ["message"=>"❌ Lỗi hệ thống"];
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);