-- Check what section name the teacher is assigned to
SELECT 
    t.first_name,
    t.last_name,
    s.id as section_id,
    s.name as section_name_in_sections_table,
    tss.is_primary
FROM teacher_section_subject tss
JOIN teachers t ON tss.teacher_id = t.id
JOIN sections s ON tss.section_id = s.id
WHERE tss.teacher_id = 1 AND tss.is_primary = true;

-- Check what section names students have
SELECT DISTINCT 
    section as section_name_in_student_details,
    COUNT(*) as student_count
FROM student_details
GROUP BY section
ORDER BY section;

-- Check if there's a match
SELECT 
    'Teacher section' as source,
    s.name as section_name
FROM teacher_section_subject tss
JOIN sections s ON tss.section_id = s.id
WHERE tss.teacher_id = 1 AND tss.is_primary = true
UNION ALL
SELECT 
    'Student sections' as source,
    section as section_name
FROM student_details
WHERE section IN (
    SELECT s.name 
    FROM teacher_section_subject tss
    JOIN sections s ON tss.section_id = s.id
    WHERE tss.teacher_id = 1 AND tss.is_primary = true
);
