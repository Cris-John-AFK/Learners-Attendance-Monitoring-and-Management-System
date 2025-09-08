# LAMMS Database Schema Enhancement Proposal

## Current Issues
1. Dashboard shows 5 students (mock data) vs 4 students (real data) in attendance
2. No proper connection between subjects and attendance records
3. Missing subject-specific attendance tracking
7 students exist in database but only 4 show in attendance system

## Proposed Schema Structure

### Core Relationships
```
Grade Level → Sections → Students
Grade Level → Curriculum → Subjects
Sections → Subjects (via section_subject)
Teachers → Section-Subject assignments
Students → Attendance per Subject per Session
```

### Required Tables (Enhanced)

#### 1. Enhanced Attendance System
```sql
-- Main attendance sessions (one per subject per day)
CREATE TABLE attendance_sessions (
    id BIGINT PRIMARY KEY,
    teacher_id BIGINT FOREIGN KEY,
    section_id BIGINT FOREIGN KEY,
    subject_id BIGINT FOREIGN KEY,
    session_date DATE,
    session_start_time TIME,
    session_end_time TIME NULL,
    session_type ENUM('regular', 'makeup', 'special'),
    status ENUM('active', 'completed', 'cancelled'),
    metadata JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(section_id, subject_id, session_date)
);

-- Individual student attendance records
CREATE TABLE attendance_records (
    id BIGINT PRIMARY KEY,
    session_id BIGINT FOREIGN KEY REFERENCES attendance_sessions(id),
    student_id BIGINT FOREIGN KEY,
    attendance_status_id BIGINT FOREIGN KEY,
    marked_at TIMESTAMP,
    remarks TEXT NULL,
    marked_by BIGINT FOREIGN KEY REFERENCES teachers(id),
    
    UNIQUE(session_id, student_id)
);
```

#### 2. Enhanced Student-Section Assignment
```sql
-- Your existing student_section table is good, but add:
ALTER TABLE student_section ADD COLUMN enrollment_date DATE DEFAULT CURRENT_DATE;
ALTER TABLE student_section ADD COLUMN status ENUM('enrolled', 'transferred', 'dropped') DEFAULT 'enrolled';
```

#### 3. Subject Schedules Integration
```sql
-- Link schedules to attendance
ALTER TABLE subject_schedules ADD COLUMN attendance_required BOOLEAN DEFAULT true;
ALTER TABLE subject_schedules ADD COLUMN auto_create_sessions BOOLEAN DEFAULT true;
```

## Implementation Plan

### Phase 1: Database Updates
1. Create attendance_sessions table
2. Create attendance_records table  
3. Update existing tables with new columns
4. Create proper indexes for performance

### Phase 2: API Updates
1. Update TeacherAttendanceService to use new schema
2. Create session-based attendance endpoints
3. Update student retrieval to use student_section table

### Phase 3: Frontend Updates
1. Update dashboard to use real student data
2. Modify attendance page to work with sessions
3. Ensure consistent data across all components

## Benefits
- ✅ Proper subject-specific attendance tracking
- ✅ Session-based attendance (can have multiple sessions per day)
- ✅ Consistent student counts across dashboard and attendance
- ✅ Better data integrity with foreign key constraints
- ✅ Audit trail for attendance changes
- ✅ Support for different session types and statuses
