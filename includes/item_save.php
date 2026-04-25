<?php
include_once __DIR__.'/functions.php';

header('Content-Type: application/json');

$db = db_connect('storage');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$client = isset($_POST['client_price']) ? (float)$_POST['client_price'] : 0;
$sales = isset($_POST['sales_price']) ? (float)$_POST['sales_price'] : 0;
$promoNote = isset($_POST['promo_note']) ? $_POST['promo_note'] : '';

if(!$id){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $db->prepare("
    UPDATE nomenclatures
    SET client_price = ?, sales_price = ?, promo_note = ?
    WHERE id = ?
");

if(!$stmt){
    echo json_encode(['success' => false]);
    exit;
}

$stmt->bind_param("ddsi", $client, $sales, $promoNote, $id);

$ok = $stmt->execute();

echo json_encode([
    'success' => $ok ? true : false
]);

$stmt->close();
exit;