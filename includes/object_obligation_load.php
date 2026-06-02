<?php
include_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$db = db_connect('storage');

$idObject = isset($_POST['id_object']) ? (int)$_POST['id_object'] : 0;

if ($idObject <= 0) {
    echo json_encode(['success' => false, 'error' => 'Невалиден обект.']);
    exit;
}

$old = [];
$stmt = $db->prepare("
    SELECT
        oo.id,
        oo.id_ppp,
        oo.from_date,
        oo.total_sum,
        COALESCE(oo.paid_sum, 0) AS paid_sum,
        oo.total_sum - COALESCE(oo.paid_sum, 0) AS diff
    FROM objects_obligations oo
    WHERE oo.id_object = ?
      AND oo.total_sum > COALESCE(oo.paid_sum, 0)
    ORDER BY oo.from_date ASC
");
$stmt->bind_param('i', $idObject);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $old[] = $row;
}
$stmt->close();

$ppp = [];
$stmt = $db->prepare("
    SELECT
        p.id AS id_ppp,
        DATE(p.dest_date) AS dest_date,
        SUM(pe.`count` * pe.single_price) AS ppp_sum
    FROM ppp p
    JOIN ppp_elements pe
        ON pe.id_ppp = p.id
       AND pe.`count` > 0
    LEFT JOIN objects_obligations oo
        ON oo.id_object = p.id_dest
       AND oo.id_ppp = p.id
    WHERE p.id_dest = ?
      AND p.`status` = 'confirm'
      AND oo.id IS NULL
    GROUP BY p.id, p.dest_date
    ORDER BY p.dest_date ASC
");
$stmt->bind_param('i', $idObject);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $ppp[] = $row;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'old_obligations' => $old,
    'ppp_without_obligation' => $ppp
]);

$db->close();
exit;