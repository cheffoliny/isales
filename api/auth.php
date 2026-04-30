<?php
/*
|--------------------------------------------------------------------------
| API Auth Helper
|--------------------------------------------------------------------------
|
| Token is accepted from:
|   1. Authorization: Bearer <token>  header  (when available)
|   2. ?token=<token>                 query param (fallback for proxies
|                                     that strip Authorization header)
|
| CREATE TABLE api_tokens (
|     id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
|     id_user     INT UNSIGNED NOT NULL,
|     token       VARCHAR(64)  NOT NULL UNIQUE,
|     created_at  DATETIME     NOT NULL DEFAULT NOW(),
|     last_seen   DATETIME     NOT NULL DEFAULT NOW(),
|     expires_at  DATETIME     NOT NULL,
|     INDEX idx_token (token)
| ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
|
*/

require_once __DIR__ . '/api_config.php';

/**
 * Send a JSON error and exit.
 */
function api_error(string $message, int $httpCode = 401): void
{
    ob_clean();
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Extract Bearer token from all possible locations.
 * Priority: Authorization header → query param ?token=
 */
function extract_token(): ?string
{
    // 1. Try every known $_SERVER and header location
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION']          ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['HTTP_X_AUTHORIZATION']        ?? '',
    ];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $candidates[] = $headers['Authorization']   ?? '';
        $candidates[] = $headers['authorization']   ?? '';
        $candidates[] = $headers['X-Authorization'] ?? '';
    }

    foreach ($candidates as $h) {
        if (preg_match('/^Bearer\s+(\S+)$/i', trim($h), $m)) {
            return $m[1];
        }
    }

    // 2. Fallback: ?token= query parameter
    if (!empty($_GET['token'])) {
        return trim($_GET['token']);
    }

    return null;
}

/**
 * Validate token and return user info.
 * Calls api_error() and exits on failure.
 */
function api_require_auth(): array
{
    $token = extract_token();

    if (!$token) {
        api_error('Missing or invalid Authorization header.');
    }

    $db   = db_connect('system');
    $stmt = $db->prepare("
        SELECT
            t.id_user,
            t.expires_at,
            p.fname,
            p.lname
        FROM api_tokens t
        JOIN cpnlnmm83445_alaska_personnel.personnel p
            ON p.id = (
                SELECT id_person FROM access_account WHERE id = t.id_user LIMIT 1
            )
        WHERE t.token = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $db->close();
        api_error('Invalid token.');
    }

    if (new DateTime() > new DateTime($row['expires_at'])) {
        $db->close();
        api_error('Token expired. Please log in again.');
    }

    // Bump last_seen
    $safeToken = $db->real_escape_string($token);
    $db->query("UPDATE api_tokens SET last_seen = NOW() WHERE token = '{$safeToken}'");
    $db->close();

    return [
        'user_id'    => (int) $row['id_user'],
        'first_name' => $row['fname'],
        'last_name'  => $row['lname'],
        'token'      => $token,
    ];
}
