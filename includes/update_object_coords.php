<?php

include_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

/* ================= SESSION CHECK ================= */

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Нямате достъп.'
    ]);
    exit;
}

/* ================= INPUT ================= */

$id  = isset($_POST['id'])  ? (int)$_POST['id']  : 0;
$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : 0;
$lan = isset($_POST['lan']) ? (float)$_POST['lan'] : 0;

/* ================= VALIDATION ================= */

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Невалиден ID.'
    ]);
    exit;
}

if ($lat == 0 || $lan == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Невалидни координати.'
    ]);
    exit;
}

/* ================= DB ================= */

$db = db_connect('sod');

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'DB връзката е неуспешна.'
    ]);
    exit;
}

/* ================= QUERY ================= */

$stmt = $db->prepare("
    UPDATE objects
    SET geo_lat = ?, geo_lan = ?
    WHERE id = ?
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Prepare error: ' . $db->error
    ]);
    exit;
}

/* ================= EXECUTE ================= */

$stmt->bind_param("ddi", $lat, $lan, $id);

$ok = $stmt->execute();

/* ================= RESPONSE ================= */

if ($ok) {

    echo json_encode([
        "success" => true,
        "message" => "Координатите са записани успешно.",
        "id" => $id,
        "lat" => $lat,
        "lan" => $lan
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Грешка при запис: " . $stmt->error
    ]);
}

/* ================= CLEANUP ================= */

$stmt->close();
$db->close();