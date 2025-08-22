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

// Check if file exists
$file_path = __DIR__ . '/..' . $transcript['file_path'];
if (!file_exists($file_path)) {
    header('Location: transcripts.php?error=file_not_found');
    exit();
}

// Set appropriate headers for PDF display
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($transcript['file_path']) . '"');
header('Content-Length: ' . filesize($file_path));

// Output the PDF
readfile($file_path);
exit();
?>
