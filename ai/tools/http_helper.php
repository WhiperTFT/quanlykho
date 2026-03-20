<?php

function ai_call_api($url,$data){

$ch=curl_init();

curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POST,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,$data);

$res=curl_exec($ch);

if(curl_errno($ch)){
    return [
        "success"=>false,
        "message"=>curl_error($ch)
    ];
}

curl_close($ch);

return json_decode($res,true);

}