<?php
// Set the base directory
$baseDir = __DIR__ . '/config/config.php';

// Check if the file exists, if not try alternative path
if (!file_exists($baseDir)) {
    $baseDir = __DIR__ . '/../config/config.php';
}

require_once $baseDir;
require_once __DIR__ . '/includes/auth_functions.php';

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to the common login page at root
header("Location: " . SITE_URL . "/login.php");
exit;

// Fallback
die('Logging out...');
?>
