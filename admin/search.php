<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

// Get search query
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

// Initialize results array
$results = [
    'students' => [],
    'courses' => [],
    'assignments' => []
];

if (!empty($searchQuery)) {
    // Search students
    $stmt = $conn->prepare("SELECT id, username, email, full_name FROM users 
                          WHERE (username LIKE ? OR email LIKE ? OR full_name LIKE ?)
                          AND role = 'student'");
    $searchParam = "%$searchQuery%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
    $results['students'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Search courses
    $stmt = $conn->prepare("SELECT id, title as course_name, description FROM courses 
                          WHERE title LIKE ? OR description LIKE ?");
    $stmt->bind_param("ss", $searchParam, $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
    $results['courses'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Search assignments
    $stmt = $conn->prepare("SELECT a.id, a.title, c.title as course_name 
                          FROM assignments a 
                          JOIN courses c ON a.course_id = c.id 
                          WHERE a.title LIKE ? OR a.description LIKE ? OR c.title LIKE ?");
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
    $results['assignments'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Set page title
$pageTitle = "Search Results: " . htmlspecialchars($searchQuery);
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"</h1>
    
    <?php if (empty($searchQuery)): ?>
        <div class="alert alert-info">Please enter a search term.</div>
    <?php else: ?>
        <!-- Students Results -->
        <?php if (!empty($results['students'])): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Students</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['students'] as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                        <td>
                                            <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                            <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Courses Results -->
        <?php if (!empty($results['courses'])): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Courses</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['courses'] as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td>
                                            <a href="view_course.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                            <a href="edit_course.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Assignments Results -->
        <?php if (!empty($results['assignments'])): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Assignments</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['assignments'] as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['course_name']); ?></td>
                                        <td>
                                            <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                            <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($results['students']) && empty($results['courses']) && empty($results['assignments'])): ?>
            <div class="alert alert-warning">No results found for "<?php echo htmlspecialchars($searchQuery); ?>".</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
