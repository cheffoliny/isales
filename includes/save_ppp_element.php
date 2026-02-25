<?php
require_once '../core/init.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Няма достъп']);
    exit;
}

$idUser = (int)$_SESSION['user_id'];

$id_ppp = (int)($_POST['id_ppp'] ?? 0);
$id_nomenclature = (int)($_POST['id_nomenclature'] ?? 0);
$count = (float)($_POST['count'] ?? 0);
$single_price = (float)($_POST['single_price'] ?? 0);

if ($id_ppp <= 0 || $id_nomenclature <= 0 || $count <= 0) {
    echo json_encode([
        'success'=>false,
        'message'=>"Невалидни данни ppp:$id_ppp nom:$id_nomenclature count:$count"
    ]);
    exit;
}

$db = db_connect('storage');

/* ТРЯБВА ДА ИМАШ UNIQUE KEY */
# ALTER TABLE ppp_elements ADD UNIQUE KEY uniq (id_ppp,id_nomenclature);

$sql = "
INSERT INTO ppp_elements
(id_ppp,id_nomenclature,`count`,single_price,updated_time,updated_user)
VALUES (?,?,?,?,NOW(),?)
ON DUPLICATE KEY UPDATE
`count`=VALUES(`count`),
single_price=VALUES(single_price),
updated_time=NOW(),
updated_user=VALUES(updated_user)
";

$stmt = $db->prepare($sql);
$stmt->bind_param("iiddi",
    $id_ppp,
    $id_nomenclature,
    $count,
    $single_price,
    $idUser
);

if(!$stmt->execute()){
    echo json_encode(['success'=>false,'message'=>$stmt->error]);
    exit;
}

echo json_encode(['success'=>true]);