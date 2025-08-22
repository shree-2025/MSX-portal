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
    die('Access denied. Please login as admin.');
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if tables exist
function check_table($conn, $table_name) {
    $result = $conn->query("SHOW TABLES LIKE '$table_name'");
    return $result->num_rows > 0;
}

// Check directory permissions
function check_directory($path) {
    if (!file_exists($path)) {
        return ['exists' => false, 'writable' => false, 'error' => 'Directory does not exist'];
    }
    if (!is_dir($path)) {
        return ['exists' => false, 'writable' => false, 'error' => 'Path is not a directory'];
    }
    return [
        'exists' => true,
        'writable' => is_writable($path),
        'path' => realpath($path)
    ];
}

// Check database tables
$tables = ['transcripts', 'transcript_courses', 'users', 'courses'];
$table_status = [];
foreach ($tables as $table) {
    $table_status[$table] = check_table($conn, $table);
}

// Check uploads directory
$uploads_dir = __DIR__ . '/../uploads/transcripts';
$uploads_status = check_directory($uploads_dir);

// Check for recent errors in the error log
$error_log = ini_get('error_log');
$recent_errors = [];
if (file_exists($error_log)) {
    $log_content = file_get_contents($error_log);
    $lines = explode("\n", $log_content);
    $recent_errors = array_slice($lines, -20); // Get last 20 lines
}

// Output the results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Transcript Issuance Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Transcript Issuance Debug</h1>
        
        <div class="card mb-4">
            <div class="card-header">Database Tables</div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($table_status as $table => $exists): ?>
                        <tr>
                            <td><?= htmlspecialchars($table) ?></td>
                            <td><?= $exists ? '✅ Yes' : '❌ No' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Uploads Directory</div>
            <div class="card-body">
                <?php if ($uploads_status['exists']): ?>
                    <p>Path: <?= htmlspecialchars($uploads_status['path']) ?></p>
                    <p>Writable: <?= $uploads_status['writable'] ? '✅ Yes' : '❌ No' ?></p>
                    <?php if (!$uploads_status['writable']): ?>
                        <p class="text-danger">Error: The uploads directory is not writable. Please run: <code>chmod 777 <?= htmlspecialchars($uploads_status['path']) ?></code></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-danger">Error: <?= htmlspecialchars($uploads_status['error'] ?? 'Unknown error') ?></p>
                    <p>Please create the directory: <code>mkdir -p <?= htmlspecialchars($uploads_dir) ?></code></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($recent_errors)): ?>
        <div class="card mb-4">
            <div class="card-header">Recent Errors</div>
            <div class="card-body">
                <pre><?= htmlspecialchars(implode("\n", $recent_errors)) ?></pre>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Quick Fixes</div>
            <div class="card-body">
                <h5>1. Create missing tables:</h5>
                <pre class="bg-light p-3">
-- Create transcripts table if not exists
CREATE TABLE IF NOT EXISTS `transcripts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `gpa` decimal(3,2) DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `program_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `transcripts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transcripts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create transcript_courses table if not exists
CREATE TABLE IF NOT EXISTS `transcript_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transcript_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `grade` varchar(2) NOT NULL,
  `credits_earned` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transcript_id` (`transcript_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `transcript_courses_ibfk_1` FOREIGN KEY (`transcript_id`) REFERENCES `transcripts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transcript_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                </pre>

                <h5 class="mt-4">2. Fix directory permissions:</h5>
                <pre class="bg-light p-3">
# Create uploads directory if it doesn't exist
mkdir -p <?= htmlspecialchars($uploads_dir) ?>

# Set proper permissions (Linux/Unix)
chmod -R 777 <?= htmlspecialchars($uploads_dir) ?>

# For Windows, ensure the web server user has full control over the directory
                </pre>
            </div>
        </div>
    </div>
</body>
</html>
