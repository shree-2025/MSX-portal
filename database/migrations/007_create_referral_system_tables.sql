-- Add referral_code to users table if not exists
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `referral_code` VARCHAR(20) NULL UNIQUE AFTER `role`,
ADD COLUMN IF NOT EXISTS `referred_by` INT(11) NULL AFTER `referral_code`,
ADD INDEX `idx_referral_code` (`referral_code`),
ADD INDEX `idx_referred_by` (`referred_by`),
ADD CONSTRAINT `fk_users_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Create student_wallet table
CREATE TABLE IF NOT EXISTS `student_wallet` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `balance` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student` (`student_id`),
  CONSTRAINT `fk_wallet_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create wallet_transactions table
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` INT(11) NOT NULL,
  `amount` INT(11) NOT NULL,
  `type` ENUM('credit', 'debit') NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `reference_type` VARCHAR(50) NOT NULL,
  `reference_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet` (`wallet_id`),
  KEY `idx_reference` (`reference_type`, `reference_id`),
  CONSTRAINT `fk_transaction_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `student_wallet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create rewards table
CREATE TABLE IF NOT EXISTS `rewards` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `coin_cost` INT(11) NOT NULL,
  `stock` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create reward_redemptions table
CREATE TABLE IF NOT EXISTS `reward_redemptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) NOT NULL,
  `reward_id` INT(11) NOT NULL,
  `status` ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  `shipping_address` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_reward` (`reward_id`),
  CONSTRAINT `fk_redemption_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_redemption_reward` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial rewards
INSERT INTO `rewards` (`name`, `description`, `coin_cost`, `stock`, `is_active`) VALUES
('MSX Branded Watch', 'Elegant watch with MSX logo', 2000, 20, 1),
('MSX Laptop Bag', 'Premium laptop bag for students', 1500, 50, 1),
('MSX Water Bottle', 'Insulated water bottle', 1000, 100, 1),
('MSX T-Shirt', 'Comfortable cotton t-shirt', 800, 100, 1),
('MSX Sticker Pack', 'Set of MSX branded stickers', 200, 200, 1);

-- Create a function to generate referral codes
DELIMITER //
CREATE FUNCTION IF NOT EXISTS generate_referral_code() 
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    DECLARE chars VARCHAR(36) DEFAULT 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    DECLARE code VARCHAR(20) DEFAULT '';
    DECLARE i INT DEFAULT 0;
    DECLARE code_length INT DEFAULT 8;
    
    WHILE i < code_length DO
        SET code = CONCAT(code, SUBSTRING(chars, FLOOR(1 + RAND() * 36), 1));
        SET i = i + 1;
    END WHILE;
    
    -- Check if code already exists
    WHILE EXISTS (SELECT 1 FROM users WHERE referral_code = code) DO
        SET code = '';
        SET i = 0;
        WHILE i < code_length DO
            SET code = CONCAT(code, SUBSTRING(chars, FLOOR(1 + RAND() * 36), 1));
            SET i = i + 1;
        END WHILE;
    END WHILE;
    
    RETURN code;
END //
DELIMITER ;

-- Create a trigger to generate referral code for new users
DELIMITER //
CREATE TRIGGER IF NOT EXISTS before_user_insert
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.role = 'student' AND (NEW.referral_code IS NULL OR NEW.referral_code = '') THEN
        SET NEW.referral_code = generate_referral_code();
    END IF;
END //
DELIMITER ;

-- Create a trigger to create wallet for new students
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_user_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.role = 'student' THEN
        INSERT INTO student_wallet (student_id, balance) VALUES (NEW.id, 0);
    END IF;
END //
DELIMITER ;
