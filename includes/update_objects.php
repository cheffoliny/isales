<?php
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$id   = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$info = trim($_POST['info'] ?? '');
$lat  = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng  = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

// JSON string от JS
$offices_json = $_POST['offices_ids'] ?? '[]';
$offices = json_decode($offices_json, true);

if(!is_array($offices)){
    $offices = [];
}

$offices = array_map('intval', $offices);
$offices_json = json_encode($offices, JSON_UNESCAPED_UNICODE);

// само name задължително
if(!$id || !$name){
    echo json_encode(['success' => false]);
    exit;
}

// за backward compatibility
$id_office = count($offices) ? $offices[0] : null;

$db = db_connect('sod');

$stmt = $db->prepare("
UPDATE objects SET
    name = ?,
    operativ_info = ?,
    offices_ids = ?,
    id_office = ?,
    geo_lat = ?,
    geo_lan = ?
WHERE id = ?
");

$stmt->bind_param(
    "sssiddi",
    $name,
    $info,
    $offices_json,
    $id_office,
    $lat,
    $lng,
    $id
);

if($stmt->execute()){
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error'=>$stmt->error]);
}

$stmt->close();
$db->close();