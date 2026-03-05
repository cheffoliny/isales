<?php
include_once __DIR__.'/functions.php';

$db=db_connect('storage');

$id=(int)$_POST['id'];

$client=(float)$_POST['client_price'];
$sales=(float)$_POST['sales_price'];
$calc=(int)$_POST['is_calc'];

$stmt=$db->prepare("

UPDATE nomenclatures
SET
client_price=?,
sales_price=?,
is_calc=?
WHERE id=?

");

$stmt->bind_param("ddii",$client,$sales,$calc,$id);

$ok=$stmt->execute();

echo json_encode(['success'=>$ok]);