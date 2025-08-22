<?php
require_once __DIR__ . '/config/config.php';

// Check video_meetings table structure
$result = $conn->query("SHOW CREATE TABLE video_meetings");
$row = $result->fetch_assoc();
echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";

// Check users table structure
$result = $conn->query("SHOW CREATE TABLE users");
$row = $result->fetch_assoc();
echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
?>
