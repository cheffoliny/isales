<?php

define('INCLUDE_CHECK', true);
require_once '../session_init.php';
require_once '../config.php';

if (!$_SESSION['user_id']) {
    http_response_code(403);
    exit('Access denied.');
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
