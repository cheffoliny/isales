<?php
include_once __DIR__.'/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || ($_SESSION['is_admin'] ?? 0) != 1) {
    echo json_encode([
        'success' => false,
        'error' => 'forbidden'
    ]);
    exit;
}

$db = db_connect('storage');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$is_new = isset($_POST['newp']) ? (int)$_POST['newp'] : 0;
$client = isset($_POST['client_price']) ? (float)$_POST['client_price'] : 0;
$sales = isset($_POST['sales_price']) ? (float)$_POST['sales_price'] : 0;
$promoNote = isset($_POST['promo_note']) ? trim((string)$_POST['promo_note']) : '';
$idType = isset($_POST['id_type']) ? (int)$_POST['id_type'] : 0;

if (!$id) {
    echo json_encode([
        'success' => false,
        'error' => 'missing_id'
    ]);
    exit;
}

$stmt = $db->prepare("
    UPDATE nomenclatures
    SET
        client_price = ?,
        sales_price = ?,
        promo_note = ?,
        is_new = ?,
        id_type = ?
    WHERE id = ?
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => $db->error
    ]);
    exit;
}

$stmt->bind_param(
    "ddsiii",
    $client,
    $sales,
    $promoNote,
    $is_new,
    $idType,
    $id
);

$ok = $stmt->execute();

echo json_encode([
    'success' => $ok,
    'error' => $ok ? null : $stmt->error
]);

$stmt->close();
exit;