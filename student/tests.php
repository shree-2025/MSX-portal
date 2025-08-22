<?php
require_once __DIR__ . '/includes/header.php';

$student_id = $_SESSION['user_id'];
$current_time = date('Y-m-d H:i:s');

// Get student's enrolled courses
$query = "SELECT c.id, c.title, c.code 
          FROM courses c 
          JOIN student_courses sc ON c.id = sc.course_id 
          WHERE sc.student_id = ? AND sc.status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrolled_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$enrolled_course_ids = array_column($enrolled_courses, 'id');
$stmt->close();

// Get available tests for enrolled courses
$available_tests = [];
$upcoming_tests = [];

error_log("Student ID: $student_id");
error_log("Enrolled courses: " . print_r($enrolled_course_ids, true));

if (!empty($enrolled_course_ids)) {
    $placeholders = str_repeat('?,', count($enrolled_course_ids) - 1) . '?';
    
    // Get all published tests for enrolled courses that are within date range
    $query = "SELECT t.*, c.title as course_title, c.code as course_code,
              (SELECT COUNT(*) FROM test_questions WHERE test_id = t.id) as question_count
              FROM tests t
              JOIN courses c ON t.course_id = c.id
              WHERE t.course_id IN ($placeholders)
              AND t.is_active = 1
              AND (t.start_date IS NULL OR t.start_date <= ?)
              AND (t.end_date IS NULL OR t.end_date >= ?)
              ORDER BY t.start_date ASC, t.title ASC";
    
    $types = str_repeat('i', count($enrolled_course_ids)) . 'ss';
    $params = array_merge($enrolled_course_ids, [$current_time, $current_time]);
    
    // Log the prepared query with parameters for debugging
    $debug_query = $query;
    foreach ($params as $param) {
        $debug_query = preg_replace('/\?/', "'" . $param . "'", $debug_query, 1);
    }
    error_log("Test query: " . $debug_query);
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        error_log("Found " . count($tests) . " tests for enrolled courses");
        
        // Process each test to check attempt status
        foreach ($tests as $test) {
            // Get student's attempts for this test
            $attempt_query = "SELECT * FROM test_attempts 
                             WHERE test_id = ? AND student_id = ? 
                             ORDER BY started_at DESC 
                             LIMIT 1";
            $stmt = $conn->prepare($attempt_query);
            $stmt->bind_param("ii", $test['id'], $student_id);
            $stmt->execute();
            $attempt = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Count total attempts
            $count_query = "SELECT COUNT(*) as attempt_count FROM test_attempts 
                           WHERE test_id = ? AND student_id = ?";
            $stmt = $conn->prepare($count_query);
            $stmt->bind_param("ii", $test['id'], $student_id);
            $stmt->execute();
            $attempt_count = $stmt->get_result()->fetch_assoc()['attempt_count'];
            $stmt->close();
            
            $test['last_attempt'] = $attempt;
            $test['can_retake'] = false;
            $test['attempt_count'] = $attempt_count;
            
            if ($attempt) {
                // Check if test can be retaken
                if ($attempt['status'] === 'evaluated' || $attempt['status'] === 'submitted') {
                    // Allow retake if max attempts not reached or not set
                    $max_attempts = $test['max_attempts'] ?? 0;
                    if ($max_attempts == 0 || $attempt_count < $max_attempts) {
                        $test['can_retake'] = true;
                    }
                } elseif ($attempt['status'] === 'in_progress') {
                    // Test is in progress
                    $test['in_progress'] = true;
                }
            }
            
            // Categorize as available or upcoming
            $current_time = time();
            $test_start_time = $test['start_date'] ? strtotime($test['start_date']) : 0;
            $test_end_time = $test['end_date'] ? strtotime($test['end_date']) : PHP_INT_MAX;
            
            error_log(sprintf(
                "Test: %s | Start: %s | End: %s | Now: %s | Active: %s | Course: %s",
                $test['title'],
                $test['start_date'] ?: 'No start date',
                $test['end_date'] ?: 'No end date',
                date('Y-m-d H:i:s'),
                $test['is_active'] ? 'Yes' : 'No',
                $test['course_title']
            ));
            
            if ($test['is_active'] && 
                (empty($test['start_date']) || $test_start_time <= $current_time) &&
                (empty($test['end_date']) || $test_end_time >= $current_time)) {
                $available_tests[] = $test;
                error_log("Added to available tests");
            } elseif ($test['is_active'] && $test_start_time > $current_time) {
                $upcoming_tests[] = $test;
                error_log("Added to upcoming tests");
            } else {
                error_log("Test not added - Active: " . ($test['is_active'] ? 'Yes' : 'No') . ", Start: " . ($test_start_time > $current_time ? 'Future' : 'Past') . ", End: " . ($test_end_time < $current_time ? 'Expired' : 'Active'));
            }
        }
    }
}

