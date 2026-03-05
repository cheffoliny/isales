<?php
include_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if(empty($_POST['pppID']) || empty($_POST['status'])){
    echo json_encode(['success'=>false,'message'=>'Missing data']);
    exit;
}

$pppID = (int)$_POST['pppID'];
$status = trim($_POST['status']);

$allowed = ['open','wait','confirm','cancel'];

if(!in_array($status,$allowed)){
    echo json_encode(['success'=>false,'message'=>'Invalid status']);
    exit;
}

$success = update_ppp_status($pppID, $status, $_SESSION['user_id']);
echo json_encode(['success'=>$success]);