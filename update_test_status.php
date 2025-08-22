<?php
require_once __DIR__ . '/config/config.php';

// Update test status to 'published'
$test_id = 2;
$status = 'published';

$stmt = $conn->prepare("UPDATE tests SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $test_id);

if ($stmt->execute()) {
    echo "Test status updated to 'published' successfully!<br>";
    echo "<a href='debug_test.php'>Go back to debug page</a>";
} else {
    echo "Error updating test status: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
