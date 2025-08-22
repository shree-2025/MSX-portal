<?php
// Ensure no output is sent before headers
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Clear any previous output
ob_clean();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notes'])) {
    $feedback_id = filter_input(INPUT_POST, 'feedback_id', FILTER_VALIDATE_INT);
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    if (!$feedback_id) {
        $_SESSION['error'] = 'Invalid feedback ID';
        header('Location: feedback_management.php');
        exit();
    }
    
    $query = "UPDATE feedback SET admin_notes = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt === false) {
        $_SESSION['error'] = 'Failed to prepare statement: ' . $conn->error;
        header("Location: feedback_details.php?id=" . $feedback_id);
        exit();
    }
    
    $stmt->bind_param("si", $admin_notes, $feedback_id);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        $_SESSION['success'] = 'Admin notes updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update admin notes: ' . $conn->error;
    }
    
    header("Location: feedback_details.php?id=" . $feedback_id);
    exit();
} else {
    header('Location: feedback_management.php');
    exit();
}
