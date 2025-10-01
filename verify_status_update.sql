-- Check if the student status was updated in student_details
SELECT 
    id,
    name,
    section,
    enrollment_status,
    dropout_reason,
    dropout_reason_category,
    status_effective_date
FROM student_details
WHERE id = 14;

-- Check if a history record was created
SELECT 
    id,
    student_id,
    previous_status,
    new_status,
    reason,
    reason_category,
    effective_date,
    changed_by_teacher_id,
    notes,
    created_at
FROM student_status_history
ORDER BY created_at DESC
LIMIT 5;
