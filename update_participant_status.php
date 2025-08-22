<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_functions.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$meeting_id = $input['meeting_id'] ?? null;
$user_id = $input['user_id'] ?? null;

if (!$meeting_id || !$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

try {
    // Update participant status to left
    $stmt = $conn->prepare("
        UPDATE meeting_participants 
        SET status = 'left', left_at = NOW()
        WHERE meeting_id = ? AND user_id = ?
    ");
    $stmt->bind_param('ii', $meeting_id, $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
