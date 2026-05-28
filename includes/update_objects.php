<?php
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$id   = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$info = trim($_POST['info'] ?? '');
$lat  = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng  = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

$offices_json = $_POST['offices_ids'] ?? '[]';
$offices = json_decode($offices_json, true);

if(!is_array($offices)){
    $offices = [];
}

$offices = array_map('intval', $offices);

if(!$id || !$name){
    echo json_encode(['success' => false]);
    exit;
}

$db = db_connect('sod');

$db->begin_transaction();

try {

    /* ===================================
       UPDATE OBJECT
    =================================== */
    $stmt = $db->prepare("
        UPDATE objects SET
            name = ?,
            operativ_info = ?,
            geo_lat = ?,
            geo_lan = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssddi",
        $name,
        $info,
        $lat,
        $lng,
        $id
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $stmt->close();

    /* ===================================
       SOFT DELETE OLD OFFICES
    =================================== */
    $stmtDelete = $db->prepare("
        UPDATE offices_objects
        SET to_arc = 1
        WHERE id_object = ?
    ");

    $stmtDelete->bind_param("i", $id);

    if(!$stmtDelete->execute()){
        throw new Exception($stmtDelete->error);
    }

    $stmtDelete->close();

    /* ===================================
       INSERT NEW OFFICES
    =================================== */
    if(count($offices)){

        $stmtInsert = $db->prepare("
            INSERT INTO offices_objects
            (
                id_object,
                id_office,
                to_arc
            )
            VALUES
            (
                ?, ?, 0
            )
        ");

        foreach($offices as $officeId){

            $stmtInsert->bind_param(
                "ii",
                $id,
                $officeId
            );

            if(!$stmtInsert->execute()){
                throw new Exception($stmtInsert->error);
            }
        }

        $stmtInsert->close();
    }

    $db->commit();

    echo json_encode([
        'success' => true
    ]);

} catch(Exception $e){

    $db->rollback();

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$db->close();