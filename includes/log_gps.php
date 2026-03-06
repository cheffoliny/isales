<?php

include_once __DIR__ . '/../core/init.php';
include_once __DIR__ . '/../config/config.php';

$db = db_connect('sod');

$id_person = 0;

if (!empty($_SESSION['user_id'])) {
    $id_person = intval($_SESSION['user_id']);
}
elseif (!empty($_POST['user_id'])) {
    $id_person = intval($_POST['user_id']);
}

if (!$id_person) {
    exit("NO USER");
}

$latRaw = $_POST['lat'] ?? null;
$lngRaw = $_POST['lng'] ?? null;

if ($latRaw !== null) $latRaw = str_replace(',', '.', trim($latRaw));
if ($lngRaw !== null) $lngRaw = str_replace(',', '.', trim($lngRaw));

$lat = is_numeric($latRaw) ? (float)$latRaw : null;
$lng = is_numeric($lngRaw) ? (float)$lngRaw : null;

if ($lat === null || $lng === null) {
    exit("NO COORD");
}

$accuracy = isset($_POST['accuracy']) ? floatval($_POST['accuracy']) : -1;
$speed    = isset($_POST['speed']) ? floatval($_POST['speed']) : -1;
$bearing  = isset($_POST['bearing']) ? floatval($_POST['bearing']) : -1;
$altitude = isset($_POST['altitude']) ? floatval($_POST['altitude']) : -1;

$geo_data = $lat . ',' . $lng;
$geo_source = "android_webview";

/* last geo */

$lastGeo = null;

$sql = "SELECT geo_data
        FROM work_card_geo_log
        WHERE id_person=?
        ORDER BY id DESC
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->bind_param("i",$id_person);
$stmt->execute();
$stmt->bind_result($lastGeo);
$stmt->fetch();
$stmt->close();

if ($lastGeo) {

$parts = explode(",",$lastGeo);

if(count($parts)==2){

$dist = distanceHaversine(
$lat,$lng,
floatval($parts[0]),
floatval($parts[1])
);

if($dist < 5){
echo "SKIP";
exit;
}

}

}

/* insert */

$sql = "
INSERT INTO work_card_geo_log
(id_person,geo_data,geo_acc,geo_speed,geo_bearing,geo_altitude,geo_source,geo_time,server_time)
VALUES (?,?,?,?,?,?,?,NOW(),NOW())
";

$stmt = $db->prepare($sql);

$stmt->bind_param(
"isdddds",
$id_person,
$geo_data,
$accuracy,
$speed,
$bearing,
$altitude,
$geo_source
);

$stmt->execute();

echo "OK";

$stmt->close();
$db->close();


function distanceHaversine($lat1,$lon1,$lat2,$lon2){

$R=6371000;

$dLat=deg2rad($lat2-$lat1);
$dLon=deg2rad($lon2-$lon1);

$a=sin($dLat/2)*sin($dLat/2)
+cos(deg2rad($lat1))
*cos(deg2rad($lat2))
*sin($dLon/2)
*sin($dLon/2);

$c=2*atan2(sqrt($a),sqrt(1-$a));

return $R*$c;

}

/* ------------------------------------
    Логванe на грешки
-------------------------------------*/
function logError($db, $id_person, $message) {
    if (!$db) return;
    $sql = "INSERT INTO geo_error_log ( id_person, error_message, time ) VALUES (?, ?, NOW())";
    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param("is", $id_person, $message);
        $stmt->execute();
        $stmt->close();
    }
}
