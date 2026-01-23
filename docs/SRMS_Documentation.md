# Student Records Management System (SRMS) Documentation

## Cover Page

**Project Title:** Student Records Management System (SRMS)

**Institution / Organization:** LSC (Learning Systems College)

**Developed By:** SRMS Development Team

**Version:** 1.0

**Date:** January 2026

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [System Overview](#2-system-overview)
3. [System Architecture](#3-system-architecture)
4. [Functional Requirements](#4-functional-requirements)
5. [Non-Functional Requirements](#5-non-functional-requirements)
6. [User Roles and Permissions](#6-user-roles-and-permissions)
7. [Database Design](#7-database-design)
8. [File Storage Structure](#8-file-storage-structure)
9. [Security Design](#9-security-design)
10. [Deployment Process](#10-deployment-process)
11. [Backup & Recovery Strategy](#11-backup--recovery-strategy)
12. [Error Handling & Logging](#12-error-handling--logging)
13. [Testing](#13-testing)
14. [Known Issues](#14-known-issues)
15. [Maintenance Plan](#15-maintenance-plan)
16. [Future Improvements](#16-future-improvements)
17. [Appendix](#17-appendix)

---

## 1. Introduction

### 1.1 Purpose of the System
The Student Records Management System (SRMS) is designed to manage all aspects of student information, academic records, course registrations, and administrative operations for educational institutions. The system automates various manual processes, improves data accuracy, enables secure document handling, and streamlines enrollment processing.

### 1.2 Scope
The system covers:
- Student registration and account management
- Course registration and management
- Academic records and results management
- Document handling and file downloads
- User role management and permissions
- Financial records and fee management
- Human resource management
- Administrative reporting and analytics

The system does NOT cover:
- External payment gateway integration (currently uses manual verification)
- Mobile application (web-based only)
- Advanced scheduling algorithms

### 1.3 Definitions & Acronyms
- SRMS – Student Records Management System
- API – Application Programming Interface
- LSC – Learning Systems College
- CRUD – Create, Read, Update, Delete
- PDO – PHP Data Objects
- VPS – Virtual Private Server
- FPDF – Free PDF library for PHP

---

## 2. System Overview

### 2.1 System Description
The SRMS is a web-based application built with PHP and MySQL that provides a centralized platform for managing student-related operations. The system follows a modular architecture with separate sections for administration, academics, enrollment, finance, human resources, and student portals.

### 2.2 Objectives
- Automate student records management
- Improve data accuracy and consistency
- Enable secure document handling
- Improve enrollment processing speed
- Streamline course registration processes
- Facilitate financial tracking and reporting
- Provide role-based access control
- Enable comprehensive reporting and analytics

---

## 3. System Architecture

### 3.1 Architecture Diagram
```
Client Browser → Web Server (Apache) → PHP Backend → MySQL Database → File Storage
```

### 3.2 Technologies Used

| Layer | Technology |
|-------|------------|
| Frontend | HTML, CSS, JavaScript, Font Awesome |
| Backend | PHP (Vanilla), PDO |
| Database | MySQL / MariaDB |
| Server | Apache |
| Version Control | Git |
| Security | SSL, Session Management, Password Hashing |
| File Generation | FPDF, DomPDF, PHPMailer |

---

## 4. Functional Requirements

### 4.1 User Authentication
- User login/logout functionality
- Role-based access control
- Password reset capability
- Session management

### 4.2 Student Management
- Student profile creation and management
- Student number generation
- Registration approval workflows
- Course registration management

### 4.3 Course Management
- Course definition and management
- Course registration approval/rejection
- Course assignment per intake and term
- Course availability tracking

### 4.4 Academic Management
- Results entry and management
- Results publishing
- Student performance tracking
- Academic reporting

### 4.5 Document Management
- File upload/download functionality
- Document verification and approval
- Secure file storage
- Download authorization

### 4.6 Financial Management
- Fee structure management
- Payment tracking
- Financial reporting
- Student balance tracking

### 4.7 Administrative Functions
- User management
- Role assignment
- System configuration
- Reporting and analytics

---

## 5. Non-Functional Requirements

### 5.1 Security
- All passwords must be hashed using PHP's password_hash()
- Session-based authentication with timeout
- Input validation and sanitization
- File upload restrictions and virus scanning

### 5.2 Performance
- System should handle 100+ concurrent users
- Page load times under 3 seconds
- Database queries optimized for performance

### 5.3 Availability
- 99.5% uptime during operational hours
- Regular backup and recovery procedures
- Monitoring and alerting for system health

### 5.4 Maintainability
- Well-documented code with comments
- Modular design for easy updates
- Consistent coding standards

---

## 6. User Roles and Permissions

| Role | Permissions |
|------|-------------|
| Super Admin | Full system control, all modules, user management |
| Admin | Core administrative functions, course management, results |
| Enrollment Officer | Application management, document downloads, approvals |
| Finance Officer | Fee management, payment tracking, financial reports |
| HR Officer | Employee management, payroll, HR reports |
| Lecturer | Results entry, student management, report viewing |
| Student | Profile management, course registration, results viewing |

---

## 7. Database Design

### 7.1 ER Diagram
```
users (id, username, password_hash, email, is_active)
├── user_roles (user_id, role_id)
├── admin_profile (user_id, full_name, staff_id)
├── student_profile (user_id, full_name, student_number, programme_id, intake_id)
└── employee_profile (user_id, full_name, employee_number, department_id)

roles (id, name, description)

programme (id, name, duration, degree_level)
├── course (id, name, code, credits, programme_id)
└── intake (id, name, start_date, end_date)

course_registration (id, student_id, course_id, term, status)
intake_courses (id, intake_id, term, course_id, programme_id)
results (id, student_id, course_id, marks, grade, semester)

documents (id, student_id, document_type, file_path, status, uploaded_at)
applications (id, student_id, programme_id, status, submitted_at)

fee_types (id, name, description)
programme_fees (id, programme_id, fee_type_id, amount)
payments (id, student_id, amount, payment_method, transaction_id, paid_at)
```

### 7.2 Key Tables

| Table | Purpose |
|-------|---------|
| users | System users with authentication credentials |
| roles | User role definitions |
| user_roles | Junction table linking users to roles |
| student_profile | Student personal and academic information |
| course | Course definitions |
| course_registration | Student course registration records |
| intake_courses | Required courses per intake and term |
| results | Academic results and grades |
| documents | Student document uploads |
| applications | Student applications |

---

## 8. File Storage Structure

```
/srms_root/
├── uploads/
│   ├── documents/
│   │   ├── students/
│   │   ├── applications/
│   │   └── other/
│   ├── results/
│   └── profiles/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── admin/
├── academics/
├── enrollment/
├── finance/
├── lecturer/
├── student/
├── api/
├── includes/
├── config/
└── logs/
```

### 8.1 Naming Convention
Files are stored with the following format:
- `timestamp_userId_fileType_uniqueId.ext`
- Example: `20260122153045_123_transcript_abc123.pdf`

---

## 9. Security Design

### 9.1 Session Management
- Session timeout after 30 minutes of inactivity
- Regenerate session IDs after login
- Secure session cookie settings

### 9.2 Input Validation
- All user inputs are validated and sanitized
- Prepared statements for database queries
- CSRF protection for forms

### 9.3 File Security
- File type validation (whitelist approach)
- File size limitations
- Secure file upload directory with restricted access
- Virus scanning for uploaded files

### 9.4 Access Control
- Role-based access control (RBAC)
- Permission checks for all sensitive operations
- Secure download mechanisms with authentication

---

## 10. Deployment Process

### 10.1 Pre-deployment Checklist
- [ ] Backup current production database
- [ ] Test all critical functionalities
- [ ] Verify file permissions
- [ ] Update configuration files

### 10.2 Deployment Steps
1. Pull latest code from version control
2. Update database schema if needed
3. Set appropriate file permissions
4. Configure web server (Apache virtual host)
5. Update configuration files (database credentials, etc.)
6. Clear any cache files
7. Test all major functionality
8. Monitor system for 24 hours post-deployment

### 10.3 Post-deployment Verification
- [ ] User login functionality
- [ ] Database connectivity
- [ ] File upload/download
- [ ] Email notifications
- [ ] Report generation

---

## 11. Backup & Recovery Strategy

### 11.1 Backup Schedule
- Daily database backups at 2 AM
- Weekly full system backups on Sundays
- Monthly archive of all backups

### 11.2 Backup Locations
- Primary: Local server backup directory
- Secondary: Remote cloud storage
- Tertiary: Physical media rotation

### 11.3 Recovery Procedure
1. Assess the extent of data loss
2. Identify the most recent clean backup
3. Restore database from backup
4. Restore file system from backup
5. Verify system functionality
6. Notify stakeholders of recovery

---

## 12. Error Handling & Logging

### 12.1 Error Types
- Database connection errors
- Authentication failures
- File operation errors
- Business logic violations

### 12.2 Logging Strategy
- Error logs stored in `/logs/` directory
- Different log levels (INFO, WARNING, ERROR)
- Automatic log rotation to prevent disk space issues
- Sensitive information masked in logs

### 12.3 Debug vs Production
- Debug mode: Detailed error messages and stack traces
- Production mode: Generic error messages, detailed logging

---

## 13. Testing

### 13.1 Unit Tests
- Individual function testing
- Database operation validation
- Authentication flow testing

### 13.2 Integration Tests
- End-to-end user workflows
- Cross-module functionality
- API endpoint testing

### 13.3 User Acceptance Tests
- Role-based functionality testing
- Real-world scenario validation
- Performance under load

---

## 14. Known Issues

### 14.1 Current Limitations
- Mobile responsiveness improvements needed
- Some forms lack comprehensive validation
- File upload size limits may be restrictive
- Email notification system requires configuration

### 14.2 Planned Fixes
- Improved UI/UX for mobile devices
- Enhanced form validation
- Configurable file upload limits
- Automated email system configuration

---

## 15. Maintenance Plan

### 15.1 Code Updates
- Monthly security patches
- Quarterly feature updates
- Annual major version upgrades

### 15.2 Monitoring Activities
- Daily log review
- Weekly performance metrics
- Monthly security audits
- Quarterly system health checks

### 15.3 Database Optimization
- Monthly index maintenance
- Quarterly data archival
- Ongoing query optimization

---

## 16. Future Improvements

### 16.1 Short-term Goals (6 months)
- Mobile-responsive UI improvements
- Enhanced reporting capabilities
- API development for external integrations
- Improved search functionality

### 16.2 Long-term Goals (1 year)
- RESTful API implementation
- Mobile application development
- Advanced analytics dashboard
- Cloud deployment migration
- Automated workflow improvements

---

## 17. Appendix

### 17.1 Sample Configuration File (config.php)
```php
<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'lscrms');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');

// Site configuration
define('SITE_URL', 'http://localhost/srms');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB

// Email configuration
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
?>
```

### 17.2 Sample Database Migration
```sql
-- Add bio column to student_profile table
ALTER TABLE student_profile ADD COLUMN bio TEXT;

-- Create permissions tables
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
);

CREATE TABLE role_permissions (
    role_id INT,
    permission_id INT,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id),
    PRIMARY KEY (role_id, permission_id)
);
```

### 17.3 Common SQL Queries

**Get all pending course registrations:**
```sql
SELECT cr.*, sp.full_name, sp.student_number, c.name as course_name
FROM course_registration cr
JOIN student_profile sp ON cr.student_id = sp.user_id
JOIN course c ON cr.course_id = c.id
WHERE cr.status = 'pending';
```

**Count students by programme:**
```sql
SELECT p.name as programme_name, COUNT(sp.id) as student_count
FROM programme p
LEFT JOIN student_profile sp ON p.id = sp.programme_id
GROUP BY p.id, p.name;
```