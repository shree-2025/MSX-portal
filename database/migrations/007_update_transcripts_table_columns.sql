-- Add missing columns to transcripts table
ALTER TABLE transcripts
ADD COLUMN gpa DECIMAL(3,2) AFTER student_id,
ADD COLUMN completion_date DATE AFTER gpa,
ADD COLUMN transcript_number VARCHAR(50) AFTER completion_date,
ADD COLUMN additional_notes TEXT AFTER file_path,
ADD COLUMN program_name VARCHAR(100) AFTER additional_notes,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Create transcript_courses table if it doesn't exist
CREATE TABLE IF NOT EXISTS transcript_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transcript_id INT NOT NULL,
    course_id INT NOT NULL,
    grade VARCHAR(2) NOT NULL,
    credits_earned INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transcript_id) REFERENCES transcripts(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
