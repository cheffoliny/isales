<?php

/*
|--------------------------------------------------------------------------
| SESSION CONFIG
|--------------------------------------------------------------------------
*/

$sessionLifetime = 60 * 60 * 120; // 12 часа

/*
|--------------------------------------------------------------------------
| SESSION COOKIE SETTINGS
|--------------------------------------------------------------------------
*/

session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path'     => '/',
    'domain'   => '',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

/*
|--------------------------------------------------------------------------
| PHP SESSION GC
|--------------------------------------------------------------------------
*/

ini_set('session.gc_maxlifetime', $sessionLifetime);

/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| AUTO LOGOUT AFTER INACTIVITY
|--------------------------------------------------------------------------
*/

$currentPage = basename($_SERVER['PHP_SELF']);

if (
    $currentPage !== 'index.php' &&
    isset($_SESSION['LAST_ACTIVITY'])
) {

    $inactiveTime = time() - $_SESSION['LAST_ACTIVITY'];

    if ($inactiveTime > $sessionLifetime) {

        session_unset();

        session_destroy();

        header('Location: index.php?expired=1');

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE ACTIVITY TIME
|--------------------------------------------------------------------------
*/

$_SESSION['LAST_ACTIVITY'] = time();

/*
|--------------------------------------------------------------------------
| LOAD CONFIG
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/config.php';