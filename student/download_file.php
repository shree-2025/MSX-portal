<?php
require_once __DIR__ . '/includes/header.php';
requireLogin();

if (!isset($_GET['path']) || !isset($_GET['name'])) {
    http_response_code(400);
    die('Invalid request. Missing required parameters.');
}

$relativePath = urldecode($_GET['path']);
$fileName = basename(urldecode($_GET['name']));
$userId = $_SESSION['user_id'];

// Security: Prevent directory traversal
$basePath = realpath(__DIR__ . '/../uploads');
$fullPath = realpath($basePath . '/' . ltrim($relativePath, '/'));

if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
    http_response_code(403);
    die('Access denied. Invalid file path.');
}

// Verify file exists and is readable
if (!file_exists($fullPath) || !is_readable($fullPath)) {
    http_response_code(404);
    die('File not found or not accessible.');
}

// Get file extension and set appropriate MIME type
$fileExtension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed'
];

// Set content type with fallback to octet-stream
$contentType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';

// Force download headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);

// Check if we should force download or display in browser
$forceDownload = isset($_GET['force_download']) && $_GET['force_download'] == 1;

// For PDFs, we can show in browser by default unless force download is requested
if ($fileExtension === 'pdf' && !$forceDownload) {
    header('Content-Disposition: inline; filename="' . $fileName . '"');
} else {
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
}

header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, must-revalidate, max-age=0');
header('Pragma: public');
header('Expires: 0');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Accept-Ranges: bytes');

// Output the file
readfile($fullPath);

// Log the download activity
$activityType = 'download_' . pathinfo($fileName, PATHINFO_EXTENSION);
log_activity($userId, $activityType, 'Downloaded file: ' . $fileName);

exit;
