<?php
require_once __DIR__ . '/includes/header.php';

// Get course ID from URL
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$course_id) {
    header("Location: my_courses.php");
    exit();
}

// Fetch course details
$course_query = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$course_query->bind_param("i", $course_id);
$course_query->execute();
$course = $course_query->get_result()->fetch_assoc();

// Initialize teacher name
$course['teacher_name'] = 'Not Assigned';

// Get teacher name if teacher_id exists
if (!empty($course['teacher_id'])) {
    $teacher_query = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'teacher'");
    $teacher_query->bind_param("i", $course['teacher_id']);
    $teacher_query->execute();
    $teacher = $teacher_query->get_result()->fetch_assoc();
    if ($teacher && isset($teacher['name'])) {
        $course['teacher_name'] = $teacher['name'];
    }
}

if (!$course) {
    $_SESSION['error'] = "Course not found.";
    header("Location: my_courses.php");
    exit();
}
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo isset($course['name']) ? htmlspecialchars($course['name']) : 'Course Materials'; ?></h1>
    </div>

    <!-- Course Info Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Course Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Course Code:</strong> <?php echo htmlspecialchars($course['code']); ?></p>
                    <p><strong>Teacher:</strong> <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Materials -->
    <div class="row">
        <!-- Syllabus -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Syllabus</h6>
                    <i class="fas fa-book"></i>
                </div>
                <div class="card-body">
                    <?php 
                    // Fetch syllabus for this course
                    $syllabus_query = $conn->prepare("SELECT * FROM syllabus WHERE course_id = ? ORDER BY created_at DESC LIMIT 1");
                    $syllabus_query->bind_param("i", $course_id);
                    $syllabus_query->execute();
                    $syllabus_result = $syllabus_query->get_result();
                    $syllabus = $syllabus_result->fetch_assoc();
                    
                    if ($syllabus && !empty($syllabus['file_path'])): ?>
                        <p class="card-text">Download the course syllabus for detailed information.</p>
                        <div class="d-flex gap-2">
                            <a href="download_syllabus.php?id=<?php echo $syllabus['id']; ?>" 
                               class="btn btn-primary flex-grow-1" title="Download as PDF">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                            <!-- <a href="<?php echo htmlspecialchars($syllabus['file_path']); ?>" 
                               class="btn btn-outline-primary flex-grow-1" download
                               title="Download Original">
                                <i class="fas fa-download"></i> Original
                            </a> -->
                        </div>
                        <?php if (!empty($syllabus['description'])): ?>
                            <p class="mt-2 small text-muted"><?php echo htmlspecialchars($syllabus['description']); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="card-text text-muted">No syllabus has been uploaded for this course yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-success">Course Notes</h6>
                    <i class="fas fa-sticky-note"></i>
                </div>
                <div class="card-body">
                    <?php
                    $notes_query = $conn->prepare("SELECT * FROM notes WHERE course_id = ?");
                    $notes_query->bind_param("i", $course_id);
                    $notes_query->execute();
                    $notes = $notes_query->get_result();
                    
                    if ($notes->num_rows > 0): ?>
                        <p class="card-text">Download course notes and study materials.</p>
                        <div class="list-group">
                            <?php while ($note = $notes->fetch_assoc()): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($note['title']); ?>
                                    <div class="btn-group">
                                        <a href="download_note.php?id=<?php echo $note['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Download as PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <!-- <a href="<?php echo htmlspecialchars($note['file_path']); ?>" 
                                           class="btn btn-sm btn-outline-success" download title="Download Original">
                                            <i class="fas fa-download"></i>
                                        </a> -->
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="card-text text-muted">No notes have been uploaded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Assignments -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-info">Assignments</h6>
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="card-body">
                    <?php
                    $assignments_query = $conn->prepare("SELECT * FROM assignments WHERE course_id = ? AND (due_date >= CURDATE() OR due_date IS NULL)");
                    $assignments_query->bind_param("i", $course_id);
                    $assignments_query->execute();
                    $assignments = $assignments_query->get_result();
                    
                    if ($assignments->num_rows > 0): ?>
                        <p class="card-text">View and submit your assignments.</p>
                        <div class="list-group">
                            <?php while ($assignment = $assignments->fetch_assoc()): ?>
                                <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" 
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                    <span class="badge bg-<?php echo strtotime($assignment['due_date']) > time() ? 'primary' : 'danger'; ?> rounded-pill">
                                        <?php echo $assignment['due_date'] ? 'Due: ' . date('M d', strtotime($assignment['due_date'])) : 'No Deadline'; ?>
                                    </span>
                                </a>
                            <?php endwhile; ?>
                        </div>
                        <a href="assignments.php" class="btn btn-outline-info btn-block mt-3">
                            View All Assignments <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <p class="card-text text-muted">No assignments have been posted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tests -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-warning">Tests & Quizzes</h6>
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="card-body">
                    <?php
                    $tests_query = $conn->prepare("SELECT * FROM tests WHERE course_id = ? AND (end_date >= NOW() OR end_date IS NULL)");
                    $tests_query->bind_param("i", $course_id);
                    $tests_query->execute();
                    $tests = $tests_query->get_result();
                    
                    if ($tests->num_rows > 0): ?>
                        <p class="card-text">Upcoming tests and quizzes for this course.</p>
                        <div class="list-group">
                            <?php while ($test = $tests->fetch_assoc()): 
                                $testEndDate = !empty($test['end_date']) ? strtotime($test['end_date']) : null;
                                $isActive = $testEndDate ? ($testEndDate > time()) : true;
                            ?>
                                <a href="view_test.php?id=<?php echo $test['id']; ?>" 
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($test['title']); ?>
                                    <?php if (!empty($test['end_date'])): ?>
                                        <span class="badge bg-<?php echo $isActive ? 'warning' : 'secondary'; ?> text-dark">
                                            <?php echo $isActive ? 'Active' : 'Ended'; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            <?php endwhile; ?>
                        </div>
                        <a href="tests.php" class="btn btn-outline-warning btn-block mt-3">
                            View All Tests <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <p class="card-text text-muted">No upcoming tests or quizzes.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Submitted Assignments -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">My Submissions</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Submitted On</th>
                            <th>Status</th>
                            <th>Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $submissions_query = $conn->prepare("SELECT s.*, a.title as assignment_title 
                                                          FROM assignment_submissions s 
                                                          JOIN assignments a ON s.assignment_id = a.id 
                                                          WHERE s.student_id = ? AND a.course_id = ?");
                        $submissions_query->bind_param("ii", $_SESSION['user_id'], $course_id);
                        $submissions_query->execute();
                        $submissions = $submissions_query->get_result();
                        
                        if ($submissions->num_rows > 0) {
                            while ($submission = $submissions->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($submission['assignment_title']) . "</td>";
                                echo "<td>" . date('M d, Y H:i', strtotime($submission['submitted_at'])) . "</td>";
                                echo "<td><span class='badge badge-" . 
                                     ($submission['status'] == 'graded' ? 'success' : 'warning') . "'>" . 
                                     ucfirst($submission['status']) . "</span></td>";
                                echo "<td>";
                                $score = $submission['score'] ?? null;
                                $maxScore = $submission['max_score'] ?? null;
                                
                                if ($score !== null && $maxScore !== null) {
                                    echo htmlspecialchars($score) . " / " . htmlspecialchars($maxScore);
                                } elseif ($score !== null) {
                                    echo htmlspecialchars($score);
                                } else {
                                    echo "-";
                                }
                                echo "</td>";
                                echo "<td>";
                                if (!empty($submission['file_path'])) {
                                    echo "<a href='" . htmlspecialchars($submission['file_path']) . "' class='btn btn-sm btn-primary' download>";
                                    echo "<i class='fas fa-download'></i> Download";
                                    echo "</a> ";
                                }
                                echo "<a href='view_assignment.php?id=" . $submission['id'] . "' class='btn btn-sm btn-info'>";
                                echo "<i class='fas fa-eye'></i> View";
                                echo "</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center'>No submissions found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
