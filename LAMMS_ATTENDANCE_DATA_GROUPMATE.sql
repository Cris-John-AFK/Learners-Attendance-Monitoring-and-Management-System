-- ========================================
-- LAMMS ATTENDANCE DATA FOR GROUPMATES
-- Generated: October 21, 2025
-- Database: lamms_db
-- ========================================
-- 
-- INSTRUCTIONS FOR YOUR GROUPMATE:
-- 1. Open pgAdmin or psql
-- 2. Connect to your PostgreSQL database
-- 3. Run this entire SQL file
-- 4. This will populate your database with the same data
-- 
-- NOTE: This file contains INSERT statements only.
-- Make sure your database schema is already created.
-- ========================================

-- ========================================
-- STUDENTS DATA (Sample from main database)
-- ========================================

-- Clear existing data (OPTIONAL - only if you want fresh start)
-- TRUNCATE TABLE student_details CASCADE;
-- TRUNCATE TABLE guardhouse_attendance CASCADE;
-- TRUNCATE TABLE attendance_records CASCADE;
-- TRUNCATE TABLE attendance_sessions CASCADE;

-- Insert Students
INSERT INTO student_details (id, studentId, name, firstName, lastName, middleName, extensionName, email, gradeLevel, section, lrn, schoolYearStart, schoolYearEnd, gender, sex, birthdate, birthplace, age, psaBirthCertNo, motherTongue, profilePhoto, currentAddress, permanentAddress, contactInfo, father, mother, parentName, parentContact, status, enrollmentDate, admissionDate, requirements, isIndigenous, indigenousCommunity, is4PsBeneficiary, householdID, hasDisability, disabilities, created_at, updated_at, qr_code, student_id, photo, qr_code_path, address, isActive, is_active, current_status, status_changed_date, enrollment_status, dropout_reason, dropout_reason_category, status_effective_date) 
VALUES 
(10, 'STU2025001001', 'Edwin', 'Juan', 'Dela Cruz', 'Santos', NULL, NULL, 3, 'Malikhain', 123456789012, NULL, NULL, 'Male', 'Male', NULL, NULL, 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'enrolled', '2025-09-08 14:19:23', NULL, NULL, false, NULL, false, NULL, false, NULL, '2025-09-08 14:19:23', '2025-09-09 13:47:20', NULL, 'STU2025001001', NULL, 'qr-codes/123456789012_qr.svg', NULL, true, true, 'active', NULL, 'active', NULL, NULL, NULL),
(20, 'STU2025202515889', 'der Kin', 'der', 'Kin', NULL, NULL, 'dwa@mail.com', 'K', NULL, NULL, NULL, NULL, 'Male', 'Male', '2025-09-06', NULL, NULL, NULL, NULL, '/demo/images/student-photo.jpg', '{"street":"Sitio Pier","barangay":"Malubog","city":"Naawan","province":"Misamis Oriental","country":"Philippines"}', '{"house_no":null,"street":null,"barangay":"awdaw","city_municipality":"awda","province":"awda","country":"Philippines","zip_code":null}', NULL, '{"first_name":null,"last_name":null,"middle_name":null,"contact_number":null}', '{"first_name":null,"last_name":null,"middle_name":null,"contact_number":null}', 'N/A', 'Father: N/A, Mother: N/A', 'Enrolled', '2025-09-25 13:50:21', '2025-09-25 13:50:21', '[]', false, NULL, false, NULL, false, '[]', '2025-09-25 21:50:21', '2025-10-04 22:46:45', NULL, 'STU2025202515889', '/demo/images/student-photo.jpg', NULL, ', awdaw, awda, awda', true, true, 'active', NULL, 'active', NULL, NULL, NULL),
(16, 'STU2025035388012', 'Male Kind One', 'Male', 'Kind One', NULL, NULL, 'ci@mail.com', 'K', NULL, NULL, NULL, NULL, 'Male', 'Male', '2025-09-06', NULL, NULL, NULL, NULL, '/demo/images/student-photo.jpg', '{"street":"Purok Fishport","barangay":"Malubog","city":"Naawan","province":"Misamis Oriental","country":"Philippines"}', '{"house_no":null,"street":null,"barangay":"awda","city_municipality":"awdawd","province":"wda","country":"Philippines","zip_code":null}', NULL, '{"first_name":null,"last_name":null,"middle_name":null,"contact_number":null}', '{"first_name":null,"last_name":null,"middle_name":null,"contact_number":null}', 'N/A', 'Father: N/A, Mother: N/A', 'Enrolled', '2025-09-17 14:08:19', '2025-09-17 14:08:19', '[]', false, NULL, false, NULL, false, '[]', '2025-09-17 22:08:19', '2025-10-04 22:46:45', NULL, 'STU2025035388012', '/demo/images/student-photo.jpg', NULL, ', awda, awdawd, wda', true, true, 'active', NULL, 'active', 'b3', 'individual', '2025-09-30'),
(25, 'NCS-2025-00001', 'Michelle M. Garcia', 'Michelle', 'Garcia', 'M', NULL, NULL, 'Kinder', 'Sampaguita', 176368434917, 2024, 2025, 'Female', 'Female', '2015-10-02', 'Naawan, Misamis Oriental', 10, NULL, 'Cebuano', NULL, '{"street":"Sitio Central","barangay":"Mapulog","city":"Naawan","province":"Misamis Oriental","country":"Philippines"}', '{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}', NULL, NULL, NULL, 'Alma Garcia', '+63 9156497609', 'Enrolled', '2024-06-01 00:00:00', '2024-06-01 00:00:00', NULL, false, NULL, false, NULL, false, NULL, '2025-10-02 10:32:49', '2025-10-04 22:46:45', NULL, 'NCS-2025-00001', NULL, NULL, NULL, true, true, 'active', NULL, 'active', NULL, NULL, NULL),
(26, 'NCS-2025-00002', 'Isabella L. Rivera', 'Isabella', 'Rivera', 'L', NULL, NULL, 'Kinder', 'Sampaguita', 154689511695, 2024, 2025, 'Female', 'Female', '2013-10-02', 'Naawan, Misamis Oriental', 12, NULL, 'Cebuano', NULL, '{"street":"Sitio Plateau","barangay":"Kapatagan","city":"Naawan","province":"Misamis Oriental","country":"Philippines"}', '{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}', NULL, NULL, NULL, 'Roberto Rivera', '+63 9638765527', 'Enrolled', '2024-06-01 00:00:00', '2024-06-01 00:00:00', NULL, false, NULL, false, NULL, false, NULL, '2025-10-02 10:32:49', '2025-10-04 22:46:45', NULL, 'NCS-2025-00002', NULL, NULL, NULL, true, true, 'active', NULL, 'active', NULL, NULL, NULL)
ON CONFLICT (id) DO NOTHING;

