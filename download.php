<?php
// Disable output buffering at the very beginning
while (ob_get_level()) {
    ob_end_clean();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_functions.php';
require_once __DIR__ . '/includes/document_functions.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['type']) || !isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid request');
}

$documentType = sanitize($_GET['type']);
$documentId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

$validTypes = ['syllabus', 'notes', 'assignment', 'test'];
if (!in_array($documentType, $validTypes)) {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid document type');
}

try {
    // Get document details
    $table = $documentType === 'assignment' ? 'assignments' : $documentType . 's';
    $query = "SELECT * FROM $table WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $stmt->bind_param("i", $documentId);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();

    if (!$document || !isset($document['file_path'])) {
        header('HTTP/1.1 404 Not Found');
        die('Document not found');
    }

    // Verify user has access to this course
    if (!isAdmin()) {
        $enrollmentCheck = $conn->prepare(
            "SELECT 1 FROM student_courses 
            WHERE student_id = ? AND course_id = ? AND status = 'active'"
        );
        if (!$enrollmentCheck) {
            throw new Exception('Database error: ' . $conn->error);
        }
        $enrollmentCheck->bind_param("ii", $userId, $document['course_id']);
        $enrollmentCheck->execute();
        
        if (!$enrollmentCheck->get_result()->num_rows) {
            header('HTTP/1.1 403 Forbidden');
            die('Access denied');
        }
    }

    // Log the download (if logging is enabled)
    if (function_exists('logDocumentDownload')) {
        logDocumentDownload(
            $conn,
            $userId,
            $document['course_id'],
            $documentType,
            $documentId,
            $document['title']
        );
    }

    // Serve the file
    $filePath = $document['file_path'];
    if (!file_exists($filePath)) {
        header('HTTP/1.1 404 Not Found');
        die('File not found on server');
    }

    // Get file info
    $fileSize = filesize($filePath);
    $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    // Create a safe filename with the correct extension
    $originalName = pathinfo($document['file_name'] ?? $document['title'] ?? 'download', PATHINFO_FILENAME);
    $fileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName) . '.' . $fileExtension;
    
    // Ensure the file exists and is readable
    if (!is_readable($filePath)) {
        throw new Exception('File is not readable');
    }
    
    // Set content type based on file extension
    $contentTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed'
    ];
    
    $contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Description: File Transfer');
    // Force download with the correct MIME type and extension
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);
    
    // Disable any compression
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    @ini_set('zlib.output_compression', 'Off');
    
    // Disable any output buffering
    if (ob_get_level()) {
        ob_end_flush();
    }
    
    // Output the file in chunks to handle large files
    $chunkSize = 1024 * 1024; // 1MB chunks
    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        throw new Exception('Cannot open file for reading');
    }
    
    while (!feof($handle)) {
        echo fread($handle, $chunkSize);
        ob_flush();
        flush();
    }
    fclose($handle);
    exit;
    
} catch (Exception $e) {
    // Ensure no output has been sent
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log the error
    error_log('Download error: ' . $e->getMessage());
    
    // Send error response
    header('HTTP/1.1 500 Internal Server Error');
    die('An error occurred while processing your request. Please try again later.');
}
