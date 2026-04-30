<?php
/*
|--------------------------------------------------------------------------
| POST /api/login.php
|--------------------------------------------------------------------------
|
| Body (JSON or form-encoded):
|   { "username": "...", "password": "..." }
|
| Response (success):
|   {
|     "success": true,
|     "token":      "...",
|     "expires_at": "2025-07-28 10:00:00",
|     "user": {
|       "id":         12,
|       "username":   "john",
|       "first_name": "John",
|       "last_name":  "Doe",
|       "is_admin":   0
|     }
|   }
|
| Response (failure):
|   { "success": false, "message": "..." }
|
*/

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/config.php';

// Accept both JSON body and form-encoded POST
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

// ── 1. Verify credentials (same query as web index.php, but uses MD5) ─────
$db = db_connect('system');

$stmt = $db->prepare("
    SELECT
        sa.id        AS user_id,
        sa.username,
        sa.id_profile AS is_admin,
        p.fname      AS first_name,
        p.lname      AS last_name
    FROM access_account sa
    LEFT JOIN " . DB_NAMES['personnel'] . ".personnel p
        ON p.id = sa.id_person
    WHERE sa.to_arc   = 0
      AND p.status    = 'active'
      AND sa.username = ?
      AND sa.password = MD5(?)
    LIMIT 1
");

$stmt->bind_param('ss', $username, $password);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    $db->close();
    exit;
}

// ── 2. Generate token ──────────────────────────────────────────────────────
$token     = bin2hex(random_bytes(32));           // 64-char hex string
$expiresAt = (new DateTime('+90 days'))->format('Y-m-d H:i:s');
$userId    = (int) $user['user_id'];

// Upsert: one active token per user (replace old one on re-login)
$stmt = $db->prepare("
    INSERT INTO api_tokens (id_user, token, expires_at)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
        token      = VALUES(token),
        expires_at = VALUES(expires_at),
        last_seen  = NOW()
");

// Note: ON DUPLICATE KEY works on the UNIQUE(id_user) index.
// Add that index if you want one-token-per-user behaviour:
//   ALTER TABLE api_tokens ADD UNIQUE KEY uniq_user (id_user);
// Otherwise multiple tokens per user are allowed (e.g. multiple devices).

$stmt->bind_param('iss', $userId, $token, $expiresAt);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not create session token.']);
    $stmt->close();
    $db->close();
    exit;
}

$stmt->close();
$db->close();

// ── 3. Respond ─────────────────────────────────────────────────────────────
echo json_encode([
    'success'    => true,
    'token'      => $token,
    'expires_at' => $expiresAt,
    'user'       => [
        'id'         => $userId,
        'username'   => $user['username'],
        'first_name' => $user['first_name'],
        'last_name'  => $user['last_name'],
        'is_admin'   => (int) $user['is_admin'],
    ],
]);