-- ========================================
-- GUARDHOUSE ATTENDANCE DATA
-- ========================================

-- Sample guardhouse attendance records
INSERT INTO guardhouse_attendance (student_id, qr_code_data, record_type, timestamp, date, guard_name, guard_id, is_manual, notes)
VALUES 
(10, 'STU2025001001', 'check-in', '2025-10-21 07:30:00', '2025-10-21', 'Guard Juan', 'G-001', false, 'Regular entry'),
(20, 'STU2025202515889', 'check-in', '2025-10-21 07:35:00', '2025-10-21', 'Guard Juan', 'G-001', false, 'Regular entry'),
(16, 'STU2025035388012', 'check-in', '2025-10-21 07:40:00', '2025-10-21', 'Guard Juan', 'G-001', false, 'Regular entry'),
(25, 'NCS-2025-00001', 'check-in', '2025-10-21 07:45:00', '2025-10-21', 'Guard Juan', 'G-001', false, 'Regular entry'),
(26, 'NCS-2025-00002', 'check-in', '2025-10-21 07:50:00', '2025-10-21', 'Guard Juan', 'G-001', false, 'Regular entry'),
(10, 'STU2025001001', 'check-out', '2025-10-21 15:30:00', '2025-10-21', 'Guard Maria', 'G-002', false, 'Regular exit'),
(20, 'STU2025202515889', 'check-out', '2025-10-21 15:35:00', '2025-10-21', 'Guard Maria', 'G-002', false, 'Regular exit')
ON CONFLICT DO NOTHING;

-- ========================================
-- ATTENDANCE SESSIONS (for teacher attendance)
-- ========================================

-- Sample attendance sessions
INSERT INTO attendance_sessions (teacher_id, section_id, subject_id, session_date, session_time, status, created_at, updated_at)
VALUES 
(1, 1, 1, '2025-10-21', '08:00:00', 'completed', NOW(), NOW()),
(1, 1, 2, '2025-10-21', '09:00:00', 'completed', NOW(), NOW()),
(1, 1, 3, '2025-10-21', '10:00:00', 'active', NOW(), NOW())
ON CONFLICT DO NOTHING;

-- ========================================
-- ATTENDANCE RECORDS (student attendance in class)
-- ========================================

-- Get the attendance status IDs first (assuming they exist)
-- Present = 1, Absent = 2, Late = 3, Excused = 4

-- Sample attendance records for the sessions above
INSERT INTO attendance_records (attendance_session_id, student_id, attendance_status_id, marked_at, marked_by_teacher_id, marking_method, created_at, updated_at)
VALUES 
(1, 10, 1, NOW(), 1, 'manual', NOW(), NOW()),
(1, 20, 1, NOW(), 1, 'manual', NOW(), NOW()),
(1, 16, 2, NOW(), 1, 'manual', NOW(), NOW()),
(2, 10, 1, NOW(), 1, 'qr_scan', NOW(), NOW()),
(2, 20, 3, NOW(), 1, 'qr_scan', NOW(), NOW()),
(2, 16, 1, NOW(), 1, 'qr_scan', NOW(), NOW())
ON CONFLICT DO NOTHING;

-- ========================================
-- VERIFICATION QUERIES
-- ========================================

-- Check if data was inserted successfully
-- Run these queries after importing:

-- SELECT COUNT(*) FROM student_details;
-- SELECT COUNT(*) FROM guardhouse_attendance;
-- SELECT COUNT(*) FROM attendance_sessions;
-- SELECT COUNT(*) FROM attendance_records;

-- View recent guardhouse entries:
-- SELECT ga.*, sd.firstName, sd.lastName 
-- FROM guardhouse_attendance ga
-- JOIN student_details sd ON ga.student_id = sd.id
-- ORDER BY ga.timestamp DESC
-- LIMIT 10;

-- ========================================
-- NOTES FOR YOUR GROUPMATE
-- ========================================

-- 1. Make sure PostgreSQL is running on your machine
-- 2. Make sure the database 'lamms_db' exists
-- 3. Make sure all tables are created (run migrations first)
-- 4. Then run this SQL file to populate with sample data
-- 5. Adjust the IDs if you have conflicts with existing data

-- ========================================
-- END OF SQL FILE
-- ========================================
