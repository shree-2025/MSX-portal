<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Set JSON content type
header('Content-Type: application/json');

// Log the request
file_put_contents('feedback_debug.log', "[" . date('Y-m-d H:i:s') . "] New request: " . print_r($_GET, true) . "\n", FILE_APPEND);

try {
    // Ensure this is an AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        throw new Exception('Direct access not allowed');
    }

    // Check if user is admin
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }

    // Get feedback ID from request
    $feedback_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$feedback_id) {
        throw new Exception('Invalid feedback ID');
    }

    // Fetch feedback details with all fields
    $query = "SELECT 
                f.*, 
                u.full_name, 
                u.email,
                f.content_relevance,
                f.instructor_effectiveness,
                f.confidence_application,
                f.materials_helpfulness,
                f.suggestions_improvement
              FROM feedback f 
              JOIN users u ON f.student_id = u.id 
              WHERE f.id = ?";

    file_put_contents('feedback_debug.log', "[" . date('Y-m-d H:i:s') . "] Query: $query\n", FILE_APPEND);
    file_put_contents('feedback_debug.log', "[" . date('Y-m-d H:i:s') . "] Feedback ID: $feedback_id\n", FILE_APPEND);

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $stmt->bind_param("i", $feedback_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $feedback = $result->fetch_assoc();
        
        // Format dates for display
        $feedback['created_at_formatted'] = date('M d, Y h:i A', strtotime($feedback['created_at']));
        $feedback['updated_at_formatted'] = $feedback['updated_at'] 
            ? date('M d, Y h:i A', strtotime($feedback['updated_at'])) 
            : 'N/A';
        
        $response = [
            'success' => true,
            'data' => $feedback
        ];
        
        file_put_contents('feedback_debug.log', "[" . date('Y-m-d H:i:s') . "] Response: " . json_encode($response) . "\n", FILE_APPEND);
        echo json_encode($response);
    } else {
        throw new Exception('Feedback not found');
    }
    
} catch (Exception $e) {
    $error = [
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    file_put_contents('feedback_debug.log', "[" . date('Y-m-d H:i:s') . "] Error: " . print_r($error, true) . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode($error);
} finally {
    if (isset($stmt) && $stmt) $stmt->close();
    if (isset($conn) && $conn) $conn->close();
}
?>
