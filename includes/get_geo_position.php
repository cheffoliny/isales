<?php

include_once __DIR__ . '/../core/init.php';
include_once __DIR__ . '/../config/config.php';

if(empty($_SESSION['user_id'])){
    echo json_encode(['success'=>false]);
    exit;
}

$idUser = intval($_GET['idUser'] ?? 0);
if ($idUser === 0) {
    exit('');
}

$stmt = $db_sod->prepare("
    SELECT geo_data
    FROM work_card_geo_log
    WHERE id_person = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param('i', $idUser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo $row['geo_data'] ?? '';
