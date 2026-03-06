<?php

include_once __DIR__.'/functions.php';

header('Content-Type: application/json');

if(empty($_SESSION['user_id'])){
    echo json_encode(['success'=>false]);
    exit;
}

$id=(int)$_POST['id'];
$lat=(float)$_POST['lat'];
$lan=(float)$_POST['lan'];

$db=db_connect('sod');

$stmt=$db->prepare("
UPDATE objects
SET geo_lat=?, geo_lan=?
WHERE id=?
");

$stmt->bind_param("ddi",$lat,$lan,$id);

$ok=$stmt->execute();

echo json_encode([
    "success"=>$ok
]);