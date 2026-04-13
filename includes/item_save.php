<?php
include_once __DIR__.'/functions.php';

header('Content-Type: application/json');

$db = db_connect('storage');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$client = isset($_POST['client_price']) ? (float)$_POST['client_price'] : 0;
$sales = isset($_POST['sales_price']) ? (float)$_POST['sales_price'] : 0;

if(!$id){
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $db->prepare("
    UPDATE nomenclatures
    SET client_price = ?, sales_price = ?
    WHERE id = ?
");

if(!$stmt){
    echo json_encode(['success' => false]);
    exit;
}

$stmt->bind_param("ddi", $client, $sales, $id);

$ok = $stmt->execute();

echo json_encode([
    'success' => $ok ? true : false
]);

$stmt->close();
exit;