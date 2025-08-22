<?php
// Start session and include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

$page_title = 'Add New Assignment';
$errors = [];

// Get all active courses for the dropdown
$courses = $conn->query("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $course_id = (int)($_POST['course_id'] ?? 0);
    $due_date = $_POST['due_date'] ?? '';
    $total_marks = (int)($_POST['total_marks'] ?? 100);
    $status = in_array($_POST['status'] ?? '', ['draft', 'published']) ? $_POST['status'] : 'draft';
    $is_active = isset($_POST['is_active']) && $_POST['is_active'] == 1 ? 1 : 0;
    
    // Validate required fields
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    
    if ($course_id <= 0) {
        $errors[] = 'Please select a valid course';
    }
    
    if (empty($due_date) || strtotime($due_date) === false) {
        $errors[] = 'Please select a valid due date';
    }
    
    // Handle file upload if present
    $file_path = null;
    $file_name = null;
    $file_size = null;
    
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['assignment_file'];
        $file_name = basename($file['name']);
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'ppt', 'pptx', 'xls', 'xlsx'];
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions);
        }
        
        // Validate file size (max 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file_size > $max_size) {
            $errors[] = 'File is too large. Maximum size is 10MB.';
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/assignments/';
        
        try {
            // Create directory recursively if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }
            
            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                throw new Exception('Upload directory is not writable. Please check permissions.');
            }
            
            // Generate unique filename
            $file_path = 'assignment_' . time() . '_' . uniqid() . '.' . $file_ext;
            $destination = $upload_dir . $file_path;
            
            // Verify the file was actually uploaded via HTTP POST
            if (!is_uploaded_file($file_tmp)) {
                throw new Exception('File upload failed: Invalid file upload.');
            }
            
            // Move the uploaded file
            if (!move_uploaded_file($file_tmp, $destination)) {
                $error = error_get_last();
                throw new Exception('Failed to move uploaded file: ' . ($error['message'] ?? 'Unknown error'));
            }
            
            // Verify the file was moved successfully
            if (!file_exists($destination)) {
                throw new Exception('File upload failed: File not found after move.');
            }
            
        } catch (Exception $e) {
            $errors[] = 'File upload error: ' . $e->getMessage();
            error_log('File upload failed: ' . $e->getMessage());
            $file_path = null;
            $file_name = null;
            $file_size = null;
        }
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Log file upload attempt
            error_log('Attempting to save assignment. File: ' . ($file_name ?? 'No file') . ', Size: ' . ($file_size ?? 0) . ' bytes');
            
            // First, insert the basic assignment data
            $stmt = $conn->prepare("INSERT INTO assignments (title, description, course_id, due_date, total_marks, status, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisssii", 
                $title, 
                $description, 
                $course_id, 
                $due_date, 
                $total_marks, 
                $status,
                $is_active,
                $_SESSION['user_id']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create assignment: ' . $conn->error);
            }
            
            $assignment_id = $conn->insert_id;
            
            // Handle file upload if a file was provided
            if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['assignment_file'];
                $file_name = basename($file['name']);
                $file_size = $file['size'];
                $file_tmp = $file['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Validate file type
                $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'ppt', 'pptx', 'xls', 'xlsx'];
                if (!in_array($file_ext, $allowed_extensions)) {
                    throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions));
                }
                
                // Create uploads directory if it doesn't exist
                $upload_dir = __DIR__ . '/../uploads/assignments/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_path = 'assignment_' . $assignment_id . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $file_path;
                
                // Move the uploaded file
                if (!move_uploaded_file($file_tmp, $destination)) {
                    throw new Exception('Failed to upload file. Please try again.');
                }
                
                // Update the assignment with file info
                $update_sql = "UPDATE assignments SET file_path = ?, file_name = ?, file_size = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssii", $file_path, $file_name, $file_size, $assignment_id);
                
                if (!$update_stmt->execute()) {
                    throw new Exception('Failed to update file information: ' . $conn->error);
                }
            }
            
            // If we got here, everything worked
            $conn->commit();
            setFlashMessage('success', 'Assignment created successfully!');
            header('Location: assignments.php');
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

include_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Add New Assignment</h1>
        <a href="assignments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Assignments
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="title" class="form-label">Assignment Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= (isset($_POST['course_id']) && $_POST['course_id'] == $course['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="due_date" name="due_date" 
                               value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="total_marks" class="form-label">Total Marks</label>
                        <input type="number" class="form-control" id="total_marks" name="total_marks" 
                               value="<?= htmlspecialchars($_POST['total_marks'] ?? 100) ?>" min="1">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="draft" <?= (isset($_POST['status']) && $_POST['status'] === 'draft') ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= (isset($_POST['status']) && $_POST['status'] === 'published') ? 'selected' : '' ?>>Published</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= (isset($_POST['is_active']) && $_POST['is_active'] == 1) ? 'checked' : 'checked' ?>>
                        <label class="form-check-label" for="is_active">Active (Visible to students)</label>
                        <div class="form-text">Uncheck to hide this assignment from students</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="assignment_file" class="form-label">Assignment File</label>
                    <input class="form-control" type="file" id="assignment_file" name="assignment_file">
                    <div class="form-text">Supported formats: PDF, DOC, DOCX, TXT, ZIP, RAR, PPT, PPTX, XLS, XLSX (Max 10MB)</div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<!-- Initialize date picker -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date to today
    const today = new Date().toISOString().slice(0, 16);
    document.getElementById('due_date').min = today;
    
    // If no due date is set, set it to tomorrow by default
    if (!document.getElementById('due_date').value) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('due_date').value = tomorrow.toISOString().slice(0, 16);
    }
});
</script>
