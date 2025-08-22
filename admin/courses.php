<?php
// Start session and include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

// Initialize variables
$page_title = 'Manage Courses';
$courses = [];

// Handle course actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    
    switch ($action) {
        case 'add':
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $duration = (int)($_POST['duration'] ?? 0);
            
            $stmt = $conn->prepare("INSERT INTO courses (title, description, duration, status) VALUES (?, ?, ?, 'active')");
            $stmt->bind_param("ssi", $title, $description, $duration);
            if ($stmt->execute()) {
                setFlashMessage('success', 'Course added successfully!');
            } else {
                setFlashMessage('error', 'Error adding course: ' . $conn->error);
            }
            break;
            
        case 'edit':
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $duration = (int)($_POST['duration'] ?? 0);

            $stmt = $conn->prepare("UPDATE courses SET title = ?, description = ?, duration = ? WHERE id = ?");
            $stmt->bind_param("ssii", $title, $description, $duration, $course_id);
            if ($stmt->execute()) {
                setFlashMessage('success', 'Course updated successfully!');
            } else {
                setFlashMessage('error', 'Error updating course: ' . $conn->error);
            }
            break;
            
        case 'delete':
            // First, delete related records in transcript_courses
            $conn->begin_transaction();
            
            try {
                // Delete from transcript_courses first
                $stmt1 = $conn->prepare("DELETE FROM transcript_courses WHERE course_id = ?");
                $stmt1->bind_param("i", $course_id);
                $stmt1->execute();
                
                // Then delete the course
                $stmt2 = $conn->prepare("DELETE FROM courses WHERE id = ?");
                $stmt2->bind_param("i", $course_id);
                
                if ($stmt2->execute()) {
                    $conn->commit();
                    setFlashMessage('success', 'Course and related records deleted successfully!');
                } else {
                    throw new Exception($stmt2->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                setFlashMessage('error', 'Error deleting course: ' . $e->getMessage());
            }
            break;
    }
    
    // Redirect to prevent resubmission
    header("Location: courses.php");
    exit();
}

// Get all courses
$result = $conn->query("SELECT * FROM courses ORDER BY title");
if ($result) {
    $courses = $result->fetch_all(MYSQLI_ASSOC);
}

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Manage Courses</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="fas fa-plus"></i> Add New Course
        </button>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="coursesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Duration (months)</th>
                            <th>Status</th>
                            <th>Materials</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?= htmlspecialchars($course['id']); ?></td>
                            <td><?= htmlspecialchars($course['title']); ?></td>
                            <td><?= htmlspecialchars($course['description']); ?></td>
                            <td><?= htmlspecialchars($course['duration']); ?> months</td>
                            <td><?= isset($course['status']) ? ucfirst($course['status']) : 'N/A'; ?></td>
                            <td class="text-center">
                                <a href="course_materials.php?course_id=<?= $course['id']; ?>" 
                                   class="btn btn-sm btn-info" title="Manage Materials">
                                    <i class="fas fa-book"></i> Materials
                                </a>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-primary edit-course" 
                                        data-id="<?= $course['id']; ?>"
                                        data-title="<?= htmlspecialchars($course['title']); ?>"
                                        data-description="<?= htmlspecialchars($course['description']); ?>"
                                        data-duration="<?= $course['duration']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="courses.php" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="course_id" value="<?= $course['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" 
                                            onclick="return confirm('Delete course: <?= htmlspecialchars($course['title']); ?> ?');">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="courses.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea class="form-control" name="description" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Duration (months)</label>
                        <input type="number" class="form-control" name="duration" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="courses.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="course_id" id="edit_course_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Course Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea class="form-control" id="edit_description" name="description" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Duration (months)</label>
                        <input type="number" class="form-control" id="edit_duration" name="duration" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function(){
    // Fill edit modal with course data
    $('.edit-course').click(function(){
        $('#edit_course_id').val($(this).data('id'));
        $('#edit_title').val($(this).data('title'));
        $('#edit_description').val($(this).data('description'));
        $('#edit_duration').val($(this).data('duration'));
        var modal = new bootstrap.Modal(document.getElementById('editCourseModal'));
        modal.show();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
