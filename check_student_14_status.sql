-- Check the CURRENT status of student 14 in student_details
SELECT 
    id,
    name,
    enrollment_status,
    dropout_reason,
    dropout_reason_category,
    status_effective_date
FROM student_details
WHERE id = 14;
