<?php
require_once '../config/config.php';
require_once '../includes/auth_functions.php';
requireLogin();
requireAdmin();

$page_title = 'Student Details';
$student_id = (int)($_GET['id'] ?? 0);

if (!$student_id) {
    setFlashMessage('error', 'Invalid student ID.');
    header('Location: students.php');
    exit();
}

// Get student details
$query = "SELECT u.*, c.title as course_name, sc.status as enrollment_status 
          FROM users u 
          LEFT JOIN student_courses sc ON u.id = sc.student_id 
          LEFT JOIN courses c ON sc.course_id = c.id 
          WHERE u.id = ? AND u.role = 'student'
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    setFlashMessage('error', 'Student not found.');
    header('Location: students.php');
    exit();
}

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Student Details</h1>
        <div>
            <a href="edit_student.php?id=<?= $student['id'] ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="students.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile</h6>
                </div>
                <div class="card-body text-center">
                    <img src="<?= !empty($student['profile_photo']) ? '../uploads/profile_photos/' . htmlspecialchars($student['profile_photo']) : 'https://ui-avatars.com/api/?name=' . urlencode($student['full_name']) . '&background=4f46e5&color=fff&size=200' ?>" 
                         class="img-profile rounded-circle mb-3" 
                         style="width: 150px; height: 150px; object-fit: cover;"
                         alt="Profile Photo">
                    <h4><?= htmlspecialchars($student['full_name']) ?></h4>
                    <p class="text-muted"><?= htmlspecialchars($student['email']) ?></p>
                    <span class="badge bg-<?= $student['status'] === 'active' ? 'success' : 'secondary' ?>">
                        <?= ucfirst($student['status']) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Details</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Phone:</strong> <?= !empty($student['phone']) ? htmlspecialchars($student['phone']) : 'N/A' ?></p>
                            <p><strong>Enrolled Course:</strong> <?= !empty($student['course_name']) ? htmlspecialchars($student['course_name']) : 'Not Enrolled' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Member Since:</strong> <?= date('M d, Y', strtotime($student['created_at'])) ?></p>
                            <p><strong>Last Login:</strong> <?= !empty($student['last_login']) ? date('M d, Y h:i A', strtotime($student['last_login'])) : 'Never' ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
