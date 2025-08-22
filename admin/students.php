<?php
require_once '../config/config.php';
require_once '../includes/auth_functions.php';
requireLogin();
requireAdmin();

$page_title = 'Manage Students';

// Handle student actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_student') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $course_id = (int)$_POST['course_id'];
        
        // Generate unique username in format MSX-YYYY-XXXXX
        $year = date('Y');
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username LIKE ?");
        $prefix = "MSX-{$year}-";
        $like_prefix = $prefix . '%';
        $stmt->bind_param("s", $like_prefix);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $next_number = str_pad(($result['count'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);
        $username = $prefix . $next_number;
        
        // Generate strong temporary password (12 characters with mixed case, numbers, and special chars)
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Mark password as temporary
        $is_temp_password = 1;
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            setFlashMessage('error', 'A user with this email already exists.');
            header("Location: students.php");
            exit();
        }
        
        // Get course details for email
        $course_stmt = $conn->prepare("SELECT title, code FROM courses WHERE id = ?");
        $course_stmt->bind_param("i", $course_id);
        $course_stmt->execute();
        $course = $course_stmt->get_result()->fetch_assoc();
        $course_stmt->close();
        
        // Insert new student with temp password flag
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, phone, role, status, is_temp_password) VALUES (?, ?, ?, ?, ?, 'student', 'active', ?)");
        $stmt->bind_param("sssssi", $username, $hashed_password, $email, $full_name, $phone, $is_temp_password);
        
        if ($stmt->execute()) {
            $student_id = $stmt->insert_id;
            
            // Enroll student in course
            $stmt = $conn->prepare("INSERT INTO student_courses (student_id, course_id, enrollment_date, status) VALUES (?, ?, NOW(), 'active')");
            $stmt->bind_param("ii", $student_id, $course_id);
            $enrolled = $stmt->execute();
            
            if ($enrolled) {
                // Include email sending function
                require_once __DIR__ . '/../includes/email_functions.php';
                
                // Prepare email data with course details
                $email_data = [
                    'full_name' => $full_name,
                    'username' => $username,
                    'password' => $password,
                    'login_url' => SITE_URL . '/login.php',
                    'site_name' => SITE_NAME,
                    'course_name' => $course['title'] ?? 'N/A',
                    'course_code' => $course['code'] ?? 'N/A',
                    'enrollment_date' => date('F j, Y'),
                    'support_email' => 'support@' . parse_url(SITE_URL, PHP_URL_HOST)
                ];
                
                // Send the email with student credentials and course details
                if (sendEmailTemplate($email, 'student_registration', $email_data)) {
                    setFlashMessage('success', 'Student added successfully. Login details have been sent to their email.');
                } else {
                    setFlashMessage('warning', 'Student was added, but there was an error sending the email. Please provide the login details manually.');
                }
            } else {
                // If course enrollment fails, delete the user to maintain data consistency
                $conn->query("DELETE FROM users WHERE id = $student_id");
                setFlashMessage('error', 'Failed to enroll student in course.');
            }
        } else {
            setFlashMessage('error', 'Failed to add student: ' . $conn->error);
        }
        
        header("Location: students.php");
        exit();
    }
}

// Get all students with their course information
$query = "SELECT u.*, c.title as course_name, sc.status as enrollment_status 
          FROM users u 
          LEFT JOIN student_courses sc ON u.id = sc.student_id 
          LEFT JOIN courses c ON sc.course_id = c.id 
          WHERE u.role = 'student' 
          ORDER BY u.created_at DESC";
$students = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get all active courses for the add student form
$courses = $conn->query("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title")->fetch_all(MYSQLI_ASSOC);

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">Manage Students</h1>
        </div>
        <!-- <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="fas fa-plus"></i> Add New Student
            </button>
        </div> -->
    </div>

    <?php echo displayFlashMessage(); ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">All Students</h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                        <i class="fas fa-upload"></i> Bulk Upload
                    </button>
                    <a href="download_student_template.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-file-download"></i> Download Format
                    </a>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus"></i> Add Student
                    </button>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <form method="get" class="row g-2">
                        <div class="col-md-3">
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($_GET['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-9">
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Search students..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (isset($_GET['search']) || isset($_GET['status'])): ?>
                                    <a href="students.php" class="btn btn-outline-danger">
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
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) > 0): ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= $student['id'] ?></td>
                                    <td><?= htmlspecialchars($student['full_name']) ?></td>
                                    <td><?= htmlspecialchars($student['email']) ?></td>
                                    <td><?= !empty($student['course_name']) ? htmlspecialchars($student['course_name']) : 'Not Enrolled' ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($student['status'] ?? 'inactive') === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($student['status'] ?? 'inactive') ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($student['created_at'])) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="student_details.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_student.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete_student.php?id=<?= $student['id'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No students found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkUploadModalLabel">Bulk Upload Students</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Upload an Excel file with student details. 
                    <a href="download_student_template.php" class="alert-link">Download the template</a> for the correct format.
                </div>
                <form action="bulk_upload_students.php" method="post" enctype="multipart/form-data" id="bulkUploadForm">
                    <div class="mb-3">
                        <label for="studentFile" class="form-label">Select Excel File</label>
                        <input class="form-control" type="file" id="studentFile" name="student_file" accept=".xlsx,.xls" required>
                        <div class="form-text">Only .xlsx or .xls files are allowed. Max size: 5MB</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="bulkUploadForm" class="btn btn-primary">
                    <i class="fas fa-upload me-1"></i> Upload & Process
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStudentModalLabel">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_student">
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#dataTable').DataTable({
        responsive: true,
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting on actions column
        ]
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>