$page_title = 'My Tests';
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Tests</h1>
    </div>

    <!-- Available Tests -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-tasks me-2"></i>Available Tests
                <?php if (!empty($available_tests)): ?>
                    <span class="badge bg-primary"><?php echo count($available_tests); ?></span>
                <?php endif; ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($available_tests)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5>No tests available at the moment</h5>
                    <p class="text-muted">Check back later for new tests or contact your instructor.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Course</th>
                                <th>Duration</th>
                                <th>Questions</th>
                                <th>Marks</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_tests as $test): 
                                $attempt = $test['last_attempt'] ?? null;
                                $attempt_count = $test['attempt_count'] ?? 0;
                                $can_attempt = true;
                                $status_text = 'Not Attempted';
                                $status_class = 'secondary';
                                
                                if ($attempt) {
                                    if ($attempt['status'] === 'completed') {
                                        $status_text = 'Completed';
                                        $status_class = $attempt['is_passed'] ? 'success' : 'danger';
                                        $can_attempt = $test['can_retake'];
                                    } elseif ($attempt['status'] === 'in_progress') {
                                        $status_text = 'In Progress';
                                        $status_class = 'warning';
                                    }
                                }
                                
                                // Check if test has questions
                                $has_questions = ($test['question_count'] ?? 0) > 0;
                                $can_attempt = $can_attempt && $has_questions;
                            ?>
                                <tr>
                                    <td>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($test['title']); ?></h6>
                                        <small class="text-muted">
                                            <?php if ($test['start_date']): ?>
                                                Available until: <?php echo date('M j, Y', strtotime($test['end_date'])); ?>
                                            <?php else: ?>
                                                No time limit
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($test['course_code']); ?></small><br>
                                        <?php echo htmlspecialchars($test['course_title']); ?>
                                    </td>
                                    <td><?php echo $test['duration_minutes'] ? $test['duration_minutes'] . ' mins' : 'No limit'; ?></td>
                                    <td><?php echo $test['question_count'] ?? 0; ?></td>
                                    <td>
                                        <?php echo $test['total_marks']; ?> 
                                        <small class="text-muted">(Pass: <?php echo $test['passing_marks']; ?>%)</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                            <?php if ($attempt_count > 0): ?>
                                                (<?php echo $attempt_count; ?>)
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($attempt && $attempt['status'] === 'completed'): ?>
                                            <div class="mt-1">
                                                <small>Score: <strong><?php echo number_format($attempt['score'] ?? 0, 1); ?>%</strong></small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($can_attempt): ?>
                                            <form action="view_test.php" method="POST" style="display: inline-block;">
                                                <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                                                <button type="submit" name="start_attempt" class="btn btn-sm btn-primary">
                                                    <?php echo $attempt ? 'Retake Test' : 'Start Test'; ?>
                                                </button>
                                            </form>
                                        <?php elseif ($test['in_progress'] ?? false): ?>
                                            <a href="view_test.php?attempt_id=<?php echo $attempt['id']; ?>" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-play me-1"></i> Continue
                                            </a>
                                        <?php elseif (!$has_questions): ?>
                                            <span class="text-danger" title="No questions available">
                                                <i class="fas fa-exclamation-triangle"></i> Not Ready
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Attempts exhausted</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($attempt && $attempt['status'] === 'completed'): ?>
                                            <a href="test_result.php?attempt_id=<?php echo $attempt['id']; ?>" 
                                               class="btn btn-sm btn-outline-info mt-1"
                                               title="View Results">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Tests -->
    <?php if (!empty($upcoming_tests)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="far fa-calendar-alt me-2"></i>Upcoming Tests
                    <span class="badge bg-secondary"><?php echo count($upcoming_tests); ?></span>
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Course</th>
                                <th>Starts On</th>
                                <th>Duration</th>
                                <th>Questions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_tests as $test): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($test['title']); ?></td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($test['course_code']); ?></small><br>
                                        <?php echo htmlspecialchars($test['course_title']); ?>
                                    </td>
                                    <td><?php echo date('M j, Y \a\t g:i A', strtotime($test['start_date'])); ?></td>
                                    <td><?php echo $test['duration_minutes'] ? $test['duration_minutes'] . ' mins' : 'No limit'; ?></td>
                                    <td><?php echo $test['question_count'] ?? 0; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>
