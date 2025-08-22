<?php
// Prevent any output before headers
ob_start();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/document_functions.php';
requireAdmin();

// Clear any previous output
ob_clean();

$courseId = (int)($_GET['course_id'] ?? 0);
$activeTab = $_GET['tab'] ?? 'syllabus';

// Get course details
$course = $conn->query("SELECT * FROM courses WHERE id = $courseId")->fetch_assoc();
if (!$course) {
    setFlashMessage('error', 'Course not found.');
    header("Location: courses.php");
    exit();
}

// Handle file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['title'])) {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $uploadDir = __DIR__ . "/../uploads/course_{$courseId}/";
        
        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Failed to create upload directory. Please check directory permissions.');
            }
        }
        
        $fileField = $activeTab === 'syllabus' ? 'syllabus_file' : 'notes_file';
        $table = $activeTab === 'syllabus' ? 'syllabus' : 'notes';
        
        if (empty($_FILES[$fileField]['name'])) {
            throw new Exception('Please select a file to upload.');
        }
        
        // Validate file upload
        if ($_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed with error code: ' . $_FILES[$fileField]['error']);
        }
        
        // Ensure the subdirectory exists
        $targetDir = $uploadDir . $table . '/';
        if (!file_exists($targetDir)) {
            if (!mkdir($targetDir, 0777, true)) {
                throw new Exception('Failed to create target directory.');
            }
        }
        
        $result = handleFileUpload($_FILES[$fileField], $targetDir);
        
        if (!$result['success']) {
            throw new Exception($result['message'] ?? 'File upload failed.');
        }
        
        $filePath = str_replace(__DIR__ . '/../', '/', $result['path']);
        
        if ($table === 'syllabus') {
            $sql = "INSERT INTO $table (course_id, title, file_path) 
                    VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("iss", $courseId, $title, $filePath);
        } else {
            $sql = "INSERT INTO $table (course_id, title, description, file_path) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("isss", $courseId, $title, $description, $filePath);
        }
        
        if (!$stmt->execute()) {
            // Clean up the uploaded file if database insert fails
            if (file_exists($result['path'])) {
                @unlink($result['path']);
            }
            throw new Exception('Failed to save file information to database: ' . $stmt->error);
        }
        
        setFlashMessage('success', ucfirst($table) . ' uploaded successfully.');
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?course_id=$courseId&tab=$activeTab");
        exit();
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Upload failed: ' . $e->getMessage());
    }
}

// Get existing materials
$syllabus = $conn->query("SELECT * FROM syllabus WHERE course_id = $courseId ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$notes = $conn->query("SELECT * FROM notes WHERE course_id = $courseId ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Tailwind CSS -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

<div class="min-h-screen bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Course Materials</h1>
                <p class="text-gray-600"><?= htmlspecialchars($course['title']) ?></p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="courses.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Courses
                </a>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <a href="?course_id=<?= $courseId ?>&tab=syllabus" 
                       class="py-4 px-6 text-center border-b-2 font-medium text-sm <?= $activeTab === 'syllabus' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                        <i class="fas fa-book mr-2"></i> Syllabus
                    </a>
                    <a href="?course_id=<?= $courseId ?>&tab=notes" 
                       class="py-4 px-6 text-center border-b-2 font-medium text-sm <?= $activeTab === 'notes' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                        <i class="fas fa-sticky-note mr-2"></i> Notes
                    </a>
                </nav>
            </div>
            
            <!-- Upload Button -->
            <div class="p-4 border-b border-gray-200">
                <button onclick="openUploadModal('<?= $activeTab ?>')" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i> Upload <?= ucfirst($activeTab) ?>
                </button>
            </div>
            
            <!-- Content -->
            <div class="p-6">
                <?php if ($activeTab === 'syllabus') include 'includes/syllabus_tab.php'; ?>
                <?php if ($activeTab === 'notes') include 'includes/notes_tab.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md mx-4">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Upload File</h3>
                <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="uploadForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="tab" id="currentTab" value="<?= $activeTab ?>">
                
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" id="title" name="title" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div id="descriptionContainer">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description (Optional)</label>
                    <textarea id="description" name="description" rows="3"
                             class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div>
                    <label for="fileInput" class="block text-sm font-medium text-gray-700 mb-1">File</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                        <div class="space-y-1 text-center">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mx-auto"></i>
                            <div class="flex text-sm text-gray-600">
                                <label for="fileInput" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                    <span>Upload a file</span>
                                    <input id="fileInput" name="file" type="file" class="sr-only" required>
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">
                                PDF, DOC, DOCX, TXT up to 10MB
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeUploadModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openUploadModal(type) {
    const modal = document.getElementById('uploadModal');
    const modalTitle = document.getElementById('modalTitle');
    const fileInput = document.getElementById('fileInput');
    const descriptionContainer = document.getElementById('descriptionContainer');
    const currentTab = document.getElementById('currentTab');
    
    // Update modal title and form action
    modalTitle.textContent = `Upload ${type.charAt(0).toUpperCase() + type.slice(1)}`;
    currentTab.value = type;
    
    // Update file input name based on type
    fileInput.name = `${type}_file`;
    fileInput.accept = type === 'syllabus' ? '.pdf,.doc,.docx,.txt' : '.pdf,.doc,.docx,.txt,.ppt,.pptx,.xls,.xlsx';
    
    // Show/hide description field
    descriptionContainer.style.display = type === 'notes' ? 'block' : 'none';
    
    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    
    // Reset form
    document.getElementById('uploadForm').reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('uploadModal');
    if (event.target === modal) {
        closeUploadModal();
    }
}

// Handle drag and drop
const dropArea = document.querySelector('.border-dashed');
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropArea.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropArea.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropArea.addEventListener(eventName, unhighlight, false);
});

function highlight() {
    dropArea.classList.add('border-blue-500', 'bg-blue-50');
}

function unhighlight() {
    dropArea.classList.remove('border-blue-500', 'bg-blue-50');
}

dropArea.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles(files);
}

function handleFiles(files) {
    const fileInput = document.getElementById('fileInput');
    fileInput.files = files;
    
    // Update UI to show selected file name
    const fileName = files[0]?.name || 'No file selected';
    const fileLabel = document.querySelector('.text-blue-600 + p');
    if (fileLabel) {
        fileLabel.textContent = fileName;
    }
}
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<!-- Bootstrap JS (optional, for modal & UI features) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        $('#dataTable').DataTable({
            responsive: true,
            columnDefs: [
                { orderable: false, targets: -1 }
            ]
        });
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
