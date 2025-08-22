<?php
// Start session and include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

$page_title = 'Manage Assignments';

// Handle assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete' && isset($_POST['assignment_id'])) {
        $assignment_id = (int)$_POST['assignment_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First, get the file path to delete the file
            $stmt = $conn->prepare("SELECT file_path FROM assignments WHERE id = ?");
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $assignment = $result->fetch_assoc();
            $stmt->close();
            
            if ($assignment) {
                // Delete the assignment file if it exists
                if (!empty($assignment['file_path'])) {
                    $file_path = __DIR__ . '/..' . $assignment['file_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                // Delete the assignment
                $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ?");
                $stmt->bind_param("i", $assignment_id);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                setFlashMessage('success', 'Assignment deleted successfully!');
            } else {
                throw new Exception('Assignment not found');
            }
        } catch (Exception $e) {
            $conn->rollback();
            setFlashMessage('error', 'Failed to delete assignment: ' . $e->getMessage());
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get all assignments with course and student info
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? "%" . sanitize($_GET['search']) . "%" : "%%";
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$query = "SELECT a.*, c.title as course_title,
          (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count
          FROM assignments a 
          LEFT JOIN courses c ON a.course_id = c.id 
          WHERE a.title LIKE ?";
          
$params = ["s", &$search];

if ($course_filter) {
    $query .= " AND a.course_id = ?";
    $params[0] .= "i";
    $params[] = &$course_filter;
}

if ($status_filter) {
    $query .= " AND a.status = ?";
    $params[0] .= "s";
    $params[] = &$status_filter;
}

$query .= " ORDER BY a.due_date DESC LIMIT ? OFFSET ?";
$params[0] .= "ii";
$params[] = &$per_page;
$params[] = &$offset;

$stmt = $conn->prepare($query);
call_user_func_array([$stmt, 'bind_param'], $params);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get courses for filter dropdown
$courses = $conn->query("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title")->fetch_all(MYSQLI_ASSOC);

include_once 'includes/header.php'; 
?>

<!-- Include jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Manage Assignments</h1>
                <a href="add_assignment.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Assignment
                </a>
            </div>
        </div>
    </div>

    <?php echo displayFlashMessage(); ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h6 class="m-0 font-weight-bold text-primary">All Assignments</h6>
                </div>
                <div class="col-md-6">
                    <form method="get" class="row g-2">
                        <div class="col-md-4">
                            <select name="course_id" class="form-select" onchange="this.form.submit()">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" <?= ($course_filter ?? '') == $course['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="draft" <?= ($status_filter ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= ($status_filter ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                                <option value="closed" <?= ($status_filter ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search assignments..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (isset($_GET['search']) || isset($_GET['course_id']) || isset($_GET['status'])): ?>
                                    <a href="assignments.php" class="btn btn-outline-danger">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Course</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Submissions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($assignments) > 0): ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><?= $assignment['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($assignment['title']) ?></strong>
                                        <?php if (!empty($assignment['description'])): ?>
                                            <p class="text-muted small mb-0">
                                                <?= nl2br(htmlspecialchars(substr($assignment['description'], 0, 100) . (strlen($assignment['description']) > 100 ? '...' : ''))) ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($assignment['course_title']) ?></td>
                                    <td><?= date('M d, Y', strtotime($assignment['due_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $assignment['status'] === 'published' ? 'success' : 
                                            ($assignment['status'] === 'draft' ? 'warning' : 'secondary') 
                                        ?>">
                                            <?= ucfirst($assignment['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($assignment['submission_count'] > 0): ?>
                                            <a href="submissions.php?assignment_id=<?= $assignment['id'] ?>">
                                                <?= $assignment['submission_count'] ?> submissions
                                            </a>
                                        <?php else: ?>
                                            No submissions
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_assignment.php?id=<?= $assignment['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="assignment_details.php?id=<?= $assignment['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="submissions.php?assignment_id=<?= $assignment['id'] ?>" class="btn btn-sm btn-success" title="View Submissions">
                                                <i class="fas fa-file-upload"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger delete-assignment" 
                                                    data-id="<?= $assignment['id'] ?>" 
                                                    data-title="<?= htmlspecialchars($assignment['title']) ?>"
                                                    title="Delete Assignment">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No assignments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the assignment "<span id="assignmentTitle"></span>"?</p>
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. All submissions for this assignment will also be deleted.</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="post" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="assignment_id" id="deleteAssignmentId">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Assignment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Use jQuery's document.ready
$(document).ready(function() {
    // Handle delete button click
    $('.delete-assignment').on('click', function(e) {
        e.preventDefault();
        
        // Get assignment data from data attributes
        var assignmentId = $(this).data('id');
        var assignmentTitle = $(this).data('title');
        
        // Update modal content
        $('#assignmentTitle').text(assignmentTitle);
        $('#deleteAssignmentId').val(assignmentId);
        
        // Initialize and show modal using Bootstrap 5
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    });
    
    // Handle form submission
    $('#deleteForm').on('submit', function(e) {
        e.preventDefault();
        
        // Submit the form
        this.submit();
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
