<?php
require_once __DIR__ . '/config/config.php';

// SQL to create video_meetings table if it doesn't exist
$sql = "
CREATE TABLE IF NOT EXISTS `video_meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `meeting_id` varchar(100) NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'User ID of the meeting creator',
  `start_time` datetime NOT NULL,
  `duration` int(11) DEFAULT 60 COMMENT 'Meeting duration in minutes',
  `status` enum('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
  `is_recurring` tinyint(1) DEFAULT 0,
  `recurring_pattern` varchar(50) DEFAULT NULL COMMENT 'daily, weekly, monthly',
  `max_participants` int(11) DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `meeting_id` (`meeting_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Execute the query
if ($conn->query($sql) === TRUE) {
    echo "Table 'video_meetings' created successfully or already exists<br>";
} else {
    die("Error creating table: " . $conn->error);
}

// SQL to create meeting_participants table if it doesn't exist
$sql = "
CREATE TABLE IF NOT EXISTS `meeting_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT NULL,
  `left_at` datetime DEFAULT NULL,
  `role` enum('host','participant') NOT NULL DEFAULT 'participant',
  `status` enum('invited','joined','declined','left') DEFAULT 'invited',
  PRIMARY KEY (`id`),
  UNIQUE KEY `meeting_user` (`meeting_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Execute the query
if ($conn->query($sql) === TRUE) {
    echo "Table 'meeting_participants' created successfully or already exists<br>";
} else {
    die("Error creating table: " . $conn->error);
}

// Add foreign key constraints if they don't exist
$sql = "
ALTER TABLE `meeting_participants`
  ADD CONSTRAINT `meeting_participants_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `video_meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meeting_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;";

// Execute the query with error suppression to avoid duplicate key errors
@$conn->query($sql);

echo "<p>Setup completed. <a href='/New%20folder%20(2)/admin/meetings.php'>Go to meetings page</a></p>";
?>
