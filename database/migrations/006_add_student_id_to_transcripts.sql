-- Add student_id column to transcripts table if it doesn't exist
ALTER TABLE transcripts
ADD COLUMN student_id INT NOT NULL AFTER id,
ADD CONSTRAINT fk_student_id FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE;

-- If there are existing records, you'll need to update them with valid student IDs
-- This is just an example - you'll need to replace 1 with an actual user ID
-- UPDATE transcripts SET student_id = 1 WHERE student_id = 0;
