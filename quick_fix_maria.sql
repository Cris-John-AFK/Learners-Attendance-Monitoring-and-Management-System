-- Quick fix for Maria Santos - Set her as primary for Kinder Juan
-- Update all her assignments for Kinder Juan to be primary
UPDATE teacher_section_subject
SET is_primary = true, role = 'primary'
WHERE teacher_id = 1 
  AND section_id = 1;

-- Verify Maria is now primary
SELECT 
    tss.id,
    t.first_name,
    t.last_name,
    s.name as section_name,
    sub.name as subject_name,
    tss.role,
    tss.is_primary
FROM teacher_section_subject tss
LEFT JOIN teachers t ON tss.teacher_id = t.id
LEFT JOIN sections s ON tss.section_id = s.id
LEFT JOIN subjects sub ON tss.subject_id = sub.id
WHERE tss.teacher_id = 1;
