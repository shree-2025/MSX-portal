<?php
require_once __DIR__ . '/includes/header.php';

// Validate assignment ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid assignment ID');
    redirect('assignments.php');
}

$assignment_id = intval($_GET['id']);
$student_id = $_SESSION['user_id'];

// Get assignment details
$query = "SELECT a.*, c.title as course_title, c.id as course_id
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN student_courses sc ON c.id = sc.course_id AND sc.student_id = ?
          WHERE a.id = ? AND a.status = 'active'";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    setFlashMessage('error', 'Assignment not found or access denied');
    redirect('assignments.php');
}

// Get student's submission
$query = "SELECT * FROM assignment_submissions 
          WHERE assignment_id = ? AND student_id = ?
          ORDER BY submission_date DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $assignment_id, $student_id);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Format dates
$due_date = new DateTime($assignment['due_date']);
$now = new DateTime();
$is_past_due = $due_date < $now;
$can_submit = !$submission || ($assignment['allow_resubmission'] && $is_past_due === false);
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <?php echo htmlspecialchars($assignment['title']); ?>
                <span class="badge bg-<?php echo $is_past_due ? 'danger' : 'primary'; ?> ms-2">
                    Due: <?php echo $due_date->format('M d, Y'); ?>
                </span>
            </h1>
            <p class="text-muted">
                Course: <?php echo htmlspecialchars($assignment['course_title']); ?>
            </p>
        </div>
        <div>
            <?php if ($can_submit): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitModal">
                    <i class="fas fa-upload me-1"></i>
                    <?php echo $submission ? 'Resubmit' : 'Submit Assignment'; ?>
                </button>
            <?php endif; ?>
            <a href="assignments.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Assignment Details -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Assignment Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5>Description</h5>
                        <div class="border rounded p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submission Status -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">My Submission</h6>
                </div>
                <div class="card-body">
                    <?php if ($submission): ?>
                        <div class="mb-3">
                            <h6>Status</h6>
                            <?php if ($submission['marks_obtained'] !== null): ?>
                                <span class="badge bg-success">Graded</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Submitted</span>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <h6>Submitted File</h6>
                            <div class="border rounded p-2 bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-file me-2"></i>
                                        <?php echo basename($submission['file_path']); ?>
                                    </div>
                                    <a href="download.php?file=<?php echo urlencode($submission['file_path']); ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <?php if ($submission['marks_obtained'] !== null): ?>
                            <div class="mb-3">
                                <h6>Grade</h6>
                                <div class="d-flex align-items-center">
                                    <div class="progress flex-grow-1 me-3" style="height: 20px;">
                                        <?php 
                                        $percentage = ($submission['marks_obtained'] / $assignment['max_marks']) * 100;
                                        $progress_class = $percentage < 40 ? 'bg-danger' : ($percentage < 70 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress-bar <?php echo $progress_class; ?>" 
                                             style="width: <?php echo $percentage; ?>%">
                                            <?php echo round($percentage); ?>%
                                        </div>
                                    </div>
                                    <div class="text-nowrap">
                                        <?php echo $submission['marks_obtained']; ?>/<?php echo $assignment['max_marks']; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                            <h5>No Submission</h5>
                            <p class="text-muted">
                                <?php echo $is_past_due ? 'The due date has passed.' : 'Submit your work before the due date.'; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submit Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="submitForm" action="submit_assignment.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="submissionFile" class="form-label">
                            Upload your work <span class="text-danger">*</span>
                        </label>
                        <input class="form-control" type="file" id="submissionFile" name="file" required>
                        <div class="form-text">
                            PDF, DOC, DOCX, TXT, or ZIP (Max: 10MB)
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="submissionNotes" class="form-label">
                            Notes (Optional)
                        </label>
                        <textarea class="form-control" id="submissionNotes" name="notes" rows="3" 
                                 placeholder="Add any additional notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('submitForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;

    form.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

        const fileInput = document.getElementById('submissionFile');
        const file = fileInput.files[0];

        // Client-side validation
        if (!file) {
            alert('Please select a file to upload.');
            return;
        }

        const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/zip', 'application/x-zip-compressed'];
        const maxSize = 10 * 1024 * 1024; // 10MB

        if (!validTypes.includes(file.type)) {
            alert('Invalid file type. Allowed: PDF, DOC, DOCX, TXT, ZIP. You provided: ' + file.type);
            return;
        }

        if (file.size > maxSize) {
            alert('File size exceeds 10MB limit.');
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';

        const formData = new FormData(form);

        fetch('submit_assignment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Assignment submitted successfully!');
                window.location.reload(); // Reload to see updated status
            } else {
                // Display the specific error from the server
                let errorMessage = data.message || 'An unknown error occurred.';
                if (data.error) {
                    // For developers, log detailed error
                    console.error('Server Error:', data.error);
                    // For users, show a cleaner message if possible
                    if (typeof data.error === 'string') {
                        errorMessage += `\nDetails: ${data.error}`;
                    }
                }
                alert('Submission Failed: ' + errorMessage);
            }
        })
        .catch(error => {
            console.error('Submission Fetch Error:', error);
            alert('An error occurred while submitting the assignment. Please check the console for details.');
        })
        .finally(() => {
            // Restore button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
