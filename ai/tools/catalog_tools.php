<?php

require_once __DIR__.'/http_helper.php';
require_once __DIR__.'/catalog_lookup.php';

define("CATEGORY_API","http://localhost/quanlykho/process/category_handler.php");
define("PRODUCT_API","http://localhost/quanlykho/process/product_handler.php");

function ai_add_category($name,$parent=null){

$parent_id=null;

if($parent){
$parent_cat=find_category_by_name($parent);

if(!$parent_cat){
return [
"success"=>false,
"message"=>"Parent category not found"
];
}

$parent_id=$parent_cat['id'];
}

$data=[
"action"=>"add",
"name"=>$name,
"parent_id"=>$parent_id
];

return ai_call_api(CATEGORY_API,$data);

}

function ai_add_product($name,$category,$unit,$description=""){

$cat=find_category_smart($category);

if(!$cat){
return [
"success"=>false,
"message"=>"Category not found"
];
}

$u=find_unit_by_name($unit);

if(!$u){
return [
"success"=>false,
"message"=>"Unit not found"
];
}

$data=[
"action"=>"add",
"name"=>$name,
"category_id"=>$cat['id'],
"unit_id"=>$u['id'],
"description"=>$description
];

return ai_call_api(PRODUCT_API,$data);

}

function ai_delete_product($name){

global $pdo;

$sql="SELECT id FROM products
WHERE name LIKE :name
LIMIT 1";

$stmt=$pdo->prepare($sql);

$stmt->execute([
"name"=>"%$name%"
]);

$product=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$product){
return [
"success"=>false,
"message"=>"Product not found"
];
}

$data=[
"action"=>"delete",
"id"=>$product['id']
];

return ai_call_api(PRODUCT_API,$data);

}