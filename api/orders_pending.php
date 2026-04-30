<?php
/*
|--------------------------------------------------------------------------
| GET /api/orders_pending.php
|--------------------------------------------------------------------------
|
| Returns the authenticated user's orders from the last 30 days
| so the Android app can display them (read-only history).
|
| Optional query params:
|   ?days=30        → how many days back to look (default 30, max 90)
|   ?status=wait    → filter by status: open | wait | confirm | cancel
|
| Headers required:
|   Authorization: Bearer <token>
|
| Response:
|   {
|     "success": true,
|     "orders": [
|       {
|         "ppp_id":      42,
|         "object_id":   7,
|         "object_name": "Магазин Иванов",
|         "status":      "wait",
|         "source_date": "2025-04-28 09:15:00",
|         "items": [
|           { "nomenclature_id": 5, "code": "ABC123", "name": "Продукт X",
|             "qty": 3, "single_price": 2.50 },
|           ...
|         ]
|       },
|       ...
|     ]
|   }
|
*/

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/auth.php';

$user   = api_require_auth();
$userId = $user['user_id'];

// ── Query params ───────────────────────────────────────────────────────────
$days = min(90, max(1, (int)($_GET['days'] ?? 30)));

$allowedStatuses = ['open', 'wait', 'confirm', 'cancel'];
$statusFilter    = $_GET['status'] ?? null;
if ($statusFilter && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = null;
}

// ── Fetch orders ───────────────────────────────────────────────────────────
$dbStorage = db_connect('storage');

$whereStatus = $statusFilter
    ? "AND p.status = '" . $dbStorage->real_escape_string($statusFilter) . "'"
    : '';

// source_user in ppp is stored as "FirstName LastName<id>" (see delivery_request.php)
// We match on the numeric suffix to find orders belonging to this user.
$sql = "
    SELECT
        p.id            AS ppp_id,
        p.id_dest       AS object_id,
        p.status,
        DATE_FORMAT(p.source_date, '%Y-%m-%d %H:%i:%s') AS source_date
    FROM ppp p
    WHERE p.source_date >= NOW() - INTERVAL ? DAY
      AND p.dest_type  = 'object'
      AND p.source_user LIKE CONCAT('%', ?)
    $whereStatus
    ORDER BY p.source_date DESC
";

$userSuffix = (string)$userId;   // the numeric suffix appended in delivery_request.php

$stmt = $dbStorage->prepare($sql);
$stmt->bind_param('is', $days, $userSuffix);
$stmt->execute();
$ordersResult = $stmt->get_result();
$stmt->close();

// ── Collect ppp IDs and fetch items in one query ───────────────────────────
$orders = [];
$pppIds = [];

while ($row = $ordersResult->fetch_assoc()) {
    $pppId = (int)$row['ppp_id'];
    $orders[$pppId] = [
        'ppp_id'      => $pppId,
        'object_id'   => (int)$row['object_id'],
        'object_name' => '',        // filled below
        'status'      => $row['status'],
        'source_date' => $row['source_date'],
        'items'       => [],
    ];
    $pppIds[] = $pppId;
}

if (empty($pppIds)) {
    $dbStorage->close();
    echo json_encode(['success' => true, 'orders' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Fetch line items for all found orders ──────────────────────────────────
$placeholders = implode(',', array_fill(0, count($pppIds), '?'));
$types        = str_repeat('i', count($pppIds));

$itemsSql = "
    SELECT
        pe.id_ppp,
        pe.id_nomenclature,
        UPPER(n.nom_code)          AS code,
        n.name,
        pe.count                   AS qty,
        pe.single_price
    FROM ppp_elements pe
    JOIN nomenclatures n ON n.id = pe.id_nomenclature
    WHERE pe.id_ppp IN ($placeholders)
      AND pe.to_arc = 0
    ORDER BY n.name ASC
";

$stmt = $dbStorage->prepare($itemsSql);
$stmt->bind_param($types, ...$pppIds);
$stmt->execute();
$itemsResult = $stmt->get_result();
$stmt->close();

while ($item = $itemsResult->fetch_assoc()) {
    $pppId = (int)$item['id_ppp'];
    if (isset($orders[$pppId])) {
        $orders[$pppId]['items'][] = [
            'nomenclature_id' => (int)   $item['id_nomenclature'],
            'code'            =>         $item['code'],
            'name'            =>         $item['name'],
            'qty'             => (float) $item['qty'],
            'single_price'    => (float) $item['single_price'],
        ];
    }
}

$dbStorage->close();

// ── Fetch object names for all unique object IDs ───────────────────────────
$objectIds = array_unique(array_column($orders, 'object_id'));

if (!empty($objectIds)) {
    $dbSod  = db_connect('sod');
    $ph     = implode(',', array_fill(0, count($objectIds), '?'));
    $types2 = str_repeat('i', count($objectIds));

    $objStmt = $dbSod->prepare("SELECT id, name FROM objects WHERE id IN ($ph)");
    $objStmt->bind_param($types2, ...$objectIds);
    $objStmt->execute();
    $objResult = $objStmt->get_result();
    $objStmt->close();
    $dbSod->close();

    while ($obj = $objResult->fetch_assoc()) {
        $oId = (int)$obj['id'];
        foreach ($orders as &$order) {
            if ($order['object_id'] === $oId) {
                $order['object_name'] = $obj['name'];
            }
        }
        unset($order);
    }
}

// ── Respond ────────────────────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'orders'  => array_values($orders),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
