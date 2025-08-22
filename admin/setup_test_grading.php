<?php
require_once __DIR__ . '/../config/database.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Create test_answers table if not exists
$sql = [
    "CREATE TABLE IF NOT EXISTS test_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attempt_id INT NOT NULL,
        question_id INT NOT NULL,
        student_id INT NOT NULL,
        answer TEXT,
        marks_obtained DECIMAL(10,2) DEFAULT 0,
        feedback TEXT,
        is_correct TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (attempt_id) REFERENCES test_attempts(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES test_questions(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    
    // 2. Create test_attempts table if not exists
    "CREATE TABLE IF NOT EXISTS test_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        test_id INT NOT NULL,
        student_id INT NOT NULL,
        started_at DATETIME NOT NULL,
        submitted_at DATETIME,
        status ENUM('in_progress','submitted','graded') DEFAULT 'in_progress',
        total_marks_obtained DECIMAL(10,2) DEFAULT 0,
        percentage DECIMAL(5,2) DEFAULT 0,
        is_passed TINYINT(1) DEFAULT 0,
        feedback TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    
    // 3. Add missing columns to tests table
    "ALTER TABLE tests 
     ADD COLUMN IF NOT EXISTS passing_marks DECIMAL(5,2) DEFAULT 60.00,
     ADD COLUMN IF NOT EXISTS allow_retake TINYINT(1) DEFAULT 1,
     ADD COLUMN IF NOT EXISTS show_answers_after TINYINT(1) DEFAULT 1"
];

// Execute SQL
$conn->begin_transaction();
try {
    foreach ($sql as $query) {
        if (!$conn->query($query)) {
            throw new Exception("Query failed: " . $conn->error);
        }
    }
    $conn->commit();
    echo "<h2>Test Grading System Ready</h2>";
    echo "<p>✓ Database tables and columns are properly set up.</p>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<h2>Error Setting Up</h2>";
    echo "<p style='color:red;'>" . $e->getMessage() . "</p>";
}
?>
