<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid question ID');
    header('Location: tests.php');
    exit();
}

$question_id = (int)$_GET['id'];

// Fetch question details
$stmt = $conn->prepare("
    SELECT q.*, t.title as test_title, t.id as test_id 
    FROM test_questions q
    JOIN tests t ON q.test_id = t.id
    WHERE q.id = ?
");
$stmt->bind_param("i", $question_id);
$stmt->execute();
$question = $stmt->get_result()->fetch_assoc();

if (!$question) {
    setFlashMessage('error', 'Question not found');
    header('Location: tests.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_text = trim($_POST['question_text']);
    $marks = (int)$_POST['marks'];
    $explanation = !empty($_POST['explanation']) ? trim($_POST['explanation']) : null;
    
    // Get the old marks for updating the test total
    $old_marks = $question['marks'];
    $marks_diff = $marks - $old_marks;
    
    // Prepare data based on question type
    $options = $question['options'];
    $correct_answer = $question['correct_answer'];
    
    if ($question['question_type'] === 'mcq') {
        $options_arr = [];
        $correct_option = (int)$_POST['correct_answer'];
        
        if (!isset($_POST['options']) || count($_POST['options']) < 2) {
            setFlashMessage('error', 'At least 2 options are required for MCQ');
            header("Location: edit_question.php?id=$question_id");
            exit();
        }
        
        foreach ($_POST['options'] as $index => $option) {
            $option_text = trim($option);
            if (!empty($option_text)) {
                $options_arr[] = $option_text;
            }
        }
        
        if (count($options_arr) < 2) {
            setFlashMessage('error', 'At least 2 valid options are required for MCQ');
            header("Location: edit_question.php?id=$question_id");
            exit();
        }
        
        if (!isset($options_arr[$correct_option])) {
            $correct_option = 0; // Default to first option if invalid
        }
        
        $correct_answer = $options_arr[$correct_option];
        $options = json_encode($options_arr);
    } elseif ($question['question_type'] === 'true_false') {
        $correct_answer = isset($_POST['correct_answer']) ? 'true' : 'false';
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update the question
        $update_stmt = $conn->prepare("
            UPDATE test_questions 
            SET question_text = ?, 
                options = ?, 
                correct_answer = ?, 
                marks = ?,
                explanation = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $update_stmt->bind_param(
            "sssisi",
            $question_text,
            $options,
            $correct_answer,
            $marks,
            $explanation,
            $question_id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update question: " . $update_stmt->error);
        }
        
        // Update test's total marks if marks changed
        if ($marks_diff != 0) {
            $update_test = $conn->prepare("
                UPDATE tests 
                SET total_marks = total_marks + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $update_test->bind_param("ii", $marks_diff, $question['test_id']);
            
            if (!$update_test->execute()) {
                throw new Exception("Failed to update test total marks: " . $update_test->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        setFlashMessage('success', 'Question updated successfully');
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        setFlashMessage('error', $e->getMessage());
    }
    
    header("Location: manage_questions.php?test_id={$question['test_id']}");
    exit();
}

// For MCQ questions, decode the options
$options = [];
if ($question['question_type'] === 'mcq' && !empty($question['options'])) {
    $options = json_decode($question['options'], true);
}

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Edit Question</h1>
        <div>
            <a href="manage_questions.php?test_id=<?php echo $question['test_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Questions
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Test: <?php echo htmlspecialchars($question['test_title']); ?>
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Question Type</label>
                    <input type="text" class="form-control" value="<?php 
                        echo ucfirst(str_replace('_', ' ', $question['question_type'])); 
                    ?>" readonly>
                </div>
                
                <div class="mb-3">
                    <label for="question_text" class="form-label">Question Text</label>
                    <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?php 
                        echo htmlspecialchars($question['question_text']); 
                    ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="marks" class="form-label">Marks</label>
                    <input type="number" class="form-control" id="marks" name="marks" 
                           min="1" value="<?php echo (int)$question['marks']; ?>" required>
                </div>
                
                <?php if ($question['question_type'] === 'mcq' && !empty($options)): ?>
                    <div class="mb-3">
                        <label class="form-label">Options</label>
                        <div id="optionsContainer">
                            <?php foreach ($options as $index => $option): ?>
                                <div class="input-group mb-2">
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0" type="radio" 
                                               name="correct_answer" value="<?php echo $index; ?>"
                                               <?php echo $option === $question['correct_answer'] ? 'checked' : ''; ?>>
                                    </div>
                                    <input type="text" class="form-control" name="options[]" 
                                           value="<?php echo htmlspecialchars($option); ?>" required>
                                    <?php if ($index >= 2): ?>
                                        <button type="button" class="btn btn-outline-danger remove-option">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="addOption">
                            <i class="fas fa-plus"></i> Add Option
                        </button>
                    </div>
                <?php elseif ($question['question_type'] === 'true_false'): ?>
                    <div class="mb-3">
                        <label class="form-label">Correct Answer</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="correct_answer" id="trueOption" value="1"
                                   <?php echo $question['correct_answer'] === 'true' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="trueOption">
                                True
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="correct_answer" id="falseOption" value="0"
                                   <?php echo $question['correct_answer'] === 'false' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="falseOption">
                                False
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="explanation" class="form-label">Explanation (Optional)</label>
                    <textarea class="form-control" id="explanation" name="explanation" rows="2"><?php 
                        echo htmlspecialchars($question['explanation'] ?? ''); 
                    ?></textarea>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="manage_questions.php?test_id=<?php echo $question['test_id']; ?>" class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add option button
let optionCounter = <?php echo count($options); ?>;
document.getElementById('addOption')?.addEventListener('click', function() {
    const container = document.getElementById('optionsContainer');
    const newOption = document.createElement('div');
    newOption.className = 'input-group mb-2';
    newOption.innerHTML = `
        <div class="input-group-text">
            <input class="form-check-input mt-0" type="radio" name="correct_answer" value="${optionCounter}">
        </div>
        <input type="text" class="form-control" name="options[]" placeholder="Option ${optionCounter + 1}" required>
        <button type="button" class="btn btn-outline-danger remove-option">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(newOption);
    optionCounter++;
});

// Remove option button (delegated event)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-option') || e.target.closest('.remove-option')) {
        const button = e.target.classList.contains('remove-option') ? e.target : e.target.closest('.remove-option');
        button.closest('.input-group').remove();
        // Re-index radio buttons
        document.querySelectorAll('input[name="correct_answer"]').forEach((radio, index) => {
            radio.value = index;
        });
        optionCounter--;
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>
