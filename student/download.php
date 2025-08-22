<?php
require_once __DIR__ . '/includes/header.php';
requireLogin();

if (!isset($_GET['type']) || !isset($_GET['id'])) {
    header('HTTP/1.0 400 Bad Request');
    die('Invalid request');
}

$type = $_GET['type'];
$id = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

// Validate type
if (!in_array($type, ['certificate', 'transcript'])) {
    header('HTTP/1.0 400 Bad Request');
    die('Invalid document type');
}

// Get file path from database
$table = $type === 'certificate' ? 'certificates' : 'transcripts';
$query = $conn->prepare("SELECT file_path FROM $table WHERE id = ? AND user_id = ?");
$query->bind_param('ii', $id, $userId);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.0 404 Not Found');
    die('File not found or access denied');
}

$file = $result->fetch_assoc();
$filePath = $file['file_path'];

// Check if file exists
if (!file_exists($filePath)) {
    header('HTTP/1.0 404 Not Found');
    die('File not found on server');
}

// Get file info
$fileName = basename($filePath);
$fileSize = filesize($filePath);
$fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// Set content type based on file extension
$contentTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed'
];

$contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';

// Set headers for download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, must-revalidate');
header('Pragma: private');
header('Expires: 0');

// Output the file
readfile($filePath);
exit;
