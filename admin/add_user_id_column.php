<?php
require_once __DIR__ . '/../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Adding user_id Column to Transcripts Table</h2>";

try {
    // Add user_id column
    $sql = "ALTER TABLE transcripts 
            ADD COLUMN user_id INT NOT NULL AFTER student_id,
            ADD CONSTRAINT fk_transcript_user FOREIGN KEY (user_id) REFERENCES users(id)";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div style='color: green;'>✓ Successfully added user_id column to transcripts table</div>";
        
        // Update existing records to set user_id (using admin user or first available user)
        $updateSql = "UPDATE transcripts t 
                     SET user_id = (SELECT id FROM users WHERE role = 'admin' LIMIT 1)
                     WHERE user_id = 0 OR user_id IS NULL";
        
        if ($conn->query($updateSql) === TRUE) {
            echo "<div style='color: green;'>✓ Updated existing records with default user_id</div>";
        } else {
            echo "<div style='color: orange;'>Note: Could not update existing records: " . $conn->error . "</div>";
        }
    } else {
        throw new Exception("Error adding column: " . $conn->error);
    }
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<div style='color: blue;'>ℹ️ user_id column already exists in the table</div>";
    } else {
        echo "<div style='color: red;'>✗ Error: " . $e->getMessage() . "</div>";
    }
}

// Show current table structure
$result = $conn->query("DESCRIBE transcripts");
echo "<h3>Current Table Structure:</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
