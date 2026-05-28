<?php

// TEMPORARY DEBUG — remove after testing
$allHeaders = getallheaders();
error_log("HEADERS: " . json_encode($allHeaders));
error_log("SERVER AUTH: " . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET'));

/*
|--------------------------------------------------------------------------
| GET /api/sync_down.php
|--------------------------------------------------------------------------
|
| Returns everything the Android app needs to work offline:
|   - routes   (offices / territories)
|   - objects  (client locations, linked to routes)
|   - nomenclatures (product catalogue — only active, priced items)
|
| Optional query param:
|   ?since=2025-04-01T00:00:00   → only records updated after this timestamp
|                                   (for delta / incremental sync)
|                                   If omitted, returns full dataset.
|
| Headers required:
|   Authorization: Bearer <token>
|
| Response:
|   {
|     "success": true,
|     "synced_at": "2025-04-28T10:00:00Z",
|     "routes": [ { "id", "name" }, ... ],
|     "objects": [ { "id", "num", "name", "address", "route_ids", "lat", "lng" }, ... ],
|     "nomenclatures": [ { "id", "code", "name", "client_price", "sales_price" }, ... ]
|   }
|
*/

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/auth.php';

$user = api_require_auth();

// ── Optional delta timestamp ───────────────────────────────────────────────
$since = null;
if (!empty($_GET['since'])) {
    $dt = DateTime::createFromFormat(DateTime::ATOM, $_GET['since'])
       ?: DateTime::createFromFormat('Y-m-d H:i:s', $_GET['since']);
    if ($dt) {
        $since = $dt->format('Y-m-d H:i:s');
    }
}

$syncedAt = (new DateTime('now', new DateTimeZone('UTC')))->format(DateTime::ATOM);

// ── 1. Routes (offices) ────────────────────────────────────────────────────
$dbSod = db_connect('sod');

$routesSql = "SELECT id, name FROM offices ORDER BY name ASC";
$routesResult = $dbSod->query($routesSql);
$routes = [];
while ($row = $routesResult->fetch_assoc()) {
    $routes[] = [
        'id'   => (int) $row['id'],
        'name' => $row['name'],
    ];
}

// ── 2. Objects (client locations) ─────────────────────────────────────────
$objectsSql = "
    SELECT
        id,
        num,
        name,
        COALESCE(address, '') AS address,
        COALESCE(offices_ids, '[]') AS offices_ids,
        COALESCE(geo_lat, 0) AS lat,
        COALESCE(geo_lan, 0) AS lng
    FROM objects
    WHERE id_status = 1
    ORDER BY name ASC
";

$objectsResult = $dbSod->query($objectsSql);
$objects = [];
while ($row = $objectsResult->fetch_assoc()) {
    // Decode JSON array of route IDs, ensure it is a clean int array
    $routeIds = json_decode($row['offices_ids'], true);
    if (!is_array($routeIds)) {
        $routeIds = [];
    }
    $routeIds = array_values(array_map('intval', $routeIds));

    $objects[] = [
        'id'        => (int)   $row['id'],
        'num'       =>         $row['num'],
        'name'      =>         $row['name'],
        'address'   =>         $row['address'],
        'route_ids' =>         $routeIds,
        'lat'       => (float) $row['lat'],
        'lng'       => (float) $row['lng'],
    ];
}

$dbSod->close();

// ── 3. Nomenclatures (product catalogue) ──────────────────────────────────
$dbStorage = db_connect('storage');

// Only items that have a client price and are active for calculation
$nomSql = "
    SELECT
        id,
        UPPER(nom_code)   AS code,
        name,
        client_price,
        COALESCE(sales_price, 0) AS sales_price
    FROM nomenclatures
    WHERE client_price > 0
      AND is_calc > 0
    ORDER BY name ASC
";

$nomResult = $dbStorage->query($nomSql);
$nomenclatures = [];
while ($row = $nomResult->fetch_assoc()) {
    $nomenclatures[] = [
        'id'          => (int)   $row['id'],
        'code'        =>         $row['code'],
        'name'        =>         $row['name'],
        'client_price'=> (float) $row['client_price'],
        'sales_price' => (float) $row['sales_price'],   // 0 means no promo
    ];
}

$dbStorage->close();

// ── Respond ────────────────────────────────────────────────────────────────
echo json_encode([
    'success'        => true,
    'synced_at'      => $syncedAt,
    'routes'         => $routes,
    'objects'        => $objects,
    'nomenclatures'  => $nomenclatures,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
