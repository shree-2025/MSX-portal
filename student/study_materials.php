<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/auth_functions.php';
require_once __DIR__ . '/../config/database.php';

// RecursiveDirectoryIterator and RecursiveIteratorIterator are built into PHP's SPL

// Get all study materials for the student's enrolled courses
$student_id = $_SESSION['user_id'];

// Get all enrolled courses
$enrolled_courses_query = $conn->prepare("SELECT c.id, c.title 
                                        FROM courses c 
                                        JOIN student_courses sc ON c.id = sc.course_id 
                                        WHERE sc.student_id = ?");
$enrolled_courses_query->bind_param("i", $student_id);
$enrolled_courses_query->execute();
$enrolled_courses = $enrolled_courses_query->get_result();

// Get all study materials
$study_materials = [
    'syllabus' => [],
    'notes' => [],
    'assignments' => [],
    'tests' => []
];

// Get syllabus - using syllabus column instead of syllabus_path
$syllabus_query = $conn->prepare("SELECT c.id as course_id, c.title as course_title, c.syllabus as file_path, 
                                'Syllabus' as type, 'syllabus' as material_type, 
                                c.updated_at as last_updated
                                FROM courses c 
                                JOIN student_courses sc ON c.id = sc.course_id 
                                WHERE sc.student_id = ? AND c.syllabus IS NOT NULL");
$syllabus_query->bind_param("i", $student_id);
$syllabus_query->execute();
$study_materials['syllabus'] = $syllabus_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get notes
$notes_query = $conn->prepare("SELECT n.course_id, c.title as course_title, n.title, n.file_path, 
                             'Note' as type, 'note' as material_type,
                             n.updated_at as last_updated
                             FROM notes n
                             JOIN courses c ON n.course_id = c.id
                             JOIN student_courses sc ON c.id = sc.course_id 
                             WHERE sc.student_id = ?");
$notes_query->bind_param("i", $student_id);
$notes_query->execute();
$study_materials['notes'] = $notes_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get assignments
$assignments_query = $conn->prepare("SELECT a.id, a.course_id, c.title as course_title, a.title, 
                                   a.file_path, 'Assignment' as type, 'assignment' as material_type,
                                   a.due_date as last_updated
                                   FROM assignments a
                                   JOIN courses c ON a.course_id = c.id
                                   JOIN student_courses sc ON c.id = sc.course_id 
                                   WHERE sc.student_id = ? AND a.file_path IS NOT NULL");
$assignments_query->bind_param("i", $student_id);
$assignments_query->execute();
$study_materials['assignments'] = $assignments_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get tests - temporarily showing tests without file downloads
$tests_query = $conn->prepare("SELECT t.id, t.course_id, c.title as course_title, t.title, 
                             NULL as file_path, 'Test' as type, 'test' as material_type,
                             t.created_at as last_updated, t.end_date
                             FROM tests t
                             JOIN courses c ON t.course_id = c.id
                             JOIN student_courses sc ON c.id = sc.course_id 
                             WHERE sc.student_id = ?");
$tests_query->bind_param("i", $student_id);
$tests_query->execute();
$study_materials['tests'] = $tests_query->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Study Materials</h1>
    </div>

    <!-- Study Materials Tabs -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <ul class="nav nav-tabs card-header-tabs" id="studyMaterialsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">
                        <i class="fas fa-th-list me-1"></i> All Materials
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="syllabus-tab" data-bs-toggle="tab" data-bs-target="#syllabus" type="button" role="tab" aria-controls="syllabus" aria-selected="false">
                        <i class="fas fa-book me-1"></i> Syllabus
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false">
                        <i class="fas fa-sticky-note me-1"></i> Notes
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignments" type="button" role="tab" aria-controls="assignments" aria-selected="false">
                        <i class="fas fa-tasks me-1"></i> Assignments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tests-tab" data-bs-toggle="tab" data-bs-target="#tests" type="button" role="tab" aria-controls="tests" aria-selected="false">
                        <i class="fas fa-file-alt me-1"></i> Tests
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="studyMaterialsTabContent">
                <!-- All Materials Tab -->
                <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                    <?php 
                    $all_materials = array_merge(
                        $study_materials['syllabus'],
                        $study_materials['notes'],
                        $study_materials['assignments'],
                        $study_materials['tests']
                    );
                    
                    // Sort by last updated (newest first)
                    usort($all_materials, function($a, $b) {
                        return strtotime($b['last_updated']) - strtotime($a['last_updated']);
                    });
                    
                    if (count($all_materials) > 0): 
                    ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="allMaterialsTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Course</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_materials as $material): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($material['title'] ?? 'Untitled'); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($material['type']) {
                                                        case 'Syllabus': echo 'bg-primary';
                                                            break;
                                                        case 'Note': echo 'bg-success';
                                                            break;
                                                        case 'Assignment': echo 'bg-info';
                                                            break;
                                                        case 'Test': echo 'bg-warning';
                                                            break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>">
                                                    <?php echo htmlspecialchars($material['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($material['course_title'] ?? 'N/A'); ?></td>
                                            <td><?php echo !empty($material['last_updated']) ? date('M d, Y', strtotime($material['last_updated'])) : 'N/A'; ?></td>
                                            <td>
                                                <?php 
                                                // Determine the correct download path based on material type
                                                $downloadLink = '#';
                                                $disabled = true; // Default to disabled
                                                $fileName = '';
                                                $filePath = '';
                                                
                                                // Debug: Check if file_path exists and is not empty
                                                $fileExists = !empty($material['file_path']);
                                                
                                                if ($fileExists) {
                                                    $filePath = $material['file_path'];
                                                    // Clean up the file path
                                                    $filePath = ltrim($filePath, '/');
                                                    $fullPath = realpath(__DIR__ . '/../' . $filePath);
                                                    
                                                    // If file doesn't exist at the exact path, try to find it in uploads
                                                    if (!$fullPath || !file_exists($fullPath)) {
                                                        $fileName = basename($filePath);
                                                        $uploadPath = realpath(__DIR__ . '/../uploads/') . '/' . $fileName;
                                                        if (file_exists($uploadPath)) {
                                                            $filePath = 'uploads/' . $fileName;
                                                            $fullPath = $uploadPath;
                                                        }
                                                    }
                                                    
                                                    if ($fullPath && file_exists($fullPath)) {
                                                        $disabled = false;
                                                        $originalName = basename($filePath);
                                                        $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
                                                        
                                                        switch($material['material_type'] ?? '') {
                                                            case 'syllabus':
                                                            case 'notes':
                                                                // Always force PDF download for notes
                                                                $fileName = preg_replace('/\.[^.\s]{3,4}$/', '', $originalName) . '.pdf';
                                                                $downloadLink = 'download_file.php?path=' . urlencode($filePath) . '&name=' . urlencode($fileName) . '&force_download=1';
                                                                break;
                                                                
                                                            case 'assignment':
                                                                $fileName = 'Assignment_' . ($material['id'] ?? '') . '_' . date('Y-m-d') . '.' . ($fileExtension ?: 'pdf');
                                                                $downloadLink = 'download_file.php?path=' . urlencode($filePath) . '&name=' . urlencode($fileName);
                                                                break;
                                                        }
                                                    }
                                                }
                                                ?>
                                                <?php if (!$disabled): ?>
                                                    <a href="<?php echo $downloadLink; ?>" 
                                                       class="btn btn-sm btn-primary download-btn"
                                                       download="<?php echo htmlspecialchars($fileName); ?>">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-secondary" disabled>
                                                        <i class="fas fa-download"></i> Download
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($material['material_type'] === 'test'): ?>
                                                    <a href="view_test.php?id=<?php echo $material['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary ms-2">
                                                        <i class="fas fa-eye me-1"></i> View Test
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-folder-open fa-4x text-gray-300 mb-3"></i>
                            <p class="text-muted">No study materials found.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Individual Tabs for Each Material Type -->
                <?php foreach (['syllabus', 'notes', 'assignments', 'tests'] as $type): 
                    $type_singular = rtrim($type, 's');
                    $type_display = ucfirst($type_singular);
                    $icon_class = [
                        'syllabus' => 'book',
                        'note' => 'sticky-note',
                        'assignment' => 'tasks',
                        'test' => 'file-alt'
                    ][$type_singular] ?? 'file';
                ?>
                    <div class="tab-pane fade" id="<?php echo $type; ?>" role="tabpanel" aria-labelledby="<?php echo $type; ?>-tab">
                        <?php if (!empty($study_materials[$type])): ?>
                            <div class="row">
                                <?php foreach ($study_materials[$type] as $material): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h5 class="card-title mb-0">
                                                        <i class="fas fa-<?php echo $icon_class; ?> text-<?php 
                                                            echo [
                                                                'syllabus' => 'primary',
                                                                'note' => 'success',
                                                                'assignment' => 'info',
                                                                'test' => 'warning'
                                                            ][$type] ?? 'secondary';
                                                        ?> me-2"></i>
                                                        <?php echo htmlspecialchars($material['title'] ?? 'Untitled ' . $type_display); ?>
                                                    </h5>
                                                    <span class="badge bg-<?php 
                                                        echo [
                                                            'syllabus' => 'primary',
                                                            'note' => 'success',
                                                            'assignment' => 'info',
                                                            'test' => 'warning'
                                                        ][$type] ?? 'secondary';
                                                    ?>">
                                                        <?php echo $type_display; ?>
                                                    </span>
                                                </div>
                                                <p class="card-text text-muted small">
                                                    <i class="fas fa-book me-1"></i> 
                                                    <?php echo htmlspecialchars($material['course_title'] ?? 'N/A'); ?>
                                                </p>
                                                <p class="card-text text-muted small">
                                                    <i class="far fa-clock me-1"></i> 
                                                    Last updated: <?php echo !empty($material['last_updated']) ? date('M d, Y', strtotime($material['last_updated'])) : 'N/A'; ?>
                                                </p>
                                                <?php 
                                                // Debug output (temporary)
                                                // echo '<pre>'; print_r($material); echo '</pre>';
                                                
                                                // Determine download link based on material type
                                                $downloadLink = '#';
                                                $disabled = false;
                                                $fileName = '';
                                                
                                                // Check if file_path exists and is accessible
                                                if (isset($material['file_path']) && !empty($material['file_path'])) {
                                                    $filePath = ltrim($material['file_path'], '/');
                                                    $fullPath = realpath(__DIR__ . '/../' . $filePath);
                                                    
                                                    // If file doesn't exist at the exact path, try to find it in uploads
                                                    if (!$fullPath || !file_exists($fullPath)) {
                                                        $filename = basename($filePath);
                                                        $uploadsPath = realpath(__DIR__ . '/../uploads');
                                                        $found = false;
                                                        
                                                        // Look for file in uploads directory
                                                        $dir = new RecursiveDirectoryIterator($uploadsPath);
                                                        $iterator = new RecursiveIteratorIterator($dir);
                                                        
                                                        foreach ($iterator as $file) {
                                                            if ($file->isFile() && $file->getFilename() === $filename) {
                                                                $fullPath = $file->getPathname();
                                                                $material['file_path'] = 'uploads/' . substr($file->getPathname(), strlen($uploadsPath) + 1);
                                                                $found = true;
                                                                break;
                                                            }
                                                        }
                                                        
                                                        if (!$found) {
                                                            $disabled = true;
                                                        }
                                                    }
                                                } else {
                                                    $disabled = true;
                                                }
                                                
                                                switch($material['material_type'] ?? '') {
                                                    case 'syllabus':
                                                    case 'notes':
                                                        $filePath = $material['file_path'] ?? '';
                                                        if (!empty($filePath)) {
                                                            $originalName = basename($filePath);
                                                            $fileName = preg_replace('/\.[^.\s]{3,4}$/', '', $originalName) . '.pdf';
                                                            $downloadLink = 'download_file.php?path=' . urlencode($filePath) . '&name=' . urlencode($fileName) . '&force_download=1';
                                                        } else {
                                                            $disabled = true;
                                                        }
                                                        break;
                                                        
                                                    case 'assignment':
                                                        $filePath = $material['file_path'] ?? '';
                                                        if (!empty($filePath)) {
                                                            $fileName = 'Assignment_' . $material['id'] . '_' . date('Y-m-d') . '.pdf';
                                                            $downloadLink = 'download_file.php?path=' . urlencode($filePath) . '&name=' . urlencode($fileName);
                                                        } else {
                                                            $disabled = true;
                                                        }
                                                        break;
                                                        
                                                    case 'test':
                                                        $disabled = true;
                                                        break;
                                                        
                                                    default:
                                                        $disabled = true;
                                                }
                                                ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <?php if (!$disabled): ?>
                                                        <a href="<?php echo $downloadLink; ?>" 
                                                           class="btn btn-sm btn-outline-primary download-btn"
                                                           download="<?php echo htmlspecialchars($fileName); ?>">
                                                            <i class="fas fa-download me-1"></i> Download
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" disabled>
                                                            <i class="fas fa-download me-1"></i> Download
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($type === 'tests'): ?>
                                                        <a href="view_test.php?id=<?php echo $material['id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-eye me-1"></i> View
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($type === 'assignments'): ?>
                                                        <a href="view_assignment.php?id=<?php echo $material['id']; ?>" 
                                                           class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-paper-plane me-1"></i> Submit
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-<?php echo $icon_class; ?> fa-4x text-gray-300 mb-3"></i>
                                <p class="text-muted">No <?php echo $type; ?> found.</p>
                                <a href="course_materials.php" class="btn btn-primary mt-2">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Courses
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Initialize DataTables and Tabs -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize main materials table if it exists
        const allMaterialsTable = document.getElementById('allMaterialsTable');
        if (allMaterialsTable) {
            $('#allMaterialsTable').DataTable({
                "pageLength": 10,
                "order": [[0, 'desc']],
                "responsive": true,
                "language": {
                    "search": "_INPUT_",
                    "searchPlaceholder": "Search...",
                },
                "columnDefs": [
                    { "orderable": false, "targets": [4] } // Disable sorting on action column
                ]
            });
        }

        // Initialize Bootstrap tabs
        const tabEls = [].slice.call(document.querySelectorAll('button[data-bs-toggle="tab"]'));
        
        tabEls.forEach(function(tabEl) {
            tabEl.addEventListener('click', function(event) {
                event.preventDefault();
                
                // Get the tab content to show
                const target = this.getAttribute('data-bs-target');
                const tabContent = document.querySelector(target);
                
                if (tabContent) {
                    // Hide all tab panes
                    const allTabPanes = [].slice.call(document.querySelectorAll('.tab-pane'));
                    allTabPanes.forEach(function(pane) {
                        pane.classList.remove('show', 'active');
                    });
                    
                    // Remove active class from all tab buttons
                    const allTabButtons = [].slice.call(document.querySelectorAll('.nav-link'));
                    allTabButtons.forEach(function(button) {
                        button.classList.remove('active');
                        button.setAttribute('aria-selected', 'false');
                    });
                    
                    // Show the selected tab
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');
                    tabContent.classList.add('show', 'active');
                }
            });
        });

        // Toggle functionality for material sections
        const toggleButtons = [].slice.call(document.querySelectorAll('.material-section-toggle'));
        toggleButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('data-bs-target');
                const targetElement = document.querySelector(target);
                
                if (targetElement) {
                    // Toggle the collapse
                    const bsCollapse = new bootstrap.Collapse(targetElement, {
                        toggle: true
                    });
                    
                    // Toggle icon
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-chevron-down');
                        icon.classList.toggle('fa-chevron-up');
                    }
                }
            });
        });

        // Handle download button click for better UX
        const downloadButtons = [].slice.call(document.querySelectorAll('.download-btn'));
        downloadButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const originalHTML = this.innerHTML;
                
                // Show loading state
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';
                this.disabled = true;
                
                // Re-enable button after 2 seconds if still disabled (in case of error)
                setTimeout(() => {
                    if (this.disabled) {
                        this.disabled = false;
                        this.innerHTML = originalHTML;
                    }
                }, 2000);
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
