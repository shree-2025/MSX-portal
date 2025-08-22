<?php
require_once __DIR__ . '/../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Fixing Transcript Table Columns</h2>";

// Check if we need to add any columns
$alter_queries = [];

// Check for missing columns in transcripts table
$result = $conn->query("DESCRIBE transcripts");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

// Define required columns and their definitions
$required_columns = [
    'user_id' => "INT(11) NOT NULL COMMENT 'Admin who issued the transcript' AFTER `id`",
    'student_id' => "INT(11) NOT NULL AFTER `user_id`",
    'transcript_number' => "VARCHAR(50) NOT NULL AFTER `student_id`",
    'gpa' => "DECIMAL(3,2) DEFAULT NULL AFTER `status`",
    'completion_date' => "DATE DEFAULT NULL AFTER `gpa`",
    'additional_notes' => "TEXT DEFAULT NULL AFTER `completion_date`",
    'program_name' => "VARCHAR(255) DEFAULT NULL AFTER `additional_notes`"
];

// Generate ALTER TABLE statements for missing columns
foreach ($required_columns as $column => $definition) {
    if (!in_array($column, $existing_columns)) {
        $alter_queries[] = "ADD COLUMN `{$column}` {$definition}";
    }
}

// Add unique index for transcript_number if it doesn't exist
if (!in_array('transcript_number', $existing_columns)) {
    $alter_queries[] = "ADD UNIQUE INDEX IF NOT EXISTS `unique_transcript_number` (`transcript_number`)";
}

// Add foreign key constraints if they don't exist
$fk_checks = [
    'user_id' => 'ADD CONSTRAINT `transcripts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'student_id' => 'ADD CONSTRAINT `transcripts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE'
];

foreach ($fk_checks as $column => $constraint) {
    if (in_array($column, $existing_columns)) {
        $result = $conn->query("
            SELECT COUNT(*) as count 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'transcripts' 
            AND CONSTRAINT_NAME = '{$constraint}'
        ")->fetch_assoc();
        
        if ($result['count'] == 0) {
            $alter_queries[] = $constraint;
        }
    }
}

// Execute ALTER TABLE if we have changes
if (!empty($alter_queries)) {
    $alter_sql = "ALTER TABLE `transcripts` " . implode(", ", $alter_queries);
    
    echo "<h3>Running SQL:</h3>";
    echo "<pre>" . htmlspecialchars($alter_sql) . "</pre>";
    
    if ($conn->multi_query($alter_sql)) {
        echo "<p style='color: green;'>✅ Successfully updated transcripts table structure.</p>";
        
        // Clear all results from multi_query
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
    } else {
        echo "<p style='color: red;'>❌ Error updating table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>No changes needed. All required columns already exist.</p>";
}

// Verify the table structure
$result = $conn->query("DESCRIBE transcripts");
echo "<h3>Current Table Structure:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
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

// Check for transcript_courses table structure
$result = $conn->query("DESCRIBE transcript_courses");
echo "<h3>Transcript Courses Table Structure:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
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

// Close connection
$conn->close();
?>
