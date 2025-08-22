<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

$test_id = (int)($_GET['test_id'] ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0);

// Get test details if test_id is provided
$test = [];
if ($test_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM tests WHERE id = ?");
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $test = $stmt->get_result()->fetch_assoc();
}

// Get all tests for filter dropdown
$tests = [];
$result = $conn->query("SELECT id, title FROM tests ORDER BY title");
if ($result) {
    $tests = $result->fetch_all(MYSQLI_ASSOC);
}

// Build query for results
$query = "
    SELECT 
        ta.*, 
        u.username as student_name,
        u.email as student_email,
        t.title as test_title,
        c.title as course_title
    FROM test_attempts ta
    JOIN users u ON ta.student_id = u.id
    JOIN tests t ON ta.test_id = t.id
    JOIN courses c ON t.course_id = c.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($test_id > 0) {
    $query .= " AND ta.test_id = ?";
    $params[] = $test_id;
    $types .= "i";
}

if ($student_id > 0) {
    $query .= " AND ta.student_id = ?";
    $params[] = $student_id;
    $types .= "i";
}

$query .= " ORDER BY ta.submitted_at DESC";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Include header
$page_title = 'Test Results' . ($test ? ': ' . $test['title'] : '');
include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
        <a href="tests.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Tests
        </a>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Select Test</label>
                    <select name="test_id" class="form-select">
                        <option value="">All Tests</option>
                        <?php foreach ($tests as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $test_id == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Student ID (optional)</label>
                    <input type="number" name="student_id" class="form-control" 
                           value="<?php echo $student_id ?: ''; ?>" placeholder="Enter Student ID">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Test Attempts</h6>
        </div>
        <div class="card-body">
            <?php if (empty($results)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                    <p class="text-muted">No test attempts found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="resultsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Test</th>
                                <th>Course</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Submitted At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['student_name']); ?><br>
                                        <small class="text-muted"><?php echo $row['student_email']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['test_title']); ?></td>
                                    <td><?php echo htmlspecialchars($row['course_title']); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?php 
                                                echo $row['is_passed'] ? 'bg-success' : 'bg-danger'; 
                                            ?>" role="progressbar" 
                                                style="width: <?php echo $row['percentage']; ?>%" 
                                                aria-valuenow="<?php echo $row['percentage']; ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                                <?php echo number_format($row['percentage'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $row['is_passed'] ? 'success' : 'danger'; 
                                        ?>">
                                            <?php echo $row['is_passed'] ? 'Passed' : 'Failed'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($row['submitted_at'])); ?></td>
                                    <td>
                                        <a href="test_result_details.php?attempt_id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DataTables JS -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#resultsTable').DataTable({
        order: [[6, 'desc']], // Sort by submission date by default
        pageLength: 25,
        responsive: true
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
