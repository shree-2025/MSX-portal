<?php
require_once __DIR__ . '/../config/database.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Transcript Debug</h2>";

// 1. Check tables
echo "<h3>Checking Tables</h3>";
$tables = ['transcripts', 'users', 'courses', 'transcript_courses'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo $result->num_rows ? "✓ $table exists<br>" : "✗ $table missing<br>";
}

// 2. Check transcript columns
echo "<h3>Transcript Table Structure</h3>";
$result = $conn->query("DESCRIBE transcripts");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} ({$row['Type']})<br>";
}

// 3. Test insert
if (isset($_GET['test'])) {
    echo "<h3>Test Insert</h3>";
    try {
        $conn->begin_transaction();
        
        // Get first student
        $student = $conn->query("SELECT id FROM users WHERE role='student' LIMIT 1")->fetch_assoc();
        if (!$student) throw new Exception("No students found");
        
        // Insert test transcript
        $sql = "INSERT INTO transcripts (student_id, user_id, gpa, transcript_number, file_path) 
                VALUES (?, 1, 3.5, 'TEST-" . uniqid() . "', '/test.pdf')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $student['id']);
        $stmt->execute();
        $id = $conn->insert_id;
        
        echo "✓ Inserted test transcript ID: $id<br>";
        $conn->rollback(); // Don't actually save
        echo "<i>Note: Transaction rolled back - no changes saved</i><br>";
        
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "<br>";
        if (isset($sql)) echo "SQL: $sql<br>";
    }
}

// 4. Show recent transcripts
echo "<h3>Recent Transcripts</h3>";
$result = $conn->query("SELECT t.*, u.username as student_name FROM transcripts t JOIN users u ON t.student_id = u.id ORDER BY t.id DESC LIMIT 5");
if ($result->num_rows) {
    while ($row = $result->fetch_assoc()) {
        echo "#{$row['id']} - {$row['student_name']} - {$row['transcript_number']}<br>";
    }
} else {
    echo "No transcripts found<br>";
}

// 5. Test link
echo "<p><a href='?test=1' class='btn btn-primary'>Run Test Insert</a></p>";
?>
