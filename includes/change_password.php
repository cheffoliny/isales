<?php

header('Content-Type: application/json');

include_once __DIR__ . '/../includes/functions.php';

/* ===============================
   AUTH
=============================== */
if (empty($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Нямате достъп'
    ]);

    exit;
}

/* ===============================
   DB
=============================== */
$db = db_connect('system');

$db->set_charset("utf8mb4");

/* ===============================
   DATA
=============================== */
$userId = (int)$_SESSION['user_id'];

$currentPassword = trim($_POST['current_password'] ?? '');
$newPassword     = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

/* ===============================
   VALIDATION
=============================== */
if (
    $currentPassword === '' ||
    $newPassword === '' ||
    $confirmPassword === ''
) {

    echo json_encode([
        'success' => false,
        'message' => 'Попълнете всички полета'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| PASSWORD LENGTH
|--------------------------------------------------------------------------
*/
if (mb_strlen($newPassword) < 6) {

    echo json_encode([
        'success' => false,
        'message' => 'Паролата трябва да е поне 6 символа'
    ]);

    exit;
}

if ($newPassword !== $confirmPassword) {

    echo json_encode([
        'success' => false,
        'message' => 'Паролите не съвпадат'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| SAME PASSWORD CHECK
|--------------------------------------------------------------------------
*/
if ($currentPassword === $newPassword) {

    echo json_encode([
        'success' => false,
        'message' => 'Новата парола трябва да е различна'
    ]);

    exit;
}

/* ===============================
   LOAD USER
=============================== */
$stmt = $db->prepare("
    SELECT password
    FROM access_account
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {

    echo json_encode([
        'success' => false,
        'message' => 'Потребителят не е намерен'
    ]);

    exit;
}

/* ===============================
   VERIFY CURRENT PASSWORD
=============================== */
$isValid = password_verify(
    $currentPassword,
    $user['password']
);

if (!$isValid) {

    echo json_encode([
        'success' => false,
        'message' => 'Текущата парола е грешна'
    ]);

    exit;
}

/* ===============================
   HASH NEW PASSWORD
=============================== */
$newHash = password_hash(
    $newPassword,
    PASSWORD_DEFAULT
);

/* ===============================
   UPDATE PASSWORD
=============================== */
$stmt = $db->prepare("
    UPDATE access_account
    SET password = ?
    WHERE id = ?
");

$stmt->bind_param(
    "si",
    $newHash,
    $userId
);

$ok = $stmt->execute();

$stmt->close();

$db->close();

/* ===============================
   RESPONSE
=============================== */
echo json_encode([
    'success' => $ok,
    'message' => $ok
        ? 'Паролата е сменена успешно'
        : 'Грешка при запис'
]);