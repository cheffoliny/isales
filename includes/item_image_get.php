<?php
include_once __DIR__.'/functions.php';
$db=db_connect('storage');

$id=(int)($_GET['id']??0);
$stmt=$db->prepare("SELECT image FROM nomenclatures WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$res=$stmt->get_result();
$row=$res->fetch_assoc();

if(!$row || !$row['image']){
    http_response_code(404);
    exit;
}

header("Content-Type: image/jpeg");
echo $row['image'];