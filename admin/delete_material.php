<?php
// Prevent any output before headers
ob_start();

require_once __DIR__ . '/includes/header.php';
requireAdmin();

// Clear any previous output
ob_clean();

// Get and validate input
$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$courseId = (int)($_GET['course_id'] ?? 0);

if (!in_array($type, ['syllabus', 'notes']) || $id < 1) {
    setFlashMessage('error', 'Invalid request.');
    header("Location: courses.php");
    exit();
}

try {
    // Get the file path before deleting
    $result = $conn->query("SELECT file_path FROM $type WHERE id = $id");
    if ($result->num_rows === 0) {
        throw new Exception(ucfirst($type) . ' not found.');
    }

    $filePath = __DIR__ . '/..' . $result->fetch_assoc()['file_path'];

    // Delete from database
    if ($conn->query("DELETE FROM $type WHERE id = $id")) {
        // Delete the file if it exists
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        setFlashMessage('success', ucfirst($type) . ' deleted successfully.');
    } else {
        throw new Exception('Failed to delete ' . $type . ' from database.');
    }
} catch (Exception $e) {
    setFlashMessage('error', $e->getMessage());
}

header("Location: course_materials.php?course_id=$courseId&tab=$type");
exit();
