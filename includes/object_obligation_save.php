<?php
include_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'unauthorized'
    ]);
    exit;
}

$db = db_connect('storage');

$idObject = isset($_POST['id_object']) ? (int)$_POST['id_object'] : 0;
$idPpp    = isset($_POST['id_ppp']) ? (int)$_POST['id_ppp'] : 0;

$fromDateInput = trim((string)($_POST['from_date'] ?? ''));

$totalSumInput = trim((string)($_POST['total_sum'] ?? '0'));
$totalSumInput = str_replace(',', '.', $totalSumInput);
$totalSum = (float)$totalSumInput;

$createdUser = (int)$_SESSION['user_id'];

if ($idObject <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Невалиден обект.'
    ]);
    exit;
}

$dateResult = normalizeDateToMysql($fromDateInput);

if (!$dateResult['success']) {
    echo json_encode([
        'success' => false,
        'error' => $dateResult['error']
    ]);
    exit;
}

$fromDateMysql = $dateResult['date'];

if ($totalSum <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Моля, въведете сума по-голяма от 0.'
    ]);
    exit;
}

$idPppForDb = $idPpp > 0 ? $idPpp : null;

if ($idPppForDb !== null) {
    $stmtCheck = $db->prepare("
        SELECT id
        FROM objects_obligations
        WHERE id_object = ?
          AND id_ppp = ?
        LIMIT 1
    ");

    if (!$stmtCheck) {
        echo json_encode([
            'success' => false,
            'error' => $db->error
        ]);
        exit;
    }

    $stmtCheck->bind_param('ii', $idObject, $idPppForDb);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();

    if ($resCheck && $resCheck->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'За този ППП вече има добавено задължение.'
        ]);
        $stmtCheck->close();
        $db->close();
        exit;
    }

    $stmtCheck->close();
}

$stmt = $db->prepare("
    INSERT INTO objects_obligations
        (
            id_object,
            id_ppp,
            from_date,
            total_sum,
            created_user,
            created_time
        )
    VALUES
        (?, ?, ?, ?, ?, NOW())
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => $db->error
    ]);
    exit;
}

$stmt->bind_param(
    'iisdi',
    $idObject,
    $idPppForDb,
    $fromDateMysql,
    $totalSum,
    $createdUser
);

$ok = $stmt->execute();

$obligationId = $stmt->insert_id;
if ($ok) {

    $currentBalance = getObjectDebtBalance( $db, $idObject );
    $newBalance = $currentBalance + $totalSum;

    $stmtTx = $db->prepare("
        INSERT INTO objects_obligation_transactions
        (
            id_object,
            id_obligation,
            transaction_type,
            amount,
            balance_after,
            created_user,
            created_time,
            transaction_date
        )
        VALUES
        (
            ?, ?, 'create',
            ?, ?,
            ?, NOW(), NOW()
        )
    ");

    $stmtTx->bind_param(
        'iiddi',
        $idObject,
        $obligationId,
        $totalSum,
        $newBalance,
        $createdUser
    );

    $stmtTx->execute();
    $stmtTx->close();
}

echo json_encode([
    'success' => $ok,
    'id' => $ok ? $stmt->insert_id : 0,
    'error' => $ok ? null : $stmt->error
]);

$stmt->close();
$db->close();
exit;