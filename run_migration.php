<?php
/**
 * Script to run database migrations
 */

// Load CodeIgniter database config
require_once 'config/database.php';

// Database connection
$conn = new mysqli(
    $db['default']['hostname'],
    $db['default']['username'],
    $db['default']['password'],
    $db['default']['database']
);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Get all migration files
$migrationFiles = glob('database/migrations/*.php');
sort($migrationFiles);

// Check which migrations have already been run
$migrationsTableQuery = "CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($migrationsTableQuery)) {
    die("Error creating migrations table: " . $conn->error);
}

// Get already run migrations
$runMigrations = [];
$result = $conn->query("SELECT migration FROM migrations");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $runMigrations[] = $row['migration'];
    }
}

// Find migrations to run
$migrationsToRun = [];
foreach ($migrationFiles as $file) {
    $migrationName = basename($file, '.php');
    if (!in_array($migrationName, $runMigrations)) {
        $migrationsToRun[] = $file;
    }
}

if (empty($migrationsToRun)) {
    echo "No new migrations to run.\n";
    exit(0);
}

// Get the next batch number
$batchResult = $conn->query("SELECT MAX(batch) as max_batch FROM migrations");
$batch = $batchResult->fetch_assoc()['max_batch'] ?? 0;
$batch++;

// Run migrations
echo "Running migrations...\n";
foreach ($migrationsToRun as $file) {
    $migrationName = basename($file, '.php');
    echo "- $migrationName\n";
    
    // Include the migration file
    require_once $file;
    
    // Get the class name from the file name
    $className = 'Migration_' . str_replace('-', '_', $migrationName);
    
    // Check if the class exists
    if (!class_exists($className)) {
        echo "  Error: Class $className not found in $file\n";
        continue;
    }
    
    // Create an instance of the migration
    $migration = new $className($conn);
    
    // Run the migration
    try {
        $conn->begin_transaction();
        $migration->up();
        
        // Record the migration
        $stmt = $conn->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $stmt->bind_param("si", $migrationName, $batch);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo "  ✓ Success\n";
    } catch (Exception $e) {
        $conn->rollback();
        echo "  ✗ Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nMigrations completed successfully!\n";

// Base Migration class for all migrations
class Migration {
    protected $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function up() {
        // To be implemented by child classes
    }
    
    public function down() {
        // To be implemented by child classes
    }
    
    protected function query($sql) {
        if (!$this->db->query($sql)) {
            throw new Exception("Query failed: " . $this->db->error . "\nSQL: " . $sql);
        }
        return true;
    }
}
