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
$allocationsRaw = $_POST['allocations'] ?? '';

$updateUser = (int)$_SESSION['user_id'];

if ($idObject <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Невалиден обект.'
    ]);
    exit;
}

$allocations = json_decode($allocationsRaw, true);

if (!is_array($allocations) || count($allocations) === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Няма разнесени суми за плащане.'
    ]);
    exit;
}

$db->begin_transaction();

try {

    foreach ($allocations as $allocation) {

        $idObligation = isset($allocation['id']) ? (int)$allocation['id'] : 0;
        $amount = isset($allocation['amount']) ? (float)$allocation['amount'] : 0;

        if ($idObligation <= 0 || $amount <= 0) {
            throw new Exception('Невалидни данни за плащане.');
        }

        $stmt = $db->prepare("
            SELECT
                total_sum,
                COALESCE(paid_sum, 0) AS paid_sum
            FROM objects_obligations
            WHERE id = ?
              AND id_object = ?
            LIMIT 1
            FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception($db->error);
        }

        $stmt->bind_param('ii', $idObligation, $idObject);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception('Задължението не е намерено.');
        }

        $totalSum = (float)$row['total_sum'];
        $paidSum = (float)$row['paid_sum'];
        $diff = round($totalSum - $paidSum, 2);

        if ($diff <= 0) {
            continue;
        }

        if ($amount > $diff) {
            $amount = $diff;
        }

        $stmt = $db->prepare("
            UPDATE objects_obligations
            SET
                paid_sum = COALESCE(paid_sum, 0) + ?,
                paid_date = NOW(),
                updated_user = ?
            WHERE id = ?
              AND id_object = ?
        ");

        if (!$stmt) {
            throw new Exception($db->error);
        }

        $stmt->bind_param(
            'diii',
            $amount,
            $updateUser,
            $idObligation,
            $idObject
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $stmt->close();
    }

    $db->commit();

    echo json_encode([
        'success' => true
    ]);

} catch (Exception $e) {

    $db->rollback();

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$db->close();
exit;