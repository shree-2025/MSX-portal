<?php
require_once 'config/config.php';

// Add status column to tests table if it doesn't exist
$sql = "ALTER TABLE tests 
        ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether the test is active and visible to students'";

if ($conn->query($sql) === TRUE) {
    echo "Success: Added is_active column to tests table.\n";
    
    // Update existing tests to be active by default
    $update_sql = "UPDATE tests SET is_active = TRUE WHERE is_active IS NULL";
    if ($conn->query($update_sql) === TRUE) {
        echo "Success: Set all existing tests as active by default.\n";
    } else {
        echo "Error updating existing tests: " . $conn->error . "\n";
    }
    
} else {
    echo "Error adding column: " . $conn->error . "\n";
}

$conn->close();
?>
