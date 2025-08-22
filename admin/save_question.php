<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Invalid request method');
    header('Location: tests.php');
    exit();
}

// Get form data
$test_id = (int)$_POST['test_id'];
$question_type = $_POST['question_type'];
$question_text = trim($_POST['question_text']);
$marks = (int)$_POST['marks'];
$explanation = !empty($_POST['explanation']) ? trim($_POST['explanation']) : null;

// Validate required fields
if (empty($question_text) || $marks <= 0) {
    setFlashMessage('error', 'Please fill in all required fields');
    header("Location: manage_questions.php?test_id=$test_id");
    exit();
}

// Prepare data based on question type
$options = null;
$correct_answer = '';

if ($question_type === 'mcq') {
    $options = [];
    $correct_option = (int)($_POST['correct_answer'] ?? 0);
    
    if (!isset($_POST['options']) || count($_POST['options']) < 2) {
        setFlashMessage('error', 'At least 2 options are required for MCQ');
        header("Location: manage_questions.php?test_id=$test_id");
        exit();
    }
    
    foreach ($_POST['options'] as $index => $option) {
        $option_text = trim($option);
        if (!empty($option_text)) {
            $options[] = $option_text;
        }
    }
    
    if (count($options) < 2) {
        setFlashMessage('error', 'At least 2 valid options are required for MCQ');
        header("Location: manage_questions.php?test_id=$test_id");
        exit();
    }
    
    if (!isset($options[$correct_option])) {
        $correct_option = 0; // Default to first option if invalid
    }
    
    $correct_answer = $options[$correct_option];
    $options = json_encode($options);
} elseif ($question_type === 'true_false') {
    $correct_answer = isset($_POST['correct_answer_tf']) ? $_POST['correct_answer_tf'] : 'true';
} elseif ($question_type === 'short_answer') {
    $correct_answer = trim($_POST['correct_answer_sa'] ?? '');
    if (empty($correct_answer)) {
        setFlashMessage('error', 'Please provide a correct answer for the short answer question');
        header("Location: manage_questions.php?test_id=$test_id");
        exit();
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // Insert question into database
    $stmt = $conn->prepare("
        INSERT INTO test_questions 
        (test_id, question_text, question_type, options, correct_answer, marks, explanation)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "issssis",
        $test_id,
        $question_text,
        $question_type,
        $options,
        $correct_answer,
        $marks,
        $explanation
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to save question: " . $stmt->error);
    }
    
    // Update test's total marks
    $update_stmt = $conn->prepare("
        UPDATE tests 
        SET total_marks = total_marks + ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $update_stmt->bind_param("ii", $marks, $test_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update test total marks: " . $update_stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    setFlashMessage('success', 'Question added successfully');
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    setFlashMessage('error', $e->getMessage());
}

header("Location: manage_questions.php?test_id=$test_id");
exit();
