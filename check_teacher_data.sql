-- Check Maria Santos (Teacher ID 1) assignments
SELECT 
    tss.id,
    tss.teacher_id,
    t.first_name,
    t.last_name,
    tss.section_id,
    s.name as section_name,
    tss.subject_id,
    sub.name as subject_name,
    tss.is_primary,
    tss.is_active,
    tss.role
FROM teacher_section_subject tss
LEFT JOIN teachers t ON tss.teacher_id = t.id
LEFT JOIN sections s ON tss.section_id = s.id
LEFT JOIN subjects sub ON tss.subject_id = sub.id
WHERE tss.teacher_id = 1;

-- Check students in sections
SELECT 
    id,
    studentId,
    name,
    firstName,
    lastName,
    section,
    gradeLevel
FROM student_details
WHERE section IN ('Kinder Juan', 'Juan')
LIMIT 10;
