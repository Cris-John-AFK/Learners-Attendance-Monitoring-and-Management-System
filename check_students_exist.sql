-- First, check what columns actually exist
SHOW COLUMNS FROM student_details;

-- Check if students exist with section "Kinder Juan"
SELECT *
FROM student_details
WHERE section = 'Kinder Juan'
LIMIT 5;

-- Check all sections that have students
SELECT DISTINCT 
    section,
    COUNT(*) as student_count
FROM student_details
GROUP BY section
ORDER BY section;

-- Check all homeroom teacher assignments (who should be primary)
SELECT 
    s.id as section_id,
    s.name as section_name,
    s.homeroom_teacher_id,
    t.first_name,
    t.last_name,
    tss.is_primary,
    tss.role
FROM sections s
LEFT JOIN teachers t ON s.homeroom_teacher_id = t.id
LEFT JOIN teacher_section_subject tss ON tss.teacher_id = s.homeroom_teacher_id AND tss.section_id = s.id
WHERE s.homeroom_teacher_id IS NOT NULL;
