<?php
require_once __DIR__ . '/../config/database.php';

// Check if the script is being run from command line
$isCli = php_sapi_name() === 'cli';

function logMessage($message) {
    global $isCli;
    if ($isCli) {
        echo "$message\n";
    } else {
        echo "$message<br>";
    }
}

logMessage("Checking database structure...");

// Check if transcript_courses table exists
$result = $conn->query("SHOW TABLES LIKE 'transcript_courses'");
if ($result->num_rows === 0) {
    logMessage("Creating transcript_courses table...");
    $sql = "CREATE TABLE IF NOT EXISTS transcript_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transcript_id INT NOT NULL,
        course_id INT NOT NULL,
        grade VARCHAR(2) NOT NULL,
        credits_earned INT DEFAULT 3,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transcript_id) REFERENCES transcripts(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    
    if ($conn->query($sql)) {
        logMessage("Successfully created transcript_courses table");
    } else {
        logMessage("Error creating transcript_courses table: " . $conn->error);
        exit(1);
    }
} else {
    logMessage("transcript_courses table already exists");
}

// Check if required columns exist in transcripts table
$result = $conn->query("SHOW COLUMNS FROM transcripts LIKE 'gpa'");
if ($result->num_rows === 0) {
    logMessage("Adding missing columns to transcripts table...");
    $sql = "ALTER TABLE transcripts 
            ADD COLUMN gpa DECIMAL(4,2) DEFAULT 0.00 AFTER student_id,
            ADD COLUMN completion_date DATE AFTER gpa,
            ADD COLUMN transcript_number VARCHAR(50) AFTER completion_date,
            ADD COLUMN additional_notes TEXT AFTER file_path,
            ADD COLUMN program_name VARCHAR(100) AFTER additional_notes,
            ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
    
    if ($conn->query($sql)) {
        logMessage("Successfully added missing columns to transcripts table");
    } else {
        logMessage("Error adding columns to transcripts table: " . $conn->error);
        exit(1);
    }
} else {
    logMessage("All required columns exist in transcripts table");
}

logMessage("Database structure check complete!");

// Test inserting a transcript
logMessage("\nTesting transcript insertion...");
try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Insert test transcript
    $studentId = 1; // Change this to a valid student ID
    $transcriptNumber = 'TR-' . strtoupper(uniqid());
    $filePath = '/uploads/transcripts/' . $transcriptNumber . '.pdf';
    
    $sql = "INSERT INTO transcripts 
            (student_id, gpa, completion_date, transcript_number, file_path, program_name) 
            VALUES (?, ?, CURDATE(), ?, ?, 'Test Program')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $gpa = 3.5;
    $stmt->bind_param('idss', $studentId, $gpa, $transcriptNumber, $filePath);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $transcriptId = $conn->insert_id;
    logMessage("Successfully inserted test transcript with ID: $transcriptId");
    
    // Add a test course to the transcript
    $sql = "INSERT INTO transcript_courses (transcript_id, course_id, grade, credits_earned) 
            VALUES (?, 1, 'A', 3)"; // Assuming course with ID 1 exists
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('i', $transcriptId);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $conn->commit();
    logMessage("Successfully added test course to transcript");
    logMessage("Test completed successfully!");
    
} catch (Exception $e) {
    $conn->rollback();
    logMessage("Error during test: " . $e->getMessage());
}

$conn->close();
?>
