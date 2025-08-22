<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Test database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Database connection successful!<br><br>";

// Get the first student ID for testing
$result = $conn->query("SELECT id FROM users WHERE role = 'student' LIMIT 1");
if ($result->num_rows === 0) {
    die("No student found in the database. Please add a student first.");
}

$student = $result->fetch_assoc();
$studentId = $student['id'];
echo "Using student ID: $studentId<br>";

// Begin transaction
$conn->begin_transaction();

try {
    // Insert test transcript
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
    echo "Successfully inserted test transcript with ID: $transcriptId<br>";
    
    // Get first course ID for testing
    $result = $conn->query("SELECT id FROM courses LIMIT 1");
    if ($result->num_rows === 0) {
        throw new Exception("No courses found in the database. Please add a course first.");
    }
    $course = $result->fetch_assoc();
    $courseId = $course['id'];
    
    // Add a test course to the transcript
    $sql = "INSERT INTO transcript_courses (transcript_id, course_id, grade, credits_earned) 
            VALUES (?, ?, 'A', 3)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('ii', $transcriptId, $courseId);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $conn->commit();
    echo "Successfully added test course to transcript<br>";
    echo "Test completed successfully!<br>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "<br>";
}

// Show all transcripts for this student
$sql = "SELECT t.*, COUNT(tc.id) as course_count 
        FROM transcripts t 
        LEFT JOIN transcript_courses tc ON t.id = tc.transcript_id 
        WHERE t.student_id = ? 
        GROUP BY t.id";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Transcripts for student ID $studentId:</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Transcript #</th><th>GPA</th><th>Issue Date</th><th>Courses</th><th>File Path</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['transcript_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['gpa']) . "</td>";
    echo "<td>" . htmlspecialchars($row['issue_date']) . "</td>";
    echo "<td>" . htmlspecialchars($row['course_count']) . "</td>";
    echo "<td>" . htmlspecialchars($row['file_path']) . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
