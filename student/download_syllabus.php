<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth_functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Get syllabus ID from URL
$syllabus_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($syllabus_id <= 0) {
    die('Invalid syllabus ID');
}

// Fetch syllabus details from database
$stmt = $conn->prepare("SELECT s.*, c.title as course_title 
                      FROM syllabus s 
                      JOIN courses c ON s.course_id = c.id 
                      JOIN student_courses sc ON c.id = sc.course_id 
                      WHERE s.id = ? AND sc.student_id = ?");
$stmt->bind_param("ii", $syllabus_id, $_SESSION['user_id']);
$stmt->execute();
$syllabus = $stmt->get_result()->fetch_assoc();

if (!$syllabus) {
    die('Syllabus not found or you do not have permission to access it');
}

// Check if the file exists
$file_path = __DIR__ . '/..' . $syllabus['file_path'];
if (!file_exists($file_path)) {
    die('File not found at: ' . $file_path);
}

// Set headers for file download
$file_name = basename($syllabus['file_path']);
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// If it's a PDF, serve it directly
if ($file_extension === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $syllabus['title'] . '.pdf"');
    readfile($file_path);
    exit();
}

// For other file types, serve as download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $syllabus['title'] . '.' . $file_extension . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));
ob_clean();
flush();
readfile($file_path);
exit();
?>
