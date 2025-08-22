<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once __DIR__ . '/../config/database.php';

echo "Fixing database structure...<br><br>";

// 1. Fix the transcripts table structure
$sql = [
    // First, add any missing columns without modifying existing ones
    "ALTER TABLE transcripts 
     ADD COLUMN IF NOT EXISTS gpa DECIMAL(4,2) DEFAULT 0.00 AFTER student_id,
     ADD COLUMN IF NOT EXISTS completion_date DATE AFTER gpa,
     ADD COLUMN IF NOT EXISTS transcript_number VARCHAR(50) AFTER completion_date,
     ADD COLUMN IF NOT EXISTS additional_notes TEXT AFTER file_path,
     ADD COLUMN IF NOT EXISTS program_name VARCHAR(100) DEFAULT 'General Studies' AFTER additional_notes,
     ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
     MODIFY COLUMN issue_date DATE NOT NULL DEFAULT (CURRENT_DATE)",
     
    // Then add unique constraint if it doesn't exist
    "ALTER TABLE transcripts 
     ADD UNIQUE INDEX IF NOT EXISTS unique_transcript_number (transcript_number)",
     
    // Create transcript_courses table if it doesn't exist
    "CREATE TABLE IF NOT EXISTS transcript_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transcript_id INT NOT NULL,
        course_id INT NOT NULL,
        grade VARCHAR(2) NOT NULL,
        credits_earned INT DEFAULT 3,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transcript_id) REFERENCES transcripts(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE KEY unique_transcript_course (transcript_id, course_id)
    ) ENGINE=InnoDB"
];

foreach ($sql as $query) {
    echo "Executing: " . substr($query, 0, 100) . "...<br>";
    if ($conn->query($query) === FALSE) {
        echo "Error: " . $conn->error . "<br><br>";
    } else {
        echo "Success!<br><br>";
    }
}

echo "Database structure fixed successfully!<br>";
$conn->close();
?>
