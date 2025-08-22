<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid test ID');
    header('Location: tests.php');
    exit();
}

$test_id = (int)$_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $course_id = (int)$_POST['course_id'];
    $duration_minutes = (int)$_POST['duration_minutes'];
    $total_marks = (int)$_POST['total_marks'];
    $status = $_POST['status'];
    
    // Update test in database
    $stmt = $conn->prepare("UPDATE tests SET 
        title = ?, description = ?, course_id = ?, 
        duration_minutes = ?, total_marks = ?, status = ?,
        updated_at = NOW() WHERE id = ?");
    
    $stmt->bind_param("ssiisii", $title, $description, $course_id, 
        $duration_minutes, $total_marks, $status, $test_id);

    if ($stmt->execute()) {
        setFlashMessage('success', 'Test updated successfully');
        header('Location: tests.php');
        exit();
    } else {
        $error = 'Failed to update test: ' . $conn->error;
    }
}

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

// Get courses for dropdown
$courses = [];
$result = $conn->query("SELECT id, title FROM courses ORDER BY title");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">Edit Test</h1>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="title" class="form-label">Test Title</label>
                    <input type="text" class="form-control" id="title" name="title" 
                           value="<?php echo htmlspecialchars($test['title']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php 
                        echo htmlspecialchars($test['description']); 
                    ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="course_id" class="form-label">Course</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" 
                                    <?php echo $course['id'] == $test['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                        <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" 
                               min="1" value="<?php echo (int)$test['duration_minutes']; ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="total_marks" class="form-label">Total Marks</label>
                        <input type="number" class="form-control" id="total_marks" name="total_marks" 
                               min="1" value="<?php echo (int)$test['total_marks']; ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft" <?php echo $test['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo $test['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="completed" <?php echo $test['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="tests.php" class="btn btn-secondary">Cancel</a>
                    <div>
                        <a href="manage_questions.php?test_id=<?php echo $test_id; ?>" class="btn btn-info">
                            Manage Questions
                        </a>
                        <button type="submit" class="btn btn-primary">Update Test</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
