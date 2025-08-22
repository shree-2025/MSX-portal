<?php
// Start session and include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

$page_title = 'Assignment Details';

// Get assignment ID from URL
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($assignment_id <= 0) {
    setFlashMessage('error', 'Invalid assignment ID.');
    header('Location: assignments.php');
    exit();
}

// Get assignment details with course information
$stmt = $conn->prepare("
    SELECT a.*, c.title as course_title, 
           u.username as created_by_name, 
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count
    FROM assignments a
    LEFT JOIN courses c ON a.course_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    setFlashMessage('error', 'Assignment not found.');
    header('Location: assignments.php');
    exit();
}

// Get submissions count by status
$submissions = [
    'total' => 0,
    'submitted' => 0,
    'graded' => 0,
    'late' => 0
];

$stmt = $conn->prepare("
    SELECT status, COUNT(*) as count 
    FROM assignment_submissions 
    WHERE assignment_id = ? 
    GROUP BY status
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $submissions[$row['status']] = (int)$row['count'];
    $submissions['total'] += (int)$row['count'];
}

// Get recent submissions
$stmt = $conn->prepare("
    SELECT s.*, u.full_name as student_name, u.email as student_email
    FROM assignment_submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.assignment_id = ?
    ORDER BY s.submitted_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$recent_submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Assignment Details</h1>
        <div>
            <a href="edit_assignment.php?id=<?= $assignment['id'] ?>" class="btn btn-primary btn-icon-split">
                <span class="icon text-white-50">
                    <i class="fas fa-edit"></i>
                </span>
                <span class="text">Edit Assignment</span>
            </a>
            <a href="assignments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Assignments
            </a>
        </div>
    </div>

    <?php echo displayFlashMessage(); ?>

    <!-- Assignment Details Card -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Assignment Information</h6>
                    <span class="badge bg-<?= $assignment['status'] === 'published' ? 'success' : ($assignment['status'] === 'draft' ? 'warning' : 'secondary') ?>">
                        <?= ucfirst($assignment['status']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <h4 class="mb-3"><?= htmlspecialchars($assignment['title']) ?></h4>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Course:</strong> <?= htmlspecialchars($assignment['course_title']) ?></p>
                            <p class="mb-1"><strong>Created by:</strong> <?= htmlspecialchars($assignment['created_by_name']) ?></p>
                            <p class="mb-1"><strong>Created at:</strong> <?= date('M d, Y h:i A', strtotime($assignment['created_at'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Due Date:</strong> <?= date('M d, Y h:i A', strtotime($assignment['due_date'])) ?></p>
                            <p class="mb-1"><strong>Total Marks:</strong> <?= $assignment['total_marks'] ?></p>
                            <p class="mb-1"><strong>Last Updated:</strong> <?= date('M d, Y h:i A', strtotime($assignment['updated_at'])) ?></p>
                        </div>
                    </div>

                    <?php if (!empty($assignment['description'])): ?>
                        <div class="mb-4">
                            <h5>Description</h5>
                            <div class="border rounded p-3 bg-light">
                                <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($assignment['file_path']): ?>
                        <div class="mb-4">
                            <h5>Attached File</h5>
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file-alt fa-2x text-primary me-3"></i>
                                    <div>
                                        <div class="font-weight-bold"><?= htmlspecialchars($assignment['file_name']) ?></div>
                                        <div class="text-muted small"><?= formatFileSize($assignment['file_size']) ?></div>
                                    </div>
                                    <div class="ms-auto">
                                        <a href="/uploads/assignments/<?= htmlspecialchars($assignment['file_path']) ?>" class="btn btn-sm btn-primary" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Submissions Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Submissions Summary</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Total Submissions</span>
                            <span class="font-weight-bold"><?= $submissions['total'] ?></span>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= $submissions['total'] > 0 ? 100 : 0 ?>%" 
                                 aria-valuenow="<?= $submissions['total'] ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-1">
                            <span>Graded</span>
                            <span class="font-weight-bold"><?= $submissions['graded'] ?></span>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-info" role="progressbar" 
                                 style="width: <?= $submissions['total'] > 0 ? ($submissions['graded'] / $submissions['total'] * 100) : 0 ?>%" 
                                 aria-valuenow="<?= $submissions['graded'] ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="<?= $submissions['total'] ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-1">
                            <span>Submitted (On Time)</span>
                            <span class="font-weight-bold"><?= $submissions['submitted'] ?></span>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?= $submissions['total'] > 0 ? ($submissions['submitted'] / $submissions['total'] * 100) : 0 ?>%" 
                                 aria-valuenow="<?= $submissions['submitted'] ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="<?= $submissions['total'] ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-1">
                            <span>Late Submissions</span>
                            <span class="font-weight-bold"><?= $submissions['late'] ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" role="progressbar" 
                                 style="width: <?= $submissions['total'] > 0 ? ($submissions['late'] / $submissions['total'] * 100) : 0 ?>%" 
                                 aria-valuenow="<?= $submissions['late'] ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="<?= $submissions['total'] ?>">
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="submissions.php?assignment_id=<?= $assignment['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-tasks"></i> View All Submissions
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Submissions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Submissions</h6>
                </div>
                <div class="card-body">
                    <?php if (count($recent_submissions) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_submissions as $submission): ?>
                                <div class="list-group-item px-0 py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <?= strtoupper(substr($submission['student_name'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="ms-3 flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?= htmlspecialchars($submission['student_name']) ?></h6>
                                                <span class="badge bg-<?= $submission['status'] === 'graded' ? 'success' : ($submission['status'] === 'late' ? 'warning' : 'primary') ?> small">
                                                    <?= ucfirst($submission['status']) ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted">
                                                <?= date('M d, Y h:i A', strtotime($submission['submitted_at'])) ?>
                                                <?php if ($submission['status'] === 'graded'): ?>
                                                    • <?= $submission['marks_obtained'] ?>/<?= $assignment['total_marks'] ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                            <p class="text-muted">No submissions yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
?>
