<?php
include_once __DIR__.'/functions.php';

$db=db_connect('storage');

$id=(int)$_POST['id'];

if(empty($_FILES['image'])){
    echo json_encode(['success'=>false]);
    exit;
}

$data=file_get_contents($_FILES['image']['tmp_name']);

$stmt=$db->prepare("
UPDATE nomenclatures
SET image=?
WHERE id=?
");

$stmt->bind_param("bi",$null,$id);

$null=NULL;

$stmt->send_long_data(0,$data);

$ok=$stmt->execute();

echo json_encode(['success'=>$ok]);