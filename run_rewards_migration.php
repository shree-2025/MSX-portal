<?php
require_once 'config/config.php';

// Read the SQL file
$sqlFile = 'database/migrations/009_create_rewards_tables.sql';
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    die("Error: Could not read SQL file: $sqlFile");
}

try {
    // Execute the SQL queries
    $conn->multi_query($sql);
    
    // Clear any remaining results
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    
    echo "Migration completed successfully!<br>";
    echo "<a href='admin/rewards_management.php'>Go to Rewards Management</a>";
    
} catch (Exception $e) {
    die("Error executing migration: " . $e->getMessage());
}
?>
