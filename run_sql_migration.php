<?php
/**
 * Script to run SQL migration files
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';  // Change this to your database username
$db_pass = '';      // Change this to your database password
$db_name = 'msx';    // Change this to your database name

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Migration file to run
$migrationFile = 'database/migrations/008_create_referral_system_tables.sql';

// Check if file exists
if (!file_exists($migrationFile)) {
    die("Error: Migration file not found: $migrationFile\n");
}

// Read the SQL file
$sql = file_get_contents($migrationFile);

if ($sql === false) {
    die("Error reading migration file: $migrationFile\n");
}

// Split the SQL file into individual queries
$queries = explode(';', $sql);

// Execute each query
echo "Running migration: " . basename($migrationFile) . "\n";
$successCount = 0;
$errorCount = 0;

foreach ($queries as $query) {
    // Skip empty queries
    $query = trim($query);
    if (empty($query)) {
        continue;
    }
    
    // Execute the query
    if ($conn->query($query) === TRUE) {
        $successCount++;
        echo ".";
    } else {
        $errorCount++;
        echo "\nError executing query: " . $conn->error . "\n";
        echo "Query: " . substr($query, 0, 200) . "...\n";
    }
}

echo "\n\nMigration completed!\n";
echo "Successful queries: $successCount\n";
echo "Failed queries: $errorCount\n";

// Close connection
$conn->close();
?>
