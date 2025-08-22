-- Add status column to assignments table
ALTER TABLE assignments 
ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER course_id;

-- Update all existing assignments to be active by default
UPDATE assignments SET status = 'active';

-- Add index for better performance
CREATE INDEX idx_assignment_status ON assignments(status);
