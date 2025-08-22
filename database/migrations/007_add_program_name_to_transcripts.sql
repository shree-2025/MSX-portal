-- Add program_name column to transcripts table if it doesn't exist
ALTER TABLE transcripts
ADD COLUMN program_name VARCHAR(100) AFTER student_id;

-- Set a default value for existing records
UPDATE transcripts SET program_name = 'Bachelor of Science' WHERE program_name IS NULL;
