-- Check attendance records with reasons
SELECT 
    ar.id,
    ar.attendance_session_id,
    sd.student_id,
    CONCAT(sd."firstName", ' ', sd."lastName") as student_name,
    ast.name as status,
    ar.reason_id,
    ar.reason_notes,
    atr.reason_name,
    ar.marked_at
FROM attendance_records ar
LEFT JOIN student_details sd ON ar.student_id = sd.id
LEFT JOIN attendance_statuses ast ON ar.attendance_status_id = ast.id
LEFT JOIN attendance_reasons atr ON ar.reason_id = atr.id
WHERE ar.attendance_session_id IN (
    SELECT id 
    FROM attendance_sessions 
    WHERE session_date = CURRENT_DATE 
    ORDER BY created_at DESC 
    LIMIT 5
)
ORDER BY ar.attendance_session_id DESC, student_name;

-- Check if attendance_reasons table has data
SELECT * FROM attendance_reasons ORDER BY id;
