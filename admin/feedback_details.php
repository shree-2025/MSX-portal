<?php
require_once __DIR__ . '/includes/header.php';
requireAdmin();

// Get feedback ID from URL
$feedback_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$feedback_id) {
    $_SESSION['error'] = 'Invalid feedback ID';
    header('Location: feedback_management.php');
    exit();
}

// Fetch feedback details
$query = "SELECT 
            f.*, 
            u.full_name, 
            u.email
          FROM feedback f 
          JOIN users u ON f.student_id = u.id 
          WHERE f.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $feedback_id);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to fetch feedback details';
    header('Location: feedback_management.php');
    exit();
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Feedback not found';
    header('Location: feedback_management.php');
    exit();
}

$feedback = $result->fetch_assoc();
$stmt->close();

// Format dates
$created_at = date('M d, Y h:i A', strtotime($feedback['created_at']));
$updated_at = $feedback['updated_at'] ? date('M d, Y h:i A', strtotime($feedback['updated_at'])) : 'N/A';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Feedback Details</h4>
                    <a href="feedback_management.php" class="btn btn-secondary btn-sm float-right">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Student Information</h5>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($feedback['full_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($feedback['email']); ?></p>
                            <?php if (!empty($feedback['course_id'])): ?>
                                <p><strong>Course ID:</strong> <?php echo htmlspecialchars($feedback['course_id']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-md-right">
                            <h5>Submission Details</h5>
                            <p><strong>Submitted:</strong> <?php echo $created_at; ?></p>
                            <p><strong>Last Updated:</strong> <?php echo $updated_at; ?></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label><strong>Subject:</strong></label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($feedback['subject'] ?? 'No subject'); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label><strong>Message:</strong></label>
                                <textarea class="form-control" rows="4" readonly><?php echo htmlspecialchars($feedback['message'] ?? 'No message provided.'); ?></textarea>
                            </div>

                            <?php if (!empty($feedback['content_relevance'])): ?>
                            <div class="form-group">
                                <label><strong>Content Relevance:</strong></label>
                                <p><?php echo htmlspecialchars($feedback['content_relevance']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($feedback['instructor_effectiveness'])): ?>
                            <div class="form-group">
                                <label><strong>Instructor Effectiveness:</strong></label>
                                <p><?php echo htmlspecialchars($feedback['instructor_effectiveness']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($feedback['confidence_application'])): ?>
                            <div class="form-group">
                                <label><strong>Confidence in Application:</strong></label>
                                <p><?php echo htmlspecialchars($feedback['confidence_application']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($feedback['materials_helpfulness'])): ?>
                            <div class="form-group">
                                <label><strong>Materials Helpfulness:</strong></label>
                                <p><?php echo htmlspecialchars($feedback['materials_helpfulness']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($feedback['suggestions_improvement'])): ?>
                            <div class="form-group">
                                <label><strong>Suggestions for Improvement:</strong></label>
                                <p><?php echo nl2br(htmlspecialchars($feedback['suggestions_improvement'])); ?></p>
                            </div>
                            <?php endif; ?>

                            <form method="POST" action="update_feedback.php" class="mt-4">
                                <input type="hidden" name="feedback_id" value="<?php echo $feedback_id; ?>">
                                <div class="form-group">
                                    <label for="admin_notes"><strong>Admin Notes:</strong></label>
                                    <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                              placeholder="Add your notes here..."><?php echo htmlspecialchars($feedback['admin_notes'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" name="update_notes" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Notes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
