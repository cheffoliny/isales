<?php

include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$officeId = (int)($_POST['officeId'] ?? 0);
$km = (float)($_POST['km'] ?? 0);

if ($officeId <= 0 || $km <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid data'
    ]);
    exit;
}

$db = db_connect('sod');

$stmt = $db->prepare("
    UPDATE offices
    SET km_per_roadlist = ?
    WHERE id = ?
");

$stmt->bind_param("di", $km, $officeId);

$ok = $stmt->execute();

echo json_encode([
    'success' => $ok,
    'officeId' => $officeId,
    'km' => $km
]);

$stmt->close();
$db->close();