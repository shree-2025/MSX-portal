<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Get the first admin user ID to use as a default
    $result = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $adminUser = $result->fetch_assoc();
    
    if (!$adminUser) {
        die("No admin user found in the database. Please create an admin user first.");
    }
    
    $adminId = $adminUser['id'];
    
    // Check if user_id constraint exists and drop it if it does
    $constraintCheck = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_NAME = 'transcripts' 
        AND CONSTRAINT_TYPE = 'FOREIGN KEY' 
        AND CONSTRAINT_NAME = 'transcripts_ibfk_1'
    ");
    
    if ($constraintCheck->num_rows > 0) {
        $conn->query("ALTER TABLE transcripts DROP FOREIGN KEY transcripts_ibfk_1");
        echo "Dropped existing foreign key constraint.\n";
    }
    
    // Check if both user_id and student_id columns exist
    $userIdCheck = $conn->query("SHOW COLUMNS FROM transcripts LIKE 'user_id'");
    $studentIdCheck = $conn->query("SHOW COLUMNS FROM transcripts LIKE 'student_id'");
    
    if ($userIdCheck->num_rows > 0 && $studentIdCheck->num_rows > 0) {
        // Both columns exist, we need to merge them
        echo "Both user_id and student_id columns exist. Merging data...\n";
        
        // Copy data from user_id to student_id where student_id is NULL or 0
        $conn->query("UPDATE transcripts SET student_id = user_id WHERE student_id IS NULL OR student_id = 0");
        
        // Drop the user_id column
        $conn->query("ALTER TABLE transcripts DROP COLUMN user_id");
        echo "Merged data from user_id to student_id and dropped user_id column.\n";
    } elseif ($userIdCheck->num_rows > 0) {
        // Only user_id exists, rename it to student_id
        $conn->query("ALTER TABLE transcripts CHANGE user_id student_id INT NOT NULL");
        echo "Renamed user_id column to student_id.\n";
    }
    
    // Add the new foreign key constraint
    $conn->query("
        ALTER TABLE transcripts 
        ADD CONSTRAINT fk_transcript_student 
        FOREIGN KEY (student_id) REFERENCES users(id) 
        ON DELETE CASCADE
    ") or die("Error adding foreign key: " . $conn->error);
    
    // Update any null student_ids with the admin user ID
    $conn->query("UPDATE transcripts SET student_id = $adminId WHERE student_id IS NULL OR student_id = 0");
    
    echo "Successfully fixed foreign key constraints.\n";
    
    // Show the current table structure
    $result = $conn->query("SHOW CREATE TABLE transcripts");
    $row = $result->fetch_assoc();
    echo "\nCurrent table structure:\n" . $row['Create Table'] . "\n";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}

echo "\nDone. Please delete this file after use for security reasons.";
?>
