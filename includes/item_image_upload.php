<?php
include_once __DIR__.'/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || ($_SESSION['is_admin'] ?? 0) != 1) {
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$db = db_connect('storage');

$id = (int)($_POST['id'] ?? 0);

if (!$id || empty($_FILES['image']['tmp_name'])) {
    echo json_encode(['success' => false, 'error' => 'Липсва артикул или снимка']);
    exit;
}

$imageData = file_get_contents($_FILES['image']['tmp_name']);

if (!$imageData) {
    echo json_encode(['success' => false, 'error' => 'Невалиден файл']);
    exit;
}

$stmt = $db->prepare("UPDATE nomenclatures SET image=? WHERE id=?");

$null = null;
$stmt->bind_param("bi", $null, $id);
$stmt->send_long_data(0, $imageData);

$ok = $stmt->execute();

echo json_encode(['success' => $ok]);