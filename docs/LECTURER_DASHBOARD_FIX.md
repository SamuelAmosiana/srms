# Lecturer Dashboard Fix - Course Assignment Table

## Problem
The lecturer dashboard was throwing a fatal error:
```
Fatal error: Uncaught PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 
Table 'lscrms.course_assignment' doesn't exist
```

## Root Cause
The database schema was missing the `course_assignment` table which is needed to:
- Assign lecturers to courses they teach
- Track which courses a lecturer is responsible for
- Enable lecturer-specific dashboard statistics

## Solution Implemented

### 1. Created course_assignment Table
A new table was created with the following structure:
- `id` - Primary key
- `course_id` - Foreign key to course table
- `lecturer_id` - Foreign key to users table (lecturer's user_id)
- `academic_year` - Academic year (e.g., '2024')
- `semester` - Semester ('1', '2', or 'Summer')
- `assigned_at` - Timestamp of assignment
- `is_active` - Status flag (1 = active, 0 = inactive)

### 2. Fixed Dashboard Queries
Updated the lecturer dashboard to use correct table names:
- Fixed course count query to use `course_assignment` table
- Fixed student count query to join `course_enrollment` with `course_assignment`
- Fixed pending results query to properly join related tables

### 3. Added Sample Data
Inserted test data:
- 3 sample programmes
- 3 sample schools
- 3 sample departments
- 5 sample courses (CS101, CS102, CS201, CS202, BIT301)
- Course assignments for the test lecturer (lecturer1@lsc.ac.zm)
- Sample student enrollments

## Files Modified/Created

1. **Created:** `sql/add_course_assignment_table.sql`
   - SQL script to create the missing table
   - Includes sample data for testing

2. **Modified:** `lecturer/dashboard.php`
   - Fixed queries to use correct table names
   - Updated to match existing schema structure

3. **Modified:** `sql/lscrms_schema.sql`
   - Added course_assignment table definition
   - Future database setups will include this table

4. **Created:** `test_lecturer_queries.php`
   - Test script to verify all queries work correctly
   - Helps debug any future issues

## How to Test

1. **Test the queries directly:**
   Visit: http://localhost/srms/test_lecturer_queries.php

2. **Login as lecturer:**
   - URL: http://localhost/srms/login.php
   - Username: lecturer1@lsc.ac.zm
   - Password: Lecturer@123

3. **Access lecturer dashboard:**
   After login, you should see:
   - Total Courses: 3
   - Enrolled Students: (based on enrollments)
   - Pending Results: (based on results data)

## Database Schema Changes

The complete table structure:
```sql
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
```

## Next Steps

To assign more courses to lecturers, use this SQL pattern:
```sql
INSERT INTO course_assignment (course_id, lecturer_id, academic_year, semester) 
VALUES (course_id, lecturer_user_id, '2024', '1');
```

Or through the admin interface (when implemented), admins can:
1. Navigate to course management
2. Assign lecturers to courses
3. Set academic year and semester
