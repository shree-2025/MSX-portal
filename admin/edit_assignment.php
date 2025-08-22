<?php
// Start session and include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Ensure user is logged in and is an admin
requireAdmin();

$page_title = 'Edit Assignment';
$errors = [];

// Get assignment ID from URL
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($assignment_id <= 0) {
    setFlashMessage('error', 'Invalid assignment ID.');
    header('Location: assignments.php');
    exit();
}

// Get assignment details
$stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    setFlashMessage('error', 'Assignment not found.');
    header('Location: assignments.php');
    exit();
}

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
    $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'closed']) ? $_POST['status'] : 'draft';
    $is_active = isset($_POST['is_active']) && $_POST['is_active'] == 1 ? 1 : 0;
    $remove_file = isset($_POST['remove_file']) && $_POST['remove_file'] === '1';
    
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
    
    // Handle file upload/removal
    $file_path = $assignment['file_path'];
    $file_name = $assignment['file_name'];
    $file_size = $assignment['file_size'];
    
    // Check if removing existing file
    if ($remove_file && $file_path) {
        $old_file = __DIR__ . '/../uploads/assignments/' . $file_path;
        if (file_exists($old_file)) {
            unlink($old_file);
        }
        $file_path = null;
        $file_name = null;
        $file_size = null;
    }
    
    // Handle new file upload if present
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        // Remove old file if exists
        if ($file_path) {
            $old_file = __DIR__ . '/../uploads/assignments/' . $file_path;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        
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
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_path = 'assignment_' . time() . '_' . uniqid() . '.' . $file_ext;
        $destination = $upload_dir . $file_path;
        
        if (empty($errors)) {
            if (!move_uploaded_file($file_tmp, $destination)) {
                $errors[] = 'Failed to upload file. Please try again.';
                $file_path = $assignment['file_path'];
                $file_name = $assignment['file_name'];
                $file_size = $assignment['file_size'];
            }
        }
    }
    
    // If no errors, update the database
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Build the base query
            $sql = "UPDATE assignments SET 
                    title = ?, 
                    description = ?, 
                    course_id = ?, 
                    due_date = ?, 
                    total_marks = ?, 
                    status = ?,
                    is_active = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $params = [&$title, &$description, &$course_id, &$due_date, &$total_marks, &$status, &$is_active, &$assignment_id];
            $types = "ssisssii";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update assignment: ' . $conn->error);
            }
            
            // Handle file upload if a new file was provided
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
                $file_path = 'assignment_' . time() . '_' . uniqid() . '.' . $file_ext;
                $destination = $upload_dir . $file_path;
                
                // Move the uploaded file
                if (!move_uploaded_file($file_tmp, $destination)) {
                    throw new Exception('Failed to upload file. Please try again.');
                }
                
                // Update file info in database
                $update_file_sql = "UPDATE assignments SET file_path = ?, file_name = ?, file_size = ? WHERE id = ?";
                $update_file_stmt = $conn->prepare($update_file_sql);
                $update_file_stmt->bind_param("ssii", $file_path, $file_name, $file_size, $assignment_id);
                
                if (!$update_file_stmt->execute()) {
                    throw new Exception('Failed to update file information: ' . $conn->error);
                }
            }
            
            // If we got here, everything worked
            $conn->commit();
            setFlashMessage('success', 'Assignment updated successfully!');
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
        <h1 class="h3 mb-0 text-gray-800">Edit Assignment</h1>
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
                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($_POST['title'] ?? $assignment['title']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= (isset($_POST['course_id']) ? $_POST['course_id'] : $assignment['course_id']) == $course['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? $assignment['description']) ?></textarea>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="due_date" name="due_date" 
                               value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($_POST['due_date'] ?? $assignment['due_date']))) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="total_marks" class="form-label">Total Marks</label>
                        <input type="number" class="form-control" id="total_marks" name="total_marks" 
                               value="<?= htmlspecialchars($_POST['total_marks'] ?? $assignment['total_marks']) ?>" min="1">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft" <?= (($assignment['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= (($assignment['status'] ?? 'draft') === 'published') ? 'selected' : '' ?>>Published</option>
                            <option value="closed" <?= (($assignment['status'] ?? 'draft') === 'closed') ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="form-check mt-4 pt-2">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" <?= (($assignment['is_active'] ?? 1) == 1 ? 'checked' : '') ?>>
                            <label class="form-check-label" for="is_active">Active (Visible to students)</label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Current File</label>
                    <?php if ($assignment['file_path']): ?>
                        <div class="input-group mb-2">
                            <a href="/uploads/assignments/<?= htmlspecialchars($assignment['file_path']) ?>" class="form-control" target="_blank">
                                <i class="fas fa-file-download"></i> <?= htmlspecialchars($assignment['file_name']) ?>
                            </a>
                            <button type="button" class="btn btn-outline-danger" onclick="document.getElementById('remove_file').value = '1'; this.previousElementSibling.classList.add('text-decoration-line-through'); this.disabled = true;">
                                <i class="fas fa-times"></i> Remove
                            </button>
                            <input type="hidden" name="remove_file" id="remove_file" value="0">
                        </div>
                    <?php else: ?>
                        <div class="text-muted mb-2">No file uploaded</div>
                    <?php endif; ?>
                    
                    <label for="assignment_file" class="form-label"><?= $assignment['file_path'] ? 'Replace File' : 'Upload File' ?></label>
                    <input class="form-control" type="file" id="assignment_file" name="assignment_file">
                    <div class="form-text">Supported formats: PDF, DOC, DOCX, TXT, ZIP, RAR, PPT, PPTX, XLS, XLSX (Max 10MB)</div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="assignments.php" class="btn btn-secondary me-md-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Assignment
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
});
</script>
