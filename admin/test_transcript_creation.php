<?php
require_once __DIR__ . '/../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Transcript Creation</h2>";

// 1. Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "✓ Database connection successful<br>";

// 2. Check if tables exist
$tables = ['transcripts', 'transcript_courses', 'users', 'courses'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        die("✗ Table '$table' does not exist<br>");
    }
    echo "✓ Table '$table' exists<br>";
}

// 3. Get a test student
$student = $conn->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetch_assoc();
if (!$student) {
    die("✗ No student found in the database. Please add a student first.<br>");
}
$studentId = $student['id'];
echo "✓ Found student with ID: $studentId<br>";

// 4. Get a test course
$course = $conn->query("SELECT id FROM courses LIMIT 1")->fetch_assoc();
if (!$course) {
    die("✗ No courses found in the database. Please add a course first.<br>");
}
$courseId = $course['id'];
echo "✓ Found course with ID: $courseId<br>";

// 5. Test transcript creation
$transcriptNumber = 'TEST-' . strtoupper(uniqid());
$filePath = '/uploads/transcripts/' . $transcriptNumber . '.pdf';
$gpa = 3.5;
$completionDate = date('Y-m-d');
$additionalNotes = 'Test transcript created by admin';
$programName = 'Test Program';

// Start transaction
$conn->begin_transaction();

try {
    // Ensure uploads directory exists
    $uploadDir = __DIR__ . '/../uploads/transcripts';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Insert transcript
    $stmt = $conn->prepare("INSERT INTO transcripts (student_id, gpa, completion_date, issue_date, transcript_number, file_path, additional_notes, program_name) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param('idsssss', $studentId, $gpa, $completionDate, $transcriptNumber, $filePath, $additionalNotes, $programName);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $transcriptId = $conn->insert_id;
    $stmt->close();
    echo "✓ Created transcript with ID: $transcriptId<br>";

    // Add course to transcript
    $stmt = $conn->prepare("INSERT INTO transcript_courses (transcript_id, course_id, grade, credits_earned) VALUES (?, ?, 'A', 3)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param('ii', $transcriptId, $courseId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to add course to transcript: " . $stmt->error);
    }
    $stmt->close();
    echo "✓ Added course to transcript<br>";

    // Create a simple PDF file
    $pdfContent = "Test Transcript\n";
    $pdfContent .= "Student ID: $studentId\n";
    $pdfContent .= "Transcript #: $transcriptNumber\n";
    $pdfContent .= "GPA: $gpa\n";
    $pdfContent .= "Program: $programName\n";
    
    $pdfPath = __DIR__ . '/..' . $filePath;
    if (file_put_contents($pdfPath, $pdfContent) === false) {
        throw new Exception("Failed to create PDF file at $pdfPath");
    }
    echo "✓ Created PDF file at $filePath<br>";

    // Commit transaction
    $conn->commit();
    echo "<div style='color: green;'><strong>✓ Transcript created successfully!</strong></div>";
    echo "<p>You can view the test transcript <a href='$filePath' target='_blank'>here</a>.</p>";

} catch (Exception $e) {
    $conn->rollback();
    echo "<div style='color: red;'><strong>✗ Error:</strong> " . $e->getMessage() . "</div>";
}

$conn->close();
?>
