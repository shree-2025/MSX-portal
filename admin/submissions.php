<?php
// Start session and include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

$page_title = 'Assignment Submissions';
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

// Get assignment details if ID is provided
$assignment = null;
if ($assignment_id > 0) {
    $stmt = $conn->prepare("SELECT a.*, c.title as course_title FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.id = ?");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
}

// Handle submission actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['grade_submission'])) {
        $submission_id = (int)$_POST['submission_id'];
        $grade = (int)$_POST['grade'];
        $feedback = sanitize($_POST['feedback'] ?? '');
        
        $stmt = $conn->prepare("UPDATE assignment_submissions SET grade = ?, feedback = ?, status = 'graded', graded_at = NOW() WHERE id = ?");
        $stmt->bind_param("isi", $grade, $feedback, $submission_id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Submission graded successfully!');
            logActivity($_SESSION['user_id'], 'submission_graded', "Graded submission #$submission_id");
        } else {
            setFlashMessage('danger', 'Error grading submission: ' . $conn->error);
        }
        
        redirect("submissions.php" . ($assignment_id ? "?assignment_id=$assignment_id" : ''));
    }
}

// Get submissions with filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$status_filter = $_GET['status'] ?? '';
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Build query
$query = "SELECT s.*, a.title as assignment_title, c.title as course_title, 
          u.full_name as student_name, u.email as student_email
          FROM assignment_submissions s
          JOIN assignments a ON s.assignment_id = a.id
          JOIN courses c ON a.course_id = c.id
          JOIN users u ON s.student_id = u.id
          WHERE 1=1";
          
$params = [];
$types = "";

if ($assignment_id > 0) {
    $query .= " AND s.assignment_id = ?";
    $params[] = $assignment_id;
    $types .= "i";
}

if ($course_filter > 0) {
    $query .= " AND c.id = ?";
    $params[] = $course_filter;
    $types .= "i";
}

if ($status_filter) {
    $query .= " AND s.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . sanitize($_GET['search']) . "%";
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR a.title LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

// Add pagination
$query .= " ORDER BY s.submitted_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get courses for filter
$courses = $conn->query("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title")->fetch_all(MYSQLI_ASSOC);

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">
                <?= $assignment ? 'Submissions: ' . htmlspecialchars($assignment['title']) : 'All Submissions' ?>
            </h1>
            <?php if ($assignment): ?>
                <p class="text-muted">Course: <?= htmlspecialchars($assignment['course_title']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php echo displayFlashMessage(); ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="get" class="row g-2">
                <?php if (!$assignment_id): ?>
                    <div class="col-md-3">
                        <select name="course_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="submitted" <?= $status_filter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="graded" <?= $status_filter === 'graded' ? 'selected' : '' ?>>Graded</option>
                        <option value="late" <?= $status_filter === 'late' ? 'selected' : '' ?>>Late</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <?php if ($assignment_id): ?>
                            <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (count($submissions) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Assignment</th>
                                <?php if (!$assignment_id): ?>
                                    <th>Course</th>
                                <?php endif; ?>
                                <th>Submitted</th>
                                <th>Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sub['student_name']) ?></strong>
                                        <div class="text-muted small"><?= htmlspecialchars($sub['student_email']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($sub['assignment_title']) ?></td>
                                    <?php if (!$assignment_id): ?>
                                        <td><?= htmlspecialchars($sub['course_title']) ?></td>
                                    <?php endif; ?>
                                    <td><?= date('M d, Y', strtotime($sub['submitted_at'])) ?></td>
                                    <td>
                                        <?php if (isset($sub['marks_obtained']) && $sub['marks_obtained'] !== null): ?>
                                            <span class="badge bg-<?= $sub['marks_obtained'] >= 70 ? 'success' : ($sub['marks_obtained'] >= 50 ? 'warning' : 'danger') ?>">
                                                <?= $sub['marks_obtained'] ?>/100
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not graded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_submission.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="grade_submission.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Grade
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No submissions found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
