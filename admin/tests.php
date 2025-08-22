<?php
require_once '../config/config.php';
require_once '../includes/auth_functions.php';
requireLogin();
requireAdmin();

$page_title = 'Manage Tests';
$active_menu = 'tests';
echo function_exists('displayFlashMessages') ? "displayFlashMessages loaded\n" : "displayFlashMessages NOT loaded\n";

// Handle test creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_test') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $course_id = (int)$_POST['course_id'];
        $duration = (int)$_POST['duration_minutes'];
        $total_marks = (int)$_POST['total_marks'];
        $passing_marks = (int)$_POST['passing_marks'];
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = $_POST['status'] ?? 'published';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO tests (title, description, course_id, duration_minutes, total_marks, passing_marks, start_date, end_date, status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiiiissi", $title, $description, $course_id, $duration, $total_marks, $passing_marks, $start_date, $end_date, $status, $is_active);
        
        if ($stmt->execute()) {
            $test_id = $conn->insert_id;
            setFlashMessage('success', 'Test created successfully!');
            header("Location: edit_test.php?id=$test_id");
            exit();
        } else {
            setFlashMessage('error', 'Failed to create test: ' . $conn->error);
        }
    }
}

// Get all tests with course names
$tests = [];
$query = "SELECT t.*, c.title as course_name FROM tests t 
          JOIN courses c ON t.course_id = c.id 
          ORDER BY t.created_at DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tests[] = $row;
    }
}

// Get courses for dropdown
$courses = [];
$result = $conn->query("SELECT id, title FROM courses ORDER BY title");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Include the admin header which includes config.php with our utility functions
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">Manage Tests</h1>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTestModal">
                <i class="fas fa-plus"></i> Create New Test
            </button>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Tests</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Course</th>
                            <th>Duration</th>
                            <th>Total Marks</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tests as $test): ?>
                        <tr>
                            <td><?= $test['id'] ?></td>
                            <td>
                                <a href="edit_test.php?id=<?= $test['id'] ?>">
                                    <?php if ($test['status'] === 'draft'): ?>
                                        <span class="badge bg-secondary me-1">Draft</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($test['title']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($test['course_name']) ?></td>
                            <td><?= $test['duration_minutes'] ?> mins</td>
                            <td><?= $test['total_marks'] ?></td>
                            <td>
                                <span class="badge bg-<?= $test['status'] === 'draft' ? 'secondary' : 'success' ?>">
                                    <?= $test['status'] === 'draft' ? 'Draft' : 'Published' ?>
                                </span>
                            </td>
                            <td>
                                <a href="edit_test.php?id=<?= $test['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="test_results.php?test_id=<?= $test['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-chart-bar"></i> Results
                                </a>
                                <button class="btn btn-sm btn-danger delete-test" data-id="<?= $test['id'] ?>">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Test Modal -->
<div class="modal fade" id="addTestModal" tabindex="-1" aria-labelledby="addTestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTestModalLabel">Create New Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_test">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Test Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="duration_minutes" class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" min="1" value="30" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="total_marks" class="form-label">Total Marks <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="total_marks" name="total_marks" min="1" value="100" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="passing_marks" class="form-label">Passing Marks <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="passing_marks" name="passing_marks" min="1" value="40" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date & Time (optional)</label>
                            <input type="datetime-local" class="form-control" id="start_date" name="start_date">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date & Time (optional)</label>
                            <input type="datetime-local" class="form-control" id="end_date" name="end_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="draft">Draft</option>
                            <option value="published" selected>Published</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">
                            Active (Make this test available to students)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteTestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this test? This action cannot be undone and will also delete all related questions and attempts.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#dataTable').DataTable({
        order: [[0, 'desc']], // Sort by ID descending by default
        columnDefs: [
            { orderable: false, targets: [6] } // Disable sorting on actions column
        ]
    });

    // Handle delete button click
    $('.delete-test').on('click', function(e) {
        e.preventDefault();
        var testId = $(this).data('id');
        $('#confirmDeleteBtn').attr('href', 'delete_test.php?id=' + testId);
        $('#deleteTestModal').modal('show');
    });
});
</script>
