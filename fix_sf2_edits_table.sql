-- Fix SF2 Attendance Edits Table
-- Run this in your MySQL/PostgreSQL database

-- Drop table if exists (to recreate fresh)
DROP TABLE IF EXISTS sf2_attendance_edits;

-- Create sf2_attendance_edits table
CREATE TABLE sf2_attendance_edits (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL,
    section_id BIGINT NOT NULL,
    date DATE NOT NULL,
    month VARCHAR(7) NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_id, date, section_id, month)
);

-- Create indexes for performance
CREATE INDEX idx_sf2_edits_student ON sf2_attendance_edits(student_id);
CREATE INDEX idx_sf2_edits_section ON sf2_attendance_edits(section_id);
CREATE INDEX idx_sf2_edits_date ON sf2_attendance_edits(date);
CREATE INDEX idx_sf2_edits_month ON sf2_attendance_edits(month);

-- Verify table was created
SELECT 'SF2 Attendance Edits table created successfully!' as status;
