<?php
require_once __DIR__ . '/config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set user_id for testing (you can change this to test different users)
$_SESSION['user_id'] = 27; // Using the user_id from the error message

// Test ID from the error
$test_id = 2;

// 1. Check if test exists and get its details
$test_query = "SELECT * FROM tests WHERE id = ?";
$stmt = $conn->prepare($test_query);
$stmt->bind_param("i", $test_id);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();

if (!$test) {
    die("Test not found with ID: $test_id");
}

echo "<h2>Test Details</h2>";
echo "<pre>";
print_r($test);
echo "</pre>";

// 2. Check if user is enrolled in the course
$enrollment_query = "SELECT * FROM student_courses WHERE student_id = ? AND course_id = ?";
$stmt = $conn->prepare($enrollment_query);
$stmt->bind_param("ii", $_SESSION['user_id'], $test['course_id']);
$stmt->execute();
$enrollment = $stmt->get_result()->fetch_assoc();

echo "<h2>Enrollment Status</h2>";
if ($enrollment) {
    echo "<pre>";
    print_r($enrollment);
    echo "</pre>";
} else {
    echo "<p>User is not enrolled in this course.</p>";
}

// 3. Check for existing attempts
$attempts_query = "SELECT * FROM test_attempts WHERE test_id = ? AND student_id = ?";
$stmt = $conn->prepare($attempts_query);
$stmt->bind_param("ii", $test_id, $_SESSION['user_id']);
$stmt->execute();
$attempts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h2>Existing Attempts</h2>";
if (!empty($attempts)) {
    echo "<pre>";
    print_r($attempts);
    echo "</pre>";
} else {
    echo "<p>No previous attempts found.</p>";
}

// 4. Check test questions
$questions_query = "SELECT COUNT(*) as count FROM test_questions WHERE test_id = ?";
$stmt = $conn->prepare($questions_query);
$stmt->bind_param("i", $test_id);
$stmt->execute();
$question_count = $stmt->get_result()->fetch_assoc()['count'];

echo "<h2>Test Questions</h2>";
echo "<p>Number of questions: $question_count</p>";

// 5. Check if test is available based on dates
$current_time = date('Y-m-d H:i:s');
$start_date = !empty($test['start_date']) && $test['start_date'] !== '0000-00-00 00:00:00' ? $test['start_date'] : null;
$end_date = !empty($test['end_date']) && $test['end_date'] !== '0000-00-00 00:00:00' ? $test['end_date'] : null;

echo "<h2>Test Availability</h2>";
echo "<p>Current time: $current_time</p>";
echo "<p>Start date: " . ($start_date ?: 'Not set') . "</p>";
echo "<p>End date: " . ($end_date ?: 'Not set') . "</p>";

if ($start_date && $current_time < $start_date) {
    echo "<p style='color: red;'>Test is not available yet. It will start on: " . date('M j, Y g:i A', strtotime($start_date)) . "</p>";
} elseif ($end_date && $current_time > $end_date) {
    echo "<p style='color: red;'>Test has ended. It was available until: " . date('M j, Y g:i A', strtotime($end_date)) . "</p>";
} else {
    echo "<p style='color: green;'>Test should be available now.</p>";
}

// 6. Check if user can start the test
$max_attempts = $test['max_attempts'] ?? 1;
$can_attempt = true;
$message = '';

if ($max_attempts > 0 && count($attempts) >= $max_attempts) {
    $can_attempt = false;
    $message = "Maximum attempts ($max_attempts) reached for this test.";
}

echo "<h2>Can Start Test?</h2>";
if ($can_attempt) {
    echo "<p style='color: green;'>User can start the test.</p>";
    echo "<form action='view_test.php' method='POST'>
            <input type='hidden' name='test_id' value='$test_id'>
            <button type='submit' name='start_attempt' class='btn btn-primary'>Start Test</button>
          </form>";
} else {
    echo "<p style='color: red;'>$message</p>";
}
?>
