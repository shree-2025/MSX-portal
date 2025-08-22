<?php
require_once __DIR__ . '/includes/header.php';

$student_id = $_SESSION['user_id'];
$status = $_GET['status'] ?? 'pending';
$course_id = $_GET['course_id'] ?? null;

// Get assignments with submission status
$query = "SELECT a.*, c.title as course_title, 
          s.id as submission_id, s.submission_date, s.marks_obtained, s.status as submission_status
          FROM assignments a
          JOIN courses c ON a.course_id = c.id
          JOIN student_courses sc ON c.id = sc.course_id AND sc.student_id = ?
          LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
          WHERE a.is_active = 1 
          AND c.status = 'active'
          AND (? = 'all' OR 
              (? = 'pending' AND s.id IS NULL) OR
              (? = 'submitted' AND s.id IS NOT NULL AND s.marks_obtained IS NULL) OR
              (? = 'graded' AND s.marks_obtained IS NOT NULL))
              " . ($course_id ? " AND a.course_id = ?" : "") . "
          ORDER BY a.due_date ASC";

$params = [$student_id, $student_id, $status, $status, $status, $status];
if ($course_id) $params[] = $course_id;

$stmt = $conn->prepare($query);
$stmt->bind_param(str_repeat("i", count($params)), ...$params);
$stmt->execute();
$assignments = $stmt->get_result();
$stmt->close();

// Get courses for filter
$query = "SELECT c.id, c.title FROM courses c 
          JOIN student_courses sc ON c.id = sc.course_id 
          WHERE sc.student_id = ? AND sc.status = 'active'
          ORDER BY c.title";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$courses = $stmt->get_result();
$stmt->close();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Assignments</h1>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Assignments</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                        <option value="graded" <?= $status === 'graded' ? 'selected' : '' ?>>Graded</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="course_id" onchange="this.form.submit()">
                        <option value="">All Courses</option>
                        <?php while($course = $courses->fetch_assoc()): ?>
                            <option value="<?= $course['id'] ?>" <?= $course_id == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['title']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <a href="?" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Assignments List -->
    <div class="card shadow">
        <div class="card-body">
            <?php if ($assignments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Course</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($a = $assignments->fetch_assoc()): 
                                $due = new DateTime($a['due_date']);
                                $now = new DateTime();
                                $is_late = $due < $now && $a['submission_status'] !== 'graded';
                                
                                // Status
                                if ($a['marks_obtained'] !== null) {
                                    $status = 'Graded';
                                    $status_class = 'success';
                                } elseif ($a['submission_id']) {
                                    $status = 'Submitted';
                                    $status_class = 'primary';
                                } elseif ($is_late) {
                                    $status = 'Overdue';
                                    $status_class = 'danger';
                                } else {
                                    $status = 'Pending';
                                    $status_class = 'warning';
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['title']) ?></td>
                                    <td><?= htmlspecialchars($a['course_title']) ?></td>
                                    <td>
                                        <?= $due->format('M d, Y') ?>
                                        <?= $is_late ? '<span class="badge bg-danger">Late</span>' : '' ?>
                                    </td>
                                    <td><span class="badge bg-<?= $status_class ?>"><?= $status ?></span></td>
                                    <td>
                                        <?php 
                                        $marks_obtained = $a['marks_obtained'] ?? null;
                                        $max_marks = $a['max_marks'] ?? 0;
                                        
                                        if ($marks_obtained !== null && $max_marks > 0): 
                                            $percentage = ($marks_obtained / $max_marks) * 100;
                                            ?>
                                            <?= htmlspecialchars($marks_obtained) ?>/<?= htmlspecialchars($max_marks) ?>
                                            (<?= round($percentage) ?>%)
                                        <?php elseif ($marks_obtained !== null): ?>
                                            <?= htmlspecialchars($marks_obtained) ?>/--
                                        <?php else: ?>
                                            --
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_assignment.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if (!$a['submission_id'] || $is_late): ?>
                                            <button class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#submitModal"
                                                    data-id="<?= $a['id'] ?>">
                                                <i class="fas fa-upload"></i> Submit
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-gray-300 mb-3"></i>
                    <h5>No assignments found</h5>
                    <p class="text-muted">
                        <?= ($status !== 'all' || $course_id) ? 'Try adjusting your filters or ' : '' ?>
                        check back later.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Submit Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="submitForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="assignment_id" id="assignmentId">
                    <div class="mb-3">
                        <label class="form-label">File</label>
                        <input type="file" name="file" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle submit modal
    const submitModal = document.getElementById('submitModal');
    if (submitModal) {
        submitModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const assignmentId = button.getAttribute('data-id');
            document.getElementById('assignmentId').value = assignmentId;
        });
    }
    
    // Handle form submission
    const form = document.getElementById('submitForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            
            fetch('submit_assignment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Assignment submitted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to submit assignment'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the assignment');
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
