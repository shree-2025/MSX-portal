<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

if (!isset($_GET['test_id']) || !is_numeric($_GET['test_id'])) {
    setFlashMessage('error', 'Invalid test ID');
    header('Location: tests.php');
    exit();
}

$test_id = (int)$_GET['test_id'];

// Get test details
$stmt = $conn->prepare("SELECT * FROM tests WHERE id = ?");
$stmt->bind_param("i", $test_id);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();

if (!$test) {
    setFlashMessage('error', 'Test not found');
    header('Location: tests.php');
    exit();
}

// Handle question deletion
if (isset($_POST['delete_question'])) {
    $question_id = (int)$_POST['question_id'];
    $delete_stmt = $conn->prepare("DELETE FROM test_questions WHERE id = ? AND test_id = ?");
    $delete_stmt->bind_param("ii", $question_id, $test_id);
    if ($delete_stmt->execute()) {
        setFlashMessage('success', 'Question deleted successfully');
    } else {
        setFlashMessage('error', 'Failed to delete question');
    }
    header("Location: manage_questions.php?test_id=" . $test_id);
    exit();
}

// Get questions for this test
$questions = [];
$questions_query = $conn->prepare("
    SELECT * FROM test_questions 
    WHERE test_id = ? 
    ORDER BY id ASC
");
$questions_query->bind_param("i", $test_id);
$questions_query->execute();
$questions_result = $questions_query->get_result();

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Manage Questions: <?php echo htmlspecialchars($test['title']); ?></h1>
        <div>
            <a href="tests.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Tests
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                <i class="fas fa-plus"></i> Add Question
            </button>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <?php if ($questions_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="questionsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Question</th>
                                <th>Type</th>
                                <th>Marks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            while ($question = $questions_result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars(substr($question['question_text'], 0, 100)); 
                                            echo strlen($question['question_text']) > 100 ? '...' : ''; ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></td>
                                    <td><?php echo (int)$question['marks']; ?></td>
                                    <td>
                                        <a href="edit_question.php?id=<?php echo $question['id']; ?>" 
                                           class="btn btn-sm btn-primary me-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display: inline-block;" 
                                              onsubmit="return confirm('Are you sure you want to delete this question?');">
                                            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                            <button type="submit" name="delete_question" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                    <h4>No questions found</h4>
                    <p class="text-muted">Add your first question to get started.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                        <i class="fas fa-plus"></i> Add Question
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1" aria-labelledby="addQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addQuestionForm" action="save_question.php" method="POST">
                <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="addQuestionModalLabel">Add New Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="question_type" class="form-label">Question Type</label>
                        <select class="form-select" id="question_type" name="question_type" required>
                            <option value="mcq">Multiple Choice (MCQ)</option>
                            <option value="true_false">True/False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="essay">Essay</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question Text</label>
                        <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="marks" class="form-label">Marks</label>
                        <input type="number" class="form-control" id="marks" name="marks" min="1" value="1" required>
                    </div>
                    
                    <!-- Options for MCQ -->
                    <div class="mb-3" id="mcqOptions" style="display: none;">
                        <label class="form-label">Options (Mark the correct answer)</label>
                        <div id="optionsContainer">
                            <div class="input-group mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="correct_answer" value="0" checked>
                                </div>
                                <input type="text" class="form-control" name="options[]" placeholder="Option 1" required>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-option"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="input-group mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="correct_answer" value="1">
                                </div>
                                <input type="text" class="form-control" name="options[]" placeholder="Option 2" required>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-option"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addOption">
                            <i class="fas fa-plus"></i> Add Option
                        </button>
                    </div>
                    
                    <!-- Options for True/False -->
                    <div class="mb-3" id="trueFalseOptions" style="display: none;">
                        <label class="form-label">Correct Answer</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="correct_answer_tf" value="true" checked>
                            <label class="form-check-label">True</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="correct_answer_tf" value="false">
                            <label class="form-check-label">False</label>
                        </div>
                    </div>
                    
                    <!-- Options for Short Answer -->
                    <div class="mb-3" id="shortAnswerOptions" style="display: none;">
                        <label for="correct_answer_sa" class="form-label">Correct Answer</label>
                        <input type="text" class="form-control" id="correct_answer_sa" name="correct_answer_sa" placeholder="Enter the correct answer">
                    </div>
                    
                    <!-- Explanation -->
                    <div class="mb-3">
                        <label for="explanation" class="form-label">Explanation (Optional)</label>
                        <textarea class="form-control" id="explanation" name="explanation" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Questions Modal -->
<div class="modal fade" id="uploadQuestionsModal" tabindex="-1" aria-labelledby="uploadQuestionsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="uploadQuestionsForm" action="upload_questions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadQuestionsModalLabel">Upload Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="question_file" class="form-label">Select File</label>
                        <input class="form-control" type="file" id="question_file" name="question_file" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">
                            Upload an Excel or CSV file with questions. 
                            <a href="#" data-bs-toggle="modal" data-bs-target="#fileFormatModal">View required format</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Questions</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- File Format Modal -->
<div class="modal fade" id="fileFormatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">File Format Requirements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Please prepare your file with the following columns (for MCQ questions):</p>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>question_text</th>
                            <th>question_type</th>
                            <th>marks</th>
                            <th>option1</th>
                            <th>option2</th>
                            <th>option3</th>
                            <th>option4</th>
                            <th>correct_option</th>
                            <th>explanation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Sample question text?</td>
                            <td>mcq</td>
                            <td>2</td>
                            <td>Option A</td>
                            <td>Option B</td>
                            <td>Option C</td>
                            <td>Option D</td>
                            <td>1</td>
                            <td>Explanation text (optional)</td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="mt-3">For True/False questions:</p>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>question_text</th>
                            <th>question_type</th>
                            <th>marks</th>
                            <th>correct_answer</th>
                            <th>explanation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Is this statement true?</td>
                            <td>true_false</td>
                            <td>1</td>
                            <td>true</td>
                            <td>Explanation text (optional)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="sample_questions_template.xlsx" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download Template
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<script>
// Show/hide options based on question type
document.getElementById('question_type').addEventListener('change', function() {
    const questionType = this.value;
    const mcqOptions = document.getElementById('mcqOptions');
    const trueFalseOptions = document.getElementById('trueFalseOptions');
    const shortAnswerOptions = document.getElementById('shortAnswerOptions');
    
    // Hide all options first
    mcqOptions.style.display = 'none';
    trueFalseOptions.style.display = 'none';
    shortAnswerOptions.style.display = 'none';
    
    // Show relevant options based on question type
    if (questionType === 'mcq') {
        mcqOptions.style.display = 'block';
    } else if (questionType === 'true_false') {
        trueFalseOptions.style.display = 'block';
    } else if (questionType === 'short_answer') {
        shortAnswerOptions.style.display = 'block';
    }
});

// Trigger change event to set initial state
document.getElementById('question_type').dispatchEvent(new Event('change'));

// Add option button
let optionCounter = 2;
document.getElementById('addOption').addEventListener('click', function() {
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

// Initialize DataTable
$(document).ready(function() {
    $('#questionsTable').DataTable({
        "pageLength": 10,
        "order": [[0, 'asc']]
    });
});
</script>
