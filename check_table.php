<?php
require_once __DIR__ . '/config/config.php';

// Check if video_meetings table exists
$result = $conn->query("SHOW TABLES LIKE 'video_meetings'");
if ($result->num_rows === 0) {
    die("The 'video_meetings' table does not exist. Please run the migration script first.");
}

// Show the structure of video_meetings table
$result = $conn->query("DESCRIBE video_meetings");
if ($result === false) {
    die("Error describing table: " . $conn->error);
}

echo "<h2>video_meetings table structure:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['Extra'] ?? '') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check if the migration was applied
$migration_check = $conn->query("SELECT * FROM migrations WHERE migration = '20250820_create_video_meetings_table'");
if ($migration_check->num_rows === 0) {
    echo "<p style='color: red;'>The migration '20250820_create_video_meetings_table' has not been applied.</p>";
    echo "<p>Please run the migration script to create the required tables.</p>";
} else {
    echo "<p style='color: green;'>The migration has been applied successfully.</p>";
}
?>
