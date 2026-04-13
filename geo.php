<?php
session_start();
require_once __DIR__ . '/config/config.php';

$db = db_connect('sod');

/**
 * Geocoding (OpenStreetMap)
 */
function geocode($query)
{
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($query) . "&limit=1";

    $opts = [
        "http" => [
            "header" => "User-Agent: GeoUpdater/1.0\r\n"
        ]
    ];

    $ctx = stream_context_create($opts);
    $res = file_get_contents($url, false, $ctx);

    $json = json_decode($res, true);

    if (!empty($json[0])) {
        return [
            'lat' => $json[0]['lat'],
            'lng' => $json[0]['lon']
        ];
    }

    return null;
}

/**
 * ВАШИЯТ SELECT (както го поиска)
 */
$sql = "
    SELECT
        id,
        SUBSTRING_INDEX(`name`, ' ', -1) AS address
    FROM objects WHERE geo_lan = '0.000000000000000' OR geo_lan = '26.926660100000000'
";

$result = $db->query($sql);

while ($row = $result->fetch_assoc()) {

    $id = (int)$row['id'];
    $addr = trim($row['address']);

    if ($addr === '') continue;

    // ⚠️ добавяме контекст, за да не гърми геокодинга
    $query = 'с. ' .$addr . ', Разград, Bulgaria';

    $geo = geocode($query);

    if ($geo) {

        $stmt = $db->prepare("
            UPDATE objects
            SET geo_lat = ?, geo_lan = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ddi",
            $geo['lat'],
            $geo['lng'],
            $id
        );

        $stmt->execute();
        $stmt->close();

        echo "OK $id $addr → {$geo['lat']}, {$geo['lng']}<br>";

    } else {
        echo "MISS $id $addr<br>";
    }

usleep(5100000); // 1.1 секунди
}