<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

// Basic validation
$attempt_id = (int)($_GET['attempt_id'] ?? 0);
if (!$attempt_id) {
    setFlashMessage('error', 'Invalid attempt ID');
    header('Location: test_results.php');
    exit();
}

// Fetch attempt details
$stmt = $conn->prepare("
    SELECT ta.*, u.username, t.title as test_title, 
           t.total_marks, t.passing_marks, c.title as course_title
    FROM test_attempts ta
    JOIN users u ON ta.student_id = u.id
    JOIN tests t ON ta.test_id = t.id
    JOIN courses c ON t.course_id = c.id
    WHERE ta.id = ?
");
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();

if (!$attempt) {
    setFlashMessage('error', 'Test attempt not found');
    header('Location: test_results.php');
    exit();
}

// Fetch questions and answers
$stmt = $conn->prepare("
    SELECT q.*, ta.answer, ta.marks_obtained, ta.feedback
    FROM test_questions q
    LEFT JOIN test_answers ta ON q.id = ta.question_id AND ta.attempt_id = ?
    WHERE q.test_id = ?
    ORDER BY q.id
");
$stmt->bind_param("ii", $attempt_id, $attempt['test_id']);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_feedback'])) {
    $conn->begin_transaction();
    try {
        // Update attempt
        $stmt = $conn->prepare("UPDATE test_attempts SET feedback = ?, status = 'graded' WHERE id = ?");
        $stmt->bind_param("si", $_POST['overall_feedback'], $attempt_id);
        $stmt->execute();
        
        // Update question feedback
        foreach ($questions as $q) {
            $marks = (float)($_POST['marks'][$q['id']] ?? 0);
            $feedback = $_POST['feedback'][$q['id']] ?? '';
            $is_correct = $marks >= ($q['marks'] * 0.5) ? 1 : 0;
            
            $stmt = $conn->prepare("
                UPDATE test_answers 
                SET marks_obtained = ?, feedback = ?
                WHERE attempt_id = ? AND question_id = ?
            ");
            
            $stmt->bind_param("dsii", $marks, $feedback, $attempt_id, $q['id']);
            $stmt->execute();
        }
        
        $conn->commit();
        setFlashMessage('success', 'Feedback saved successfully');
        header("Location: test_result_details.php?attempt_id=$attempt_id");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        setFlashMessage('error', 'Failed to save feedback: ' . $e->getMessage());
    }
}

$page_title = 'Test Result: ' . $attempt['username'];
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between mb-4">
        <h1 class="h3 mb-0">Test Result</h1>
        <a href="test_results.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Results
        </a>
    </div>

    <!-- Summary Card -->
    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold">
                <?php echo htmlspecialchars($attempt['test_title']); ?>
            </h6>
            <span class="badge bg-<?php echo $attempt['is_passed'] ? 'success' : 'danger'; ?>">
                <?php echo $attempt['is_passed'] ? 'Passed' : 'Failed'; ?>
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Student:</strong> <?php echo htmlspecialchars($attempt['username']); ?></p>
                    <p><strong>Course:</strong> <?php echo htmlspecialchars($attempt['course_title']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Score:</strong> <?php echo number_format($attempt['percentage'], 1); ?>%</p>
                    <p><strong>Marks:</strong> <?php echo $attempt['total_marks_obtained']; ?>/<?php echo $attempt['total_marks']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Questions -->
    <form method="post">
        <?php foreach ($questions as $i => $q): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Question <?php echo $i + 1; ?></strong> (<?php echo $q['marks']; ?> marks)
                </div>
                <div class="card-body">
                    <p><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Student's Answer:</label>
                        <div class="form-control"><?php echo nl2br(htmlspecialchars($q['answer'] ?? 'No answer provided')); ?></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-2">
                            <label class="form-label">Marks (Max: <?php echo $q['marks']; ?>)</label>
                            <input type="number" name="marks[<?php echo $q['id']; ?>]" 
                                   class="form-control" min="0" max="<?php echo $q['marks']; ?>" 
                                   step="0.5" value="<?php echo $q['marks_obtained'] ?? 0; ?>">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">Feedback</label>
                            <input type="text" name="feedback[<?php echo $q['id']; ?>]" 
                                   class="form-control" value="<?php echo htmlspecialchars($q['feedback'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <strong>Overall Feedback</strong>
            </div>
            <div class="card-body">
                <textarea name="overall_feedback" class="form-control" rows="3"><?php 
                    echo htmlspecialchars($attempt['feedback'] ?? ''); 
                ?></textarea>
            </div>
        </div>
        
        <div class="text-end mb-4">
            <button type="submit" name="save_feedback" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Feedback
            </button>
        </div>
    </form>
</div>

<?php include_once 'includes/footer.php'; ?>
