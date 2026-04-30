<?php
include_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

if(!isset($_POST['pppID']) || !isset($_POST['value'])){
    echo json_encode(['success'=>false,'message'=>'Missing data']);
    exit;
}

$pppID = (int)$_POST['pppID'];
$value = (int)$_POST['value'];

if ($pppID <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$db = db_connect('storage');

$stmt = $db->prepare("
    UPDATE " . DB_NAMES['storage'] . ".ppp
        SET id_buy_doc = ?
    WHERE id = ?
");

$stmt->bind_param("ii", $value, $pppID);
$ok = $stmt->execute();

echo json_encode(['success' => $ok]);

$stmt->close();
$db->close();