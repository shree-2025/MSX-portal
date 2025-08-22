<?php
// This script will list all files in the uploads directory
$upload_dir = __DIR__ . '/../uploads/';

echo "<h2>Uploads Directory Contents</h2>";
echo "<p>Checking directory: " . realpath($upload_dir) . "</p>";

if (!is_dir($upload_dir)) {
    die("Uploads directory does not exist!");
}

function list_files($dir, $prefix = '') {
    $files = scandir($dir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . $file;
        if (is_dir($path)) {
            echo "<li><strong>Directory: $file</strong>";
            list_files($path . '/', $prefix . $file . '/');
            echo "</li>";
        } else {
            $file_path = $prefix . $file;
            $file_url = '/uploads/' . $file_path;
            echo "<li>
                    $file_path 
                    <a href='$file_url' target='_blank'>[View]</a> 
                    <a href='$file_url' download>[Download]</a>
                  </li>";
        }
    }
    echo "</ul>";
}

list_files($upload_dir);
?>
