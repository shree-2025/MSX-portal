-- Add new columns to feedback table for detailed feedback questions
ALTER TABLE `feedback`
ADD COLUMN `content_relevance` ENUM('Excellent', 'Good', 'Average', 'Poor') NULL AFTER `rating`,
ADD COLUMN `instructor_effectiveness` ENUM('Excellent', 'Good', 'Average', 'Poor') NULL AFTER `content_relevance`,
ADD COLUMN `confidence_application` ENUM('Very Confident', 'Somewhat Confident', 'Not Confident') NULL AFTER `instructor_effectiveness`,
ADD COLUMN `materials_helpfulness` ENUM('Very Helpful', 'Somewhat Helpful', 'Not Helpful') NULL AFTER `confidence_application`,
ADD COLUMN `suggestions_improvement` TEXT NULL AFTER `materials_helpfulness`;
