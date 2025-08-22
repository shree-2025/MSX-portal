<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: /login.php');
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Transcript ID not provided';
    header('Location: transcripts.php');
    exit();
}

$transcriptId = (int)$_GET['id'];

// Start transaction
$conn->begin_transaction();

try {
    // First, get the file path to delete the PDF
    $stmt = $conn->prepare("SELECT file_path FROM transcripts WHERE id = ?");
    $stmt->bind_param('i', $transcriptId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Transcript not found');
    }
    
    $transcript = $result->fetch_assoc();
    $filePath = __DIR__ . '/..' . $transcript['file_path'];
    
    // Delete transcript courses
    $stmt = $conn->prepare("DELETE FROM transcript_courses WHERE transcript_id = ?");
    $stmt->bind_param('i', $transcriptId);
    $stmt->execute();
    
    // Delete the transcript
    $stmt = $conn->prepare("DELETE FROM transcripts WHERE id = ?");
    $stmt->bind_param('i', $transcriptId);
    
    if ($stmt->execute()) {
        // Delete the PDF file if it exists
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        
        $conn->commit();
        $_SESSION['success'] = 'Transcript deleted successfully';
    } else {
        throw new Exception('Failed to delete transcript');
    }
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Error deleting transcript: ' . $e->getMessage();
}

header('Location: transcripts.php');
exit();
