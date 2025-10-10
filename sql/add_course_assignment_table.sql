-- Add missing course_assignment table for lecturer-course assignments
USE lscrms;

CREATE TABLE course_assignment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    lecturer_id INT NOT NULL,  -- refers to user_id of lecturer
    academic_year VARCHAR(20) DEFAULT '2024',
    semester ENUM('1','2','Summer') DEFAULT '1',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (course_id, lecturer_id, academic_year, semester)
);

-- Add some sample data for testing
-- First, let's insert some sample programmes, schools, departments and courses if they don't exist
INSERT IGNORE INTO programme (name) VALUES 
('Bachelor of Information Technology'),
('Diploma in Computer Studies'),
('Certificate in Office Administration');

INSERT IGNORE INTO school (name) VALUES 
('School of Computing'),
('School of Business'),
('School of Education');

INSERT IGNORE INTO department (name, school_id) VALUES 
('Information Technology', 1),
('Computer Science', 1),
('Business Administration', 2);

-- Insert sample courses
INSERT IGNORE INTO course (code, name, credits, programme_id, department_id) VALUES 
('CS101', 'Introduction to Programming', 3, 1, 1),
('CS102', 'Web Development Fundamentals', 3, 1, 1),
('CS201', 'Database Systems', 3, 1, 1),
('CS202', 'Software Engineering', 3, 1, 1),
('BIT301', 'Network Administration', 3, 1, 1);

-- Assign courses to the existing lecturer (lecturer1@lsc.ac.zm)
-- Get the lecturer user_id first, then insert assignments
INSERT INTO course_assignment (course_id, lecturer_id, academic_year, semester) 
SELECT c.id, u.id, '2024', '1'
FROM course c, users u 
WHERE u.username = 'lecturer1@lsc.ac.zm' 
AND c.code IN ('CS101', 'CS102', 'CS201');

-- Add some student enrollments for testing
INSERT IGNORE INTO course_enrollment (student_user_id, course_id, academic_year, semester, status)
SELECT u.id, c.id, '2024', '1', 'enrolled'
FROM users u, course c
WHERE u.username = 'LSC000001'
AND c.code IN ('CS101', 'CS102');