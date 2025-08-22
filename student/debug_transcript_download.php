<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/auth_functions.php';
requireLogin();

// Get transcript ID from URL
$transcript_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($transcript_id <= 0) {
    die("Error: Invalid transcript ID");
}

// Fetch transcript with student verification
$stmt = $conn->prepare("
    SELECT t.*, u.full_name, u.email 
    FROM transcripts t
    JOIN users u ON t.student_id = u.id
    WHERE t.id = ? AND t.student_id = ?
");
$stmt->bind_param('ii', $transcript_id, $_SESSION['user_id']);
$stmt->execute();
$transcript = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transcript) {
    die("Error: Transcript not found or access denied");
}

// Define base paths to check
$base_upload_path = __DIR__ . '/../uploads';
$file_name = basename($transcript['file_path']);
$file_exists = false;
$found_path = '';

// Check file path in multiple possible locations
$possible_paths = [
    'original_path' => [
        'path' => __DIR__ . '/..' . $transcript['file_path'],
        'exists' => false,
        'description' => 'Original path from database'
    ],
    'uploads_dir' => [
        'path' => $base_upload_path . '/' . $transcript['file_path'],
        'exists' => false,
        'description' => 'Uploads directory with relative path'
    ],
    'filename_in_uploads' => [
        'path' => $base_upload_path . '/' . $file_name,
        'exists' => false,
        'description' => 'Filename in root uploads directory'
    ]
];

// Check each initial path
foreach ($possible_paths as $key => &$path_info) {
    $path_info['exists'] = file_exists($path_info['path']) && is_readable($path_info['path']);
    if ($path_info['exists'] && !$file_exists) {
        $file_exists = true;
        $found_path = $path_info['path'];
    }
}

// If file not found yet, search in course directories
if (!$file_exists) {
    $course_dirs = glob($base_upload_path . '/course_*');
    foreach ($course_dirs as $course_dir) {
        $course_name = basename($course_dir);
        
        // Check in notes and syllabus subdirectories
        foreach (['notes', 'syllabus'] as $subdir) {
            // Check original filename in subdirectory
            $path = $course_dir . '/' . $subdir . '/' . $file_name;
            $possible_paths["${course_name}_${subdir}"] = [
                'path' => $path,
                'exists' => file_exists($path) && is_readable($path),
                'description' => "In ${course_name}/${subdir} directory"
            ];
            
            if ($possible_paths["${course_name}_${subdir}"]['exists']) {
                $file_exists = true;
                $found_path = $path;
            }
            
            // Check for files with transcript ID in the name
            $pattern = $course_dir . '/' . $subdir . '/*' . $transcript_id . '*.pdf';
            $matching_files = glob($pattern);
            if (!empty($matching_files)) {
                foreach ($matching_files as $i => $file) {
                    $possible_paths["${course_name}_${subdir}_match_${i}"] = [
                        'path' => $file,
                        'exists' => true,
                        'description' => "Matching file in ${course_name}/${subdir}"
                    ];
                    if (!$file_exists) {
                        $file_exists = true;
                        $found_path = $file;
                    }
                }
            }
        }
    }
}

// Check each path
$file_exists = false;
$found_path = '';

foreach ($possible_paths as $key => &$path_info) {
    $path_info['exists'] = file_exists($path_info['path']);
    if ($path_info['exists'] && !$file_exists) {
        $file_exists = true;
        $found_path = $path_info['path'];
    }
}

// Try to find file by ID if still not found
if (!$file_exists) {
    $possible_files = glob(__DIR__ . '/../uploads/transcripts/*' . $transcript_id . '*.pdf');
    if (!empty($possible_files)) {
        $found_path = $possible_files[0];
        $file_exists = true;
    }
}

