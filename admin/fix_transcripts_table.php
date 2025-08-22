<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Check if student_id column exists
    $check = $conn->query("SHOW COLUMNS FROM `transcripts` LIKE 'student_id'");
    
    if ($check->num_rows === 0) {
        // Add student_id column
        $conn->query("ALTER TABLE `transcripts` 
                     ADD COLUMN `student_id` INT NOT NULL AFTER `id`,
                     ADD CONSTRAINT `fk_transcript_student` 
                     FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) 
                     ON DELETE CASCADE");
        
        // Update existing records with a default student ID (you'll need to update this)
        $conn->query("UPDATE `transcripts` SET `student_id` = 1 WHERE 1");
        
        echo "Successfully added student_id column to transcripts table.\n";
    } else {
        echo "student_id column already exists in transcripts table.\n";
    }
    
    // Verify the table structure
    $result = $conn->query("SHOW CREATE TABLE `transcripts`");
    $row = $result->fetch_assoc();
    echo "\nCurrent table structure:\n" . $row['Create Table'] . "\n";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}

echo "\nDone. Please delete this file after use for security reasons.";
?>
