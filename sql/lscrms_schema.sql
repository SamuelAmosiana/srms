CREATE DATABASE IF NOT EXISTS lscrms;
USE lscrms;
-- Roles & permissions (flexible RBAC)
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Users core (shared across Admin/Staff/Student)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,   -- email or staff/admin username
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(150),
  contact VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) DEFAULT 1
);

-- link users <-> roles (many-to-many; allows multi-role)
CREATE TABLE user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Profiles: admin, staff, student (stores identity info, NRC, gender etc.)
CREATE TABLE admin_profile (
  user_id INT PRIMARY KEY,
  full_name VARCHAR(150),
  staff_id VARCHAR(50) UNIQUE,
  bio TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE staff_profile (
  user_id INT PRIMARY KEY,
  full_name VARCHAR(150),
  staff_id VARCHAR(50) UNIQUE,
  NRC VARCHAR(50),
  gender ENUM('Male','Female','Other'),
  qualification VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE student_profile (
  user_id INT PRIMARY KEY,
  full_name VARCHAR(150),
  student_number VARCHAR(20) UNIQUE,   -- like LSC000001
  NRC VARCHAR(50),
  gender ENUM('Male','Female','Other'),
  programme_id INT,
  school_id INT,
  department_id INT,
  balance DECIMAL(10,2) DEFAULT 0.00,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Programmes, courses, school, department, course enrolments and results
CREATE TABLE programme (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL
);

CREATE TABLE school (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL
);

CREATE TABLE department (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  school_id INT,
  FOREIGN KEY (school_id) REFERENCES school(id) ON DELETE SET NULL
);

CREATE TABLE course (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  credits INT DEFAULT 3,
  programme_id INT,
  department_id INT,
  FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES department(id) ON DELETE SET NULL
);

-- Course assignment table for lecturer-course assignments
CREATE TABLE course_assignment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  lecturer_id INT NOT NULL,
  academic_year VARCHAR(20) DEFAULT '2024',
  semester ENUM('1','2','Summer') DEFAULT '1',
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
  FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_assignment (course_id, lecturer_id, academic_year, semester)
);

CREATE TABLE course_enrollment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT NOT NULL,
  course_id INT NOT NULL,
  academic_year VARCHAR(20),
  semester ENUM('1','2','Summer') DEFAULT '1',
  status ENUM('enrolled','pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE
);

CREATE TABLE results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT NOT NULL,
  ca_score DECIMAL(5,2) DEFAULT 0,
  exam_score DECIMAL(5,2) DEFAULT 0,
  total_score DECIMAL(5,2) GENERATED ALWAYS AS (ca_score + exam_score) STORED,
  grade VARCHAR(4),
  uploaded_by_user_id INT,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (enrollment_id) REFERENCES course_enrollment(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Simple finance table for sub-admin(finance)
CREATE TABLE finance_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT,
  type ENUM('income','expense') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================================
-- ROLES (if not already seeded)
-- ===================================
INSERT INTO roles (name, description) VALUES
('Super Admin','Full system admin'),
('Lecturer','Course lecturer'),
('Sub Admin (Finance)','Finance officer'),
('Student','Student');

-- ===================================
-- USERS
-- ===================================
-- Passwords (plain for reference):
-- Super Admin: Admin@123
-- Lecturer: Lecturer@123
-- Sub Admin: Finance@123
-- Student: LSC000001 (same as student number)

-- Super Admin
INSERT INTO users (username, password_hash, email, contact) VALUES
('admin@lsc.ac.zm', '$2y$10$Vx0W0cKqk1sF2oS6M6S4cOa6P0tB1aYhQXo1H6r9CqYrY6gP8V1yK', 'admin@lsc.ac.zm', '0971234567');

-- Lecturer
INSERT INTO users (username, password_hash, email, contact) VALUES
('lecturer1@lsc.ac.zm', '$2y$10$G6Yk8P9aZx2sB1eV6P7Q2dE8sF6L9oA0tB1aYhQXo1H6r9CqYrY6g', 'lecturer1@lsc.ac.zm', '0972345678');

-- Sub Admin (Finance)
INSERT INTO users (username, password_hash, email, contact) VALUES
('finance@lsc.ac.zm', '$2y$10$Q7Lk9H0bXy3sC2fV7P8R3fF9sG7M0pB1bZ2aZhRYp2H7s0DqJsK2', 'finance@lsc.ac.zm', '0973456789');

-- Student
INSERT INTO users (username, password_hash, email, contact) VALUES
('LSC000001', '$2y$10$A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0UVWXYZ123456', 'student1@lsc.ac.zm', '0974567890');

-- ===================================
-- USER ROLES
-- ===================================
-- Get role ids dynamically if needed, else assume 1-4
INSERT INTO user_roles (user_id, role_id) VALUES
((SELECT id FROM users WHERE username='admin@lsc.ac.zm'), (SELECT id FROM roles WHERE name='Super Admin')),
((SELECT id FROM users WHERE username='lecturer1@lsc.ac.zm'), (SELECT id FROM roles WHERE name='Lecturer')),
((SELECT id FROM users WHERE username='finance@lsc.ac.zm'), (SELECT id FROM roles WHERE name='Sub Admin (Finance)')),
((SELECT id FROM users WHERE username='LSC000001'), (SELECT id FROM roles WHERE name='Student'));

-- ===================================
-- PROFILES
-- ===================================
-- Super Admin
INSERT INTO admin_profile (user_id, full_name, staff_id, bio) VALUES
((SELECT id FROM users WHERE username='admin@lsc.ac.zm'), 'John Admin', 'ADM001', NULL);

-- Lecturer
INSERT INTO staff_profile (user_id, full_name, staff_id, NRC, gender, qualification) VALUES
((SELECT id FROM users WHERE username='lecturer1@lsc.ac.zm'), 'Mary Lecturer', 'LEC001', '12345678', 'Female', 'B.Ed. Computing');

-- Finance
INSERT INTO staff_profile (user_id, full_name, staff_id, NRC, gender, qualification) VALUES
((SELECT id FROM users WHERE username='finance@lsc.ac.zm'), 'Peter Finance', 'FIN001', '87654321', 'Male', 'B.Com Finance');

-- Student
INSERT INTO student_profile (user_id, full_name, student_number, NRC, gender, programme_id, school_id, department_id, balance) VALUES
((SELECT id FROM users WHERE username='LSC000001'), 'Alice Student', 'LSC000001', '11223344', 'Female', NULL, NULL, NULL, 500);
