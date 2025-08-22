<?php
// Start session and include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

$submission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get submission details
$query = "SELECT s.*, u.full_name as student_name, a.title as assignment_title
          FROM assignment_submissions s
          JOIN users u ON s.student_id = u.id
          JOIN assignments a ON s.assignment_id = a.id
          WHERE s.id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission || empty($submission['file_path'])) {
    setFlashMessage('danger', 'Submission file not found.');
    redirect('submissions.php');
}

$file_path = '../uploads/submissions/' . basename($submission['file_path']);

if (!file_exists($file_path)) {
    setFlashMessage('danger', 'The requested file does not exist on the server.');
    redirect('submissions.php');
}

// Log the download
logActivity(
    $_SESSION['user_id'],
    'submission_download',
    "Downloaded submission #$submission_id: " . basename($submission['file_path'])
);

// Set headers for file download
$file_name = 'submission_' . $submission['student_name'] . '_' . 
             preg_replace('/[^a-z0-9\.]/i', '_', strtolower($submission['assignment_title'])) . 
             '_' . basename($submission['file_path']);
$file_size = filesize($file_path);
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// Common MIME types
$mime_types = [
    'pdf'  => 'application/pdf',
    'txt'  => 'text/plain',
    'html' => 'text/html',
    'htm'  => 'text/html',
    'exe'  => 'application/octet-stream',
    'zip'  => 'application/zip',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'gif'  => 'image/gif',
    'png'  => 'image/png',
    'jpeg' => 'image/jpeg',
    'jpg'  => 'image/jpg',
    'php'  => 'text/plain'
];

$content_type = $mime_types[$file_extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $file_size);
header('Accept-Ranges: bytes');
header('Cache-Control: private');
header('Pragma: private');
header('Expires: 0');

// Clear output buffer
if (ob_get_level()) {
    ob_clean();
}
flush();

// Read the file and output it to the browser
readfile($file_path);
exit;
