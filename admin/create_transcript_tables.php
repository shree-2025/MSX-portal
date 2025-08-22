<?php
require_once __DIR__ . '/../config/database.php';

// Create transcript_courses table
$sql = "CREATE TABLE IF NOT EXISTS `transcript_courses` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `transcript_id` int(11) NOT NULL,
    `course_id` int(11) NOT NULL,
    `grade` varchar(2) NOT NULL,
    `credits_earned` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `transcript_id` (`transcript_id`),
    KEY `course_id` (`course_id`),
    CONSTRAINT `transcript_courses_ibfk_1` FOREIGN KEY (`transcript_id`) REFERENCES `transcripts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `transcript_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'transcript_courses' created successfully or already exists.\n";
    
    // Update transcripts table structure if needed
    $updateSql = [
        "ALTER TABLE `transcripts` 
         ADD COLUMN IF NOT EXISTS `gpa` DECIMAL(3,2) DEFAULT NULL,
         ADD COLUMN IF NOT EXISTS `completion_date` DATE DEFAULT NULL,
         ADD COLUMN IF NOT EXISTS `transcript_number` VARCHAR(50) UNIQUE,
         ADD COLUMN IF NOT EXISTS `additional_notes` TEXT,
         ADD COLUMN IF NOT EXISTS `program_name` VARCHAR(100) DEFAULT 'Bachelor of Science',
         MODIFY COLUMN `file_path` VARCHAR(255) NOT NULL"
    ];
    
    foreach ($updateSql as $query) {
        if ($conn->query($query) === TRUE) {
            echo "Updated transcripts table structure successfully.\n";
        } else {
            echo "Error updating transcripts table: " . $conn->error . "\n";
        }
    }
    
    // Generate transcript numbers for existing records if needed
    $result = $conn->query("SELECT id FROM transcripts WHERE transcript_number IS NULL OR transcript_number = ''");
    if ($result->num_rows > 0) {
        $update = $conn->prepare("UPDATE transcripts SET transcript_number = ? WHERE id = ?");
        while ($row = $result->fetch_assoc()) {
            $transcriptNumber = 'TR-' . strtoupper(uniqid());
            $update->bind_param('si', $transcriptNumber, $row['id']);
            $update->execute();
        }
        echo "Generated transcript numbers for existing records.\n";
    }
    
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
echo "Database setup completed. Please check the output above for any errors.";
?>
