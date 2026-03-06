<?php

include_once __DIR__.'/functions.php';

header('Content-Type: application/json');

if(empty($_SESSION['user_id'])){
    echo json_encode(['success'=>false]);
    exit;
}

$id=(int)$_POST['id'];
$name=trim($_POST['name']);
$office=(int)$_POST['office'];
$info=trim($_POST['info']);

$db=db_connect('sod');

$stmt=$db->prepare("
UPDATE objects
SET
name=?,
id_office=?,
operativ_info=?
WHERE id=?
");

$stmt->bind_param("sisi",$name,$office,$info,$id);

$ok=$stmt->execute();

echo json_encode([
    "success"=>$ok
]);