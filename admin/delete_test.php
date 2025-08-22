<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

if (!isset($_POST['test_id']) || !is_numeric($_POST['test_id'])) {
    setFlashMessage('error', 'Invalid test ID');
    header('Location: tests.php');
    exit();
}

$test_id = (int)$_POST['test_id'];

// Start transaction
$conn->begin_transaction();

try {
    // First, delete all questions and their answers
    $delete_answers = $conn->prepare("
        DELETE ta FROM test_answers ta
        JOIN test_questions tq ON ta.question_id = tq.id
        WHERE tq.test_id = ?
    ");
    $delete_answers->bind_param("i", $test_id);
    
    if (!$delete_answers->execute()) {
        throw new Exception("Failed to delete test answers: " . $delete_answers->error);
    }
    
    // Delete all questions
    $delete_questions = $conn->prepare("DELETE FROM test_questions WHERE test_id = ?");
    $delete_questions->bind_param("i", $test_id);
    
    if (!$delete_questions->execute()) {
        throw new Exception("Failed to delete test questions: " . $delete_questions->error);
    }
    
    // Delete test attempts
    $delete_attempts = $conn->prepare("DELETE FROM test_attempts WHERE test_id = ?");
    $delete_attempts->bind_param("i", $test_id);
    
    if (!$delete_attempts->execute()) {
        throw new Exception("Failed to delete test attempts: " . $delete_attempts->error);
    }
    
    // Finally, delete the test
    $delete_test = $conn->prepare("DELETE FROM tests WHERE id = ?");
    $delete_test->bind_param("i", $test_id);
    
    if (!$delete_test->execute()) {
        throw new Exception("Failed to delete test: " . $delete_test->error);
    }
    
    // Commit transaction if all queries were successful
    $conn->commit();
    
    setFlashMessage('success', 'Test and all related data deleted successfully');
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    setFlashMessage('error', $e->getMessage());
}

header('Location: tests.php');
exit();
