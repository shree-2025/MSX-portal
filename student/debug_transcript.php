<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/auth_functions.php';
requireLogin();

// Get transcript ID from URL or use a default one for testing
$transcript_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

echo "<h2>Transcript Debug Information</h2>";

if ($transcript_id <= 0) {
    // List all transcripts for the student
    $stmt = $conn->prepare("
        SELECT t.*, u.full_name 
        FROM transcripts t
        JOIN users u ON t.student_id = u.id
        WHERE t.student_id = ?
        ORDER BY t.issue_date DESC
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $transcripts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($transcripts)) {
        echo "<p>No transcripts found for this student.</p>";
    } else {
        echo "<h3>Available Transcripts</h3>";
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>
        <tr>
            <th>ID</th>
            <th>Issue Date</th>
            <th>File Path</th>
            <th>File Exists</th>
            <th>Actions</th>
        </tr>";
        
        foreach ($transcripts as $t) {
            $file_path = __DIR__ . '/..' . $t['file_path'];
            $file_exists = file_exists($file_path) ? '✅ Yes' : '❌ No';
            $file_path_display = htmlspecialchars($t['file_path']);
            
            echo "<tr>
                <td>{$t['id']}</td>
                <td>" . date('Y-m-d', strtotime($t['issue_date'])) . "</td>
                <td>{$file_path_display}</td>
                <td>{$file_exists}</td>
                <td>
                    <a href='view_transcript.php?id={$t['id']}' target='_blank'>View</a> | 
                    <a href='download_transcript.php?id={$t['id']}'>Download</a>
                </td>
            </tr>";
        }
        echo "</table>";
    }
    exit();
}

// Debug specific transcript
$stmt = $conn->prepare("
    SELECT t.*, u.full_name, u.email 
    FROM transcripts t
    JOIN users u ON t.student_id = u.id
    WHERE t.id = ? AND t.student_id = ?
");
$stmt->bind_param('ii', $transcript_id, $_SESSION['user_id']);
$stmt->execute();
$transcript = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transcript) {
    die("<p>Error: Transcript not found or access denied.</p>");
}

$file_path = __DIR__ . '/..' . $transcript['file_path'];
$file_exists = file_exists($file_path);

// Output debug information
echo "<h3>Transcript #{$transcript_id} - {$transcript['full_name']}</h3>";
echo "<p><strong>Issue Date:</strong> " . date('Y-m-d', strtotime($transcript['issue_date'])) . "</p>";
echo "<p><strong>File Path in DB:</strong> " . htmlspecialchars($transcript['file_path']) . "</p>";
echo "<p><strong>Full Server Path:</strong> " . htmlspecialchars($file_path) . "</p>";
echo "<p><strong>File Exists:</strong> " . ($file_exists ? '✅ Yes' : '❌ No') . "</p>";

if ($file_exists) {
    echo "<p><strong>File Size:</strong> " . filesize($file_path) . " bytes</p>";
    echo "<p><strong>Is Readable:</strong> " . (is_readable($file_path) ? '✅ Yes' : '❌ No') . "</p>";
    
    // Try to read the file
    $content = @file_get_contents($file_path);
    if ($content === false) {
        echo "<p><strong>Error Reading File:</strong> " . error_get_last()['message'] . "</p>";
    } else {
        echo "<p><strong>File Type:</strong> " . mime_content_type($file_path) . "</p>";
    }
}

// Show directory listing
$dir_path = dirname($file_path);
echo "<h3>Directory Contents: " . htmlspecialchars($dir_path) . "</h3>";

if (is_dir($dir_path)) {
    echo "<ul>";
    $files = scandir($dir_path);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $full_path = $dir_path . '/' . $file;
        $is_dir = is_dir($full_path) ? '📁 ' : '📄 ';
        $size = is_file($full_path) ? ' (' . filesize($full_path) . ' bytes)' : '';
        echo "<li>{$is_dir}" . htmlspecialchars($file) . "{$size}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Directory does not exist.</p>";
}
?>
