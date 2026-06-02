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

if ($idObject <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Невалиден обект.'
    ]);
    exit;
}

$paidObligations = [];

$stmt = $db->prepare("
    SELECT
        oo.from_date,
        oo.total_sum,
        oo.paid_sum,
        oo.paid_date,
        CONCAT(pc.fname, ' ', pc.lname) AS created_user,
        CONCAT(pu.fname, ' ', pu.lname) AS updated_user
    FROM objects_obligations oo
    LEFT JOIN personnel.personnel pc ON pc.id = oo.created_user
    LEFT JOIN personnel.personnel pu ON pu.id = oo.updated_user
    WHERE oo.id_object = ?
      AND oo.total_sum = oo.paid_sum
    ORDER BY oo.paid_date DESC, oo.from_date DESC
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => $db->error
    ]);
    exit;
}

$stmt->bind_param('i', $idObject);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $paidObligations[] = $row;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'paid_obligations' => $paidObligations
]);

$db->close();
exit;