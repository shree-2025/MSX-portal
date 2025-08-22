<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Debug: Log session data
error_log('Session data: ' . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    error_log('User not logged in. Redirecting to login.');
    header('Location: login.php');
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Log all request parameters
error_log('Request parameters - GET: ' . print_r($_GET, true));
error_log('Request parameters - POST: ' . print_r($_POST, true));

// Get test_id from either GET or POST
$test_id = null;
if (isset($_POST['test_id']) && is_numeric($_POST['test_id'])) {
    $test_id = (int)$_POST['test_id'];
} elseif (isset($_GET['test_id']) && is_numeric($_GET['test_id'])) {
    $test_id = (int)$_GET['test_id'];
}

if (!$test_id) {
    $error = 'Invalid or missing test ID';
    error_log($error);
    setFlashMessage('error', $error);
    header('Location: tests.php');
    exit();
}
$user_id = $_SESSION['user_id'];
error_log("Processing test_id: $test_id for user_id: $user_id");

// Debug: Log the query being executed
$query = "
    SELECT t.*, c.title as course_title,
           sc.status as enrollment_status,
           (SELECT COUNT(*) FROM test_questions WHERE test_id = t.id) as question_count
    FROM tests t
    JOIN courses c ON t.course_id = c.id
    LEFT JOIN student_courses sc ON (sc.course_id = c.id AND sc.student_id = ?)
    WHERE t.id = ?
    AND (t.status = 'published' OR t.status = 'active')";

// Debug: Log the query with parameters
error_log("Executing query: " . str_replace(['?'], [$user_id, $test_id], $query));

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $test_id);
$stmt->execute();
$result = $stmt->get_result();

// Debug: Log query result or error
if ($result === false) {
    $error = "Query failed: " . $conn->error;
    error_log($error);
    setFlashMessage('error', 'Database error occurred');
    header('Location: dashboard.php');
    exit();
}

$test = $result->fetch_assoc();

// Debug: Log the test data and enrollment status
error_log("=== TEST DATA ===");
error_log("Test ID: " . $test['id']);
error_log("Test Title: " . $test['title']);
error_log("Course ID: " . $test['course_id']);
error_log("Status: " . $test['status']);
error_log("Start Date: " . ($test['start_date'] ?? 'Not set'));
error_log("End Date: " . ($test['end_date'] ?? 'Not set'));
error_log("Enrollment Status: " . ($test['enrollment_status'] ?? 'Not set'));
error_log("Question Count: " . ($test['question_count'] ?? '0'));
error_log("Full Test Data: " . print_r($test, true));

if (!$test) {
    $error = "Test not found. Test ID: $test_id";
    error_log($error);
    setFlashMessage('error', 'Test not found or is no longer available');
    header('Location: tests.php');
    exit();
}

// Check if user is enrolled in the course and enrollment is active
if (empty($test['enrollment_status']) || $test['enrollment_status'] !== 'active') {
    $error = "User $user_id is not enrolled in the course for test $test_id or enrollment is not active";
    error_log($error);
    setFlashMessage('error', 'You are not enrolled in this course or your enrollment is not active');
    header('Location: tests.php');
    exit();
}

// Check if test has questions
if (empty($test['question_count']) || $test['question_count'] == 0) {
    $error = "Test $test_id has no questions";
    error_log($error);
    setFlashMessage('error', 'This test is not available at the moment');
    header('Location: tests.php');
    exit();
}

error_log("Test found: " . print_r($test, true));

// Check test availability dates
$current_time = date('Y-m-d H:i:s');
$start_date = !empty($test['start_date']) && $test['start_date'] !== '0000-00-00 00:00:00' ? $test['start_date'] : null;
$end_date = !empty($test['end_date']) && $test['end_date'] !== '0000-00-00 00:00:00' ? $test['end_date'] : null;

error_log("=== DATE VALIDATION ===");
error_log("Current time: $current_time");
error_log("Test start date: " . ($start_date ?: 'Not set or zero date'));
error_log("Test end date: " . ($end_date ?: 'Not set or zero date'));

// Check if test has started
if ($start_date && $current_time < $start_date) {
    $message = "This test is not available yet. It will start on: " . date('M j, Y g:i A', strtotime($start_date));
    error_log($message);
    setFlashMessage('info', $message);
    header('Location: tests.php');
    exit();
}

// Check if test has ended
if ($end_date && $current_time > $end_date) {
    $message = "This test has ended. It was available until: " . date('M j, Y g:i A', strtotime($end_date));
    error_log($message);
    setFlashMessage('info', $message);
    header('Location: tests.php');
    exit();
}

