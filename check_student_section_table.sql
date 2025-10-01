-- Check the student_section junction table
SELECT * FROM student_section WHERE section_id = 1 LIMIT 10;

-- Check what's in sections table
SELECT id, name FROM sections WHERE name = 'Kinder Juan';

-- Check student relationships
SELECT 
    ss.student_id,
    ss.section_id,
    s.name as section_name,
    sd.id,
    sd.name,
    sd.section as section_in_details
FROM student_section ss
JOIN sections s ON ss.section_id = s.id
JOIN student_details sd ON ss.student_id = sd.id
WHERE s.name = 'Kinder Juan'
LIMIT 10;
