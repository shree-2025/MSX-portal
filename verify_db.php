<?php
require_once __DIR__ . '/config/config.php';

// Check if video_meetings table exists
$table_check = $conn->query("SHOW TABLES LIKE 'video_meetings'");
if ($table_check->num_rows === 0) {
    die("The 'video_meetings' table does not exist. Please run the migration script first.");
}

// Check the structure of video_meetings
echo "<h2>video_meetings table structure:</h2>";
$result = $conn->query("SHOW COLUMNS FROM video_meetings");
echo "<table border='1'>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td><td>" . $row['Null'] . "</td><td>" . ($row['Key'] ?: 'NULL') . "</td></tr>";
}
echo "</table>";

// Check if we can insert a test meeting
$test_title = "Test Meeting " . date('Y-m-d H:i:s');
$test_meeting_id = 'test_' . bin2hex(random_bytes(8));
$test_user_id = 1; // Assuming admin user with ID 1 exists

$sql = "INSERT INTO video_meetings (title, meeting_id, host_id, start_time) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $test_title, $test_meeting_id, $test_user_id);

if ($stmt->execute()) {
    echo "<p style='color:green;'>Successfully inserted test meeting!</p>";
    
    // Now try to query it back with the join
    $result = $conn->query("
        SELECT vm.*, u.username as creator_name 
        FROM video_meetings vm
        LEFT JOIN users u ON vm.created_by = u.id
        WHERE vm.meeting_id = '$test_meeting_id'
    ");
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color:green;'>Successfully queried meeting with user join!</p>";
        echo "<pre>";
        print_r($result->fetch_assoc());
        echo "</pre>";
    } else {
        echo "<p style='color:red;'>Failed to query meeting with user join: " . $conn->error . "</p>";
    }
    
    // Clean up
    $conn->query("DELETE FROM video_meetings WHERE meeting_id = '$test_meeting_id'");
} else {
    echo "<p style='color:red;'>Failed to insert test meeting: " . $stmt->error . "</p>";
}

// Check if the users table has the required fields
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'id'");
if ($result->num_rows === 0) {
    echo "<p style='color:red;'>The users table does not have an 'id' column or the column is not named 'id'.</p>";
}
?>
