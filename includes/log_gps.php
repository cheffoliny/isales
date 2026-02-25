<?php
define('INCLUDE_CHECK', true);
require_once '../session_init.php';
require_once '../config.php';

// Проверка за сесия
if (empty($_SESSION["user_id"])) {
    http_response_code(403);
    exit("Not authorized");
}

global $db_sod;

$id_person = intval($_SESSION["user_id"]);

// Получаване на данните
$latRaw = $_POST['lat'] ?? null;
$lngRaw = $_POST['lng'] ?? null;
file_put_contents(__DIR__ . "/gps_debug.log", date('Y-m-d H:i:s') . " RAW_COORDS: latRaw=$latRaw lngRaw=$lngRaw" . PHP_EOL, FILE_APPEND);

// Нормализиране (запетая -> точка)
if ($latRaw !== null) $latRaw = str_replace(',', '.', trim($latRaw));
if ($lngRaw !== null) $lngRaw = str_replace(',', '.', trim($lngRaw));

$lat = is_numeric($latRaw) ? (float)$latRaw : null;
$lng = is_numeric($lngRaw) ? (float)$lngRaw : null;

file_put_contents(__DIR__ . "/gps_debug.log", date('Y-m-d H:i:s') . " NORMALIZED: lat=$lat lng=$lng" . PHP_EOL, FILE_APPEND);


$accuracy = isset($_POST['accuracy']) ? floatval($_POST['accuracy']) : -1;
$speed    = isset($_POST['speed']) ? floatval($_POST['speed']) : -1;
$bearing  = isset($_POST['bearing']) ? floatval($_POST['bearing']) : -1;
$altitude = isset($_POST['altitude']) ? floatval($_POST['altitude']) : -1;

if ($lat === null || $lng === null) {
    logError($db_sod, $id_person, "Missing coordinates");
    exit("Missing coordinates");
}

// Ensure format lat,lng
$geo_data = $lat.','. $lng;
$geo_source = "android_webview";

/* ------------------------------
    Вариант В — АНТИ-ДУБЛИРАНЕ
--------------------------------*/

$lastGeo = null;
$checkSql = "SELECT geo_data FROM work_card_geo_log WHERE id_person = ? ORDER BY id DESC LIMIT 1";
if ($stmt = $db_sod->prepare($checkSql)) {
    $stmt->bind_param("i", $id_person);
    $stmt->execute();
    $stmt->bind_result($lastGeo);
    $stmt->fetch();
    $stmt->close();
}

if ($lastGeo) {
    // lastGeo expected "lat,lng"
    $parts = explode(",", $lastGeo);
    if (count($parts) == 2) {
        $lastLat = floatval($parts[0]);
        $lastLng = floatval($parts[1]);
        $dist = distanceHaversine($lat, $lng, $lastLat, $lastLng);

        // Ако разстоянието е по-малко от 2 метра — не записваме
        if ($dist < 2) {
            echo "SKIP";
            exit;
        }
    }
}

/* ------------------------------
    Запис в базата
--------------------------------*/

$insertSql = "
    INSERT INTO work_card_geo_log
    (id_person, geo_data, geo_acc, geo_speed, geo_bearing, geo_altitude, geo_source, geo_time, server_time)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
";

if ($stmt = $db_sod->prepare($insertSql)) {
    $stmt->bind_param("isdidds", $id_person, $geo_data, $accuracy, $speed, $bearing, $altitude, $geo_source);
    // Note: bind types: i (int), s (string), d (double)
    $ok = $stmt->execute();
    if ($ok) {
        echo "OK";
    } else {
        logError($db_sod, $id_person, "Insert failed: " . $stmt->error);
        http_response_code(500);
        echo "DB Error";
    }
    $stmt->close();
} else {
    logError($db_sod, $id_person, "Prepare failed: " . $db_sod->error);
    http_response_code(500);
    echo "DB Prepare Error";
}

/* ------------------------------------
    Haversine функция
-------------------------------------*/
function distanceHaversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
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
