<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireLogin();

$student_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total attempts count
$count_query = "SELECT COUNT(*) as total FROM test_attempts WHERE student_id = ?";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$total_attempts = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_attempts / $per_page);
$stmt->close();

// Get test attempts with pagination
$query = "
    SELECT ta.*, t.title as test_title, c.title as course_title,
           c.code as course_code, t.total_marks, t.passing_marks
    FROM test_attempts ta
    JOIN tests t ON ta.test_id = t.id
    JOIN courses c ON t.course_id = c.id
    WHERE ta.student_id = ?
    ORDER BY ta.completed_at DESC, ta.started_at DESC
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $student_id, $per_page, $offset);
$stmt->execute();
$attempts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_attempts,
        SUM(IF(ta.is_passed = 1, 1, 0)) as passed_attempts,
        AVG(ta.score) as avg_score,
        MAX(ta.score) as highest_score
    FROM test_attempts ta
    WHERE ta.student_id = ? AND ta.status = 'completed'";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pass_rate = $stats['total_attempts'] > 0 
    ? round(($stats['passed_attempts'] / $stats['total_attempts']) * 100, 1) 
    : 0;

$page_title = 'My Test Results';
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Test Results</h1>
        <a href="tests.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Tests
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Tests Taken</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['total_attempts'] ?? 0; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Pass Rate</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $pass_rate; ?>%
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percentage fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Average Score</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['avg_score'] ?? 0, 1); ?>%
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Highest Score</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['highest_score'] ?? 0, 1); ?>%
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-trophy fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Attempts Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">My Test Attempts</h6>
            <div>
                <span class="badge bg-success">Passed</span>
                <span class="badge bg-danger">Failed</span>
                <span class="badge bg-secondary">In Progress</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($attempts) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Course</th>
                                <th>Attempt Date</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): 
                                $is_passed = $attempt['is_passed'] ?? false;
                                $status = $attempt['status'];
                                $status_class = '';
                                $status_text = '';
                                
                                if ($status === 'completed') {
                                    $status_class = $is_passed ? 'bg-success' : 'bg-danger';
                                    $status_text = $is_passed ? 'Passed' : 'Failed';
                                } else {
                                    $status_class = 'bg-secondary';
                                    $status_text = 'In Progress';
                                }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attempt['test_title']); ?></td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($attempt['course_code']); ?></small><br>
                                        <?php echo htmlspecialchars($attempt['course_title']); ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($attempt['started_at'])); ?>
                                        <small class="d-block text-muted">
                                            <?php echo date('g:i A', strtotime($attempt['started_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($status === 'completed'): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                    <div class="progress-bar <?php echo $is_passed ? 'bg-success' : 'bg-danger'; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $attempt['score']; ?>%" 
                                                         aria-valuenow="<?php echo $attempt['score']; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="fw-bold"><?php echo number_format($attempt['score'], 1); ?>%</span>
                                            </div>
                                            <small class="text-muted">Pass: <?php echo $attempt['passing_marks']; ?>%</small>
                                        <?php else: ?>
                                            <span class="text-muted">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="test_result.php?attempt_id=<?php echo $attempt['id']; ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($attempt['status'] === 'in_progress'): ?>
                                            <a href="view_test.php?attempt_id=<?php echo $attempt['id']; ?>" 
                                               class="btn btn-sm btn-warning" 
                                               title="Continue Test">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                    <h4>No test attempts found</h4>
                    <p class="text-muted">You haven't taken any tests yet.</p>
                    <a href="tests.php" class="btn btn-primary">
                        <i class="fas fa-file-alt me-2"></i>View Available Tests
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
