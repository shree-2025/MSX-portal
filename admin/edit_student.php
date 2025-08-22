<?php
require_once '../config/config.php';
require_once '../includes/auth_functions.php';
requireLogin();
requireAdmin();

$page_title = 'Edit Student';
$student_id = (int)($_GET['id'] ?? 0);

if (!$student_id) {
    setFlashMessage('error', 'Invalid student ID.');
    header('Location: students.php');
    exit();
}

// Get student details
$query = "SELECT * FROM users WHERE id = ? AND role = 'student' LIMIT 1";
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'];
    
    // Basic validation
    if (empty($full_name) || empty($email)) {
        setFlashMessage('error', 'Full name and email are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlashMessage('error', 'Please enter a valid email address.');
    } else {
        // Check if email exists (excluding current student)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $student_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            setFlashMessage('error', 'A user with this email already exists.');
        } else {
            // Update user details
            $query = "UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $full_name, $email, $phone, $status, $student_id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Student updated successfully.');
                header("Location: student_details.php?id=" . $student_id);
                exit();
            } else {
                setFlashMessage('error', 'Failed to update student: ' . $conn->error);
            }
            $stmt->close();
        }
    }
}

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Student</h1>
        <div>
            <a href="student_details.php?id=<?= $student['id'] ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Details
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Edit Student Information</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?= htmlspecialchars($student['full_name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($student['email']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($student['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?= $student['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $student['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
