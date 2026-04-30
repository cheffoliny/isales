<?php
/*
|--------------------------------------------------------------------------
| POST /api/order_submit.php
|--------------------------------------------------------------------------
|
| Receives a complete order from the Android app and saves it.
| Mirrors the logic in pages/delivery_request.php but is stateless
| and JSON-based so it can be called from a background WorkManager job.
|
| Body (JSON):
|   {
|     "object_id":    7,
|     "local_id":     "uuid-or-local-int",   // echoed back for client matching
|     "order_date":   "2025-04-28T09:15:00", // when the order was created offline
|     "items": [
|       { "nomenclature_id": 5, "qty": 3, "single_price": 2.50 },
|       { "nomenclature_id": 9, "qty": 1, "single_price": 5.00 }
|     ]
|   }
|
| Response (success):
|   {
|     "success":  true,
|     "ppp_id":   42,       // server-side ID, store this on the device
|     "local_id": "...",    // echoed back unchanged
|     "status":   "wait"
|   }
|
| Response (failure):
|   { "success": false, "message": "..." }
|
| Business rules (matches web app behaviour):
|   - One PPP per object per calendar day. If one already exists for this
|     object on order_date, items are merged into it (UPSERT).
|   - New PPP starts at status = "open", then immediately moved to "wait"
|     (same as a sales rep pressing Submit in the web app).
|   - Existing PPP that is already "confirm" is rejected (locked).
|
*/

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/auth.php';

$user   = api_require_auth();
$userId = $user['user_id'];

// ── Parse body ─────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$objectId   = isset($body['object_id'])  ? (int)$body['object_id']  : 0;
$localId    = $body['local_id']          ?? '';
$orderDate  = $body['order_date']        ?? date('Y-m-d H:i:s');
$items      = $body['items']             ?? [];

// ── Validate ───────────────────────────────────────────────────────────────
if ($objectId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'object_id is required.']);
    exit;
}

if (empty($items) || !is_array($items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order must contain at least one item.']);
    exit;
}

// Normalise and validate items
$cleanItems = [];
foreach ($items as $item) {
    $nId    = isset($item['nomenclature_id']) ? (int)$item['nomenclature_id']   : 0;
    $qty    = isset($item['qty'])             ? (float)$item['qty']              : 0;
    $price  = isset($item['single_price'])    ? (float)$item['single_price']     : 0;

    if ($nId <= 0 || $qty <= 0) {
        continue;   // skip zero/invalid lines silently
    }

    $cleanItems[] = ['nomenclature_id' => $nId, 'qty' => $qty, 'price' => $price];
}

if (empty($cleanItems)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No valid items in order.']);
    exit;
}

// Parse order date — accept ISO 8601 or MySQL datetime
$orderDateObj = DateTime::createFromFormat(DateTime::ATOM, $orderDate)
    ?: DateTime::createFromFormat('Y-m-d H:i:s', $orderDate)
    ?: new DateTime();

$orderDateSql  = $orderDateObj->format('Y-m-d H:i:s');
$orderDateOnly = $orderDateObj->format('Y-m-d');     // for the one-per-day check

// ── Source user string (matches web app format) ───────────────────────────
$sourceUser = $user['first_name'] . ' ' . $user['last_name'] . $userId;

// ── Open DB and begin transaction ─────────────────────────────────────────
$db = db_connect('storage');
$db->begin_transaction();

try {

    // ── 1. Check for existing PPP on this date ─────────────────────────────
    $checkStmt = $db->prepare("
        SELECT id, status
        FROM ppp
        WHERE id_dest    = ?
          AND dest_type  = 'object'
          AND DATE(source_date) = ?
        LIMIT 1
    ");
    $checkStmt->bind_param('is', $objectId, $orderDateOnly);
    $checkStmt->execute();
    $checkStmt->bind_result($existingId, $existingStatus);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($existingId && $existingStatus === 'confirm') {
        // Order already confirmed — do not overwrite
        $db->rollback();
        $db->close();
        echo json_encode([
            'success'  => false,
            'message'  => 'Order already confirmed and cannot be modified.',
            'local_id' => $localId,
            'ppp_id'   => (int)$existingId,
            'status'   => 'confirm',
        ]);
        exit;
    }

    // ── 2. Create or reuse PPP header ─────────────────────────────────────
    if ($existingId) {

        $pppId = (int)$existingId;

        // Move to 'wait' if it was 'open'
        if ($existingStatus === 'open') {
            $upStmt = $db->prepare("
                UPDATE ppp
                SET status       = 'wait',
                    updated_user = ?,
                    updated_time = NOW()
                WHERE id = ?
            ");
            $upStmt->bind_param('ii', $userId, $pppId);
            $upStmt->execute();
            $upStmt->close();
        }

    } else {

        // Insert new PPP directly at 'wait' status (field rep already reviewed it)
        $insStmt = $db->prepare("
            INSERT INTO ppp
                (status, source_date, source_user, source_type, id_source, dest_type, id_dest)
            VALUES
                ('wait', ?, ?, 'storagehouse', 1, 'object', ?)
        ");
        $insStmt->bind_param('ssi', $orderDateSql, $sourceUser, $objectId);
        $insStmt->execute();
        $pppId = (int)$db->insert_id;
        $insStmt->close();

        if (!$pppId) {
            throw new RuntimeException('Failed to create PPP record.');
        }
    }

    // ── 3. Upsert line items ───────────────────────────────────────────────
    //
    // Requires UNIQUE KEY on ppp_elements(id_ppp, id_nomenclature).
    // SQL comment in save_ppp_element.php already mentions this:
    //   ALTER TABLE ppp_elements ADD UNIQUE KEY uniq (id_ppp, id_nomenclature);
    //
    $elemStmt = $db->prepare("
        INSERT INTO ppp_elements
            (id_ppp, id_nomenclature, `count`, single_price, updated_time, updated_user)
        VALUES
            (?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
            `count`      = VALUES(`count`),
            single_price = VALUES(single_price),
            updated_time = NOW(),
            updated_user = VALUES(updated_user)
    ");

    foreach ($cleanItems as $item) {
        $elemStmt->bind_param(
            'iiddi',
            $pppId,
            $item['nomenclature_id'],
            $item['qty'],
            $item['price'],
            $userId
        );
        $elemStmt->execute();
    }

    $elemStmt->close();

    // ── 4. Commit ──────────────────────────────────────────────────────────
    $db->commit();
    $db->close();

    echo json_encode([
        'success'  => true,
        'ppp_id'   => $pppId,
        'local_id' => $localId,
        'status'   => 'wait',
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    $db->rollback();
    $db->close();

    http_response_code(500);
    echo json_encode([
        'success'  => false,
        'message'  => 'Server error: ' . $e->getMessage(),
        'local_id' => $localId,
    ]);
}
