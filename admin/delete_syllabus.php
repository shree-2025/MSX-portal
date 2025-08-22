<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

// Get syllabus ID from the query string
$syllabusId = (int)($_GET['id'] ?? 0);
$courseId = (int)($_GET['course_id'] ?? 0);

if ($syllabusId < 1 || $courseId < 1) {
    setFlashMessage('error', 'Invalid request.');
    header("Location: course_materials.php?course_id=$courseId");
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // First, get the file path
    $stmt = $conn->prepare("SELECT file_path FROM syllabus WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $syllabusId, $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Syllabus not found.');
    }
    
    $syllabus = $result->fetch_assoc();
    $filePath = __DIR__ . '/..' . $syllabus['file_path'];
    
    // Delete the syllabus record
    $stmt = $conn->prepare("DELETE FROM syllabus WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $syllabusId, $courseId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete syllabus record.');
    }
    
    // If we got here, commit the transaction
    $conn->commit();
    
    // Delete the actual file if it exists
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    
    setFlashMessage('success', 'Syllabus deleted successfully!');
} catch (Exception $e) {
    $conn->rollback();
    setFlashMessage('error', 'Error deleting syllabus: ' . $e->getMessage());
}

// Redirect back to the course materials page
header("Location: course_materials.php?course_id=$courseId");
exit();
?>
