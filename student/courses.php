<?php
require_once __DIR__ . '/includes/header.php';

$student_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';

// Build query based on filters
$query = "SELECT c.*, sc.enrollment_date, sc.status as enrollment_status 
          FROM courses c 
          JOIN student_courses sc ON c.id = sc.course_id 
          WHERE sc.student_id = ?";
$params = [$student_id];
$types = "i";

if ($status !== 'all') {
    $query .= " AND sc.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$query .= " ORDER BY sc.enrollment_date DESC";

$stmt = $conn->prepare($query);

// Bind parameters dynamically
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$courses = $stmt->get_result();
$stmt->close();
?>

<!-- Begin Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Courses</h1>
        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Enroll in New Course
        </a>
    </div>

    <!-- Filter Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Courses</h6>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Search courses...">
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="dropped" <?php echo $status === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Courses List -->
    <div class="row">
        <?php if ($courses->num_rows > 0): ?>
            <?php while($course = $courses->fetch_assoc()): 
                // Get course progress
                $query = "SELECT 
                    COUNT(*) as total_assignments,
                    SUM(CASE WHEN s.marks_obtained IS NOT NULL THEN 1 ELSE 0 END) as completed_assignments
                    FROM assignments a
                    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
                    WHERE a.course_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $student_id, $course['id']);
                $stmt->execute();
                $progress = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $progress_percent = $progress['total_assignments'] > 0 ? 
                    round(($progress['completed_assignments'] / $progress['total_assignments']) * 100) : 0;
                
                // Get next due assignment
                $query = "SELECT title, due_date FROM assignments 
                         WHERE course_id = ? AND due_date > NOW() 
                         ORDER BY due_date ASC LIMIT 1";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $course['id']);
                $stmt->execute();
                $next_assignment = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                // Status badge
                $status_class = [
                    'active' => 'success',
                    'completed' => 'primary',
                    'dropped' => 'secondary'
                ][$course['enrollment_status']] ?? 'secondary';
                ?>
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="position-relative">
                            <img src="https://via.placeholder.com/400x200?text=<?php echo urlencode($course['title']); ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>">
                            <span class="position-absolute top-0 end-0 m-2 badge bg-<?php echo $status_class; ?>">
                                <?php echo ucfirst($course['enrollment_status']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                            <p class="card-text text-muted small">
                                <?php echo strlen($course['description']) > 100 ? 
                                    substr($course['description'], 0, 100) . '...' : 
                                    $course['description']; ?>
                            </p>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small">Progress</span>
                                    <span class="small"><?php echo $progress_percent; ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php 
                                        echo $progress_percent < 30 ? 'danger' : 
                                            ($progress_percent < 70 ? 'warning' : 'success'); 
                                    ?>" role="progressbar" 
                                         style="width: <?php echo $progress_percent; ?>%" 
                                         aria-valuenow="<?php echo $progress_percent; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="text-end small text-muted mt-1">
                                    <?php echo $progress['completed_assignments']; ?> of <?php echo $progress['total_assignments']; ?> assignments
                                </div>
                            </div>
                            
                            <?php if ($next_assignment): ?>
                                <div class="alert alert-warning p-2 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <div>
                                            <div class="fw-bold small">Next Assignment</div>
                                            <div class="small">
                                                <?php echo htmlspecialchars($next_assignment['title']); ?>
                                            </div>
                                            <div class="small text-muted">
                                                Due: <?php echo date('M d, Y', strtotime($next_assignment['due_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Enrolled: <?php echo date('M d, Y', strtotime($course['enrollment_date'])); ?>
                                </small>
                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    View Course <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-book-open fa-3x text-gray-300 mb-3"></i>
                    <h4>No courses found</h4>
                    <p class="text-muted">
                        <?php echo $status !== 'all' || !empty($search) ? 
                            'Try adjusting your filters or ' : ''; ?>
                        <a href="#" class="text-primary">browse available courses</a> to enroll.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<!-- /.container-fluid -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
