<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth_functions.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Get note ID from URL
$note_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($note_id <= 0) {
    die('Invalid note ID');
}

// Fetch note details from database
$stmt = $conn->prepare("SELECT n.*, c.title as course_title 
                      FROM notes n 
                      JOIN courses c ON n.course_id = c.id 
                      JOIN student_courses sc ON c.id = sc.course_id 
                      WHERE n.id = ? AND sc.student_id = ?");
$stmt->bind_param("ii", $note_id, $_SESSION['user_id']);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();

if (!$note) {
    die('Note not found or you do not have permission to access it');
}

// Check if the file exists
$file_path = __DIR__ . '/..' . $note['file_path'];
if (!file_exists($file_path)) {
    die('File not found');
}

// Get file extension
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// If it's already a PDF, just serve it
if ($file_extension === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($note['title'] . '.pdf') . '"');
    readfile($file_path);
    exit();
}

// For HTML/TXT files, convert to PDF
$content = file_get_contents($file_path);

// Create PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');

// Create HTML content for PDF
$html = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>' . htmlspecialchars($note['title']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .meta { color: #7f8c8d; margin-bottom: 20px; }
        .content { margin-top: 20px; }
    </style>
</head>
<body>
    <h1>' . htmlspecialchars($note['title']) . '</h1>
    <div class="meta">
        <p><strong>Course:</strong> ' . htmlspecialchars($note['course_title']) . '</p>
        <p><strong>Created:</strong> ' . date('F j, Y', strtotime($note['created_at'])) . '</p>
    </div>
    <div class="content">' . 
        ($file_extension === 'html' ? $content : nl2br(htmlspecialchars($content))) . 
    '</div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->render();

// Output the generated PDF to browser
$dompdf->stream(
    preg_replace('/[^A-Za-z0-9_\-\(\)\[\]\{\}]/', '_', $note['title']) . '.pdf',
    array('Attachment' => true)
);

$conn->close();
?>
