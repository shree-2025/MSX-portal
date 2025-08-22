<?php
require_once __DIR__ . '/../config/database.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Updating Test Settings</h2>";

// 1. Add is_active column to tests table if it doesn't exist
try {
    $sql = "ALTER TABLE tests ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1";
    $conn->query($sql);
    echo "✓ Added is_active column to tests table<br>";
    
    // Update all existing tests to be active
    $sql = "UPDATE tests SET is_active = 1 WHERE is_active IS NULL OR is_active = 0";
    $conn->query($sql);
    echo "✓ Set all existing tests to active<br>";
    
} catch (Exception $e) {
    echo "ℹ️ " . $e->getMessage() . "<br>";
}

// 2. Add allow_retake column to tests table if it doesn't exist
try {
    $sql = "ALTER TABLE tests ADD COLUMN IF NOT EXISTS allow_retake TINYINT(1) DEFAULT 1";
    $conn->query($sql);
    echo "✓ Added allow_retake column to tests table<br>";
    
    // Set allow_retake to 1 for all existing tests
    $sql = "UPDATE tests SET allow_retake = 1 WHERE allow_retake IS NULL OR allow_retake = 0";
    $conn->query($sql);
    echo "✓ Set all tests to allow retakes by default<br>";
    
} catch (Exception $e) {
    echo "ℹ️ " . $e->getMessage() . "<br>";
}

// 3. Show current test settings
echo "<h3>Current Test Settings</h3>";
$result = $conn->query("SELECT id, title, is_active, allow_retake FROM tests LIMIT 10");
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='8' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Title</th><th>Active</th><th>Allow Retake</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . ($row['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . ($row['allow_retake'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No tests found in the database.<br>";
}

// 4. Update test creation form to include these options by default
$testFormPath = __DIR__ . '/create_test.php';
if (file_exists($testFormPath)) {
    $content = file_get_contents($testFormPath);
    
    // Add is_active field if not exists
    if (strpos($content, 'name="is_active"') === false) {
        $search = "<div class='mb-3'>";
        $replace = "<div class='mb-3 form-check'>
            <input type='checkbox' class='form-check-input' id='is_active' name='is_active' value='1' checked>
            <label class='form-check-label' for='is_active'>Active (visible to students)</label>
        </div>
        <div class='mb-3 form-check'>
            <input type='checkbox' class='form-check-input' id='allow_retake' name='allow_retake' value='1' checked>
            <label class='form-check-label' for='allow_retake'>Allow students to retake this test</label>
        </div>
        <div class='mb-3'>";
        
        $content = str_replace($search, $replace, $content);
        file_put_contents($testFormPath, $content);
        echo "✓ Updated test creation form with active and retake options<br>";
    }
}

echo "<h3 style='color: green;'>✓ Update complete! All tests are now active and allow retakes by default.</h3>";
?>
