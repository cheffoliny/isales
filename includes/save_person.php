<?php

ob_start();

include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Нямате достъп'
    ]);
    exit;
}

$db = db_connect('personnel');

$id     = (int)($_POST['id'] ?? 0);
$name   = trim($_POST['fname'] ?? '');
$lname  = trim($_POST['lname'] ?? '');
$status = trim($_POST['status'] ?? 'active');

if ($name === '' || $lname === '') {

    echo json_encode([
        'success' => false,
        'message' => 'Попълнете всички полета'
    ]);

    exit;
}

/* ===============================
   INSERT
=============================== */
if ($id <= 0) {

    $stmt = $db->prepare("
        INSERT INTO personnel
            (fname, lname, status)
        VALUES
            (?, ?, ?)
    ");

    $stmt->bind_param(
        "sss",
        $name,
        $lname,
        $status
    );

    $ok = $stmt->execute();

    $newId = $stmt->insert_id;

    $stmt->close();

    echo json_encode([
        'success' => $ok,
        'id' => $newId,
        'mode' => 'insert'
    ]);

    exit;
}

/* ===============================
   UPDATE
=============================== */
$stmt = $db->prepare("
    UPDATE personnel
    SET
        fname = ?,
        lname = ?,
        status = ?
    WHERE id = ?
");

$stmt->bind_param(
    "sssi",
    $name,
    $lname,
    $status,
    $id
);

$ok = $stmt->execute();

$stmt->close();

ob_clean();

echo json_encode([
    'success' => $ok,
    'id' => $id,
    'mode' => ($id > 0 ? 'update' : 'insert')
]);

exit;