-- Add admin_comment column to results table for storing admin comments on student results
ALTER TABLE results ADD COLUMN admin_comment TEXT NULL AFTER grade;

-- Add academic_year_comments table for storing overall comments per academic year
CREATE TABLE IF NOT EXISTS academic_year_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    comment TEXT NOT NULL,
    added_by_user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_year_comment (student_user_id, academic_year)
);