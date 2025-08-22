<?php
/**
 * Migration: Create tables for the referral system
 */

class Migration_Create_referral_system_tables extends CI_Migration {
    
    public function up() {
        // Add referral_code and referred_by columns to users table if they don't exist
        $this->db->query("
            ALTER TABLE `users` 
            ADD COLUMN IF NOT EXISTS `referral_code` VARCHAR(20) NULL UNIQUE AFTER `role`,
            ADD COLUMN IF NOT EXISTS `referred_by` INT(11) NULL AFTER `referral_code`,
            ADD INDEX IF NOT EXISTS `idx_referral_code` (`referral_code`),
            ADD INDEX IF NOT EXISTS `idx_referred_by` (`referred_by`)
        ");
        
        // Add foreign key constraint separately to avoid issues
        $this->db->query("
            ALTER TABLE `users`
            ADD CONSTRAINT IF NOT EXISTS `fk_users_referred_by` 
            FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ");
        
        // Create student_wallet table if it doesn't exist
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `student_wallet` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `student_id` INT(11) NOT NULL,
                `balance` INT(11) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_student` (`student_id`),
                CONSTRAINT `fk_wallet_student` 
                    FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create wallet_transactions table if it doesn't exist
        $this->db->query("
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
                CONSTRAINT `fk_transaction_wallet` 
                    FOREIGN KEY (`wallet_id`) REFERENCES `student_wallet` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create rewards table if it doesn't exist
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `rewards` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT DEFAULT NULL,
                `image` VARCHAR(255) DEFAULT NULL,
                `coin_cost` INT(11) NOT NULL,
                `stock` INT(11) DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create reward_redemptions table if it doesn't exist
        $this->db->query("
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
                CONSTRAINT `fk_redemption_student` 
                    FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_redemption_reward` 
                    FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Insert initial rewards if they don't exist
        $this->db->query("
            INSERT IGNORE INTO `rewards` (`name`, `description`, `coin_cost`, `stock`, `is_active`) VALUES
            ('MSX Branded Watch', 'Elegant watch with MSX logo', 2000, 20, 1),
            ('MSX Laptop Bag', 'Premium laptop bag for students', 1500, 50, 1),
            ('MSX Water Bottle', 'Insulated water bottle', 1000, 100, 1),
            ('MSX T-Shirt', 'Comfortable cotton t-shirt', 800, 100, 1),
            ('MSX Sticker Pack', 'Set of MSX branded stickers', 200, 200, 1)
        ");
        
        // Drop and recreate the function to ensure it's up to date
        $this->db->query("DROP FUNCTION IF EXISTS generate_referral_code");
        
        // Create function to generate referral codes
        $this->db->query("
            CREATE FUNCTION generate_referral_code() 
            RETURNS VARCHAR(20)
            DETERMINISTIC
            BEGIN
                DECLARE chars VARCHAR(36) DEFAULT 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                DECLARE code VARCHAR(20) DEFAULT '';
                DECLARE i INT DEFAULT 0;
                DECLARE code_length INT DEFAULT 8;
                DECLARE code_exists INT;
                
                generate_new_code: LOOP
                    SET code = '';
                    SET i = 0;
                    
                    -- Generate a new code
                    WHILE i < code_length DO
                        SET code = CONCAT(code, SUBSTRING(chars, FLOOR(1 + RAND() * 36), 1));
                        SET i = i + 1;
                    END WHILE;
                    
                    -- Check if code already exists
                    SELECT COUNT(*) INTO code_exists FROM users WHERE referral_code = code;
                    
                    IF code_exists = 0 THEN
                        LEAVE generate_new_code;
                    END IF;
                END LOOP;
                
                RETURN code;
            END
        ");
        
        // Drop and recreate the trigger to ensure it's up to date
        $this->db->query("DROP TRIGGER IF EXISTS before_user_insert");
        
        // Create trigger to generate referral code for new students
        $this->db->query("
            CREATE TRIGGER before_user_insert
            BEFORE INSERT ON users
            FOR EACH ROW
            BEGIN
                IF NEW.role = 'student' AND (NEW.referral_code IS NULL OR NEW.referral_code = '') THEN
                    SET NEW.referral_code = generate_referral_code();
                END IF;
            END
        ");
        
        // Drop and recreate the trigger to ensure it's up to date
        $this->db->query("DROP TRIGGER IF EXISTS after_user_insert");
        
        // Create trigger to create wallet for new students
        $this->db->query("
            CREATE TRIGGER after_user_insert
            AFTER INSERT ON users
            FOR EACH ROW
            BEGIN
                IF NEW.role = 'student' THEN
                    INSERT INTO student_wallet (student_id, balance) VALUES (NEW.id, 0);
                END IF;
            END
        ");
        
        // Update existing students with referral codes if they don't have one
        $this->db->query("
            UPDATE users 
            SET referral_code = generate_referral_code() 
            WHERE role = 'student' AND (referral_code IS NULL OR referral_code = '')
        ");
        
        // Create wallets for existing students who don't have one
        $this->db->query("
            INSERT IGNORE INTO student_wallet (student_id, balance)
            SELECT id, 0 FROM users 
            WHERE role = 'student' 
            AND id NOT IN (SELECT student_id FROM student_wallet)
        ");
    }
    
    public function down() {
        // Drop triggers first to avoid dependency issues
        $this->db->query("DROP TRIGGER IF EXISTS before_user_insert");
        $this->db->query("DROP TRIGGER IF EXISTS after_user_insert");
        
        // Drop function
        $this->db->query("DROP FUNCTION IF EXISTS generate_referral_code");
        
        // Drop tables in reverse order of dependencies
        $this->db->query("DROP TABLE IF EXISTS reward_redemptions");
        $this->db->query("DROP TABLE IF EXISTS rewards");
        $this->db->query("DROP TABLE IF EXISTS wallet_transactions");
        $this->db->query("DROP TABLE IF EXISTS student_wallet");
        
        // Remove columns from users table
        $this->db->query("ALTER TABLE users DROP FOREIGN KEY IF EXISTS fk_users_referred_by");
        $this->db->query("ALTER TABLE users DROP INDEX IF EXISTS idx_referral_code");
        $this->db->query("ALTER TABLE users DROP INDEX IF EXISTS idx_referred_by");
        $this->db->query("ALTER TABLE users DROP COLUMN IF EXISTS referred_by");
        $this->db->query("ALTER TABLE users DROP COLUMN IF EXISTS referral_code");
    }
}
