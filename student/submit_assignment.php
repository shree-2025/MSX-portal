<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); // Disable displaying errors directly to output
ini_set('log_errors', 1); // Log errors to the server's error log
error_reporting(E_ALL);

// Centralized error response function
function send_json_error($statusCode, $message, $details = null) {
    http_response_code($statusCode);
    $response = ['success' => false, 'message' => $message];
    if ($details) {
        // Log the detailed error for debugging
        error_log($message . ' | Details: ' . (is_array($details) || is_object($details) ? json_encode($details) : $details));
        // For the client, we can decide to show simplified details
        $response['error'] = $details; 
    }
    echo json_encode($response);
    exit;
}

// Set custom error and exception handlers
set_exception_handler(function($exception) {
    send_json_error(500, 'An unexpected server exception occurred.', $exception->getMessage());
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Don't handle suppressed errors
    if (!(error_reporting() & $errno)) {
        return false;
    }
    $errorDetails = [
        'type' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ];
    send_json_error(500, 'A server error occurred.', $errorDetails);
    return true; // Mark as handled
});

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// ✅ Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error(405, 'Method not allowed');
}

// ✅ Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    send_json_error(403, 'Unauthorized');
}

// ✅ Get form data
$assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$student_id = $_SESSION['user_id'];

// Validate assignment exists and is active
$stmt = $conn->prepare("SELECT id, due_date FROM assignments WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    send_json_error(404, 'Assignment not found or inactive');
}

// ✅ Check if deadline passed
if (strtotime($assignment['due_date']) < time()) {
    send_json_error(400, 'Deadline has passed, submission not allowed');
}

// ✅ Check if assignment already submitted
$stmt = $conn->prepare("SELECT id, file_path FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
$stmt->bind_param("ii", $assignment_id, $student_id);
$stmt->execute();
$existing_submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ✅ Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    send_json_error(400, 'Please select a file to upload', $_FILES['file']['error'] ?? 'No file data');
}

$file = $_FILES['file'];

// ✅ Validate file type and size
$allowed_types = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain',
    'application/zip'
];
$max_size = 10 * 1024 * 1024; // 10MB

if (!in_array($file['type'], $allowed_types)) {
    send_json_error(400, 'Invalid file type. Allowed: PDF, DOC, DOCX, TXT, ZIP', ['provided_type' => $file['type']]);
}

if ($file['size'] > $max_size) {
    send_json_error(400, 'File size exceeds 10MB limit', ['file_size' => $file['size']]);
}

// ✅ Create uploads directory if not exists
$upload_dir = __DIR__ . '/../uploads/assignments/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ✅ Generate unique filename
$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'submission_' . $student_id . '_' . $assignment_id . '_' . time() . '.' . $file_ext;
$filepath = $upload_dir . $filename;

// ✅ Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    send_json_error(500, 'Failed to move uploaded file.', error_get_last());
}

// ✅ Start transaction
$conn->begin_transaction();

try {
    if ($existing_submission) {
        // Delete old file
        if ($existing_submission['file_path'] && file_exists($existing_submission['file_path'])) {
            unlink($existing_submission['file_path']);
        }

        // Update submission
        $query = "UPDATE assignment_submissions 
                  SET file_path = ?, notes = ?, submitted_at = NOW(), status = 'submitted', marks_obtained = NULL
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $filepath, $notes, $existing_submission['id']);
        $stmt->execute();
        $submission_id = $existing_submission['id'];
        $stmt->close();

    } else {
        // Insert new submission
        $query = "INSERT INTO assignment_submissions 
                 (assignment_id, student_id, file_path, notes, submitted_at, status) 
                 VALUES (?, ?, ?, ?, NOW(), 'submitted')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiss", $assignment_id, $student_id, $filepath, $notes);
        $stmt->execute();
        $submission_id = $stmt->insert_id;
        $stmt->close();
    }

    // ✅ Log activity
    $activity_type = $existing_submission ? 'assignment_resubmitted' : 'assignment_submitted';
    $activity_details = json_encode([
        'assignment_id' => $assignment_id,
        'submission_id' => $submission_id
    ]);

    $query = "INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $student_id, $activity_type, $activity_details);
    $stmt->execute();
    $stmt->close();

    // ✅ Commit transaction
    $conn->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $existing_submission ? 'Assignment re-submitted successfully' : 'Assignment submitted successfully',
        'submission_id' => $submission_id
    ]);

} catch (Exception $e) {
    $conn->rollback();

    if (file_exists($filepath)) {
        unlink($filepath);
    }

    send_json_error(500, 'Database error occurred', $e->getMessage());
}
?>
