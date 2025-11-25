-- Add missing columns to pending_students table
ALTER TABLE pending_students 
ADD COLUMN IF NOT EXISTS student_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS rejection_reason TEXT;

-- Make sure registration_status column exists with proper enum values
ALTER TABLE pending_students 
MODIFY COLUMN registration_status ENUM('pending','pending_approval','approved','rejected') DEFAULT 'pending';

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_pending_students_status ON pending_students(registration_status);
CREATE INDEX IF NOT EXISTS idx_pending_students_email ON pending_students(email);
CREATE INDEX IF NOT EXISTS idx_pending_students_student_number ON pending_students(student_number);