<?php
// Start output buffering to prevent headers already sent error
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config and auth functions from the correct path
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is a student
if (!isLoggedIn() || !isStudent()) {
    if (!headers_sent()) {
        header("Location: /login.php");
        exit();
    } else {
        echo '<script>window.location.href = "/login.php";</script>';
        exit();
    }
}

$studentId = $_SESSION['user_id'];
$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify student is enrolled in this course and get course details in one query
$enrollmentQuery = $conn->prepare(
    "SELECT c.*, sc.enrollment_date, sc.completion_date, sc.status as enrollment_status 
    FROM courses c
    JOIN student_courses sc ON c.id = sc.course_id
    WHERE sc.student_id = ? AND sc.course_id = ? AND sc.status = 'active'"
);
$enrollmentQuery->bind_param("ii", $studentId, $courseId);
$enrollmentQuery->execute();
$result = $enrollmentQuery->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('error', 'You are not enrolled in this course or access is denied.');
    header("Location: dashboard.php");
    exit();
}

$course = $result->fetch_assoc();

// Get course materials
$materials = [
    'syllabus' => [],
    'notes' => [],
    'assignments' => [],
    'tests' => []
];

// Get syllabus with file info
$syllabusQuery = $conn->prepare("
    SELECT s.*, 
           s.file_path as filepath,
           s.title as filename,
           'syllabus' as file_type,
           (SELECT file_size FROM (SELECT s2.id, LENGTH(s2.file_path) as file_size FROM syllabus s2 WHERE s2.id = s.id) as t) as filesize
    FROM syllabus s 
    WHERE s.course_id = ? AND s.status = 'active'"
);
$syllabusQuery->bind_param("i", $courseId);
$syllabusQuery->execute();
$materials['syllabus'] = $syllabusQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// Get notes with file info and teacher details
$notesQuery = $conn->prepare("
    SELECT n.*, 
           n.file_path as filepath,
           n.title as filename,
           'note' as file_type,
           (SELECT file_size FROM (SELECT n2.id, LENGTH(n2.file_path) as file_size FROM notes n2 WHERE n2.id = n.id) as t) as filesize,
           u.full_name as teacher_name
    FROM notes n 
    LEFT JOIN users u ON n.teacher_id = u.id
    WHERE n.course_id = ? AND n.status = 'active'"
);
$notesQuery->bind_param("i", $courseId);
$notesQuery->execute();
$materials['notes'] = $notesQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// Get assignments with submission status and due date info
$assignmentsQuery = $conn->prepare("
    SELECT 
        a.*,
        (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submission_status,
        (SELECT submitted_at FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submitted_at,
        (SELECT marks_obtained FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as marks_obtained,
        (SELECT feedback FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as feedback,
        CASE 
            WHEN a.due_date < NOW() THEN 'overdue'
            WHEN a.due_date < DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 'due_soon'
            ELSE 'upcoming'
        END as due_status
    FROM assignments a 
    WHERE a.course_id = ? AND a.status = 'active'"
);
$assignmentsQuery->bind_param("iiiis", $studentId, $studentId, $studentId, $studentId, $courseId);
$assignmentsQuery->execute();
$materials['assignments'] = $assignmentsQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// Get tests with submission status
$testsQuery = $conn->prepare("
    SELECT 
        t.*,
        (SELECT status FROM test_submissions WHERE test_id = t.id AND student_id = ?) as submission_status,
        (SELECT score FROM test_submissions WHERE test_id = t.id AND student_id = ?) as score,
        CASE 
            WHEN t.start_date > NOW() THEN 'upcoming'
            WHEN t.end_date < NOW() THEN 'completed'
            ELSE 'ongoing'
        END as test_status
    FROM tests t 
    WHERE t.course_id = ? AND t.status = 'active'"
);
$testsQuery->bind_param("iis", $studentId, $studentId, $courseId);
$testsQuery->execute();
$materials['tests'] = $testsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <!-- Course Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h1 class="h3 mb-1"><?= htmlspecialchars($course['title']) ?></h1>
            <p class="text-muted mb-0">Course Code: <?= htmlspecialchars($course['code'] ?? 'N/A') ?></p>
            <div class="mt-2">
                <span class="badge bg-<?= $course['enrollment_status'] === 'active' ? 'success' : 'secondary' ?> me-2">
                    <?= ucfirst($course['enrollment_status']) ?>
                </span>
                <small class="text-muted">
                    Enrolled on <?= date('M j, Y', strtotime($course['enrollment_date'])) ?>
                </small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="#" class="btn btn-outline-primary">
                <i class="fas fa-download me-1"></i> Course Materials
            </a>
            <a href="#" class="btn btn-primary">
                <i class="fas fa-graduation-cap me-1"></i> Continue Learning
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Course Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="courseTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                <i class="fas fa-home me-1"></i> Overview
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="syllabus-tab" data-bs-toggle="tab" data-bs-target="#syllabus" type="button" role="tab">
                <i class="fas fa-book me-1"></i> Syllabus
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab">
                <i class="fas fa-sticky-note me-1"></i> Notes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignments" type="button" role="tab">
                <i class="fas fa-tasks me-1"></i> Assignments
                <?php if (!empty($materials['assignments'])): ?>
                    <span class="badge bg-danger rounded-pill"><?= count($materials['assignments']) ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tests-tab" data-bs-toggle="tab" data-bs-target="#tests" type="button" role="tab">
                <i class="fas fa-question-circle me-1"></i> Tests
                <?php if (!empty($materials['tests'])): ?>
                    <span class="badge bg-danger rounded-pill"><?= count($materials['tests']) ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="courseTabsContent">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">About This Course</h5>
                            <div class="mb-4">
                                <?= !empty($course['description']) ? nl2br(htmlspecialchars($course['description'])) : 'No description available.' ?>
                            </div>
                            
                            <h5 class="card-title mb-3">What You'll Learn</h5>
                            <div class="row g-3">
                                <?php
                                $learningOutcomes = !empty($course['learning_outcomes']) 
                                    ? json_decode($course['learning_outcomes'], true) 
                                    : [
                                        'Master key concepts and skills',
                                        'Complete hands-on projects',
                                        'Earn a certificate of completion',
                                        'Build a portfolio'
                                    ];
                                
                                foreach ($learningOutcomes as $outcome):
                                ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-check-circle text-success mt-1 me-2"></i>
                                        <span><?= htmlspecialchars($outcome) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Course Progress -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">Your Progress</h5>
                                <span class="badge bg-primary"><?= $course['progress'] ?? 0 ?>% Complete</span>
                            </div>
                            <div class="progress mb-3" style="height: 10px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $course['progress'] ?? 0 ?>%;" 
                                     aria-valuenow="<?= $course['progress'] ?? 0 ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Started on <?= date('M j, Y', strtotime($course['enrollment_date'])) ?></span>
                                <span>Estimated completion: <?= date('M j, Y', strtotime('+3 months', strtotime($course['enrollment_date']))) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Instructor Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($course['instructor_name'] ?? 'Instructor') ?>" 
                                     class="rounded-circle border" width="100" height="100" alt="Instructor">
                            </div>
                            <h5 class="mb-1"><?= htmlspecialchars($course['instructor_name'] ?? 'Course Instructor') ?></h5>
                            <p class="text-muted small mb-3">Instructor</p>
                            <p class="card-text small">
                                <?= !empty($course['instructor_bio']) 
                                    ? htmlspecialchars($course['instructor_bio']) 
                                    : 'Experienced educator with years of teaching experience in this field.' ?>
                            </p>
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope me-1"></i> Message
                            </a>
                        </div>
                    </div>
                    
                    <!-- Course Details -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Course Details</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="fas fa-calendar-alt text-muted me-2"></i>
                                    <strong>Duration:</strong> <?= $course['duration'] ?? '12 weeks' ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-tasks text-muted me-2"></i>
                                    <strong>Assignments:</strong> <?= count($materials['assignments']) ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-question-circle text-muted me-2"></i>
                                    <strong>Tests:</strong> <?= count($materials['tests']) ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-file-alt text-muted me-2"></i>
                                    <strong>Notes:</strong> <?= count($materials['notes']) ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-users text-muted me-2"></i>
                                    <strong>Enrolled Students:</strong> 125
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-language text-muted me-2"></i>
                                    <strong>Language:</strong> <?= $course['language'] ?? 'English' ?>
                                </li>
                                <li class="mb-0">
                                    <i class="fas fa-certificate text-muted me-2"></i>
                                    <strong>Certificate:</strong> Yes
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Syllabus Tab -->
        <div class="tab-pane fade" id="syllabus" role="tabpanel" aria-labelledby="syllabus-tab">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">Course Syllabus</h5>
                        <a href="#" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-download me-1"></i> Download All
                        </a>
                    </div>
                    
                    <?php if (!empty($materials['syllabus'])): ?>
                        <div class="list-group">
                            <?php foreach ($materials['syllabus'] as $syllabus): ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded p-2 me-3">
                                                <i class="fas fa-file-pdf text-danger fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($syllabus['title']) ?></h6>
                                                <p class="text-muted small mb-0">
                                                    <?= !empty($syllabus['description']) ? htmlspecialchars($syllabus['description']) : '' ?>
                                                </p>
                                                <?php if (!empty($syllabus['filesize'])): ?>
                                                    <small class="text-muted">
                                                        <?= formatFileSize($syllabus['filesize']) ?> • 
                                                        <?= !empty($syllabus['updated_at']) ? 'Updated ' . date('M j, Y', strtotime($syllabus['updated_at'])) : '' ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="/download.php?type=syllabus&id=<?= $syllabus['id'] ?>">
                                                        <i class="fas fa-download me-2"></i> Download
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#previewModal" 
                                                       data-title="<?= htmlspecialchars($syllabus['title']) ?>" 
                                                       data-url="/preview.php?type=syllabus&id=<?= $syllabus['id'] ?>">
                                                        <i class="fas fa-eye me-2"></i> Preview
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-book text-muted fa-4x"></i>
                            </div>
                            <h5>No syllabus available</h5>
                            <p class="text-muted">The course syllabus hasn't been uploaded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Notes Tab -->
        <div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">Course Notes</h5>
                        <a href="#" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-download me-1"></i> Download All
                        </a>
                    </div>
                    
                    <?php if (!empty($materials['notes'])): ?>
                        <div class="list-group">
                            <?php foreach ($materials['notes'] as $note): ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded p-2 me-3">
                                                <i class="fas fa-file-alt text-primary fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($note['title']) ?></h6>
                                                <?php if (!empty($note['teacher_name'])): ?>
                                                    <p class="text-muted small mb-1">By <?= htmlspecialchars($note['teacher_name']) ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($note['description'])): ?>
                                                    <p class="text-muted small mb-0"><?= htmlspecialchars($note['description']) ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($note['filesize'])): ?>
                                                    <small class="text-muted">
                                                        <?= formatFileSize($note['filesize']) ?> • 
                                                        <?= !empty($note['created_at']) ? 'Posted ' . date('M j, Y', strtotime($note['created_at'])) : '' ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="/download.php?type=notes&id=<?= $note['id'] ?>">
                                                        <i class="fas fa-download me-2"></i> Download
                                                    </a>
                                                </li>
                                                <?php if (!empty($note['filepath']) && in_array(pathinfo($note['filepath'], PATHINFO_EXTENSION), ['pdf', 'jpg', 'jpeg', 'png'])): ?>
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#previewModal" 
                                                       data-title="<?= htmlspecialchars($note['title']) ?>" 
                                                       data-url="/preview.php?type=notes&id=<?= $note['id'] ?>">
                                                        <i class="fas fa-eye me-2"></i> Preview
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-sticky-note text-muted fa-4x"></i>
                            </div>
                            <h5>No notes available</h5>
                            <p class="text-muted">The course notes haven't been uploaded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Assignments Tab -->
        <div class="tab-pane fade" id="assignments" role="tabpanel" aria-labelledby="assignments-tab">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">Assignments</h5>
                        <a href="#" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus me-1"></i> Submit Assignment
                        </a>
                    </div>
                    
                    <?php if (!empty($materials['assignments'])): ?>
                        <div class="list-group">
                            <?php foreach ($materials['assignments'] as $assignment): 
                                $dueDate = new DateTime($assignment['due_date']);
                                $now = new DateTime();
                                $isOverdue = $dueDate < $now;
                                $dueInDays = $now->diff($dueDate)->days;
                                $dueInHours = $now->diff($dueDate)->h + ($now->diff($dueDate)->days * 24);
                                
                                // Determine status badge
                                $statusClass = 'secondary';
                                $statusText = 'Pending';
                                $statusIcon = 'clock';
                                
                                if (!empty($assignment['submission_status'])) {
                                    if ($assignment['submission_status'] === 'submitted') {
                                        $statusClass = 'info';
                                        $statusText = 'Submitted';
                                        $statusIcon = 'check-circle';
                                        if (!is_null($assignment['marks_obtained'])) {
                                            $statusClass = 'success';
                                            $statusText = 'Graded: ' . $assignment['marks_obtained'] . '/' . $assignment['total_marks'];
                                            $statusIcon = 'award';
                                        }
                                    } elseif ($assignment['submission_status'] === 'late') {
                                        $statusClass = 'warning';
                                        $statusText = 'Submitted Late';
                                        $statusIcon = 'exclamation-circle';
                                    }
                                } elseif ($isOverdue) {
                                    $statusClass = 'danger';
                                    $statusText = 'Overdue';
                                    $statusIcon = 'exclamation-triangle';
                                } elseif ($dueInDays <= 1) {
                                    $statusClass = 'warning';
                                    $statusText = 'Due Soon';
                                    $statusIcon = 'clock';
                                }
                                
                                // Format due date
                                $dueDateFormatted = $dueDate->format('M j, Y \a\t g:i A');
                                
                                // Calculate time remaining
                                $timeRemaining = '';
                                if ($isOverdue) {
                                    $timeRemaining = 'Overdue by ';
                                    $diff = $now->diff($dueDate);
                                    if ($diff->y > 0) $timeRemaining .= $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
                                    elseif ($diff->m > 0) $timeRemaining .= $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                                    elseif ($diff->d > 0) $timeRemaining .= $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
                                    else $timeRemaining .= $diff->h . ' hour' . ($diff->h != 1 ? 's' : '');
                                    $timeRemaining .= ' ago';
                                } else {
                                    $timeRemaining = 'Due in ';
                                    if ($dueInDays > 1) {
                                        $timeRemaining .= $dueInDays . ' days';
                                    } else {
                                        $timeRemaining .= $dueInHours . ' hours';
                                    }
                                }
                            ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="d-flex align-items-start">
                                            <div class="bg-light rounded p-2 me-3">
                                                <i class="fas fa-tasks text-<?= $statusClass ?> fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($assignment['title']) ?></h6>
                                                <p class="text-muted small mb-1">
                                                    <?= nl2br(htmlspecialchars($assignment['description'] ?? 'No description provided.')) ?>
                                                </p>
                                                <div class="d-flex flex-wrap align-items-center gap-3">
                                                    <span class="badge bg-<?= $statusClass ?>">
                                                        <i class="fas fa-<?= $statusIcon ?> me-1"></i> <?= $statusText ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <i class="far fa-calendar-alt me-1"></i> <?= $dueDateFormatted ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i> <?= $timeRemaining ?>
                                                    </small>
                                                    <?php if (!empty($assignment['submitted_at'])): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-paper-plane me-1"></i> 
                                                            Submitted on <?= date('M j, Y \a\t g:i A', strtotime($assignment['submitted_at'])) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if (!is_null($assignment['marks_obtained'])): ?>
                                                        <small class="text-success">
                                                            <i class="fas fa-check-circle me-1"></i> 
                                                            Graded: <?= $assignment['marks_obtained'] ?>/<?= $assignment['total_marks'] ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($assignment['feedback'])): ?>
                                                    <div class="mt-2 p-2 bg-light rounded small">
                                                        <strong>Feedback:</strong> <?= nl2br(htmlspecialchars($assignment['feedback'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if (empty($assignment['submission_status']) || $assignment['submission_status'] !== 'submitted'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="submit_assignment.php?id=<?= $assignment['id'] ?>">
                                                            <i class="fas fa-upload me-2"></i> Submit Work
                                                        </a>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <a class="dropdown-item" href="view_submission.php?assignment_id=<?= $assignment['id'] ?>">
                                                            <i class="fas fa-eye me-2"></i> View Submission
                                                        </a>
                                                    </li>
                                                    <?php if (!$isOverdue): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="submit_assignment.php?id=<?= $assignment['id'] ?>&resubmit=1">
                                                                <i class="fas fa-redo me-2"></i> Resubmit
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#assignmentDetailsModal" 
                                                       data-title="<?= htmlspecialchars($assignment['title']) ?>"
                                                       data-description="<?= htmlspecialchars($assignment['description']) ?>"
                                                       data-duedate="<?= $dueDateFormatted ?>"
                                                       data-points="<?= $assignment['total_marks'] ?>"
                                                       data-attachments="<?= !empty($assignment['attachments']) ? 'Yes' : 'No' ?>">
                                                        <i class="fas fa-info-circle me-2"></i> View Details
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-tasks text-muted fa-4x"></i>
                            </div>
                            <h5>No assignments yet</h5>
                            <p class="text-muted">Check back later for new assignments.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tests Tab -->
        <div class="tab-pane fade" id="tests" role="tabpanel" aria-labelledby="tests-tab">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">Tests & Quizzes</h5>
                        <?php if (!empty($materials['tests'])): ?>
                            <div class="dropdown
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-sort me-1"></i> Sort By
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" data-sort="date">Date</a></li>
                                    <li><a class="dropdown-item" href="#" data-sort="title">Title</a></li>
                                    <li><a class="dropdown-item" href="#" data-sort="status">Status</a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($materials['tests'])): ?>
                        <div class="list-group">
                            <?php foreach ($materials['tests'] as $test): 
                                $now = new DateTime();
                                $testDate = new DateTime($test['test_date']);
                                $isUpcoming = $testDate > $now;
                                $isOngoing = $testDate <= $now && $testDate->modify('+' . $test['duration_minutes'] . ' minutes') > $now;
                                $isCompleted = !empty($test['submission_id']);
                                
                                // Determine status
                                $statusClass = 'secondary';
                                $statusText = 'Upcoming';
                                $statusIcon = 'clock';
                                $testAction = 'Start Test';
                                $testHref = '#';
                                
                                if ($isOngoing) {
                                    $statusClass = 'primary';
                                    $statusText = 'In Progress';
                                    $statusIcon = 'hourglass-half';
                                    $testAction = 'Continue Test';
                                    $testHref = 'take_test.php?id=' . $test['id'];
                                } elseif ($isCompleted) {
                                    $statusClass = $test['score'] >= $test['passing_score'] ? 'success' : 'danger';
                                    $statusText = $test['score'] >= $test['passing_score'] ? 'Passed' : 'Failed';
                                    $statusIcon = $test['score'] >= $test['passing_score'] ? 'check-circle' : 'times-circle';
                                    $testAction = 'View Results';
                                    $testHref = 'test_results.php?id=' . $test['id'];
                                } else if (!$isUpcoming && !$isOngoing && !$isCompleted) {
                                    $statusClass = 'danger';
                                    $statusText = 'Missed';
                                    $statusIcon = 'times';
                                    $testAction = 'View Details';
                                    $testHref = 'test_details.php?id=' . $test['id'];
                                }
                                
                                // Format test date and time
                                $testDateFormatted = $testDate->format('M j, Y \a\t g:i A');
                                $duration = $test['duration_minutes'] . ' min';
                                if ($test['duration_minutes'] >= 60) {
                                    $hours = floor($test['duration_minutes'] / 60);
                                    $minutes = $test['duration_minutes'] % 60;
                                    $duration = $hours . 'h ' . ($minutes > 0 ? $minutes . 'm' : '');
                                }
                            ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="d-flex align-items-start">
                                            <div class="bg-light rounded p-2 me-3">
                                                <i class="fas fa-file-alt text-<?= $statusClass ?> fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($test['title']) ?></h6>
                                                <p class="text-muted small mb-2">
                                                    <?= nl2br(htmlspecialchars($test['description'] ?? 'No description provided.')) ?>
                                                </p>
                                                <div class="d-flex flex-wrap align-items-center gap-3">
                                                    <span class="badge bg-<?= $statusClass ?>">
                                                        <i class="fas fa-<?= $statusIcon ?> me-1"></i> <?= $statusText ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <i class="far fa-calendar-alt me-1"></i> <?= $testDateFormatted ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i> Duration: <?= $duration ?>
                                                    </small>
                                                    <?php if ($isCompleted): ?>
                                                        <small class="text-<?= $statusClass ?>">
                                                            <i class="fas fa-chart-bar me-1"></i> 
                                                            Score: <?= $test['score'] ?? 'N/A' ?>/<?= $test['total_marks'] ?>
                                                            <?php if (isset($test['passing_score'])): ?>
                                                                (<?= $test['score'] >= $test['passing_score'] ? 'Passed' : 'Failed' ?>)
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="window.location.href='<?= $testHref ?>'"
                                                    <?= ($isUpcoming || $statusText === 'Missed') ? 'disabled' : '' ?>>
                                                <i class="fas fa-<?= $isCompleted ? 'eye' : 'play' ?> me-1"></i> <?= $testAction ?>
                                            </button>
                                            <?php if ($isUpcoming): ?>
                                                <button class="btn btn-sm btn-outline-secondary ms-2" 
                                                        data-bs-toggle="tooltip" 
                                                        title="Test available on <?= $testDateFormatted ?>">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-file-alt text-muted fa-4x"></i>
                            </div>
                            <h5>No tests scheduled yet</h5>
                            <p class="text-muted">Check back later for upcoming tests and quizzes.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
