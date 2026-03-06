<?php

ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

$response = [
    "success" => false,
    "message" => ""
];

try {

    if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        throw new Exception("Нямате достъп.");
    }

    if (!isset($_POST['id']) || !isset($_POST['name'])) {
        throw new Exception("Липсват данни.");
    }

    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $user = (int)$_SESSION['user_id'];

    if ($name === '') {
        throw new Exception("Името не може да е празно.");
    }

    require_once __DIR__ . '/../includes/functions.php';

    $db = db_connect('sod');

    $stmt = $db->prepare("
        UPDATE offices
        SET name = ?, updated_time = NOW(), updated_user = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception("SQL грешка.");
    }

    $stmt->bind_param("sii", $name, $user, $id);

    if (!$stmt->execute()) {
        throw new Exception("Грешка при запис.");
    }

    $response["success"] = true;
    $response["message"] = "Офисът беше записан успешно.";

    $stmt->close();
    $db->close();

} catch (Exception $e) {

    $response["success"] = false;
    $response["message"] = $e->getMessage();

}

ob_clean();
echo json_encode($response);
exit;