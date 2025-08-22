-- Update transcripts table to include more fields
ALTER TABLE transcripts
ADD COLUMN gpa DECIMAL(3,2) NOT NULL AFTER student_id,
ADD COLUMN completion_date DATE NOT NULL AFTER gpa,
ADD COLUMN transcript_number VARCHAR(50) UNIQUE NOT NULL AFTER completion_date,
ADD COLUMN additional_notes TEXT AFTER file_path,
ADD COLUMN program_name VARCHAR(100) AFTER student_id,
ADD COLUMN issue_date DATE NOT NULL AFTER completion_date,
MODIFY COLUMN file_path VARCHAR(255) NOT NULL;

-- Create transcript_courses table
CREATE TABLE IF NOT EXISTS transcript_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transcript_id INT NOT NULL,
    course_id INT NOT NULL,
    grade VARCHAR(2) NOT NULL,
    credits_earned INT NOT NULL,
    FOREIGN KEY (transcript_id) REFERENCES transcripts(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
