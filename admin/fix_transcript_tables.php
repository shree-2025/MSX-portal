<?php
require_once __DIR__ . '/../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Fixing Transcript Tables</h2>";

// Create transcripts table if not exists
$sql = "CREATE TABLE IF NOT EXISTS `transcripts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL COMMENT 'Admin who issued the transcript',
    `student_id` int(11) NOT NULL,
    `transcript_number` varchar(50) NOT NULL,
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
    UNIQUE KEY `transcript_number` (`transcript_number`),
    CONSTRAINT `transcripts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `transcripts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "<p>✅ Transcripts table created/already exists</p>";
} else {
    echo "<p>❌ Error creating transcripts table: " . $conn->error . "</p>";
}

// Create transcript_courses table if not exists
$sql = "CREATE TABLE IF NOT EXISTS `transcript_courses` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "<p>✅ Transcript courses table created/already exists</p>";
} else {
    echo "<p>❌ Error creating transcript_courses table: " . $conn->error . "</p>";
}

// Create uploads directory if it doesn't exist
$upload_dir = __DIR__ . '/../uploads/transcripts';
if (!file_exists($upload_dir)) {
    if (mkdir($upload_dir, 0777, true)) {
        echo "<p>✅ Created uploads directory: " . htmlspecialchars($upload_dir) . "</p>";
    } else {
        echo "<p>❌ Failed to create uploads directory. Please create it manually with write permissions: " . htmlspecialchars($upload_dir) . "</p>";
    }
} else {
    echo "<p>✅ Uploads directory exists: " . htmlspecialchars($upload_dir) . "</p>";
}

// Check permissions
if (is_writable($upload_dir)) {
    echo "<p>✅ Uploads directory is writable</p>";
} else {
    echo "<p>❌ Uploads directory is not writable. Please run: <code>chmod 777 " . htmlspecialchars($upload_dir) . "</code></p>";
}

echo "<h3>Table Structure Check</h3>";

// Check if all required columns exist in transcripts table
$required_columns = [
    'user_id', 'student_id', 'transcript_number', 'issue_date', 'file_path', 
    'status', 'gpa', 'completion_date', 'additional_notes', 'program_name'
];

$result = $conn->query("DESCRIBE transcripts");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

foreach ($required_columns as $column) {
    if (!in_array($column, $existing_columns)) {
        echo "<p>❌ Missing column '{$column}' in transcripts table</p>";
        // Here you would add ALTER TABLE statements to add missing columns
    }
}

// Check if all required columns exist in transcript_courses table
$required_course_columns = ['transcript_id', 'course_id', 'grade', 'credits_earned'];
$result = $conn->query("DESCRIBE transcript_courses");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

foreach ($required_course_columns as $column) {
    if (!in_array($column, $existing_columns)) {
        echo "<p>❌ Missing column '{$column}' in transcript_courses table</p>";
        // Here you would add ALTER TABLE statements to add missing columns
    }
}

echo "<h3>Next Steps</h3>";
echo "<ol>";
echo "<li>Make sure the uploads directory exists and is writable: <code>" . htmlspecialchars($upload_dir) . "</code></li>";
echo "<li>Check the database tables for any missing columns (shown above)</li>";
echo "<li>Try issuing a transcript again</li>";
echo "<li>Check the PHP error log if you still encounter issues</li>";
echo "</ol>";

// Close connection
$conn->close();
?>
