<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

$page_title = 'Grade Submission';
$submission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get submission details
$query = "SELECT s.*, a.title as assignment_title, c.title as course_title, 
          u.full_name as student_name, u.email as student_email
          FROM assignment_submissions s
          JOIN assignments a ON s.assignment_id = a.id
          JOIN courses c ON a.course_id = c.id
          JOIN users u ON s.student_id = u.id
          WHERE s.id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    setFlashMessage('danger', 'Submission not found.');
    redirect('submissions.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug log
    error_log("Form submitted with data: " . print_r($_POST, true));
    
    // Get submission ID from either POST or GET
    $submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
    
    if ($submission_id === 0) {
        error_log("Error: No submission ID provided in form submission");
        setFlashMessage('danger', 'Error: No submission ID provided');
        redirect('submissions.php');
    }
    $grade = (int)$_POST['grade'];
    $feedback = sanitize($_POST['feedback'] ?? '');
    
    // Validate grade
    if ($grade < 0 || $grade > 100) {
        setFlashMessage('danger', 'Grade must be between 0 and 100.');
    } else {
        $stmt = $conn->prepare("UPDATE assignment_submissions SET 
                              marks_obtained = ?, feedback = ?, status = 'graded', 
                              graded_at = NOW(), graded_by = ? 
                              WHERE id = ?");
        $stmt->bind_param("isii", $grade, $feedback, $_SESSION['user_id'], $submission_id);
        
        if ($stmt->execute()) {
            // Get assignment and course details for the success message
            $query = "SELECT a.title as assignment_title, c.title as course_title 
                     FROM assignments a 
                     JOIN courses c ON a.course_id = c.id 
                     WHERE a.id = ?";
            $stmt2 = $conn->prepare($query);
            $stmt2->bind_param("i", $submission['assignment_id']);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $details = $result->fetch_assoc();
            $stmt2->close();
            
            $assignment_name = htmlspecialchars($details['assignment_title']);
            $course_name = htmlspecialchars($details['course_title']);
            
            setFlashMessage('success', "Successfully graded submission for <strong>$assignment_name</strong> in <strong>$course_name</strong>");
            logActivity($_SESSION['user_id'], 'submission_graded', 
                      "Graded submission for $assignment_name (Course: $course_name) with grade: $grade/100");
            redirect("submissions.php?assignment_id=" . $submission['assignment_id']);
        } else {
            setFlashMessage('danger', 'Error grading submission: ' . $conn->error);
        }
    }
}

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="submissions.php">Submissions</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Grade Submission</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">Grade Submission</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Submission Details</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">Student:</th>
                            <td>
                                <?= htmlspecialchars($submission['student_name']) ?>
                                <div class="text-muted small"><?= htmlspecialchars($submission['student_email']) ?></div>
                            </td>
                        </tr>
                        <tr>
                            <th>Course:</th>
                            <td><?= htmlspecialchars($submission['course_title']) ?></td>
                        </tr>
                        <tr>
                            <th>Assignment:</th>
                            <td><?= htmlspecialchars($submission['assignment_title']) ?></td>
                        </tr>
                        <tr>
                            <th>Submitted:</th>
                            <td><?= date('F j, Y \a\t g:i A', strtotime($submission['submitted_at'])) ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge bg-<?= 
                                    $submission['status'] === 'graded' ? 'success' : 
                                    ($submission['status'] === 'late' ? 'warning' : 'primary') 
                                ?>">
                                    <?= ucfirst($submission['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (isset($submission['marks_obtained']) && $submission['marks_obtained'] !== null): ?>
                            <tr>
                                <th>Current Grade:</th>
                                <td>
                                    <span class="badge bg-<?= 
                                        $submission['marks_obtained'] >= 70 ? 'success' : 
                                        ($submission['marks_obtained'] >= 50 ? 'warning' : 'danger') 
                                    ?>">
                                        <?= $submission['marks_obtained'] ?>/100
                                    </span>
                                    <?php if (isset($submission['graded_at']) && $submission['graded_at']): ?>
                                        <div class="text-muted small mt-1">
                                            Graded on <?= date('M j, Y', strtotime($submission['graded_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <h5 class="mt-4 mb-3">Submitted Files</h5>
                    <?php if (!empty($submission['file_path'])): ?>
                        <div class="mb-3">
                            <?php
                            // Handle file path - use the full path from the database
                            $file_path = !empty($submission['file_path']) ? '..' . str_replace($_SERVER['DOCUMENT_ROOT'], '', $submission['file_path']) : '';
                            $file_exists = !empty($file_path) && file_exists($file_path);
                            $file_ext = $file_exists ? strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) : '';
                            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            $is_pdf = $file_ext === 'pdf';
                            ?>
                            
                            <?php if ($file_exists && $is_image): ?>
                                <div class="mb-3">
                                    <img src="<?= htmlspecialchars($file_path) ?>" class="img-fluid" alt="Submission">
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-<?= $is_pdf ? 'pdf' : 'alt' ?> fa-2x me-3 text-danger"></i>
                                <div>
                                    <div><?= htmlspecialchars(basename($submission['file_path'])) ?></div>
                                    <div class="text-muted small">
                                        <?= $file_exists ? round(filesize($file_path) / 1024, 1) . ' KB' : 'File not found' ?>
                                    </div>
                                </div>
                                <div class="ms-auto">
                                    <a href="download_submission.php?id=<?= $submission['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <?php if ($is_pdf): ?>
                                        <a href="view_pdf.php?file=<?= urlencode($file_path) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">No files were submitted.</div>
                    <?php endif; ?>

                    <?php if (!empty($submission['submission_text'])): ?>
                        <h5 class="mt-4 mb-3">Submission Text</h5>
                        <div class="border p-3 bg-light rounded">
                            <?= nl2br(htmlspecialchars($submission['submission_text'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Grade & Feedback</h6>
                </div>
                <div class="card-body">
                    <?php 
                    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                    ?>
                    <form method="POST" action="<?= htmlspecialchars($current_url) ?>">
                        <input type="hidden" name="submission_id" value="<?= $submission_id ?>">
                        <div class="mb-3">
                            <label for="grade" class="form-label">Grade (0-100)</label>
                            <input type="number" class="form-control" id="grade" name="grade" 
                                   min="0" max="100" required 
                                   value="<?= htmlspecialchars($submission['marks_obtained'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="feedback" class="form-label">Feedback</label>
                            <textarea class="form-control" id="feedback" name="feedback" rows="6" 
                                      placeholder="Provide detailed feedback for the student..."><?= 
                                htmlspecialchars($submission['feedback'] ?? '') 
                            ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Grade
                            </button>
                            <a href="submissions.php?assignment_id=<?= $submission['assignment_id'] ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Submissions
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($submission['feedback'])): ?>
                <div class="card shadow mb-4 border-left-success">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-success">Previous Feedback</h6>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted mb-2">
                            Last updated: <?= date('F j, Y \a\t g:i A', strtotime($submission['graded_at'])) ?>
                        </div>
                        <div class="border-start border-success border-3 ps-3">
                            <?= nl2br(htmlspecialchars($submission['feedback'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
