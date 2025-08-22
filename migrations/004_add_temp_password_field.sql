-- Add is_temp_password field to users table
ALTER TABLE users
ADD COLUMN is_temp_password TINYINT(1) NOT NULL DEFAULT 0
COMMENT 'Flag to indicate if the user needs to change their password on next login';

-- Add last_password_reset field to track password changes
ALTER TABLE users
ADD COLUMN last_password_reset TIMESTAMP NULL DEFAULT NULL
COMMENT 'Timestamp of the last password change';

-- Add phone field if it doesn't exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL
COMMENT 'Student\'s contact number';

-- Add status field if it doesn't exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') DEFAULT 'active'
COMMENT 'Account status';
