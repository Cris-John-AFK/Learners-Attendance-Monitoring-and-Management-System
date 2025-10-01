-- Fix Maria Santos to be primary teacher for Kinder Juan
-- Set the first assignment (English) as primary/homeroom

UPDATE teacher_section_subject
SET is_primary = 1
WHERE id = 1 
  AND teacher_id = 1 
  AND section_id = 1;

-- Verify the change
SELECT 
    tss.id,
    t.first_name,
    t.last_name,
    s.name as section_name,
    sub.name as subject_name,
    tss.is_primary,
    tss.role
FROM teacher_section_subject tss
LEFT JOIN teachers t ON tss.teacher_id = t.id
LEFT JOIN sections s ON tss.section_id = s.id
LEFT JOIN subjects sub ON tss.subject_id = sub.id
WHERE tss.teacher_id = 1;
