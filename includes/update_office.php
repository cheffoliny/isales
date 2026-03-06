<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    echo json_encode(['success'=>false,'message'=>'Нямате достъп.']);
    exit;
}

if (empty($_POST['id']) || empty($_POST['name'])) {
    echo json_encode(['success'=>false,'message'=>'Липсват данни.']);
    exit;
}

$id = (int)$_POST['id'];
$name = trim($_POST['name']);
$user = (int)$_SESSION['user_id'];

if($name===''){
    echo json_encode(['success'=>false,'message'=>'Името не може да е празно.']);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

$db = db_connect('sod');

$stmt = $db->prepare("UPDATE offices SET name=?, updated_time=NOW(), updated_user=? WHERE id=?");
if(!$stmt){
    echo json_encode(['success'=>false,'message'=>'Грешка при подготовка на заявката.']);
    exit;
}

$stmt->bind_param("sii", $name, $user, $id);

if($stmt->execute()){
    echo json_encode(['success'=>true]);
}else{
    echo json_encode(['success'=>false,'message'=>'Грешка при запис.']);
}

$stmt->close();
$db->close();