// Handle test attempt
$attempt = $conn->query("
    SELECT * FROM test_attempts 
    WHERE test_id = $test_id AND student_id = $user_id
    ORDER BY started_at DESC LIMIT 1")->fetch_assoc();

// Count total attempts
$attempt_count = $conn->query("
    SELECT COUNT(*) as count FROM test_attempts 
    WHERE test_id = $test_id AND student_id = $user_id")->fetch_assoc()['count'];

$max_attempts = $test['max_attempts'] ?? 1; // Default to 1 attempt if not set
$can_attempt = ($max_attempts == 0 || $attempt_count < $max_attempts);

// Get the latest attempt
$attempt = $conn->query("
    SELECT * FROM test_attempts 
    WHERE test_id = $test_id AND student_id = $user_id
    ORDER BY started_at DESC 
    LIMIT 1")->fetch_assoc();

// Handle form submission to start a new attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_attempt']) && $can_attempt) {
    // Check if there's an existing in-progress attempt
    if ($attempt && $attempt['status'] === 'in_progress') {
        header("Location: take_test.php?attempt_id=" . $attempt['id']);
        exit();
    }
    
    // Create new test attempt
    $attempt_number = $attempt_count + 1;
    $stmt = $conn->prepare("INSERT INTO test_attempts (test_id, student_id, attempt_number, started_at, status) 
                           VALUES (?, ?, ?, NOW(), 'in_progress')");
    $stmt->bind_param("iii", $test_id, $user_id, $attempt_number);
    
    if ($stmt->execute()) {
        $attempt_id = $stmt->insert_id;
        $stmt->close();
        
        // Redirect to the test interface
        header("Location: take_test.php?attempt_id=" . $attempt_id);
        exit();
    } else {
        setFlashMessage('error', 'Failed to start test. Please try again.');
    }
}

// If there's an in-progress attempt, redirect to it
if ($attempt && $attempt['status'] === 'in_progress') {
    header("Location: take_test.php?attempt_id=" . $attempt['id']);
    exit();
}

// Check if test can be attempted
if (!$can_attempt) {
    setFlashMessage('error', 'You have reached the maximum number of attempts for this test.');
    header('Location: tests.php');
    exit();
}

// Check if the last attempt was completed recently (within 24 hours)
if ($attempt && $attempt['status'] === 'completed') {
    $last_attempt_time = strtotime($attempt['completed_at']);
    $time_since_last_attempt = time() - $last_attempt_time;
    $hours_remaining = ceil((86400 - $time_since_last_attempt) / 3600);
    
    if ($time_since_last_attempt < 86400) { // 24 hours cooldown
        setFlashMessage('info', "You can retake this test in $hours_remaining hours");
        header("Location: test_result.php?attempt_id={$attempt['id']}");
        exit();
    }
}

// Get questions and answers
$questions = $conn->query("SELECT * FROM test_questions WHERE test_id = $test_id ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$answers = [];
$result = $conn->query("SELECT question_id, answer FROM test_answers WHERE attempt_id = $attempt_id");
while ($row = $result->fetch_assoc()) {
    $answers[$row['question_id']] = $row['answer'];
}

// Calculate time remaining
$time_remaining = $test['duration_minutes'] * 60;
if ($attempt['started_at']) {
    $time_elapsed = time() - strtotime($attempt['started_at']);
    $time_remaining = max(0, $time_remaining - $time_elapsed);
}

include_once 'includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><?php echo htmlspecialchars($test['title']); ?></h4>
                    <small class="text-white-50"><?php echo htmlspecialchars($test['course_title']); ?></small>
                </div>
                <div class="card-body">
                    <?php if ($test['description']): ?>
                        <div class="mb-4">
                            <h5>Description</h5>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($test['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Test Details</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>Duration:</strong> <?php echo $test['duration_minutes'] ? $test['duration_minutes'] . ' minutes' : 'No time limit'; ?></li>
                                        <li><strong>Total Marks:</strong> <?php echo $test['total_marks']; ?></li>
                                        <li><strong>Passing Marks:</strong> <?php echo $test['passing_marks']; ?>%</li>
                                        <li><strong>Your Attempts:</strong> <?php echo $attempt_count; ?><?php echo $max_attempts > 0 ? ' of ' . $max_attempts : ''; ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Instructions</h6>
                                    <ol class="mb-0">
                                        <li>Read all questions carefully before answering.</li>
                                        <li>You cannot go back to previous questions after submitting.</li>
                                        <?php if ($test['duration_minutes'] > 0): ?>
                                            <li>You have <?php echo $test['duration_minutes']; ?> minutes to complete the test.</li>
                                        <?php endif; ?>
                                        <li>All answers are final once submitted.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($attempt && $attempt['status'] === 'evaluated'): ?>
                        <div class="alert alert-info">
                            <h5>Your Previous Attempt</h5>
                            <p class="mb-1">
                                <strong>Score:</strong> 
                                <span class="badge bg-<?php echo ($attempt['percentage'] >= $test['passing_marks']) ? 'success' : 'danger'; ?>">
                                    <?php echo number_format($attempt['percentage'], 1); ?>%
                                </span>
                                (<?php echo $attempt['obtained_marks']; ?>/<?php echo $test['total_marks']; ?>)
                            </p>
                            <p class="mb-0"><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $attempt['status'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <?php if ($can_attempt): ?>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="start_attempt" class="btn btn-primary btn-lg">
                                    <i class="fas fa-play-circle me-2"></i>
                                    <?php echo $attempt ? 'Start New Attempt' : 'Start Test'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                You have reached the maximum number of attempts for this test.
                            </div>
                        <?php endif; ?>
                        <a href="tests.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Tests
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <div class="card shadow mb-4">
        <div class="card-body">
            <?php if (!empty($test['instructions'])): ?>
                <div class="alert alert-info"><?php echo nl2br(htmlspecialchars($test['instructions'])); ?></div>
            <?php endif; ?>
            
            <form id="testForm" action="submit_test.php" method="POST">
                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
                
                <div id="questions-container">
                    <?php foreach ($questions as $i => $q): 
                        $options = $q['options'] ? json_decode($q['options'], true) : [];
                        $saved_answer = $answers[$q['id']] ?? '';
                    ?>
                        <div class="question-card mb-4" data-question-id="<?php echo $q['id']; ?>">
                            <h5>Q<?php echo $i+1; ?>. <?php echo htmlspecialchars($q['question_text']); ?></h5>
                            <div class="ms-3">
                                <?php if ($q['question_type'] === 'mcq'): ?>
                                    <?php foreach ($options as $j => $opt): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="answers[<?php echo $q['id']; ?>]" 
                                                   value="<?php echo htmlspecialchars($opt); ?>"
                                                   <?php echo ($saved_answer === $opt) ? 'checked' : ''; ?>>
                                            <label class="form-check-label"><?php echo htmlspecialchars($opt); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif ($q['question_type'] === 'true_false'): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="answers[<?php echo $q['id']; ?>]" 
                                               value="true"
                                               <?php echo ($saved_answer === 'true') ? 'checked' : ''; ?>>
                                        <label class="form-check-label">True</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="answers[<?php echo $q['id']; ?>]" 
                                               value="false"
                                               <?php echo ($saved_answer === 'false') ? 'checked' : ''; ?>>
                                        <label class="form-check-label">False</label>
                                    </div>
                                <?php else: ?>
                                    <textarea class="form-control" 
                                              name="answers[<?php echo $q['id']; ?>]"
                                              rows="<?php echo $q['question_type'] === 'essay' ? 4 : 2; ?>"><?php 
                                        echo htmlspecialchars($saved_answer); 
                                    ?></textarea>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2 save-answer" 
                                        data-question-id="<?php echo $q['id']; ?>">
                                    <i class="fas fa-save"></i> Save
                                </button>
                                <span class="saved-indicator text-success ms-2" style="display: none;">
                                    <i class="fas fa-check"></i> Saved
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-secondary" id="saveAndExit">
                        <i class="fas fa-save"></i> Save & Exit
                    </button>
                    <button type="button" class="btn btn-primary" id="submitTest">
                        <i class="fas fa-paper-plane"></i> Submit Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Timer functionality
let timeLeft = <?php echo $time_remaining; ?>;
const timer = setInterval(updateTimer, 1000);

function updateTimer() {
    if (timeLeft <= 0) {
        clearInterval(timer);
        document.getElementById('testForm').submit();
        return;
    }
    
    const hours = Math.floor(timeLeft / 3600);
    const minutes = Math.floor((timeLeft % 3600) / 60);
    const seconds = timeLeft % 60;
    
    document.getElementById('time-display').textContent = 
        `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    
    timeLeft--;
}

// Save answer via AJAX
document.querySelectorAll('.save-answer').forEach(btn => {
    btn.addEventListener('click', function() {
        const questionId = this.dataset.questionId;
        const formData = new FormData();
        formData.append('attempt_id', <?php echo $attempt_id; ?>);
        formData.append('question_id', questionId);
        formData.append('answer', this.closest('.question-card')
            .querySelector('input[type="radio"]:checked, textarea')?.value || '');
        
        fetch('save_answer.php', {
            method: 'POST',
            body: formData
        })
        .then(() => {
            const indicator = this.nextElementSibling;
            indicator.style.display = 'inline';
            setTimeout(() => indicator.style.display = 'none', 2000);
        });
    });
});

// Save and exit
document.getElementById('saveAndExit').addEventListener('click', () => {
    if (confirm('Your progress will be saved. You can continue later.')) {
        window.location.href = 'dashboard.php';
    }
});

// Submit test
document.getElementById('submitTest').addEventListener('click', () => {
    if (confirm('Are you sure you want to submit? You cannot change your answers after submission.')) {
        document.getElementById('testForm').submit();
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>
