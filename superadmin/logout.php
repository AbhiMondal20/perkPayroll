<?php
session_start();

/*
|--------------------------------------------------------------------------
| Logout Logic
|--------------------------------------------------------------------------
| - Unset all session variables
| - Destroy session
| - Prevent back-button access after logout
*/

// Unset all session variables
$_SESSION = [];

// Destroy session
if (session_id() !== '') {
    session_destroy();
}

// Delete session cookie (extra safety)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Disable caching (important)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login page
header("Location: index?logout=success");
exit;