<?php

include_once __DIR__.'/functions.php';

$db = db_connect('storage');

$id = (int)($_POST['id'] ?? 0);

if(
    !$id ||
    empty($_FILES['image']['tmp_name'])
){
    echo json_encode([
        'success' => false
    ]);
    exit;
}

$imageData =
    file_get_contents(
        $_FILES['image']['tmp_name']
    );

$stmt = $db->prepare("
    UPDATE nomenclature_types
    SET image = ?
    WHERE id = ?
");

$stmt->bind_param("bi", $null, $id);

$stmt->send_long_data(0, $imageData);

$ok = $stmt->execute();

echo json_encode([
    'success' => $ok
]);