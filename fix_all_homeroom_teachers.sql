-- Fix ALL homeroom teachers to have is_primary = 1
-- This sets is_primary based on the role column

-- First, check current homeroom assignments
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
WHERE tss.role = 'primary' OR tss.role = 'homeroom' OR sub.name = 'Homeroom';

-- Update ALL assignments where role is 'primary' or 'homeroom'
-- This is the simple approach that works
UPDATE teacher_section_subject
SET is_primary = true
WHERE role IN ('primary', 'homeroom');

-- Verify the changes
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
WHERE tss.is_primary = true;
