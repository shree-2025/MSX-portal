<?php
require_once __DIR__ . '/../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Checking Transcript Table Structure</h2>";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'transcripts'");
if ($result->num_rows === 0) {
    die("The 'transcripts' table does not exist in the database.");
}

// Get table structure
$result = $conn->query("DESCRIBE transcripts");
if (!$result) {
    die("Error describing table: " . $conn->error);
}

echo "<h3>Table Structure:</h3>";
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

// Check for required columns
$required_columns = ['id', 'student_id', 'user_id', 'gpa', 'completion_date', 'issue_date', 'transcript_number', 'file_path'];
$missing_columns = [];

$result = $conn->query("SHOW COLUMNS FROM transcripts");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

echo "<h3>Missing Columns:</h3>";
$missing = false;
foreach ($required_columns as $column) {
    if (!in_array($column, $existing_columns)) {
        echo "<div style='color: red;'>✗ $column is missing</div>";
        $missing = true;
    } else {
        echo "<div style='color: green;'>✓ $column exists</div>";
    }
}

if ($missing) {
    echo "<p>Please run the following SQL to fix the table structure:</p>";
    echo "<pre>";
    if (!in_array('student_id', $existing_columns)) {
        echo "ALTER TABLE transcripts ADD COLUMN student_id INT NOT NULL AFTER id;\n";
        echo "ALTER TABLE transcripts ADD CONSTRAINT fk_transcript_student FOREIGN KEY (student_id) REFERENCES users(id);\n";
    }
    if (!in_array('user_id', $existing_columns)) {
        echo "ALTER TABLE transcripts ADD COLUMN user_id INT NOT NULL AFTER student_id;\n";
        echo "ALTER TABLE transcripts ADD CONSTRAINT fk_transcript_user FOREIGN KEY (user_id) REFERENCES users(id);\n";
    }
    if (!in_array('gpa', $existing_columns)) {
        echo "ALTER TABLE transcripts ADD COLUMN gpa DECIMAL(3,2) DEFAULT NULL;\n";
    }
    if (!in_array('completion_date', $existing_columns)) {
        echo "ALTER TABLE transcripts ADD COLUMN completion_date DATE DEFAULT NULL;\n";
    }
    if (!in_array('issue_date', $existing_columns)) {
        echo "ALTER TABLE transcripts ADD COLUMN issue_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP;\n";
    }
    if (!in_array('transcript_number', $existing_columns)) {
        echo "ALTER TABLE transcripts ADD COLUMN transcript_number VARCHAR(50) UNIQUE;\n";
    }
    if (!in_array('file_path', $existing_columns)) {
        echo "ALTER TABLE transcripts ADD COLUMN file_path VARCHAR(255) NOT NULL;\n";
    }
    echo "</pre>";
}

// Check for foreign key constraints
$result = $conn->query("
    SELECT 
        TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
    FROM
n        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        REFERENCED_TABLE_SCHEMA = 'coaching_center' AND
        REFERENCED_TABLE_NAME = 'users' AND
        TABLE_NAME = 'transcripts';
");

echo "<h3>Foreign Key Constraints:</h3>";
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Column</th><th>References</th><th>Constraint</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['COLUMN_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['REFERENCED_TABLE_NAME'] . '.' . $row['REFERENCED_COLUMN_NAME']) . "</td>";
        echo "<td>" . htmlspecialchars($row['CONSTRAINT_NAME']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No foreign key constraints found.";
}

// Check for existing transcripts
$result = $conn->query("SELECT COUNT(*) as count FROM transcripts");
$count = $result->fetch_assoc()['count'];
echo "<h3>Existing Transcripts: $count</h3>";

if ($count > 0) {
    $result = $conn->query("SELECT * FROM transcripts ORDER BY created_at DESC LIMIT 5");
    echo "<h4>Recent Transcripts:</h4>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr>";
    // Print headers
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";
    
    // Reset pointer and print data
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>
    
    <p><a href='transcripts.php'>View all transcripts</a></p>";
}

$conn->close();
?>
