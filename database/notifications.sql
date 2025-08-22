-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    document_type ENUM('syllabus', 'notes', 'assignment', 'test') NOT NULL,
    document_id INT NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    action ENUM('download', 'upload', 'submission') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add a function to log notifications
DELIMITER //
CREATE PROCEDURE log_notification(
    IN p_user_id INT,
    IN p_course_id INT,
    IN p_document_type VARCHAR(20),
    IN p_document_id INT,
    IN p_document_name VARCHAR(255),
    IN p_action VARCHAR(20)
)
BEGIN
    INSERT INTO notifications (user_id, course_id, document_type, document_id, document_name, action)
    VALUES (p_user_id, p_course_id, p_document_type, p_document_id, p_document_name, p_action);
END //
DELIMITER ;
