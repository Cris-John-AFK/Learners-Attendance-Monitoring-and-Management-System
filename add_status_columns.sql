-- Check if columns exist first
SELECT column_name 
FROM information_schema.columns 
WHERE table_name = 'student_details' 
  AND column_name IN ('enrollment_status', 'dropout_reason', 'dropout_reason_category', 'status_effective_date');

-- Add the missing columns to student_details if they don't exist
-- Run these one at a time if you get errors

-- Add enrollment_status column
ALTER TABLE student_details 
ADD COLUMN IF NOT EXISTS enrollment_status VARCHAR(255) DEFAULT 'active';

-- Add dropout_reason column
ALTER TABLE student_details 
ADD COLUMN IF NOT EXISTS dropout_reason VARCHAR(255);

-- Add dropout_reason_category column
ALTER TABLE student_details 
ADD COLUMN IF NOT EXISTS dropout_reason_category VARCHAR(255);

-- Add status_effective_date column
ALTER TABLE student_details 
ADD COLUMN IF NOT EXISTS status_effective_date DATE;

-- Create student_status_history table
CREATE TABLE IF NOT EXISTS student_status_history (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL,
    previous_status VARCHAR(255) NOT NULL,
    new_status VARCHAR(255) NOT NULL,
    reason VARCHAR(255),
    reason_category VARCHAR(255),
    effective_date DATE NOT NULL,
    changed_by_teacher_id BIGINT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_details(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_student_status_history_student ON student_status_history(student_id, created_at);
CREATE INDEX IF NOT EXISTS idx_student_status_history_teacher ON student_status_history(changed_by_teacher_id);

-- Verify the changes
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'student_details' 
  AND column_name IN ('enrollment_status', 'dropout_reason', 'dropout_reason_category', 'status_effective_date');
