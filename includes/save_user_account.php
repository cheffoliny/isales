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
   POST DATA
=============================== */
$personId  = (int)($_POST['person_id'] ?? 0);
$accountId = (int)($_POST['account_id'] ?? 0);

$username = strtolower(trim($_POST['username'] ?? ''));
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
        'message' => 'Въведете потребителско име'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| USERNAME FORMAT
|--------------------------------------------------------------------------
*/
if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {

    echo json_encode([
        'success' => false,
        'message' => 'Невалидно потребителско име'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| OFFICES VALIDATION
|--------------------------------------------------------------------------
*/
if (!is_array($offices)) {
    $offices = [];
}

$offices = array_unique(
    array_map('intval', $offices)
);

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

    $stmtCheck->close();

    echo json_encode([
        'success' => false,
        'message' => 'Това потребителско име вече съществува'
    ]);

    exit;
}

$stmtCheck->close();

/* ===============================
   TRANSACTION
=============================== */
$db->begin_transaction();

try {

    /* ===============================
       CREATE ACCOUNT
    =============================== */
    if ($accountId <= 0) {

        if ($password === '') {

            throw new Exception('Въведете парола');
        }

        /*
        |--------------------------------------------------------------------------
        | SECURE PASSWORD HASH
        |--------------------------------------------------------------------------
        */
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

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
            $passwordHash
        );

        if (!$stmt->execute()) {

            throw new Exception(
                'Грешка при създаване на акаунт'
            );
        }

        $accountId = $stmt->insert_id;

        $stmt->close();

    } else {

        /* ===============================
           UPDATE ACCOUNT
        =============================== */

        if ($password !== '') {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

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
                $passwordHash,
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

        if (!$stmt->execute()) {

            throw new Exception(
                'Грешка при обновяване'
            );
        }

        $stmt->close();
    }

    /* ===============================
       DELETE OLD OFFICES
    =============================== */
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

    /* ===============================
       INSERT NEW OFFICES
    =============================== */
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

        foreach ($offices as $officeId) {

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
       COMMIT
    =============================== */
    $db->commit();

    echo json_encode([
        'success' => true,
        'account_id' => $accountId
    ]);

} catch (Exception $e) {

    $db->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$db->close();