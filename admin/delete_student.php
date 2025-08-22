<?php
require_once '../config/config.php';
require_once '../includes/auth_functions.php';
requireLogin();
requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid student ID.');
    header("Location: students.php");
    exit();
}

$student_id = (int)$_GET['id'];

// Begin transaction
$conn->begin_transaction();

try {
    // Delete from student_courses first (foreign key constraint)
    $stmt = $conn->prepare("DELETE FROM student_courses WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    
    // Delete from users
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $conn->commit();
        setFlashMessage('success', 'Student deleted successfully.');
    } else {
        throw new Exception('Student not found or already deleted.');
    }
} catch (Exception $e) {
    $conn->rollback();
    setFlashMessage('error', 'Failed to delete student: ' . $e->getMessage());
}

header("Location: students.php");
exit();
