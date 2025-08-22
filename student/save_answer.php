<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate input
$attempt_id = (int)($_POST['attempt_id'] ?? 0);
$question_id = (int)($_POST['question_id'] ?? 0);
$answer = $_POST['answer'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$attempt_id || !$question_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    // Verify the attempt belongs to the user
    $stmt = $conn->prepare("
        SELECT 1 FROM test_attempts ta
        WHERE ta.id = ? AND ta.user_id = ? AND ta.status = 'in_progress'
    ");
    $stmt->bind_param("ii", $attempt_id, $user_id);
    $stmt->execute();
    
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception('Invalid attempt or attempt already submitted');
    }
    
    // Check if answer already exists
    $stmt = $conn->prepare("
        SELECT id FROM test_answers 
        WHERE attempt_id = ? AND question_id = ?
    ");
    $stmt->bind_param("ii", $attempt_id, $question_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        // Update existing answer
        $stmt = $conn->prepare("
            UPDATE test_answers 
            SET answer = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("si", $answer, $existing['id']);
    } else {
        // Insert new answer
        $stmt = $conn->prepare("
            INSERT INTO test_answers 
            (attempt_id, question_id, answer, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("iis", $attempt_id, $question_id, $answer);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save answer');
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
