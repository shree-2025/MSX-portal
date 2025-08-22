-- Create rewards table
CREATE TABLE IF NOT EXISTS `rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `coin_cost` int(11) NOT NULL DEFAULT 0,
  `stock` int(11) DEFAULT NULL COMMENT 'NULL means unlimited stock',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create reward redemptions table
CREATE TABLE IF NOT EXISTS `reward_redemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `coin_cost` int(11) NOT NULL,
  `status` enum('pending','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  `shipping_address` text DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `reward_id` (`reward_id`),
  KEY `status` (`status`),
  CONSTRAINT `reward_redemptions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reward_redemptions_ibfk_2` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add notification types for rewards
INSERT IGNORE INTO `notification_types` (`type`, `name`, `description`) VALUES
('reward_redemption_requested', 'Reward Redemption Requested', 'A student has requested to redeem a reward'),
('reward_status_updated', 'Reward Status Updated', 'The status of your reward redemption has been updated');

-- Add sample rewards
INSERT INTO `rewards` (`name`, `description`, `coin_cost`, `stock`, `is_active`) VALUES
('$10 Amazon Gift Card', 'Redeem a $10 Amazon gift card', 1000, 10, 1),
('5% Off Next Course', 'Get 5% off your next course purchase', 500, 100, 1),
('Exclusive Course Content', 'Access to exclusive course materials', 300, 50, 1),
('1-on-1 Tutoring Session', '30-minute one-on-one session with an instructor', 1500, 5, 1),
('Certificate of Achievement', 'Personalized certificate for your profile', 200, NULL, 1);

-- Add admin menu item
INSERT IGNORE INTO `admin_menu` (`id`, `parent_id`, `name`, `url`, `icon`, `order_num`, `is_active`) VALUES
(NULL, 0, 'Rewards', 'rewards_management.php', 'fas fa-gift', 50, 1);
