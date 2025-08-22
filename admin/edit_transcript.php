<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: /login.php');
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Transcript ID not provided';
    header('Location: transcripts.php');
    exit();
}

$transcriptId = (int)$_GET['id'];

// Fetch transcript data
$stmt = $conn->prepare("
    SELECT t.*, u.full_name, u.email, u.created_at as enrollment_date
    FROM transcripts t
    JOIN users u ON t.student_id = u.id
    WHERE t.id = ?
");

$stmt->bind_param('i', $transcriptId);
$stmt->execute();
$transcript = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transcript) {
    $_SESSION['error'] = 'Transcript not found';
    header('Location: transcripts.php');
    exit();
}

// Fetch courses for this transcript
$stmt = $conn->prepare("
    SELECT c.*, tc.grade, tc.credits_earned
    FROM transcript_courses tc
    JOIN courses c ON tc.course_id = c.id
    WHERE tc.transcript_id = ?
    ORDER BY c.title
");

$stmt->bind_param('i', $transcriptId);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_transcript'])) {
    $gpa = $_POST['gpa'] ?? '';
    $completionDate = $_POST['completion_date'] ?? '';
    $additionalNotes = $_POST['additional_notes'] ?? '';
    $programName = $_POST['program_name'] ?? '';
    
    if ($gpa && $completionDate && $programName) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update transcript
            $stmt = $conn->prepare("UPDATE transcripts SET gpa = ?, completion_date = ?, additional_notes = ?, program_name = ? WHERE id = ?");
            $stmt->bind_param('dsssi', $gpa, $completionDate, $additionalNotes, $programName, $transcriptId);
            
            if ($stmt->execute()) {
                $stmt->close();
                
                // Update courses
                if (!empty($_POST['courses'])) {
                    // First, delete existing courses
                    $stmt = $conn->prepare("DELETE FROM transcript_courses WHERE transcript_id = ?");
                    $stmt->bind_param('i', $transcriptId);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Insert updated courses
                    $stmt = $conn->prepare("INSERT INTO transcript_courses (transcript_id, course_id, grade, credits_earned) VALUES (?, ?, ?, ?)");
                    foreach ($_POST['courses'] as $course) {
                        if (!empty($course['course_id']) && !empty($course['grade'])) {
                            $credits = $course['credits'] ?? 3; // Default to 3 credits if not provided
                            $stmt->bind_param('iisi', $transcriptId, $course['course_id'], $course['grade'], $credits);
                            $stmt->execute();
                        }
                    }
                    $stmt->close();
                }
                
                $conn->commit();
                $_SESSION['success'] = 'Transcript updated successfully!';
                header('Location: transcripts.php');
                exit();
            } else {
                throw new Exception('Failed to update transcript: ' . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields';
    }
}

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Edit Transcript</h4>
                    <p class="text-muted mb-0">Update the transcript details below</p>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Student Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($transcript['full_name']) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Program Name <span class="text-danger">*</span></label>
                                <input type="text" name="program_name" class="form-control" value="<?= htmlspecialchars($transcript['program_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">GPA <span class="text-danger">*</span></label>
                                <input type="number" name="gpa" class="form-control" step="0.01" min="0" max="4" value="<?= htmlspecialchars($transcript['gpa']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Completion Date <span class="text-danger">*</span></label>
                                <input type="date" name="completion_date" class="form-control" value="<?= htmlspecialchars($transcript['completion_date']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Transcript Number</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($transcript['transcript_number']) ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea name="additional_notes" class="form-control" rows="3"><?= htmlspecialchars($transcript['additional_notes'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5>Courses</h5>
                                <button type="button" class="btn btn-sm btn-primary" id="add-course-btn">
                                    <i class="fas fa-plus"></i> Add Course
                                </button>
                            </div>
                            
                            <div id="courses-container">
                                <?php foreach ($courses as $index => $course): ?>
                                <div class="row g-3 mb-3 align-items-end" data-course-index="<?= $index ?>">
                                    <div class="col-md-5">
                                        <label class="form-label">Course <span class="text-danger">*</span></label>
                                        <select name="courses[<?= $index ?>][course_id]" class="form-select" required>
                                            <option value="">Select Course</option>
                                            <?php
                                            $courseStmt = $conn->query("SELECT * FROM courses ORDER BY title");
                                            while ($c = $courseStmt->fetch_assoc()):
                                                $selected = ($c['id'] == $course['course_id']) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $c['id'] ?>" data-credits="<?= $c['credits'] ?? 3 ?>" <?= $selected ?>>
                                                <?= htmlspecialchars($c['title']) ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Grade <span class="text-danger">*</span></label>
                                        <select name="courses[<?= $index ?>][grade]" class="form-select" required>
                                            <option value="">Select Grade</option>
                                            <?php
                                            $grades = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'];
                                            foreach ($grades as $grade):
                                                $selected = ($grade == $course['grade']) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $grade ?>" <?= $selected ?>><?= $grade ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Credits <span class="text-danger">*</span></label>
                                        <input type="number" name="courses[<?= $index ?>][credits]" class="form-control credits" 
                                               value="<?= $course['credits_earned'] ?? ($course['credits'] ?? 3) ?>" min="1" max="10" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeCourseField(this)">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="transcripts.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                            <button type="submit" name="update_transcript" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Transcript
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Course Template (Hidden) -->
<template id="course-template">
    <div class="row g-3 mb-3 align-items-end" data-course-index="{{index}}">
        <div class="col-md-5">
            <label class="form-label">Course <span class="text-danger">*</span></label>
            <select name="courses[{{index}}][course_id]" class="form-select" required>
                <option value="">Select Course</option>
                <?php
                $courseStmt = $conn->query("SELECT * FROM courses ORDER BY title");
                while ($course = $courseStmt->fetch_assoc()):
                ?>
                <option value="<?= $course['id'] ?>" data-credits="<?= $course['credits'] ?? 3 ?>">
                    <?= htmlspecialchars($course['title']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Grade <span class="text-danger">*</span></label>
            <select name="courses[{{index}}][grade]" class="form-select" required>
                <option value="">Select Grade</option>
                <?php
                $grades = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'];
                foreach ($grades as $grade):
                ?>
                <option value="<?= $grade ?>"><?= $grade ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Credits <span class="text-danger">*</span></label>
            <input type="number" name="courses[{{index}}][credits]" class="form-control credits" value="3" min="1" max="10" required>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeCourseField(this)">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
    </div>
</template>

<script>
// Add course field
document.getElementById('add-course-btn').addEventListener('click', function() {
    const container = document.getElementById('courses-container');
    const template = document.getElementById('course-template');
    const index = container.querySelectorAll('[data-course-index]').length;
    
    // Create new course field
    const newCourse = template.innerHTML.replace(/\{\{index\}\}/g, index);
    const div = document.createElement('div');
    div.innerHTML = newCourse;
    container.appendChild(div.firstElementChild);
    
    // Initialize select2 if available
    if (typeof $.fn.select2 !== 'undefined') {
        $(div).find('select').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
    
    // Auto-update credits when course is selected
    const select = div.querySelector('select[name^="courses["]');
    const creditsInput = div.querySelector('.credits');
    
    select.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const credits = selectedOption.getAttribute('data-credits') || 3;
        creditsInput.value = credits;
    });
});

// Remove course field
function removeCourseField(button) {
    const container = document.getElementById('courses-container');
    const courseFields = container.querySelectorAll('[data-course-index]');
    
    if (courseFields.length > 1) {
        button.closest('[data-course-index]').remove();
        
        // Re-index the remaining fields
        const fields = container.querySelectorAll('[data-course-index]');
        fields.forEach((field, index) => {
            field.setAttribute('data-course-index', index);
            
            // Update the name attributes
            field.querySelectorAll('[name^="courses["]').forEach(input => {
                const name = input.getAttribute('name');
                const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                input.setAttribute('name', newName);
            });
        });
    } else {
        alert('At least one course is required');
    }
}

// Auto-update credits when course is selected (for existing fields)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select[name^="courses["]').forEach(select => {
        select.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const credits = selectedOption.getAttribute('data-credits') || 3;
            this.closest('.row').querySelector('.credits').value = credits;
        });
    });
});
</script>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';
?>
