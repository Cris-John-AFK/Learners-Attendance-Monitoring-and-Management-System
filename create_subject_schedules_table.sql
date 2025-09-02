-- PostgreSQL version for pgAdmin
CREATE TABLE IF NOT EXISTS subject_schedules (
    id BIGSERIAL PRIMARY KEY,
    section_id BIGINT NOT NULL,
    subject_id BIGINT NOT NULL,
    teacher_id BIGINT NULL,
    day VARCHAR(20) NOT NULL CHECK (day IN ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')),
    start_time VARCHAR(10) NOT NULL,
    end_time VARCHAR(10) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Add foreign key constraints
ALTER TABLE subject_schedules 
ADD CONSTRAINT fk_subject_schedules_section_id 
FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE;

ALTER TABLE subject_schedules 
ADD CONSTRAINT fk_subject_schedules_subject_id 
FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE;

ALTER TABLE subject_schedules 
ADD CONSTRAINT fk_subject_schedules_teacher_id 
FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL;
