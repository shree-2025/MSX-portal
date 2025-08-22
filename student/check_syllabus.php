<?php
require_once __DIR__ . '/../config/database.php';

// Get course ID from URL
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$course_id) {
    die('Please provide a valid course_id parameter');
}

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if syllabus table exists
$table_check = $conn->query("SHOW TABLES LIKE 'syllabus'");
if ($table_check->num_rows === 0) {
    die("Error: The 'syllabus' table does not exist in the database.");
}

// Get syllabus for the course
$sql = "SELECT * FROM syllabus WHERE course_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Syllabus Records for Course ID: $course_id</h2>";

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>File Path</th>
            <th>File Exists</th>
            <th>Created At</th>
        </tr>";

    while($row = $result->fetch_assoc()) {
        $file_exists = file_exists(__DIR__ . '/..' . $row['file_path']) ? 'Yes' : 'No';
        echo "<tr>
            <td>" . $row['id'] . "</td>
            <td>" . htmlspecialchars($row['title']) . "</td>
            <td>" . htmlspecialchars($row['file_path']) . "</td>
            <td style='color: " . ($file_exists === 'Yes' ? 'green' : 'red') . "'>$file_exists</td>
            <td>" . $row['created_at'] . "</td>
        </tr>";
    }
    echo "</table>";
} else {
    echo "No syllabus found for this course.";
}

// Show all courses for reference
$courses = $conn->query("SELECT id, code, name FROM courses");
if ($courses->num_rows > 0) {
    echo "<h3>Available Courses:</h3>";
    echo "<ul>";
    while($course = $courses->fetch_assoc()) {
        echo "<li>ID: " . $course['id'] . " - " . htmlspecialchars($course['code']) . ": " . htmlspecialchars($course['name']) . " 
             <a href='?course_id=" . $course['id'] . "'>View Syllabus</a></li>";
    }
    echo "</ul>";
}

$conn->close();
?>
