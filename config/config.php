<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site configuration
$base_path = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

// Define base URL dynamically
define('SITE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);
define('SITE_NAME', 'MindSparxs');
define('ADMIN_EMAIL', 'ctxofficial2025@gmail.com');

// Base URL - Automatically detects the project path
define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . $base_path);

// File upload paths
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/coaching-center/assets/uploads/');

// Include database connection
require_once __DIR__ . '/database.php';

// Include authentication functions
require_once __DIR__ . '/../includes/auth_functions.php';
function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        echo "<div class='alert alert-{$flash['type']}'>{$flash['message']}</div>";
        unset($_SESSION['flash']);
    }
}

// Function to sanitize input
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}
?>
