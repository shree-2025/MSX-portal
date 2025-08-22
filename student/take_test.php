<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

// Check if attempt_id is provided
if (!isset($_GET['attempt_id']) || !is_numeric($_GET['attempt_id'])) {
    setFlashMessage('error', 'Invalid test attempt');
    header('Location: tests.php');
    exit();
}

$attempt_id = (int)$_GET['attempt_id'];
$user_id = $_SESSION['user_id'];

// Get test attempt details
$stmt = $conn->prepare("
    SELECT ta.*, t.*, c.title as course_title, c.code as course_code,
           t.duration_minutes * 60 as duration_seconds
    FROM test_attempts ta
    JOIN tests t ON ta.test_id = t.id
    JOIN courses c ON t.course_id = c.id
    WHERE ta.id = ? AND ta.student_id = ? AND ta.status = 'in_progress'
");
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('error', 'Test attempt not found or already submitted');
    header('Location: tests.php');
    exit();
}

$test = $result->fetch_assoc();
$stmt->close();

// Calculate time remaining
$time_remaining = $test['duration_seconds'];
if ($test['started_at']) {
    $time_elapsed = time() - strtotime($test['started_at']);
    $time_remaining = max(0, $test['duration_seconds'] - $time_elapsed);
}

// Handle test submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    // Get all questions for this test
    $questions_stmt = $conn->prepare("
        SELECT id, question_text, question_type, marks, correct_answer, options
        FROM test_questions 
        WHERE test_id = ?
    ");
    $questions_stmt->bind_param("i", $test['test_id']);
    $questions_stmt->execute();
    $questions_result = $questions_stmt->get_result();
    
    $total_marks = 0;
    $obtained_marks = 0;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Process each question
        while ($question = $questions_result->fetch_assoc()) {
            $question_id = $question['id'];
            $answer = $_POST['answers'][$question_id] ?? '';
            $marks = 0;
            
            // Auto-grade the answer
            switch ($question['question_type']) {
                case 'mcq':
                case 'true_false':
                    $is_correct = (strtolower(trim($answer)) === strtolower(trim($question['correct_answer'])));
                    $marks = $is_correct ? $question['marks'] : 0;
                    break;
                case 'short_answer':
                    // For short answers, we'll just store the answer and mark it for manual grading
                    $marks = 0; // Will be graded manually
                    break;
            }
            
            // Save the answer
            $stmt = $conn->prepare("
                INSERT INTO test_answers (attempt_id, question_id, answer, marks_obtained)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    answer = VALUES(answer),
                    marks_obtained = VALUES(marks_obtained)
            ");
            $stmt->bind_param("iisd", $attempt_id, $question_id, $answer, $marks);
            $stmt->execute();
            $stmt->close();
            
            $total_marks += $question['marks'];
            $obtained_marks += $marks;
        }
        
        // Calculate percentage
        $percentage = $total_marks > 0 ? ($obtained_marks / $total_marks) * 100 : 0;
        $result_status = $percentage >= $test['passing_marks'] ? 'pass' : 'fail';
        
        // Update test attempt
        $stmt = $conn->prepare("
            UPDATE test_attempts 
            SET submitted_at = NOW(),
                status = 'evaluated',
                obtained_marks = ?,
                percentage = ?,
                result = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ddsi", $obtained_marks, $percentage, $result_status, $attempt_id);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        setFlashMessage('success', 'Test submitted successfully!');
        header("Location: test_result.php?attempt_id=$attempt_id");
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        setFlashMessage('error', 'Error submitting test: ' . $e->getMessage());
    }
}

// Get test questions
$questions_stmt = $conn->prepare("
    SELECT q.*, a.answer as student_answer
    FROM test_questions q
    LEFT JOIN test_answers a ON q.id = a.question_id AND a.attempt_id = ?
    WHERE q.test_id = ?
    ORDER BY q.id
");
$questions_stmt->bind_param("ii", $attempt_id, $test['test_id']);
$questions_stmt->execute();
$questions = $questions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$questions_stmt->close();

$page_title = 'Take Test: ' . $test['title'];
include_once 'includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <!-- Test Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><?php echo htmlspecialchars($test['title']); ?></h2>
                <div id="timer" class="h4 mb-0 text-danger font-weight-bold">
                    <i class="fas fa-clock"></i> 
                    <span id="time-display">00:00:00</span>
                </div>
            </div>
            
            <!-- Test Instructions -->
            <div class="alert alert-info mb-4">
                <h5><i class="fas fa-info-circle me-2"></i>Instructions</h5>
                <ul class="mb-0">
                    <li>Answer all questions before submitting.</li>
                    <li>You have <strong><?php echo floor($test['duration_minutes'] / 60); ?>h <?php echo $test['duration_minutes'] % 60; ?>m</strong> to complete this test.</li>
                    <li>Once submitted, you cannot change your answers.</li>
                </ul>
            </div>
            
            <!-- Test Form -->
            <form id="test-form" method="POST" action="">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                Question <?php echo $index + 1; ?>
                                <span class="badge bg-primary float-end">
                                    <?php echo $question['marks']; ?> mark<?php echo $question['marks'] > 1 ? 's' : ''; ?>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            
                            <?php if ($question['question_type'] === 'mcq'): 
                                $options = json_decode($question['options'], true) ?: [];
                                $student_answer = $question['student_answer'] ?? '';
                            ?>
                                <div class="ms-4">
                                    <?php foreach ($options as $key => $option): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="answers[<?php echo $question['id']; ?>]" 
                                                   id="q<?php echo $question['id']; ?>_<?php echo $key; ?>" 
                                                   value="<?php echo htmlspecialchars($key); ?>"
                                                   <?php echo ($student_answer === $key) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="q<?php echo $question['id']; ?>_<?php echo $key; ?>">
                                                <?php echo htmlspecialchars($option); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                            <?php elseif ($question['question_type'] === 'true_false'): 
                                $student_answer = $question['student_answer'] ?? '';
                            ?>
                                <div class="ms-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="answers[<?php echo $question['id']; ?>]" 
                                               id="q<?php echo $question['id']; ?>_true" 
                                               value="true"
                                               <?php echo ($student_answer === 'true') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="q<?php echo $question['id']; ?>_true">
                                            True
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="answers[<?php echo $question['id']; ?>]" 
                                               id="q<?php echo $question['id']; ?>_false" 
                                               value="false"
                                               <?php echo ($student_answer === 'false') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="q<?php echo $question['id']; ?>_false">
                                            False
                                        </label>
                                    </div>
                                </div>
                                
                            <?php else: // short answer 
                                $student_answer = $question['student_answer'] ?? '';
                            ?>
                                <div class="form-group">
                                    <textarea class="form-control" name="answers[<?php echo $question['id']; ?>]" 
                                              rows="4" placeholder="Type your answer here..."><?php echo htmlspecialchars($student_answer); ?></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                    <button type="button" class="btn btn-outline-secondary me-md-2" onclick="if(confirm('Are you sure you want to save and exit? You can come back later to complete the test.')) { document.getElementById('test-form').submit(); }">
                        <i class="fas fa-save me-1"></i> Save & Exit
                    </button>
                    <button type="submit" name="submit_test" class="btn btn-primary" id="submit-test-btn" onclick="return confirm('Are you sure you want to submit your test? You cannot change your answers after submission.');">
                        <i class="fas fa-paper-plane me-1"></i> Submit Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Timer Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    let timeLeft = <?php echo $time_remaining; ?>;
    const timerDisplay = document.getElementById('time-display');
    const testForm = document.getElementById('test-form');
    const submitBtn = document.getElementById('submit-test-btn');
    
    function formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return [
            hours.toString().padStart(2, '0'),
            minutes.toString().padStart(2, '0'),
            secs.toString().padStart(2, '0')
        ].join(':');
    }
    
    function updateTimer() {
        timerDisplay.textContent = formatTime(timeLeft);
        
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            alert('Time is up! Your test will be submitted automatically.');
            testForm.submit();
            return;
        }
        
        // Change color when less than 5 minutes left
        if (timeLeft <= 300) { // 5 minutes in seconds
            timerDisplay.classList.add('text-danger');
            timerDisplay.classList.add('blink');
        }
        
        timeLeft--;
        
        // Auto-save every 30 seconds if more than 30 seconds left
        if (timeLeft > 0 && timeLeft % 30 === 0) {
            saveProgress();
        }
    }
    
    function saveProgress() {
        // Create a hidden form to submit via AJAX
        const formData = new FormData(testForm);
        formData.append('auto_save', '1');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        }).then(data => {
            if (data.success) {
                console.log('Progress saved');
            }
        }).catch(error => {
            console.error('Error saving progress:', error);
        });
    }
    
    // Auto-save when navigating away
    window.addEventListener('beforeunload', function(e) {
        if (timeLeft > 0) {
            // This will show a confirmation dialog
            const message = 'You have unsaved changes. Are you sure you want to leave?';
            e.returnValue = message;
            return message;
        }
    });
    
    // Initialize timer
    const timerInterval = setInterval(updateTimer, 1000);
    updateTimer();
    
    // Auto-submit when time is up (in case the timer display fails)
    if (timeLeft > 0) {
        setTimeout(function() {
            if (timeLeft <= 0) {
                testForm.submit();
            }
        }, (timeLeft + 1) * 1000);
    }
});
</script>

<style>
@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
.blink {
    animation: blink 1s linear infinite;
}
</style>

<?php include_once 'includes/footer.php'; ?>
