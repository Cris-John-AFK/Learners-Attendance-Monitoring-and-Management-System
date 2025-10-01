-- Check if student_status_history table exists
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = 'public' 
  AND table_name = 'student_status_history';

-- Check if the new columns exist in student_details
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'student_details' 
  AND column_name IN ('enrollment_status', 'dropout_reason', 'dropout_reason_category', 'status_effective_date');
