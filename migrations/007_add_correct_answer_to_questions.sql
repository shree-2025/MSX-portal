-- Add correct_answer column to test_questions table
ALTER TABLE test_questions 
ADD COLUMN correct_answer TEXT NULL AFTER marks;
