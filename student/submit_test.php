<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Invalid request method');
    header('Location: dashboard.php');
    exit();
}

$attempt_id = (int)($_POST['attempt_id'] ?? 0);
$test_id = (int)($_POST['test_id'] ?? 0);
$user_id = $_SESSION['user_id'];

// Verify the attempt belongs to the user and is in progress
$attempt = $conn->query("
    SELECT ta.*, t.total_marks, t.passing_marks, t.course_id
    FROM test_attempts ta
    JOIN tests t ON ta.test_id = t.id
    WHERE ta.id = $attempt_id 
    AND ta.user_id = $user_id 
    AND ta.status = 'in_progress'")->fetch_assoc();

if (!$attempt) {
    setFlashMessage('error', 'Invalid test attempt');
    header('Location: dashboard.php');
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Save all submitted answers
    $answers = $_POST['answers'] ?? [];
    $score = 0;
    $total_questions = 0;
    $correct_answers = 0;
    
    // Get all questions for this test
    $questions = $conn->query("SELECT * FROM test_questions WHERE test_id = $test_id")->fetch_all(MYSQLI_ASSOC);
    $total_questions = count($questions);
    
    foreach ($questions as $question) {
        $question_id = $question['id'];
        $user_answer = $answers[$question_id] ?? '';
        $is_correct = 0;
        
        // Check if answer is correct based on question type
        $correct_answer = trim($question['correct_answer'] ?? '');
        $user_answer = trim($user_answer);
        $is_correct = 0;
        
        error_log("Question ID: {$question['id']}");
        error_log("Correct answer: '$correct_answer'");
        error_log("User answer: '$user_answer'");
        
        // Only process if user provided an answer
        if (!empty($user_answer)) {
            switch ($question['question_type']) {
                case 'mcq':
                    // For MCQ, check if the selected option matches the correct answer
                    $is_correct = (strcasecmp($user_answer, $correct_answer) === 0) ? 1 : 0;
                    break;
                    
                case 'true_false':
                    // For True/False, normalize both answers before comparison
                    $user_tf = strtolower($user_answer[0]); // Get first character (t/f)
                    $correct_tf = strtolower($correct_answer[0] ?? '');
                    $is_correct = ($user_tf === $correct_tf) ? 1 : 0;
                    break;
                    
                case 'short_answer':
                    // For short answer, do a case-insensitive comparison with trimmed whitespace
                    $is_correct = (strcasecmp(trim($user_answer), trim($correct_answer)) === 0) ? 1 : 0;
                    break;
                    
                case 'essay':
                default:
                    // Essays need manual grading
                    $is_correct = 0;
                    break;
            }
            
            error_log("Is correct: " . ($is_correct ? 'Yes' : 'No'));
            
            if ($is_correct) {
                $score += (float)$question['marks'];
                $correct_answers++;
            }
        }
        
        // Save or update the answer
        $stmt = $conn->prepare("
            INSERT INTO test_answers 
            (attempt_id, question_id, answer, is_correct, marks_awarded, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                answer = VALUES(answer),
                is_correct = VALUES(is_correct),
                marks_awarded = IF(marks_awarded IS NULL, VALUES(marks_awarded), marks_awarded),
                updated_at = NOW()
        ");
        
        $marks = $is_correct ? $question['marks'] : 0;
        $stmt->bind_param("iisii", $attempt_id, $question_id, $user_answer, $is_correct, $marks);
        $stmt->execute();
    }
    
    // Calculate percentage score
    $percentage = $attempt['total_marks'] > 0 ? round(($score / $attempt['total_marks']) * 100, 2) : 0;
    $is_passed = $percentage >= $attempt['passing_marks'];
    
    // Update test attempt
    $update_stmt = $conn->prepare("
        UPDATE test_attempts 
        SET completed_at = NOW(),
            status = 'completed',
            score = ?,
            is_passed = ?,
            questions_attempted = ?,
            correct_answers = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $update_stmt->bind_param("diiii", $percentage, $is_passed, $total_questions, $correct_answers, $attempt_id);
    $update_stmt->execute();
    
    // Update course progress if this is the first passing attempt
    if ($is_passed) {
        $conn->query("
            INSERT INTO user_progress 
            (user_id, course_id, test_id, score, completed_at, created_at, updated_at)
            VALUES ($user_id, {$attempt['course_id']}, $test_id, $percentage, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                score = IF(score < VALUES(score), VALUES(score), score),
                completed_at = IF(completed_at IS NULL, VALUES(completed_at), completed_at),
                updated_at = NOW()
        ");
    }
    
    // Commit transaction
    $conn->commit();
    
    // Redirect to results page
    header("Location: test_result.php?attempt_id=$attempt_id");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error submitting test: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while submitting your test. Please try again.');
    header("Location: view_test.php?id=$test_id");
    exit();
}
