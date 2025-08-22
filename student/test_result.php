<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

if (!isset($_GET['attempt_id']) || !is_numeric($_GET['attempt_id'])) {
    setFlashMessage('error', 'Invalid attempt ID');
    header('Location: tests.php');
    exit();
}

$attempt_id = (int)$_GET['attempt_id'];
$user_id = $_SESSION['user_id'];

// Get attempt details with test and course information
$stmt = $conn->prepare("
    SELECT ta.*, t.title as test_title, t.description as test_description, 
           t.total_marks, t.passing_marks, t.duration_minutes,
           c.title as course_title, c.code as course_code
    FROM test_attempts ta
    JOIN tests t ON ta.test_id = t.id
    JOIN courses c ON t.course_id = c.id
    WHERE ta.id = ? AND ta.student_id = ?
");
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) {
    setFlashMessage('error', 'Test attempt not found');
    header('Location: tests.php');
    exit();
}

// Get questions and answers
$questions = [];
$stmt = $conn->prepare("
    SELECT q.*, ta.answer as student_answer, ta.marks_obtained, ta.feedback, q.correct_answer
    FROM test_questions q
    LEFT JOIN test_answers ta ON q.id = ta.question_id AND ta.attempt_id = ?
    WHERE q.test_id = ?
    ORDER BY q.id
");
$stmt->bind_param("ii", $attempt_id, $attempt['test_id']);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate stats
$total_questions = count($questions);
$total_marks_obtained = array_sum(array_column($questions, 'marks_obtained'));
$total_possible_marks = array_sum(array_column($questions, 'marks'));
$score = $total_possible_marks > 0 ? ($total_marks_obtained / $total_possible_marks) * 100 : 0;
$is_passed = $score >= $attempt['passing_marks'];

$page_title = 'Test Results: ' . htmlspecialchars($attempt['test_title']);
include_once 'includes/header.php';
?>

<div class="container">
    <div class="d-sm-flex justify-content-between mb-4">
        <h1 class="h3 mb-0">Test Results</h1>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Test Result Header -->
            <div class="text-center mb-5">
                <h1 class="h3 mb-2">Test Results</h1>
                <h2 class="h2 mb-3"><?php echo htmlspecialchars($attempt['test_title']); ?></h2>
                <p class="lead">
                    <span class="badge bg-<?php echo $is_passed ? 'success' : 'danger'; ?> px-3 py-2">
                        <?php echo $is_passed ? 'PASSED' : 'NOT PASSED'; ?>
                    </span>
                </p>
                <p class="text-muted">
                    Course: <?php echo htmlspecialchars($attempt['course_title']); ?>
                    (<?php echo htmlspecialchars($attempt['course_code']); ?>)<br>
                    Submitted on: <?php echo date('F j, Y g:i A', strtotime($attempt['submitted_at'])); ?>
                </p>
            </div>

            <!-- Score Summary -->
            <div class="row mb-5">
                <div class="col-md-4 mb-4 mb-md-0">
                    <div class="card border-left-primary h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Your Score</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($score, 1); ?>%
                                        <small class="text-muted">(<?php echo $total_marks_obtained; ?>/<?php echo $total_possible_marks; ?>)</small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <div class="card border-left-success h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Passing Score</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $attempt['passing_marks']; ?>%
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-info h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Questions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $total_questions; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-question-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Description -->
            <?php if (!empty($attempt['test_description'])): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Test Description</h6>
                    </div>
                    <div class="card-body">
                        <?php echo nl2br(htmlspecialchars($attempt['test_description'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Questions and Answers -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Your Answers</h6>
                    <span class="badge bg-<?php echo $is_passed ? 'success' : 'danger'; ?>">
                        <?php echo $is_passed ? 'Passed' : 'Not Passed'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php foreach ($questions as $index => $question): 
                        $is_correct = isset($question['marks_obtained']) && $question['marks_obtained'] > 0;
                        $options = $question['question_type'] === 'mcq' ? json_decode($question['options'] ?? '[]', true) : [];
                        $student_answer = $question['student_answer'] ?? '';
                        $correct_answer = $question['correct_answer'] ?? '';
                    ?>
                        <div class="mb-4 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-1">
                                    Question <?php echo $index + 1; ?>
                                    <?php if ($question['marks_obtained'] !== null): ?>
                                        <span class="badge bg-<?php echo $is_correct ? 'success' : 'danger'; ?> ms-2">
                                            <?php echo $question['marks_obtained']; ?>/<?php echo $question['marks']; ?>
                                        </span>
                                    <?php endif; ?>
                                </h5>
                                <span class="text-<?php echo $is_correct ? 'success' : 'danger'; ?>">
                                    <i class="fas fa-<?php echo $is_correct ? 'check' : 'times'; ?> me-1"></i>
                                    <?php echo $is_correct ? 'Correct' : 'Incorrect'; ?>
                                </span>
                            </div>
                            
                            <p class="mb-3"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                            
                            <div class="ms-3">
                                <?php if ($question['question_type'] === 'mcq'): ?>
                                    <?php if (!empty($options)): ?>
                                        <?php foreach ($options as $key => $option): ?>
                                            <div class="form-check mb-2">
                                                <div class="d-flex align-items-center">
                                                    <input class="form-check-input" type="radio" 
                                                        name="q<?php echo $question['id']; ?>" 
                                                        id="q<?php echo $question['id']; ?>_<?php echo $key; ?>"
                                                        value="<?php echo htmlspecialchars($option); ?>"
                                                        <?php echo (trim($student_answer) === trim($option)) ? 'checked' : ''; ?>
                                                        disabled>
                                                    <label class="form-check-label ms-2" for="q<?php echo $question['id']; ?>_<?php echo $key; ?>">
                                                        <?php echo htmlspecialchars($option); ?>
                                                    </label>
                                                </div>
                                                <?php if (trim($option) === trim($correct_answer)): ?>
                                                    <div class="text-success small mt-1">
                                                        <i class="fas fa-check-circle"></i> Correct Answer
                                                    </div>
                                                <?php elseif (trim($student_answer) === trim($option) && !$is_correct): ?>
                                                    <div class="text-danger small mt-1">
                                                        <i class="fas fa-times-circle"></i> Your Answer
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-warning">No options available for this question.</div>
                                    <?php endif; ?>
                                <?php elseif ($question['question_type'] === 'true_false'): ?>
                                    <?php 
                                        $student_answer = strtolower(trim($student_answer ?? ''));
                                        $correct_answer = strtolower(trim($correct_answer ?? ''));
                                        $has_student_answer = !empty($student_answer);
                                    ?>
                                    <div class="form-check mb-2">
                                        <div class="d-flex align-items-center">
                                            <input class="form-check-input" type="radio" 
                                                name="q<?php echo $question['id']; ?>" 
                                                id="q<?php echo $question['id']; ?>_true" 
                                                value="true"
                                                <?php echo ($student_answer === 'true') ? 'checked' : ''; ?>
                                                disabled>
                                            <label class="form-check-label ms-2" for="q<?php echo $question['id']; ?>_true">
                                                True
                                            </label>
                                        </div>
                                        <?php if ($correct_answer === 'true'): ?>
                                            <div class="text-success small mt-1">
                                                <i class="fas fa-check-circle"></i> Correct Answer
                                            </div>
                                        <?php elseif ($student_answer === 'true' && !$is_correct && $has_student_answer): ?>
                                            <div class="text-danger small mt-1">
                                                <i class="fas fa-times-circle"></i> Your Answer
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-check mb-2">
                                        <div class="d-flex align-items-center">
                                            <input class="form-check-input" type="radio" 
                                                name="q<?php echo $question['id']; ?>" 
                                                id="q<?php echo $question['id']; ?>_false" 
                                                value="false"
                                                <?php echo ($student_answer === 'false') ? 'checked' : ''; ?>
                                                disabled>
                                            <label class="form-check-label ms-2" for="q<?php echo $question['id']; ?>_false">
                                                False
                                            </label>
                                        </div>
                                        <?php if ($correct_answer === 'false'): ?>
                                            <div class="text-success small mt-1">
                                                <i class="fas fa-check-circle"></i> Correct Answer
                                            </div>
                                        <?php elseif ($student_answer === 'false' && !$is_correct && $has_student_answer): ?>
                                            <div class="text-danger small mt-1">
                                                <i class="fas fa-times-circle"></i> Your Answer
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($question['question_type'] === 'short_answer' || $question['question_type'] === 'essay'): ?>
                                    <div class="mb-3">
                                        <div class="mb-2">
                                            <strong>Your Answer:</strong>
                                            <div class="p-3 bg-light rounded mt-1">
                                                <?php echo !empty($student_answer) ? nl2br(htmlspecialchars($student_answer)) : '<em class="text-muted">No answer provided</em>'; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($question['question_type'] === 'short_answer' && !empty($correct_answer)): ?>
                                            <div class="mt-3">
                                                <strong class="text-success">
                                                    <i class="fas fa-check-circle"></i> Correct Answer:
                                                </strong>
                                                <div class="p-3 bg-success bg-opacity-10 rounded mt-1">
                                                    <?php echo htmlspecialchars($correct_answer); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($question['question_type'] === 'essay' && $question['marks_obtained'] !== null): ?>
                                            <div class="alert alert-info mt-3 mb-0">
                                                <strong>Marks Awarded:</strong> 
                                                <?php echo $question['marks_obtained']; ?>/<?php echo $question['marks']; ?>
                                                
                                                <?php if (!empty($question['feedback'])): ?>
                                                    <div class="mt-2">
                                                        <strong>Feedback:</strong>
                                                        <?php echo nl2br(htmlspecialchars($question['feedback'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($question['feedback']) && $question['question_type'] !== 'essay'): ?>
                                    <div class="alert alert-info mt-2 mb-0">
                                        <strong>Feedback:</strong> <?php echo htmlspecialchars($question['feedback']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Test Details</h6>
                </div>
                <div class="card-body">
                    <p><strong>Course:</strong> <?php echo htmlspecialchars($attempt['course_title']); ?></p>
                    <p><strong>Attempt #:</strong> <?php echo $attempt['attempt_number']; ?></p>
                    <p><strong>Started At:</strong> <?php echo date('M j, Y H:i', strtotime($attempt['started_at'])); ?></p>
                    <p><strong>Score:</strong> <?php echo number_format($score, 1); ?>%</p>
                    <p><strong>Passing Score:</strong> <?php echo $attempt['passing_marks']; ?>%</p>
                    
                    <?php if (!$is_passed && $attempt['attempt_number'] < 3): ?>
                        <div class="mt-4 text-center">
                            <p class="text-muted mb-2">
                                You can retake this test after 24 hours.
                            </p>
                            <a href="view_test.php?id=<?php echo $attempt['test_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-redo"></i> Retake Test
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
