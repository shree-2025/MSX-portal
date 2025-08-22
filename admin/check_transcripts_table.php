<?php
require_once __DIR__ . '/../config/database.php';

// Check table structure
$result = $conn->query("DESCRIBE transcripts");
echo "Transcripts Table Structure:\n";
echo str_pad("Field", 20) . str_pad("Type", 20) . str_pad("Null", 10) . str_pad("Key", 10) . "Default\n";
echo str_repeat("-", 70) . "\n";

while ($row = $result->fetch_assoc()) {
    echo str_pad($row['Field'], 20) . 
         str_pad($row['Type'], 20) . 
         str_pad($row['Null'], 10) . 
         str_pad($row['Key'], 10) . 
         $row['Default'] . "\n";
}

// Check if transcript_courses table exists
$result = $conn->query("SHOW TABLES LIKE 'transcript_courses'");
if ($result->num_rows === 0) {
    echo "\nERROR: transcript_courses table is missing!\n";
} else {
    echo "\ntranscript_courses table exists.\n";
}

// Check foreign key constraints
$result = $conn->query("
    SELECT 
        TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, 
        REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM 
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE 
        TABLE_SCHEMA = 'coaching_center' 
        AND TABLE_NAME = 'transcripts' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($result->num_rows > 0) {
    echo "\nForeign key constraints found:\n";
    while ($row = $result->fetch_assoc()) {
        echo "{$row['CONSTRAINT_NAME']} ({$row['COLUMN_NAME']} -> {$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']})\n";
    }
} else {
    echo "\nNo foreign key constraints found.\n";
}

$conn->close();
?>
