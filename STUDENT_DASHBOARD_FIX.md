# Student Dashboard Fix - Column Name Correction

## Problem
The student dashboard was throwing a fatal error when students tried to login:
```
Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 
Unknown column 'sp.student_id' in 'field list' in C:\xampp\htdocs\srms\student\dashboard.php:15
```

## Root Cause
The student dashboard code was using incorrect column names and table names that don't exist in the database schema:

### Column Name Mismatch:
- **Dashboard code used:** `sp.student_id`
- **Actual column name:** `sp.student_number`

### Non-existent Tables Referenced:
1. `course_registration` - Should be `course_enrollment`
2. `student_results` - Results are stored in the `results` table
3. `student_fees` - Fee balance is in `student_profile.balance`
4. `accommodation_applications` - Table doesn't exist yet (set to default)

## Solution Implemented

### 1. Fixed Column Names
Updated the student profile query to use correct column:
```php
// OLD (incorrect)
SELECT sp.full_name, sp.student_id FROM student_profile sp

// NEW (correct)
SELECT sp.full_name, sp.student_number, sp.balance FROM student_profile sp
```

### 2. Fixed Enrolled Courses Query
Updated to use existing `course_enrollment` table:
```php
// OLD (incorrect)
SELECT COUNT(*) FROM course_registration WHERE student_id = ?

// NEW (correct)
SELECT COUNT(*) FROM course_enrollment WHERE student_user_id = ? AND status = 'enrolled'
```

### 3. Implemented GPA Calculation
Created proper GPA calculation using the existing `results` table:
```php
SELECT AVG(
    CASE 
        WHEN r.grade = 'A' THEN 4.0
        WHEN r.grade = 'B+' THEN 3.5
        WHEN r.grade = 'B' THEN 3.0
        WHEN r.grade = 'C+' THEN 2.5
        WHEN r.grade = 'C' THEN 2.0
        WHEN r.grade = 'D' THEN 1.0
        ELSE 0
    END
) as gpa 
FROM results r
JOIN course_enrollment ce ON r.enrollment_id = ce.id
WHERE ce.student_user_id = ? AND r.grade IS NOT NULL
```

### 4. Fixed Fee Balance
Used the `balance` column directly from `student_profile` table:
```php
// Get from student profile (already fetched)
$stats['fee_balance'] = $student['balance'] ?? 0;
```

### 5. Set Default Accommodation Status
Since the accommodation table doesn't exist yet:
```php
$stats['accommodation_status'] = 'Not Applied';
```

## Files Modified/Created

1. **Modified:** `student/dashboard.php`
   - Fixed column name from `student_id` to `student_number`
   - Updated all queries to use correct table names
   - Implemented proper GPA calculation
   - Fixed fee balance retrieval

2. **Created:** `test_student_queries.php`
   - Test script to verify all student queries work correctly
   - Helps debug any future issues

3. **Created:** `STUDENT_DASHBOARD_FIX.md` (this file)
   - Complete documentation of the fix

## Database Schema Reference

### student_profile Table Structure:
```sql
CREATE TABLE student_profile (
  user_id INT PRIMARY KEY,
  full_name VARCHAR(150),
  student_number VARCHAR(20) UNIQUE,    -- NOT student_id!
  NRC VARCHAR(50),
  gender ENUM('Male','Female','Other'),
  programme_id INT,
  school_id INT,
  department_id INT,
  balance DECIMAL(10,2) DEFAULT 0.00,   -- Fee balance
  intake_id INT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### course_enrollment Table Structure:
```sql
CREATE TABLE course_enrollment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT NOT NULL,         -- Links to users.id
  course_id INT NOT NULL,
  academic_year VARCHAR(20),
  semester ENUM('1','2','Summer') DEFAULT '1',
  status ENUM('enrolled','pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE
);
```

### results Table Structure:
```sql
CREATE TABLE results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT NOT NULL,
  ca_score DECIMAL(5,2) DEFAULT 0,
  exam_score DECIMAL(5,2) DEFAULT 0,
  total_score DECIMAL(5,2) GENERATED ALWAYS AS (ca_score + exam_score) STORED,
  grade VARCHAR(4),                      -- A, B+, B, C+, C, D, F
  uploaded_by_user_id INT,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (enrollment_id) REFERENCES course_enrollment(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

## How to Test

### Method 1: Test Queries Directly
Visit: `http://localhost/srms/test_student_queries.php`

This will show:
- Student profile information
- Enrolled courses count
- Current GPA
- Fee balance
- List of course enrollments

### Method 2: Login as Student
1. **URL:** `http://localhost/srms/student_login.php` or `http://localhost/srms/login.php`
2. **Username:** `LSC000001`
3. **Password:** `LSC000001`
4. You should now see the dashboard with:
   - Student name: Alice Student
   - Student number: LSC000001
   - Enrolled courses: (based on enrollments)
   - Current GPA: (calculated from results)
   - Fee balance: K500.00

## Dashboard Statistics Explained

1. **Enrolled Courses**
   - Counts all courses where student has status = 'enrolled'
   - Source: `course_enrollment` table

2. **Current GPA**
   - Calculated from grades in `results` table
   - Grade scale: A=4.0, B+=3.5, B=3.0, C+=2.5, C=2.0, D=1.0, F=0
   - Only includes courses with grades (not NULL)

3. **Fee Balance**
   - Retrieved directly from `student_profile.balance`
   - Default: K500.00 for test student

4. **Accommodation Status**
   - Currently set to "Not Applied" (placeholder)
   - Will need `accommodation_applications` table for full functionality

## Future Enhancements Needed

To make the student dashboard fully functional, you may want to add:

1. **Accommodation Applications Table:**
```sql
CREATE TABLE accommodation_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    room_number VARCHAR(20),
    block VARCHAR(50),
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

2. **Financial Transactions History:**
   - The `finance_transactions` table exists but may need enhancement
   - Link to student payments and fee structure

3. **Grade System Configuration:**
   - Create a table to define grade boundaries and points
   - Allow admins to configure grading scales

## Key Differences: Staff vs Student Schema

### Staff Profile:
- Uses `staff_id` (correct for staff)
- Example: LEC001, FIN001, ADM001

### Student Profile:
- Uses `student_number` (NOT student_id)
- Example: LSC000001

### Reference Keys:
- Both link to `users` table via `user_id`
- Course enrollments use `student_user_id` (references users.id, NOT student_number)

## Testing Checklist

- ✅ Student can login
- ✅ Dashboard displays without errors
- ✅ Student name displays correctly
- ✅ Student number displays correctly
- ✅ Enrolled courses count works
- ✅ GPA calculation works
- ✅ Fee balance displays correctly
- ✅ Accommodation status shows default

All issues have been resolved! The student dashboard should now work properly.