// Get directory permissions
$upload_dir = __DIR__ . '/../uploads/transcripts';
$dir_writable = is_writable($upload_dir);
$dir_readable = is_readable($upload_dir);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transcript Download Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">Transcript Download Debug</h1>
        
        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-2">Transcript Details</h2>
            <div class="bg-gray-50 p-4 rounded-md">
                <p><span class="font-medium">ID:</span> <?= htmlspecialchars($transcript['id']) ?></p>
                <p><span class="font-medium">Student:</span> <?= htmlspecialchars($transcript['full_name']) ?></p>
                <p><span class="font-medium">Issue Date:</span> <?= date('F j, Y', strtotime($transcript['issue_date'])) ?></p>
                <p><span class="font-medium">Status:</span> <?= htmlspecialchars($transcript['status']) ?></p>
            </div>
        </div>

        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-2">File Information</h2>
            <div class="bg-gray-50 p-4 rounded-md">
                <div class="space-y-2">
                    <p><span class="font-medium">Transcript ID:</span> <?= htmlspecialchars($transcript_id) ?></p>
                    <p><span class="font-medium">Database Path:</span> <?= htmlspecialchars($transcript['file_path']) ?></p>
                    
                    <div class="mt-4">
                        <h4 class="font-medium text-gray-700 mb-2">Checked Paths:</h4>
                        <div class="space-y-1 pl-4 border-l-2 border-gray-200">
                            <?php foreach ($possible_paths as $key => $path_info): ?>
                                <div class="flex items-start">
                                    <span class="font-mono text-sm flex-1 truncate" title="<?= htmlspecialchars($path_info['path']) ?>">
                                        <?= htmlspecialchars($key) ?>: <?= htmlspecialchars($path_info['path']) ?>
                                    </span>
                                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full <?= $path_info['exists'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                        <?= $path_info['exists'] ? 'Found' : 'Not found' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($file_exists): ?>
                        <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-md">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <span class="font-medium text-green-800">File found at:</span>
                            </div>
                            <div class="mt-1 font-mono text-sm break-all"><?= htmlspecialchars($found_path) ?></div>
                            <div class="mt-2">
                                <a href="download_transcript.php?id=<?= $transcript_id ?>" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-download mr-1"></i> Download now
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                                <span class="font-medium text-red-800">File not found in any location</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($file_exists): ?>
                    <p><span class="font-medium">File Size:</span> <?= number_format(filesize($file_path) / 1024, 2) ?> KB</p>
                    <p><span class="font-medium">Last Modified:</span> <?= date('F j, Y H:i:s', filemtime($file_path)) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Troubleshooting</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <?php if ($file_exists): ?>
                            <p class="font-medium">The file exists on the server. Try these steps:</p>
                            <ol class="list-decimal pl-5 mt-1 space-y-1">
                                <li><a href="download_transcript.php?id=<?= $transcript_id ?>" class="text-blue-600 hover:underline">Try downloading again</a></li>
                                <li>Check your browser's download folder</li>
                                <li>Right-click the download link and select "Save link as..."</li>
                            </ol>
                        <?php else: ?>
                            <p class="font-medium">The transcript file was not found. This could be due to:</p>
                            <ul class="list-disc pl-5 mt-1 space-y-1">
                                <li>The file was moved or deleted from the server</li>
                                <li>Incorrect file path stored in the database</li>
                                <li>Permissions issue preventing access to the file</li>
                            </ul>
                            <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                <p class="font-medium text-yellow-800">Directory Information:</p>
                                <ul class="mt-1 space-y-1">
                                    <li>Uploads directory: <code class="text-xs"><?= htmlspecialchars($upload_dir) ?></code></li>
                                    <li>Readable: <span class="font-mono <?= $dir_readable ? 'text-green-600' : 'text-red-600' ?>"><?= $dir_readable ? 'Yes' : 'No' ?></span></li>
                                    <li>Writable: <span class="font-mono <?= $dir_writable ? 'text-green-600' : 'text-red-600' ?>"><?= $dir_writable ? 'Yes' : 'No' ?></span></li>
                                </ul>
                            </div>
                            <p class="mt-3">
                                <a href="transcripts.php" class="text-blue-600 hover:underline">
                                    <i class="fas fa-arrow-left mr-1"></i> Back to transcripts
                                </a>
                                <span class="mx-2 text-gray-400">|</span>
                                <a href="contact.php" class="text-blue-600 hover:underline">
                                    <i class="fas fa-envelope mr-1"></i> Contact support
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <a href="transcripts.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
                Back to Transcripts
            </a>
        </div>
    </div>
</body>
</html>
