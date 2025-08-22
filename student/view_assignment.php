<?php
require_once __DIR__ . '/includes/header.php';

// Check if assignment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid assignment ID.');
    redirect('assignments.php');
}

$assignment_id = (int)$_GET['id'];
$student_id = $_SESSION['user_id'];

// Get assignment details with submission status
$query = "SELECT a.*, c.title as course_title, 
          s.id as submission_id, s.submission_date, s.marks_obtained, s.status as submission_status,
          s.file_path as submission_file, s.feedback,
          a.status as assignment_status, c.status as course_status
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN student_courses sc ON c.id = sc.course_id AND sc.student_id = ?
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
          WHERE a.id = ? AND sc.status = 'active'
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $student_id, $student_id, $assignment_id);

// Debug: Log the query and parameters
error_log("Assignment Query: " . $query);
error_log("Params: student_id=$student_id, assignment_id=$assignment_id");
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    setFlashMessage('error', 'Assignment not found or you do not have permission to view it.');
    redirect('assignments.php');
}

// Check if assignment is active
$is_assignment_active = ($assignment['assignment_status'] === 'active');
$is_course_active = ($assignment['course_status'] === 'active');

// Check if form was submitted for submission/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['submission_file'])) {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $target_dir = __DIR__ . "/../uploads/assignments/";
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            $error_msg = "Failed to create upload directory. Please check permissions.";
            error_log($error_msg);
            setFlashMessage('error', $error_msg);
            redirect("view_assignment.php?id=" . $assignment_id);
        }
    }
    
    // Check for upload errors
    if ($_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "File upload error: " . $_FILES['submission_file']['error'];
        error_log($error_msg);
        setFlashMessage('error', 'Failed to upload file. Please try again.');
        redirect("view_assignment.php?id=" . $assignment_id);
    }
    
    $file_extension = strtolower(pathinfo($_FILES["submission_file"]["name"], PATHINFO_EXTENSION));
    $file_name = "submission_{$assignment_id}_student_{$student_id}_" . time() . "." . $file_extension;
    $target_file = $target_dir . $file_name;
    
    $upload_ok = 1;
    $error_msg = '';
    
    // Check file size (5MB max)
    if ($_FILES["submission_file"]["size"] > 5000000) {
        $error_msg = "Sorry, your file is too large. Maximum size is 5MB.";
        $upload_ok = 0;
    }
    
    // Allow certain file formats
    $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
    if (!in_array($file_extension, $allowed_types)) {
        $error_msg = "Sorry, only PDF, DOC, DOCX, TXT, ZIP, RAR files are allowed.";
        $upload_ok = 0;
    }
    
    if ($upload_ok) {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Move the uploaded file
            if (move_uploaded_file($_FILES["submission_file"]["tmp_name"], $target_file)) {
                $file_path = "uploads/assignments/" . $file_name;
                $now = date('Y-m-d H:i:s');
                
                if (!empty($assignment['submission_id'])) {
                    // Get old file path before updating
                    $old_file_query = "SELECT file_path FROM assignment_submissions WHERE id = ?";
                    $stmt = $conn->prepare($old_file_query);
                    $stmt->bind_param("i", $assignment['submission_id']);
                    $stmt->execute();
                    $old_file = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    // Update existing submission
                    $query = "UPDATE assignment_submissions 
                             SET file_path = ?, submission_date = ?, status = 'submitted'
                             WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssi", $file_path, $now, $assignment['submission_id']);
                } else {
                    // Create new submission
                    $query = "INSERT INTO assignment_submissions 
                             (assignment_id, student_id, file_path, submission_date, status, submitted_at)
                             VALUES (?, ?, ?, ?, 'submitted', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iisss", $assignment_id, $student_id, $file_path, $now, $now);
                }
                
                if ($stmt->execute()) {
                    // If update was successful, delete old file if it exists
                    if (!empty($old_file) && !empty($old_file['file_path']) && file_exists(__DIR__ . '/../' . $old_file['file_path'])) {
                        @unlink(__DIR__ . '/../' . $old_file['file_path']);
                    }
                    
                    $conn->commit();
                    setFlashMessage('success', 'Assignment submitted successfully!');
                    redirect("view_assignment.php?id=" . $assignment_id);
                } else {
                    throw new Exception("Database error: " . $conn->error);
                }
                $stmt->close();
            } else {
                throw new Exception("Failed to move uploaded file. Check directory permissions.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            // Delete the uploaded file if it exists
            if (file_exists($target_file)) {
                @unlink($target_file);
            }
            error_log("Assignment submission error: " . $e->getMessage());
            $error_msg = "An error occurred while processing your submission. Please try again.";
        }
    }
    
    if (!empty($error_msg)) {
        setFlashMessage('error', $error_msg);
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?= htmlspecialchars($assignment['title']) ?>
            <small class="text-muted">
                in <?= htmlspecialchars($assignment['course_title']) ?>
            </small>
        </h1>
        <a href="assignments.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Assignments
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Assignment Details -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Assignment Details</h6>
                    <div>
                        <?php if ($assignment['file_path']): ?>
                            <a href="<?= htmlspecialchars($assignment['file_path']) ?>" class="btn btn-sm btn-primary" download>
                                <i class="fas fa-download"></i> Download Assignment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="text-primary"><?= htmlspecialchars($assignment['title']) ?></h5>
                        <p class="mb-1">
                            <i class="fas fa-book text-gray-500 me-2"></i>
                            <?= htmlspecialchars($assignment['course_title']) ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-calendar-alt text-gray-500 me-2"></i>
                            Due: <?= date('F j, Y, g:i a', strtotime($assignment['due_date'])) ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-tasks text-gray-500 me-2"></i>
                            Status: 
                            <?php 
                            $marks_obtained = $assignment['marks_obtained'] ?? null;
                            $max_marks = $assignment['max_marks'] ?? 0;
                            
                            if ($assignment['submission_status'] === 'graded'): 
                                $grade_display = htmlspecialchars($marks_obtained);
                                if ($max_marks > 0) {
                                    $grade_display .= '/' . htmlspecialchars($max_marks);
                                }
                                ?>
                                <span class="badge bg-success">Graded (<?= $grade_display ?>)</span>
                            <?php elseif ($assignment['submission_status'] === 'submitted'): ?>
                                <span class="badge bg-info">Submitted (Pending Grading)</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Not Submitted</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="mb-4">
                        <h6 class="font-weight-bold">Instructions</h6>
                        <div class="p-3 bg-light rounded">
                            <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                        </div>
                    </div>

                    <?php if ($assignment['submission_status'] === 'graded' && !empty($assignment['feedback'])): ?>
                        <div class="mb-4">
                            <h6 class="font-weight-bold">Instructor Feedback</h6>
                            <div class="p-3 bg-light rounded">
                                <?= nl2br(htmlspecialchars($assignment['feedback'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Submission Form -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?= $assignment['submission_id'] ? 'Update Submission' : 'Submit Assignment' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!$is_assignment_active): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This assignment is currently not active. Submissions may not be accepted.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$is_course_active): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            The course for this assignment is currently not active.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (strtotime($assignment['due_date']) < time() && !$assignment['submission_id']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            The due date for this assignment has passed. Late submissions may not be accepted.
                        </div>
                    <?php endif; ?>

                    <?php if ($assignment['submission_id']): ?>
                        <div class="alert alert-info">
                            <h6>Your Submission</h6>
                            <p class="mb-2">
                                <i class="fas fa-file me-2"></i>
                                <a href="<?= htmlspecialchars($assignment['submission_file']) ?>" target="_blank">
                                    View Submitted File
                                </a>
                            </p>
                            <p class="mb-0">
                                <i class="far fa-clock me-2"></i>
                                Submitted on: <?= date('F j, Y, g:i a', strtotime($assignment['submission_date'])) ?>
                            </p>
                        </div>
                        <p class="text-muted small mb-3">
                            Upload a new file to update your submission. Only the most recent submission will be graded.
                        </p>
                    <?php endif; ?>

                    <?php if ($is_assignment_active && $is_course_active): ?>
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="submission_file" class="form-label">
                                    <?= $assignment['submission_id'] ? 'New Submission File' : 'Upload Your Work' ?>
                                </label>
                                <input class="form-control" type="file" id="submission_file" name="submission_file" required>
                                <div class="form-text">
                                    Accepted formats: PDF, DOC, DOCX, TXT, ZIP, RAR (Max: 5MB)
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>
                                <?= $assignment['submission_id'] ? 'Update Submission' : 'Submit Assignment' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Submissions are currently disabled for this assignment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submission Guidelines -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Submission Guidelines</h6>
                </div>
                <div class="card-body">
                    <ul class="small">
                        <li>Ensure your file is properly named (e.g., YourName_Assignment1.pdf)</li>
                        <li>Check that all required components are included</li>
                        <li>Verify that your submission is not corrupted</li>
                        <li>You can update your submission until the due date</li>
                        <li>Only the most recent submission will be graded</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
