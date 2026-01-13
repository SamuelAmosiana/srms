-- Add missing student_number column to pending_students table
ALTER TABLE pending_students 
ADD COLUMN IF NOT EXISTS student_number VARCHAR(50) AFTER id;