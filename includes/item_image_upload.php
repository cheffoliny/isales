<?php
include_once __DIR__.'/functions.php';
$db=db_connect('storage');

$id=(int)($_POST['id']??0);
$imageData = file_get_contents($_FILES['image']['tmp_name'] ?? '');

if(!$id || !$imageData) {
    echo json_encode(['success'=>false]);
    exit;
}

$stmt=$db->prepare("UPDATE nomenclatures SET image=? WHERE id=?");
$stmt->bind_param("bi",$imageData,$id);
$stmt->send_long_data(0,$imageData);
$ok=$stmt->execute();
echo json_encode(['success'=>$ok]);