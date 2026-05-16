<?php

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

/* ===============================
   POST DATA
=============================== */
$personId  = (int)($_POST['person_id'] ?? 0);
$accountId = (int)($_POST['account_id'] ?? 0);

$username = strtolower(trim($_POST['username'] ?? ''));
//$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

$profile = (int)($_POST['profile'] ?? 2);

$offices = json_decode(
    $_POST['offices'] ?? '[]',
    true
);

/* ===============================
   VALIDATION
=============================== */
if ($personId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Невалиден служител'
    ]);

    exit;
}

if ($username === '') {

    echo json_encode([
        'success' => false,
        'message' => 'Въведете username'
    ]);

    exit;
}

/* ===============================
   CHECK DUPLICATE USERNAME
=============================== */
$stmtCheck = $db->prepare("
    SELECT id
    FROM access_account
    WHERE LOWER(TRIM(username)) = LOWER(TRIM(?))
    AND id != ?
    LIMIT 1
");

$stmtCheck->bind_param(
    "si",
    $username,
    $accountId
);

$stmtCheck->execute();

$resCheck = $stmtCheck->get_result();

if ($resCheck->num_rows > 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Това потребителско име вече съществува'
    ]);

    exit;
}

$stmtCheck->close();

/* ===============================
   CREATE ACCOUNT
=============================== */
if ($accountId <= 0) {

    if ($password === '') {

        echo json_encode([
            'success' => false,
            'message' => 'Въведете парола'
        ]);

        exit;
    }

    $md5 = md5($password);

    $stmt = $db->prepare("
        INSERT INTO access_account
        (
            id_person,
            id_profile,
            username,
            password
        )
        VALUES
        (
            ?, ?, ?, ?
        )
    ");

    $stmt->bind_param(
        "iiss",
        $personId,
        $profile,
        $username,
        $md5
    );

    $ok = $stmt->execute();

    if (!$ok) {

        echo json_encode([
            'success' => false,
            'message' => 'Грешка при създаване на акаунт'
        ]);

        exit;
    }

    $accountId = $stmt->insert_id;

    $stmt->close();

} else {

    /* ===============================
       UPDATE ACCOUNT
    =============================== */

    if ($password !== '') {

        $md5 = md5($password);

        $stmt = $db->prepare("
            UPDATE access_account
            SET
                id_profile = ?,
                username = ?,
                password = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "issi",
            $profile,
            $username,
            $md5,
            $accountId
        );

    } else {

        $stmt = $db->prepare("
            UPDATE access_account
            SET
                id_profile = ?,
                username = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "isi",
            $profile,
            $username,
            $accountId
        );
    }

    $ok = $stmt->execute();

    $stmt->close();
}

/* ===============================
   SYNC OFFICES
=============================== */

/*
 * Изтриваме старите
 */
$stmtDelete = $db->prepare("
    DELETE FROM account_office
    WHERE id_account = ?
");

$stmtDelete->bind_param(
    "i",
    $accountId
);

$stmtDelete->execute();

$stmtDelete->close();

/*
 * Добавяме новите
 */
if (!empty($offices)) {

    $stmtOffice = $db->prepare("
        INSERT INTO account_office
        (
            id_account,
            id_office
        )
        VALUES
        (
            ?, ?
        )
    ");

    foreach ((array)$offices as $officeId) {

        $officeId = (int)$officeId;

        $stmtOffice->bind_param(
            "ii",
            $accountId,
            $officeId
        );

        $stmtOffice->execute();
    }

    $stmtOffice->close();
}

/* ===============================
   RESPONSE
=============================== */
echo json_encode([
    'success' => true,
    'account_id' => $accountId
]);