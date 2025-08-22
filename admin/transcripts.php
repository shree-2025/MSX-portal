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

// Initialize variables
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error']);
unset($_SESSION['success']);

// Start output buffering after all potential redirects
ob_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_transcript'])) {
    
    $userId = $_POST['user_id'] ?? '';
    $gpa = $_POST['gpa'] ?? '';
    $completionDate = $_POST['completion_date'] ?? date('Y-m-d');
    $additionalNotes = $_POST['additional_notes'] ?? '';
    $programName = $_POST['program_name'] ?? 'Bachelor of Science';
    
    if ($userId && $gpa && !empty($_POST['courses'])) {
        $transcriptNumber = 'TR-' . strtoupper(uniqid());
        $filePath = '/uploads/transcripts/' . $transcriptNumber . '.pdf';
        
        $conn->begin_transaction();
        
        try {
            // Insert transcript
            $stmt = $conn->prepare("INSERT INTO transcripts (user_id, student_id, transcript_number, issue_date, file_path, gpa, completion_date, additional_notes, program_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $issueDate = date('Y-m-d');
                $stmt->bind_param('iisssdsss', $_SESSION['user_id'], $userId, $transcriptNumber, $issueDate, $filePath, $gpa, $completionDate, $additionalNotes, $programName);
                $stmt->execute();
                $newTranscriptId = $conn->insert_id;
                $stmt->close();
                
                // Insert courses
                $courseStmt = $conn->prepare("INSERT INTO transcript_courses (transcript_id, course_id, grade, credits_earned) VALUES (?, ?, ?, ?)");
                foreach ($_POST['courses'] as $course) {
                    if (isset($course['course_id'], $course['grade'], $course['credits'])) {
                        $courseStmt->bind_param('iisd', $newTranscriptId, $course['course_id'], $course['grade'], $course['credits']);
                        $courseStmt->execute();
                    }
                }
                $courseStmt->close();
                
                // Temporarily set ID for generation
                $_GET['id'] = $newTranscriptId;
                
                // Generate the PDF
                require_once __DIR__ . '/generate_transcript.php';
                
                $conn->commit();
                $_SESSION['success'] = 'Transcript issued and generated successfully!';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } else {
                throw new Exception('Failed to prepare transcript statement: ' . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Failed to issue transcript: ' . $e->getMessage();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
        
        // Close connection after transaction
        $conn->close();  // Add this line
    } else {
        $_SESSION['error'] = 'Please fill in all required fields and add at least one course';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Initialize pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10; // Number of items per page
$offset = ($page - 1) * $perPage;

// End output buffering and clean any output
ob_end_clean();

// Include header after all processing is done
require_once __DIR__ . '/includes/header.php';

// Initialize variables
$completionDate = $_POST['completion_date'] ?? date('Y-m-d');
$additionalNotes = $_POST['additional_notes'] ?? '';

// Initialize pagination variables
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$totalTranscripts = 0;
$totalPages = 1;
$transcripts = [];

// Fetch transcripts with user details
$query = "SELECT t.*, u1.username as student_username, u1.email as student_email, u2.username as issued_by
          FROM transcripts t 
          JOIN users u1 ON t.student_id = u1.id 
          LEFT JOIN users u2 ON t.user_id = u2.id
          ORDER BY t.created_at DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param('ii', $perPage, $offset);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $transcripts = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = 'Failed to fetch transcripts: ' . $conn->error;
    }
    $stmt->close();
} else {
    $error = 'Failed to prepare statement: ' . $conn->error;
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM transcripts";
$countResult = $conn->query($countQuery);
if ($countResult) {
    $row = $countResult->fetch_assoc();
    $totalTranscripts = (int)$row['total'];
    $totalPages = max(1, ceil($totalTranscripts / $perPage));
    $countResult->free();
}

// Fetch students and courses for the issue form
$students = [];
$studentsResult = $conn->query("SELECT id, username, email FROM users WHERE role = 'student' ORDER BY username");
if ($studentsResult) {
    $students = $studentsResult->fetch_all(MYSQLI_ASSOC);
    $studentsResult->free();
}

$courses = [];
$coursesResult = $conn->query("SELECT id, title as course_name FROM courses ORDER BY course_name");
if ($coursesResult) {
    $courses = $coursesResult->fetch_all(MYSQLI_ASSOC);
    $coursesResult->free();
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Transcript Management</h4>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transcriptModal">
                        <i class="fas fa-file-alt me-2"></i> Issue New Transcript
                    </button>
                </div>
            </div>
            <p class="text-muted mt-2">Manage and issue academic transcripts</p>
        </div>
    </div>


    <?php if (isset($_SESSION['success'])): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?= htmlspecialchars($_SESSION['success']) ?></span>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Transcript List -->
    <div class="card">
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover table-centered table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Issued Date</th>
                            <th>GPA</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transcripts)): ?>
                            <?php foreach ($transcripts as $key => $transcript): ?>
                                <tr>
                                    <td><?= ($offset + $key + 1) ?></td>
                                    <td><?= htmlspecialchars($transcript['student_username']) ?></td>
                                    <td>STU-<?= str_pad($transcript['student_id'], 5, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= date('M j, Y', strtotime($transcript['issue_date'])) ?></td>
                                    <td><?= number_format($transcript['gpa'], 2) ?></td>
                                    <td>
                                        <?php if (!empty($transcript['file_path'])): ?>
                                            <a href="download_transcript.php?id=<?= $transcript['id'] ?>" class="btn btn-sm btn-primary">Download PDF</a>
                                        <?php else: ?>
                                            <span class="text-muted">No PDF</span>
                                        <?php endif; ?>
                                        <a href="edit_transcript.php?id=<?= $transcript['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="delete_transcript.php?id=<?= $transcript['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No transcripts found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="row mt-4">
                    <div class="col-sm-12 col-md-5">
                        <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                            Showing <?= ($offset + 1) ?> to <?= min($offset + $perPage, $totalTranscripts) ?> of <?= $totalTranscripts ?> entries
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-7">
                        <div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
                            <ul class="pagination pagination-rounded justify-content-end mb-2">
                                <li class="paginate_button page-item previous <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a href="?page=<?= max(1, $page - 1) ?>" class="page-link">
                                        <i class="mdi mdi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="paginate_button page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a href="?page=<?= $i ?>" class="page-link"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="paginate_button page-item next <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a href="?page=<?= min($totalPages, $page + 1) ?>" class="page-link">
                                        <i class="mdi mdi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
</div>

<!-- Issue Transcript Modal -->
<div class="modal fade" id="transcriptModal" tabindex="-1" role="dialog" aria-labelledby="transcriptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transcriptModalLabel">Issue New Transcript</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="issueTranscriptForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="modal-body">
                    <!-- Student Selection -->
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Student <span class="text-danger">*</span></label>
                        <select id="user_id" name="user_id" class="form-select select2" required>
                            <option value="">Select a student</option>
                            <?php 
                            $studentQuery = $conn->query("SELECT id, username, email FROM users WHERE role = 'student' ORDER BY username");
                            while ($student = $studentQuery->fetch_assoc()): 
                            ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['username'] . ' (' . $student['email'] . ')') ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- GPA -->
                    <div class="mb-3">
                        <label for="gpa" class="form-label">GPA <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" max="4.0" class="form-control" id="gpa" name="gpa" required 
                               placeholder="e.g. 3.75">
                    </div>

                    <!-- Completion Date -->
                    <div class="mb-3">
                        <label for="completion_date" class="form-label">Completion Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="completion_date" name="completion_date" required
                               value="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Additional Notes -->
                    <div class="mb-3">
                        <label for="additional_notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="additional_notes" name="additional_notes" 
                                 rows="3" placeholder="Any additional notes or comments"></textarea>
                    </div>

                    <!-- Courses -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label">Courses</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCourseField()">
                                <i class="fas fa-plus me-1"></i> Add Course
                            </button>
                        </div>
                        
                        <div id="courses-container" class="row g-3">
                            <!-- Course fields will be added here dynamically -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="issue_transcript" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> Issue Transcript
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- eemplate (Hidden) -->
<template id="course-template">
    <div class="row g-3 mb-3 align-items-end" data-course-index="{{index}}">
        <div class="col-md-5">
            <label class="form-label">Course <span class="text-danger">*</span></label>
            <select name="courses[{{index}}][course_id]" class="form-select course-select" required data-index="{{index}}">
                <option value="">Select a course</option>
                <?php 
                $courseQuery = $conn->query("SELECT id, title FROM courses ORDER BY title");
                while ($course = $courseQuery->fetch_assoc()): 
                ?>
                    <option value="<?= $course['id'] ?>">
                        <?= htmlspecialchars($course['title']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Grade <span class="text-danger">*</span></label>
            <select name="courses[{{index}}][grade]" class="form-select" required>
                <option value="">Select grade</option>
                <option value="A+">A+ (4.0)</option>
                <option value="A">A (4.0)</option>
                <option value="A-">A- (3.7)</option>
                <option value="B+">B+ (3.3)</option>
                <option value="B">B (3.0)</option>
                <option value="B-">B- (2.7)</option>
                <option value="C+">C+ (2.3)</option>
                <option value="C">C (2.0)</option>
                <option value="C-">C- (1.7)</option>
                <option value="D+">D+ (1.3)</option>
                <option value="D">D (1.0)</option>
                <option value="F">F (0.0)</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Credits</label>
            <input type="number" name="courses[{{index}}][credits_earned]" class="form-control" min="1" max="6" value="3">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeCourseField(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</template>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= $course['id'] ?>">
                        <?= htmlspecialchars($course['course_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700">Grade *</label>
            <select name="courses[{{index}}][grade]" required
                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                <option value="">Select grade</option>
                <option value="A+">A+ (4.0)</option>
                <option value="A">A (4.0)</option>
                <option value="A-">A- (3.7)</option>
                <option value="B+">B+ (3.3)</option>
                <option value="B">B (3.0)</option>
                <option value="B-">B- (2.7)</option>
                <option value="C+">C+ (2.3)</option>
                <option value="C">C (2.0)</option>
                <option value="C-">C- (1.7)</option>
                <option value="D+">D+ (1.3)</option>
                <option value="D">D (1.0)</option>
                <option value="F">F (0.0)</option>
            </select>
        </div>
        <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700">Credits</label>
            <input type="number" name="courses[{{index}}][credits_earned]" min="1" max="6" value="3"
                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>
        <div class="md:col-span-1">
            <button type="button" onclick="removeCourseRow(this)" 
                    class="inline-flex items-center justify-center p-2 border border-transparent rounded-full text-red-600 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</template>

<script>
// Function to add course fields dynamically
function addCourseField() {
    const container = document.getElementById('courses-container');
    const template = document.getElementById('course-template');
    const index = container.children.length;
    
    // Clone the template
    const newCourse = template.content.cloneNode(true);
    const newCourseElement = newCourse.querySelector('.row');
    
    // Update all input/select names with the new index
    newCourseElement.querySelectorAll('select, input').forEach(input => {
        const name = input.getAttribute('name') || '';
        if (name.includes('{{index}}')) {
            input.name = name.replace('{{index}}', index);
        }
        // Set the data-index attribute for course select
        if (input.classList.contains('course-select')) {
            input.setAttribute('data-index', index);
        }
    });
    
    // Set the data attribute
    newCourseElement.setAttribute('data-course-index', index);
    
    // Add the new course field
    container.appendChild(newCourse);
    
    // Initialize Select2 if it exists
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(`select[name="courses[${index}][course_id]"]`).select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
}

// Function to remove a course field
function removeCourseField(button) {
    const container = document.getElementById('courses-container');
    const rows = container.querySelectorAll('[data-course-index]');
    
    if (rows.length <= 1) {
        // Don't remove the last row
        return;
    }
    
    const row = button.closest('.row');
    if (row) {
        row.remove();
        
        // Re-index the remaining course fields
        const remainingRows = container.querySelectorAll('[data-course-index]');
        remainingRows.forEach((row, index) => {
            row.setAttribute('data-course-index', index);
            
            // Update all input/select names with the new index
            row.querySelectorAll('select, input').forEach(input => {
                const name = input.getAttribute('name') || '';
                const match = name.match(/courses\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    input.name = `courses[${index}][${match[2]}]`;
                }
                // Update data-index for course select
                if (input.classList.contains('course-select')) {
                    input.setAttribute('data-index', index);
                }
            });
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Select2
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Select an option',
            allowClear: true
        });
    }
    
    // Add initial course field when modal is shown
    const transcriptModal = document.getElementById('transcriptModal');
    if (transcriptModal) {
        transcriptModal.addEventListener('shown.bs.modal', function () {
            // Clear any existing course fields
            document.getElementById('courses-container').innerHTML = '';
            // Add one course field by default
            addCourseField();
        });
        
        // Reset form when modal is hidden
        transcriptModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('issueTranscriptForm').reset();
            document.getElementById('courses-container').innerHTML = '';
        });
    }
});

// Add course field
function addCourseField() {
    const container = document.getElementById('courses-container');
    const template = document.getElementById('course-template').innerHTML;
    const index = document.querySelectorAll('#courses-container [data-course-index]').length;
    const html = template.replace(/\{\{index\}\}/g, index);
    
    const div = document.createElement('div');
    div.innerHTML = html;
    const newCourseField = div.firstElementChild;
    container.appendChild(newCourseField);
    
    // Initialize Select2 on the new select element if it exists
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(newCourseField).find('select').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Select an option',
            allowClear: true
        });
    }
    
    return newCourseField;
}

// Remove course field
function removeCourseField(button) {
    const container = document.getElementById('courses-container');
    const courseFields = container.querySelectorAll('[data-course-index]');
    
    if (courseFields.length > 1) {
        button.closest('[data-course-index]').remove();
        
        // Re-index remaining course fields
        const remainingFields = container.querySelectorAll('[data-course-index]');
        remainingFields.forEach((field, index) => {
            field.setAttribute('data-course-index', index);
            // Update all form control names with new index
            field.querySelectorAll('[name^="courses["]').forEach(input => {
                const name = input.getAttribute('name');
                input.setAttribute('name', name.replace(/courses\[\d+\]/, `courses[${index}]`));
            });
        });
    } else {
        // Show error using Bootstrap's toast or alert
        const alert = document.createElement('div');
        alert.className = 'alert alert-warning alert-dismissible fade show';
        alert.role = 'alert';
        alert.innerHTML = `
            At least one course is required.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insert after the courses container
        container.parentNode.insertBefore(alert, container.nextSibling);
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 3000);
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('transcriptModal');
    if (event.target === modal) {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
};
</script>

<?php
// Close any open database connections or resources if needed
if (isset($conn)) {
    // $conn->close(); // Uncomment if you need to explicitly close the connection
}
?>

<!-- Include jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Then include Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Scripts -->
<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    $('.alert').fadeOut('slow');
}, 5000);

// Initialize tooltips
$(function () {
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Handle course selection changes
    $(document).on('change', '.course-select', function() {
        const index = $(this).data('index');
        const courseId = $(this).val();
        const gradeSelect = $(`select[name="courses[${index}][grade]"]`);
        
        // Only update if grade hasn't been set yet
        if (!gradeSelect.val()) {
            // Reset to default
            gradeSelect.val('');
        }
    });
});
</script>

</body>
</html>