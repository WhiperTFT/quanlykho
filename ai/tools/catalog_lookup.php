<?php

require_once __DIR__.'/../../includes/init.php';

function find_category_by_name($name){

global $pdo;

$sql="SELECT id,name,parent_id
FROM categories
WHERE name LIKE :name
LIMIT 1";

$stmt=$pdo->prepare($sql);

$stmt->execute([
"name"=>"%$name%"
]);

return $stmt->fetch(PDO::FETCH_ASSOC);

}

function find_unit_by_name($name){

global $pdo;

$sql="SELECT id,name
FROM units
WHERE name LIKE :name
LIMIT 1";

$stmt=$pdo->prepare($sql);

$stmt->execute([
"name"=>"%$name%"
]);

return $stmt->fetch(PDO::FETCH_ASSOC);

}