<?php
include_once __DIR__.'/functions.php';
$db=db_connect('storage');

$id=(int)($_POST['id']??0);
$stmt=$db->prepare("UPDATE nomenclatures SET image=NULL WHERE id=?");
$stmt->bind_param("i",$id);
$ok=$stmt->execute();
echo json_encode(['success'=>$ok]);