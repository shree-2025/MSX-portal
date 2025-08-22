<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/auth_functions.php';
requireLogin();

// Get transcript ID from URL
$transcript_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($transcript_id <= 0) {
    header('Location: transcripts.php?error=invalid_id');
    exit();
}

// Fetch transcript with student verification
$stmt = $conn->prepare("
    SELECT t.*, u.full_name, u.email 
    FROM transcripts t
    JOIN users u ON t.student_id = u.id
    WHERE t.id = ? AND t.student_id = ?
");
$stmt->bind_param('ii', $transcript_id, $_SESSION['user_id']);
$stmt->execute();
$transcript = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transcript) {
    header('Location: transcripts.php?error=not_found');
    exit();
}

// Define possible file locations
$file_path = '';
$filename = '';
$base_upload_path = __DIR__ . '/../uploads';

// 1. First, try the direct path from database
if (!empty($transcript['file_path'])) {
    if (strpos($transcript['file_path'], '/') === 0) {
        // Absolute path
        $file_path = __DIR__ . '/..' . $transcript['file_path'];
    } else {
        // Relative path
        $file_path = $base_upload_path . '/' . $transcript['file_path'];
    }
    $filename = basename($transcript['file_path']);
}

// 2. If file not found, search in course directories and check for files with timestamp prefixes
if (!file_exists($file_path)) {
    // Try to find by transcript ID in all course directories
    $possible_files = [];
    
    // First, check the transcripts directory for exact match
    $possible_files = glob($base_upload_path . '/transcripts/' . $filename);
    
    // If not found, look for files with timestamp prefix
    if (empty($possible_files)) {
        $base_filename = pathinfo($filename, PATHINFO_FILENAME);
        $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Check in transcripts directory with any prefix
        $possible_files = glob($base_upload_path . '/transcripts/*' . $base_filename . '*.' . $file_extension);
        
        // If still not found, search in course directories
        if (empty($possible_files)) {
            $course_dirs = glob($base_upload_path . '/course_*');
            foreach ($course_dirs as $course_dir) {
                // Check in notes and syllabus subdirectories
                $possible_files = array_merge(
                    $possible_files,
                    glob($course_dir . '/notes/*' . $base_filename . '*.' . $file_extension),
                    glob($course_dir . '/syllabus/*' . $base_filename . '*.' . $file_extension)
                );
            }
        }
    }
    
    if (!empty($possible_files)) {
        // Sort by modification time to get the most recent file first
        usort($possible_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $file_path = $possible_files[0];
        $filename = basename($file_path);
    } else {
        // Log the error for debugging
        error_log("Transcript file not found for ID: " . $transcript_id . ", Filename: " . $filename);
        header('Location: debug_transcript_download.php?id=' . $transcript_id);
        exit();
    }
}

// If we couldn't determine a better filename, generate one
if (empty($filename)) {
    $filename = 'Transcript_' . preg_replace('/[^a-z0-9]/i', '_', $transcript['full_name']) . '_' . date('Y-m-d', strtotime($transcript['issue_date'])) . '.pdf';
}

// Set appropriate headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, must-revalidate');
header('Pragma: private');
header('Expires: 0');

// Output the PDF
readfile($file_path);
exit();
?>
