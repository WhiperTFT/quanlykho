<?php

require_once __DIR__.'/catalog_tools.php';

function ai_router($command){

switch($command['tool']){

case "add_category":

return ai_add_category(
$command['name'],
$command['parent'] ?? null
);

case "add_product":

return ai_add_product(
$command['name'],
$command['category'],
$command['unit'],
$command['description'] ?? ""
);

case "delete_product":

return ai_delete_product(
$command['name']
);

default:

return [
"success"=>false,
"message"=>"Unknown tool"
];

}

}