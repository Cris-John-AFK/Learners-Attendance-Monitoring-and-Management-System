--
-- PostgreSQL database dump
--

-- Dumped from database version 17.0
-- Dumped by pg_dump version 17.0

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: archive_old_guardhouse_records(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.archive_old_guardhouse_records() RETURNS integer
    LANGUAGE plpgsql
    AS $$
        DECLARE
            archived_count INTEGER := 0;
            cutoff_date DATE := CURRENT_DATE - INTERVAL '1 day';
        BEGIN
            -- Move records older than 1 day to archive
            INSERT INTO guardhouse_attendance_archive (
                original_id, student_id, qr_code_data, record_type, timestamp, date,
                guard_name, guard_id, is_manual, notes, created_at, updated_at
            )
            SELECT 
                id, student_id, qr_code_data, record_type, timestamp, date,
                guard_name, guard_id, is_manual, notes, created_at, updated_at
            FROM guardhouse_attendance
            WHERE date < cutoff_date;
            
            GET DIAGNOSTICS archived_count = ROW_COUNT;
            
            -- Delete archived records from main table
            DELETE FROM guardhouse_attendance WHERE date < cutoff_date;
            
            -- Update cache for archived dates
            INSERT INTO guardhouse_attendance_cache (cache_date, total_checkins, total_checkouts, records_data)
            SELECT 
                date,
                COUNT(CASE WHEN record_type = 'check-in' THEN 1 END) as total_checkins,
                COUNT(CASE WHEN record_type = 'check-out' THEN 1 END) as total_checkouts,
                jsonb_agg(
                    jsonb_build_object(
                        'id', original_id,
                        'student_id', student_id,
                        'record_type', record_type,
                        'timestamp', timestamp,
                        'guard_name', guard_name
                    )
                ) as records_data
            FROM guardhouse_attendance_archive 
            WHERE date >= CURRENT_DATE - INTERVAL '90 days'
            GROUP BY date
            ON CONFLICT (cache_date) DO UPDATE SET
                total_checkins = EXCLUDED.total_checkins,
                total_checkouts = EXCLUDED.total_checkouts,
                records_data = EXCLUDED.records_data,
                last_updated = CURRENT_TIMESTAMP;
            
            RETURN archived_count;
        END;
        $$;


ALTER FUNCTION public.archive_old_guardhouse_records() OWNER TO postgres;

--
-- Name: cleanup_old_archive_records(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.cleanup_old_archive_records() RETURNS integer
    LANGUAGE plpgsql
    AS $$
        DECLARE
            deleted_count INTEGER := 0;
            cutoff_date DATE := CURRENT_DATE - INTERVAL '90 days';
        BEGIN
            -- Delete archive records older than 90 days
            DELETE FROM guardhouse_attendance_archive WHERE date < cutoff_date;
            GET DIAGNOSTICS deleted_count = ROW_COUNT;
            
            -- Delete cache records older than 90 days
            DELETE FROM guardhouse_attendance_cache WHERE cache_date < cutoff_date;
            
            RETURN deleted_count;
        END;
        $$;


ALTER FUNCTION public.cleanup_old_archive_records() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: admins; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.admins (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    phone_number character varying(255),
    address text,
    date_of_birth date,
    gender character varying(255),
    "position" character varying(255) DEFAULT 'Administrator'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT admins_gender_check CHECK (((gender)::text = ANY ((ARRAY['male'::character varying, 'female'::character varying, 'other'::character varying])::text[])))
);


ALTER TABLE public.admins OWNER TO postgres;

--
-- Name: admins_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.admins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.admins_id_seq OWNER TO postgres;

--
-- Name: admins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.admins_id_seq OWNED BY public.admins.id;


--
-- Name: attendance_audit_log; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_audit_log (
    id bigint NOT NULL,
    entity_type character varying(255) NOT NULL,
    entity_id bigint NOT NULL,
    action character varying(255) NOT NULL,
    performed_by_teacher_id bigint,
    old_values json,
    new_values json,
    reason text,
    ip_address inet,
    user_agent character varying(255),
    context json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT attendance_audit_log_action_check CHECK (((action)::text = ANY ((ARRAY['create'::character varying, 'update'::character varying, 'delete'::character varying, 'complete'::character varying, 'edit'::character varying, 'verify'::character varying])::text[])))
);


ALTER TABLE public.attendance_audit_log OWNER TO postgres;

--
-- Name: attendance_audit_log_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_audit_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_audit_log_id_seq OWNER TO postgres;

--
-- Name: attendance_audit_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_audit_log_id_seq OWNED BY public.attendance_audit_log.id;


--
-- Name: attendance_modifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_modifications (
    id bigint NOT NULL,
    attendance_record_id bigint NOT NULL,
    modified_by_teacher_id bigint NOT NULL,
    old_values json NOT NULL,
    new_values json NOT NULL,
    modification_type character varying(255) DEFAULT 'status_change'::character varying NOT NULL,
    reason text NOT NULL,
    authorized_by_teacher_id bigint,
    authorized_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT attendance_modifications_modification_type_check CHECK (((modification_type)::text = ANY ((ARRAY['status_change'::character varying, 'time_correction'::character varying, 'remarks_update'::character varying, 'verification'::character varying])::text[])))
);


ALTER TABLE public.attendance_modifications OWNER TO postgres;

--
-- Name: attendance_modifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_modifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_modifications_id_seq OWNER TO postgres;

--
-- Name: attendance_modifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_modifications_id_seq OWNED BY public.attendance_modifications.id;


--
-- Name: attendance_policies; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_policies (
    id bigint NOT NULL,
    policy_name character varying(255) NOT NULL,
    scope character varying(255) DEFAULT 'school_wide'::character varying NOT NULL,
    scope_id bigint,
    late_threshold_minutes integer DEFAULT 15 NOT NULL,
    absent_threshold_minutes integer DEFAULT 30 NOT NULL,
    allow_teacher_override boolean DEFAULT true NOT NULL,
    require_verification boolean DEFAULT false NOT NULL,
    allowed_statuses json NOT NULL,
    effective_from date NOT NULL,
    effective_until date,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT attendance_policies_scope_check CHECK (((scope)::text = ANY ((ARRAY['school_wide'::character varying, 'grade_level'::character varying, 'section'::character varying, 'subject'::character varying])::text[])))
);


ALTER TABLE public.attendance_policies OWNER TO postgres;

--
-- Name: attendance_policies_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_policies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_policies_id_seq OWNER TO postgres;

--
-- Name: attendance_policies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_policies_id_seq OWNED BY public.attendance_policies.id;


--
-- Name: attendance_reasons; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_reasons (
    id bigint NOT NULL,
    reason_name character varying(255) NOT NULL,
    reason_type character varying(255) NOT NULL,
    category character varying(255),
    display_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT attendance_reasons_reason_type_check CHECK (((reason_type)::text = ANY ((ARRAY['late'::character varying, 'excused'::character varying])::text[])))
);


ALTER TABLE public.attendance_reasons OWNER TO postgres;

--
-- Name: attendance_reasons_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_reasons_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_reasons_id_seq OWNER TO postgres;

--
-- Name: attendance_reasons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_reasons_id_seq OWNED BY public.attendance_reasons.id;


--
-- Name: attendance_records; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_records (
    id bigint NOT NULL,
    attendance_session_id bigint NOT NULL,
    student_id bigint NOT NULL,
    attendance_status_id bigint NOT NULL,
    marked_by_teacher_id bigint NOT NULL,
    marked_at timestamp(0) without time zone NOT NULL,
    arrival_time time(0) without time zone,
    departure_time time(0) without time zone,
    remarks text,
    marking_method character varying(255) DEFAULT 'manual'::character varying NOT NULL,
    marked_from_ip inet,
    location_data json,
    is_verified boolean DEFAULT false NOT NULL,
    verified_by_teacher_id bigint,
    verified_at timestamp(0) without time zone,
    verification_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    version integer DEFAULT 1 NOT NULL,
    original_record_id bigint,
    is_current_version boolean DEFAULT true NOT NULL,
    data_source character varying(255) DEFAULT 'manual'::character varying NOT NULL,
    validation_metadata json,
    reason_id bigint,
    reason_notes text,
    CONSTRAINT attendance_records_data_source_check CHECK (((data_source)::text = ANY ((ARRAY['manual'::character varying, 'qr_scan'::character varying, 'bulk_import'::character varying, 'system_generated'::character varying])::text[]))),
    CONSTRAINT attendance_records_marking_method_check CHECK (((marking_method)::text = ANY ((ARRAY['manual'::character varying, 'qr_scan'::character varying, 'auto'::character varying, 'bulk'::character varying])::text[])))
);


ALTER TABLE public.attendance_records OWNER TO postgres;

--
-- Name: attendance_records_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_records_id_seq OWNER TO postgres;

--
-- Name: attendance_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_records_id_seq OWNED BY public.attendance_records.id;


--
-- Name: attendance_session_edits; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_session_edits (
    id bigint NOT NULL,
    session_id bigint NOT NULL,
    edited_by_teacher_id bigint NOT NULL,
    changes json NOT NULL,
    edit_type character varying(255) NOT NULL,
    edit_reason character varying(255) NOT NULL,
    notes text,
    edited_from_ip inet,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT attendance_session_edits_edit_reason_check CHECK (((edit_reason)::text = ANY ((ARRAY['correction'::character varying, 'late_entry'::character varying, 'system_error'::character varying, 'administrative'::character varying])::text[]))),
    CONSTRAINT attendance_session_edits_edit_type_check CHECK (((edit_type)::text = ANY ((ARRAY['session_data'::character varying, 'attendance_records'::character varying, 'status_change'::character varying, 'time_correction'::character varying])::text[])))
);


ALTER TABLE public.attendance_session_edits OWNER TO postgres;

--
-- Name: attendance_session_edits_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_session_edits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_session_edits_id_seq OWNER TO postgres;

--
-- Name: attendance_session_edits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_session_edits_id_seq OWNED BY public.attendance_session_edits.id;


--
-- Name: attendance_session_stats; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_session_stats (
    id bigint NOT NULL,
    session_id bigint NOT NULL,
    total_students integer NOT NULL,
    marked_students integer NOT NULL,
    present_count integer NOT NULL,
    absent_count integer NOT NULL,
    late_count integer NOT NULL,
    excused_count integer NOT NULL,
    attendance_rate numeric(5,2) NOT NULL,
    detailed_stats json,
    calculated_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.attendance_session_stats OWNER TO postgres;

--
-- Name: attendance_session_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_session_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_session_stats_id_seq OWNER TO postgres;

--
-- Name: attendance_session_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_session_stats_id_seq OWNED BY public.attendance_session_stats.id;


--
-- Name: attendance_sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_sessions (
    id bigint NOT NULL,
    teacher_id bigint NOT NULL,
    section_id bigint NOT NULL,
    subject_id bigint,
    session_date date NOT NULL,
    session_start_time time(0) without time zone NOT NULL,
    session_end_time time(0) without time zone,
    session_type character varying(255) DEFAULT 'regular'::character varying NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    metadata json,
    created_at timestamp(0) without time zone NOT NULL,
    updated_at timestamp(0) without time zone NOT NULL,
    completed_at timestamp(0) without time zone,
    version integer DEFAULT 1 NOT NULL,
    original_session_id bigint,
    edit_reason character varying(255),
    edit_notes text,
    edited_by_teacher_id bigint,
    edited_at timestamp(0) without time zone,
    is_current_version boolean DEFAULT true NOT NULL,
    school_year_id bigint,
    is_valid_school_day boolean DEFAULT true NOT NULL,
    CONSTRAINT attendance_sessions_edit_reason_check CHECK (((edit_reason)::text = ANY ((ARRAY['correction'::character varying, 'late_entry'::character varying, 'system_error'::character varying, 'administrative'::character varying])::text[]))),
    CONSTRAINT attendance_sessions_session_type_check CHECK (((session_type)::text = ANY ((ARRAY['regular'::character varying, 'makeup'::character varying, 'special'::character varying])::text[]))),
    CONSTRAINT attendance_sessions_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'completed'::character varying, 'cancelled'::character varying])::text[])))
);


ALTER TABLE public.attendance_sessions OWNER TO postgres;

--
-- Name: attendance_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_sessions_id_seq OWNER TO postgres;

--
-- Name: attendance_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_sessions_id_seq OWNED BY public.attendance_sessions.id;


--
-- Name: attendance_statuses; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_statuses (
    id bigint NOT NULL,
    code character varying(10) NOT NULL,
    name character varying(100) NOT NULL,
    description character varying(255),
    color character varying(7) DEFAULT '#000000'::character varying NOT NULL,
    background_color character varying(7) DEFAULT '#FFFFFF'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.attendance_statuses OWNER TO postgres;

--
-- Name: attendance_statuses_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_statuses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_statuses_id_seq OWNER TO postgres;

--
-- Name: attendance_statuses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_statuses_id_seq OWNED BY public.attendance_statuses.id;


--
-- Name: attendance_validation_rules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_validation_rules (
    id bigint NOT NULL,
    rule_name character varying(255) NOT NULL,
    rule_type character varying(255) NOT NULL,
    rule_config json NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    priority integer DEFAULT 100 NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT attendance_validation_rules_rule_type_check CHECK (((rule_type)::text = ANY ((ARRAY['time_validation'::character varying, 'status_validation'::character varying, 'duplicate_check'::character varying, 'business_logic'::character varying])::text[])))
);


ALTER TABLE public.attendance_validation_rules OWNER TO postgres;

--
-- Name: attendance_validation_rules_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_validation_rules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_validation_rules_id_seq OWNER TO postgres;

--
-- Name: attendance_validation_rules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_validation_rules_id_seq OWNED BY public.attendance_validation_rules.id;


--
-- Name: attendances; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendances (
    id bigint NOT NULL,
    student_id bigint NOT NULL,
    subject_id bigint,
    teacher_id bigint,
    date date NOT NULL,
    time_in time(0) without time zone,
    status character varying(255) DEFAULT 'present'::character varying NOT NULL,
    remarks character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    marked_at timestamp(0) without time zone,
    section_id bigint,
    attendance_status_id bigint,
    CONSTRAINT attendances_status_check CHECK (((status)::text = ANY ((ARRAY['present'::character varying, 'absent'::character varying, 'late'::character varying, 'excused'::character varying])::text[])))
);


ALTER TABLE public.attendances OWNER TO postgres;

--
-- Name: attendances_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendances_id_seq OWNER TO postgres;

--
-- Name: attendances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendances_id_seq OWNED BY public.attendances.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO postgres;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO postgres;

--
-- Name: class_schedules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.class_schedules (
    id bigint NOT NULL,
    teacher_id bigint NOT NULL,
    section_id bigint NOT NULL,
    subject_id bigint NOT NULL,
    day_of_week character varying(255) NOT NULL,
    start_time time(0) without time zone NOT NULL,
    end_time time(0) without time zone NOT NULL,
    effective_from date NOT NULL,
    effective_until date,
    school_year character varying(20) NOT NULL,
    semester character varying(255) DEFAULT '1st'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT class_schedules_day_of_week_check CHECK (((day_of_week)::text = ANY ((ARRAY['monday'::character varying, 'tuesday'::character varying, 'wednesday'::character varying, 'thursday'::character varying, 'friday'::character varying, 'saturday'::character varying, 'sunday'::character varying])::text[]))),
    CONSTRAINT class_schedules_semester_check CHECK (((semester)::text = ANY ((ARRAY['1st'::character varying, '2nd'::character varying, 'summer'::character varying])::text[])))
);


ALTER TABLE public.class_schedules OWNER TO postgres;

--
-- Name: class_schedules_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.class_schedules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_schedules_id_seq OWNER TO postgres;

--
-- Name: class_schedules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.class_schedules_id_seq OWNED BY public.class_schedules.id;


--
-- Name: curricula; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.curricula (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    start_year integer NOT NULL,
    end_year integer NOT NULL,
    is_active boolean DEFAULT false NOT NULL,
    status character varying(255) DEFAULT 'Draft'::character varying NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT curricula_status_check CHECK (((status)::text = ANY ((ARRAY['Draft'::character varying, 'Active'::character varying, 'Archived'::character varying])::text[])))
);


ALTER TABLE public.curricula OWNER TO postgres;

--
-- Name: curricula_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.curricula_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.curricula_id_seq OWNER TO postgres;

--
-- Name: curricula_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.curricula_id_seq OWNED BY public.curricula.id;


--
-- Name: curriculum_grade; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.curriculum_grade (
    id bigint NOT NULL,
    curriculum_id bigint NOT NULL,
    grade_id bigint NOT NULL
);


ALTER TABLE public.curriculum_grade OWNER TO postgres;

--
-- Name: curriculum_grade_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.curriculum_grade_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.curriculum_grade_id_seq OWNER TO postgres;

--
-- Name: curriculum_grade_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.curriculum_grade_id_seq OWNED BY public.curriculum_grade.id;


--
-- Name: curriculum_grade_subject; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.curriculum_grade_subject (
    id bigint NOT NULL,
    curriculum_id bigint NOT NULL,
    grade_id bigint NOT NULL,
    subject_id bigint NOT NULL,
    units integer DEFAULT 1 NOT NULL,
    hours_per_week integer DEFAULT 1 NOT NULL,
    sequence_number integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT curriculum_grade_subject_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying])::text[])))
);


ALTER TABLE public.curriculum_grade_subject OWNER TO postgres;

--
-- Name: curriculum_grade_subject_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.curriculum_grade_subject_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.curriculum_grade_subject_id_seq OWNER TO postgres;

--
-- Name: curriculum_grade_subject_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.curriculum_grade_subject_id_seq OWNED BY public.curriculum_grade_subject.id;


--
-- Name: gate_attendance; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.gate_attendance (
    id bigint NOT NULL,
    student_id bigint NOT NULL,
    student_qr_code character varying(255),
    type character varying(255) DEFAULT 'check_in'::character varying NOT NULL,
    scan_time timestamp(0) without time zone NOT NULL,
    scan_date date NOT NULL,
    gate_location character varying(255) DEFAULT 'main_gate'::character varying NOT NULL,
    scanner_device character varying(255),
    metadata json,
    is_valid boolean DEFAULT true NOT NULL,
    remarks character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT gate_attendance_type_check CHECK (((type)::text = ANY ((ARRAY['check_in'::character varying, 'check_out'::character varying])::text[])))
);


ALTER TABLE public.gate_attendance OWNER TO postgres;

--
-- Name: gate_attendance_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.gate_attendance_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.gate_attendance_id_seq OWNER TO postgres;

--
-- Name: gate_attendance_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.gate_attendance_id_seq OWNED BY public.gate_attendance.id;


--
-- Name: grades; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.grades (
    id bigint NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    level character varying(255) NOT NULL,
    description text,
    display_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.grades OWNER TO postgres;

--
-- Name: grades_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.grades_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.grades_id_seq OWNER TO postgres;

--
-- Name: grades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.grades_id_seq OWNED BY public.grades.id;


--
-- Name: guardhouse_archive_sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.guardhouse_archive_sessions (
    id bigint NOT NULL,
    session_date date NOT NULL,
    total_records integer DEFAULT 0 NOT NULL,
    archived_at timestamp(0) without time zone NOT NULL,
    archived_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.guardhouse_archive_sessions OWNER TO postgres;

--
-- Name: guardhouse_archive_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.guardhouse_archive_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.guardhouse_archive_sessions_id_seq OWNER TO postgres;

--
-- Name: guardhouse_archive_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.guardhouse_archive_sessions_id_seq OWNED BY public.guardhouse_archive_sessions.id;


--
-- Name: guardhouse_archived_records; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.guardhouse_archived_records (
    id bigint NOT NULL,
    session_id bigint,
    student_id character varying(255),
    student_name character varying(255) NOT NULL,
    grade_level character varying(255),
    section character varying(255),
    record_type character varying(255) NOT NULL,
    "timestamp" timestamp(0) without time zone NOT NULL,
    session_date date NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT guardhouse_archived_records_record_type_check CHECK (((record_type)::text = ANY ((ARRAY['check-in'::character varying, 'check-out'::character varying])::text[])))
);


ALTER TABLE public.guardhouse_archived_records OWNER TO postgres;

--
-- Name: guardhouse_archived_records_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.guardhouse_archived_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.guardhouse_archived_records_id_seq OWNER TO postgres;

--
-- Name: guardhouse_archived_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.guardhouse_archived_records_id_seq OWNED BY public.guardhouse_archived_records.id;


--
-- Name: guardhouse_attendance; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.guardhouse_attendance (
    id integer NOT NULL,
    student_id integer NOT NULL,
    qr_code_data character varying(255) NOT NULL,
    record_type character varying(20) NOT NULL,
    "timestamp" timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    date date DEFAULT CURRENT_DATE,
    guard_name character varying(100) DEFAULT 'Bread Doe'::character varying,
    guard_id character varying(20) DEFAULT 'G-12345'::character varying,
    is_manual boolean DEFAULT false,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT guardhouse_attendance_record_type_check CHECK (((record_type)::text = ANY ((ARRAY['check-in'::character varying, 'check-out'::character varying])::text[])))
);


ALTER TABLE public.guardhouse_attendance OWNER TO postgres;

--
-- Name: guardhouse_attendance_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.guardhouse_attendance_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.guardhouse_attendance_id_seq OWNER TO postgres;

--
-- Name: guardhouse_attendance_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.guardhouse_attendance_id_seq OWNED BY public.guardhouse_attendance.id;


--
-- Name: guardhouse_users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.guardhouse_users (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    phone_number character varying(255),
    shift character varying(255) DEFAULT 'morning'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT guardhouse_users_shift_check CHECK (((shift)::text = ANY ((ARRAY['morning'::character varying, 'afternoon'::character varying, 'night'::character varying])::text[])))
);


ALTER TABLE public.guardhouse_users OWNER TO postgres;

--
-- Name: guardhouse_users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.guardhouse_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.guardhouse_users_id_seq OWNER TO postgres;

--
-- Name: guardhouse_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.guardhouse_users_id_seq OWNED BY public.guardhouse_users.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.notifications (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    type character varying(50) NOT NULL,
    title character varying(255) NOT NULL,
    message text NOT NULL,
    data json,
    priority character varying(255) DEFAULT 'medium'::character varying NOT NULL,
    is_read boolean DEFAULT false NOT NULL,
    read_at timestamp(0) without time zone,
    related_student_id bigint,
    created_by_user_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT notifications_priority_check CHECK (((priority)::text = ANY ((ARRAY['low'::character varying, 'medium'::character varying, 'high'::character varying, 'critical'::character varying])::text[])))
);


ALTER TABLE public.notifications OWNER TO postgres;

--
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.notifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.notifications_id_seq OWNER TO postgres;

--
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.notifications_id_seq OWNED BY public.notifications.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO postgres;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO postgres;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: schedules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.schedules (
    id bigint NOT NULL,
    section_id bigint NOT NULL,
    subject_id bigint,
    teacher_id bigint NOT NULL,
    day_of_week character varying(255) NOT NULL,
    start_time time(0) without time zone NOT NULL,
    end_time time(0) without time zone NOT NULL,
    period_type character varying(255) NOT NULL,
    room_number character varying(255),
    notes text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT schedules_day_of_week_check CHECK (((day_of_week)::text = ANY ((ARRAY['Monday'::character varying, 'Tuesday'::character varying, 'Wednesday'::character varying, 'Thursday'::character varying, 'Friday'::character varying, 'Saturday'::character varying])::text[]))),
    CONSTRAINT schedules_period_type_check CHECK (((period_type)::text = ANY ((ARRAY['homeroom'::character varying, 'subject'::character varying, 'break'::character varying, 'lunch'::character varying])::text[])))
);


ALTER TABLE public.schedules OWNER TO postgres;

--
-- Name: schedules_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.schedules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.schedules_id_seq OWNER TO postgres;

--
-- Name: schedules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.schedules_id_seq OWNED BY public.schedules.id;


--
-- Name: school_calendar_events; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.school_calendar_events (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    start_date date NOT NULL,
    end_date date NOT NULL,
    event_type character varying(255) NOT NULL,
    affects_attendance boolean DEFAULT true NOT NULL,
    modified_start_time time(0) without time zone,
    modified_end_time time(0) without time zone,
    affected_sections json,
    affected_grade_levels json,
    is_recurring boolean DEFAULT false NOT NULL,
    recurrence_pattern character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT school_calendar_events_event_type_check CHECK (((event_type)::text = ANY ((ARRAY['holiday'::character varying, 'half_day'::character varying, 'early_dismissal'::character varying, 'no_classes'::character varying, 'school_event'::character varying, 'teacher_training'::character varying, 'exam_day'::character varying])::text[])))
);


ALTER TABLE public.school_calendar_events OWNER TO postgres;

--
-- Name: school_calendar_events_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.school_calendar_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.school_calendar_events_id_seq OWNER TO postgres;

--
-- Name: school_calendar_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.school_calendar_events_id_seq OWNED BY public.school_calendar_events.id;


--
-- Name: school_days; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.school_days (
    id bigint NOT NULL,
    date date NOT NULL,
    school_year_id bigint NOT NULL,
    is_class_day boolean DEFAULT true NOT NULL,
    day_type character varying(255) DEFAULT 'regular'::character varying NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.school_days OWNER TO postgres;

--
-- Name: school_days_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.school_days_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.school_days_id_seq OWNER TO postgres;

--
-- Name: school_days_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.school_days_id_seq OWNED BY public.school_days.id;


--
-- Name: school_holidays; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.school_holidays (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    date date NOT NULL,
    type character varying(255) NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.school_holidays OWNER TO postgres;

--
-- Name: school_holidays_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.school_holidays_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.school_holidays_id_seq OWNER TO postgres;

--
-- Name: school_holidays_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.school_holidays_id_seq OWNED BY public.school_holidays.id;


--
-- Name: school_years; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.school_years (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    is_active boolean DEFAULT false NOT NULL,
    quarters json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.school_years OWNER TO postgres;

--
-- Name: school_years_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.school_years_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.school_years_id_seq OWNER TO postgres;

--
-- Name: school_years_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.school_years_id_seq OWNED BY public.school_years.id;


--
-- Name: seating_arrangements; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.seating_arrangements (
    id bigint NOT NULL,
    section_id bigint NOT NULL,
    subject_id bigint,
    teacher_id bigint NOT NULL,
    layout json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.seating_arrangements OWNER TO postgres;

--
-- Name: seating_arrangements_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.seating_arrangements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.seating_arrangements_id_seq OWNER TO postgres;

--
-- Name: seating_arrangements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.seating_arrangements_id_seq OWNED BY public.seating_arrangements.id;


--
-- Name: section_subject; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.section_subject (
    id bigint NOT NULL,
    section_id bigint NOT NULL,
    subject_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.section_subject OWNER TO postgres;

--
-- Name: section_subject_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.section_subject_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.section_subject_id_seq OWNER TO postgres;

--
-- Name: section_subject_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.section_subject_id_seq OWNED BY public.section_subject.id;


--
-- Name: sections; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sections (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    capacity integer DEFAULT 40 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    deleted_at timestamp(0) without time zone,
    curriculum_id bigint,
    curriculum_grade_id bigint NOT NULL,
    homeroom_teacher_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.sections OWNER TO postgres;

--
-- Name: sections_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sections_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sections_id_seq OWNER TO postgres;

--
-- Name: sections_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sections_id_seq OWNED BY public.sections.id;


--
-- Name: sf2_attendance_edits; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sf2_attendance_edits (
    id bigint NOT NULL,
    student_id bigint NOT NULL,
    section_id bigint NOT NULL,
    date date NOT NULL,
    month character varying(7) NOT NULL,
    status character varying(50) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.sf2_attendance_edits OWNER TO postgres;

--
-- Name: sf2_attendance_edits_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sf2_attendance_edits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sf2_attendance_edits_id_seq OWNER TO postgres;

--
-- Name: sf2_attendance_edits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sf2_attendance_edits_id_seq OWNED BY public.sf2_attendance_edits.id;


--
-- Name: student_details; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.student_details (
    id bigint NOT NULL,
    "studentId" character varying(255) NOT NULL,
    name character varying(255),
    "firstName" character varying(255),
    "lastName" character varying(255),
    "middleName" character varying(255),
    "extensionName" character varying(255),
    email character varying(255),
    "gradeLevel" character varying(255),
    section character varying(255),
    lrn character varying(255),
    "schoolYearStart" character varying(255),
    "schoolYearEnd" character varying(255),
    gender character varying(255),
    sex character varying(255),
    birthdate date,
    birthplace character varying(255),
    age integer,
    "psaBirthCertNo" character varying(255),
    "motherTongue" character varying(255),
    "profilePhoto" text,
    "currentAddress" json,
    "permanentAddress" json,
    "contactInfo" character varying(255),
    father json,
    mother json,
    "parentName" character varying(255),
    "parentContact" character varying(255),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    "enrollmentDate" timestamp(0) without time zone,
    "admissionDate" timestamp(0) without time zone,
    requirements json,
    "isIndigenous" boolean DEFAULT false NOT NULL,
    "indigenousCommunity" character varying(255),
    "is4PsBeneficiary" boolean DEFAULT false NOT NULL,
    "householdID" character varying(255),
    "hasDisability" boolean DEFAULT false NOT NULL,
    disabilities json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    qr_code character varying(255),
    student_id character varying(255) NOT NULL,
    photo character varying(255),
    qr_code_path character varying(255),
    address text,
    "isActive" boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    enrollment_status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    dropout_reason character varying(255),
    dropout_reason_category character varying(255),
    status_effective_date date
);


ALTER TABLE public.student_details OWNER TO postgres;

--
-- Name: student_details_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.student_details_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.student_details_id_seq OWNER TO postgres;

--
-- Name: student_details_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.student_details_id_seq OWNED BY public.student_details.id;


--
-- Name: student_enrollment_history; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.student_enrollment_history (
    id bigint NOT NULL,
    student_id bigint NOT NULL,
    section_id bigint NOT NULL,
    enrolled_date date NOT NULL,
    unenrolled_date date,
    enrollment_status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    school_year character varying(20) NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT student_enrollment_history_enrollment_status_check CHECK (((enrollment_status)::text = ANY ((ARRAY['active'::character varying, 'transferred'::character varying, 'dropped'::character varying, 'graduated'::character varying])::text[])))
);


ALTER TABLE public.student_enrollment_history OWNER TO postgres;

--
-- Name: student_enrollment_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.student_enrollment_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.student_enrollment_history_id_seq OWNER TO postgres;

--
-- Name: student_enrollment_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.student_enrollment_history_id_seq OWNED BY public.student_enrollment_history.id;


--
-- Name: student_qr_codes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.student_qr_codes (
    id bigint NOT NULL,
    student_id bigint NOT NULL,
    qr_code_data character varying(255) NOT NULL,
    qr_code_hash character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    generated_at timestamp(0) without time zone NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.student_qr_codes OWNER TO postgres;

--
-- Name: student_qr_codes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.student_qr_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.student_qr_codes_id_seq OWNER TO postgres;

--
-- Name: student_qr_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.student_qr_codes_id_seq OWNED BY public.student_qr_codes.id;


--
-- Name: student_section; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.student_section (
    id bigint NOT NULL,
    student_id bigint NOT NULL,
    section_id bigint NOT NULL,
    school_year character varying(255) DEFAULT '2025-2026'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    enrollment_date date DEFAULT '2025-10-07'::date NOT NULL,
    status character varying(255) DEFAULT 'enrolled'::character varying NOT NULL,
    CONSTRAINT student_section_status_check CHECK (((status)::text = ANY ((ARRAY['enrolled'::character varying, 'transferred'::character varying, 'dropped'::character varying])::text[])))
);


ALTER TABLE public.student_section OWNER TO postgres;

--
-- Name: student_section_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.student_section_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.student_section_id_seq OWNER TO postgres;

--
-- Name: student_section_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.student_section_id_seq OWNED BY public.student_section.id;


--
-- Name: student_status_history; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.student_status_history (
    id bigint NOT NULL,
    student_id bigint NOT NULL,
    previous_status character varying(255) NOT NULL,
    new_status character varying(255) NOT NULL,
    reason character varying(255),
    reason_category character varying(255),
    effective_date date NOT NULL,
    changed_by_teacher_id bigint NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.student_status_history OWNER TO postgres;

--
-- Name: student_status_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.student_status_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.student_status_history_id_seq OWNER TO postgres;

--
-- Name: student_status_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.student_status_history_id_seq OWNED BY public.student_status_history.id;


--
-- Name: students; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.students (
    id bigint NOT NULL,
    "studentId" character varying(255) NOT NULL,
    name character varying(255),
    "firstName" character varying(255),
    "lastName" character varying(255),
    "middleName" character varying(255),
    "extensionName" character varying(255),
    email character varying(255),
    "gradeLevel" character varying(255),
    section character varying(255),
    lrn character varying(255),
    "schoolYearStart" character varying(255),
    "schoolYearEnd" character varying(255),
    gender character varying(255),
    sex character varying(255),
    birthdate date,
    birthplace character varying(255),
    age integer,
    "psaBirthCertNo" character varying(255),
    "motherTongue" character varying(255),
    "profilePhoto" character varying(255),
    "currentAddress" json,
    "permanentAddress" json,
    "contactInfo" character varying(255),
    father json,
    mother json,
    "parentName" character varying(255),
    "parentContact" character varying(255),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    "enrollmentDate" timestamp(0) without time zone,
    "admissionDate" timestamp(0) without time zone,
    requirements json,
    "isIndigenous" boolean DEFAULT false NOT NULL,
    "indigenousCommunity" character varying(255),
    "is4PsBeneficiary" boolean DEFAULT false NOT NULL,
    "householdID" character varying(255),
    "hasDisability" boolean DEFAULT false NOT NULL,
    disabilities json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    archive_reason character varying(255),
    archive_notes text,
    archived_at timestamp(0) without time zone,
    archived_by bigint
);


ALTER TABLE public.students OWNER TO postgres;

--
-- Name: students_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.students_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.students_id_seq OWNER TO postgres;

--
-- Name: students_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.students_id_seq OWNED BY public.students.id;


--
-- Name: subject_schedules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.subject_schedules (
    id bigint NOT NULL,
    section_id bigint NOT NULL,
    subject_id bigint NOT NULL,
    teacher_id bigint,
    day character varying(255) NOT NULL,
    start_time character varying(10) NOT NULL,
    end_time character varying(10) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT subject_schedules_day_check CHECK (((day)::text = ANY ((ARRAY['Monday'::character varying, 'Tuesday'::character varying, 'Wednesday'::character varying, 'Thursday'::character varying, 'Friday'::character varying])::text[])))
);


ALTER TABLE public.subject_schedules OWNER TO postgres;

--
-- Name: subject_schedules_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.subject_schedules_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.subject_schedules_id_seq OWNER TO postgres;

--
-- Name: subject_schedules_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.subject_schedules_id_seq OWNED BY public.subject_schedules.id;


--
-- Name: subjects; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.subjects (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    description text,
    credits integer,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.subjects OWNER TO postgres;

--
-- Name: subjects_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.subjects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.subjects_id_seq OWNER TO postgres;

--
-- Name: subjects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.subjects_id_seq OWNED BY public.subjects.id;


--
-- Name: submitted_sf2_reports; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.submitted_sf2_reports (
    id bigint NOT NULL,
    section_id bigint NOT NULL,
    section_name character varying(255) NOT NULL,
    grade_level character varying(255) NOT NULL,
    month character varying(255) NOT NULL,
    month_name character varying(255) NOT NULL,
    report_type character varying(255) DEFAULT 'SF2'::character varying NOT NULL,
    status character varying(255) DEFAULT 'submitted'::character varying NOT NULL,
    submitted_by bigint NOT NULL,
    submitted_at timestamp(0) without time zone NOT NULL,
    reviewed_at timestamp(0) without time zone,
    reviewed_by bigint,
    admin_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT submitted_sf2_reports_status_check CHECK (((status)::text = ANY ((ARRAY['submitted'::character varying, 'reviewed'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[])))
);


ALTER TABLE public.submitted_sf2_reports OWNER TO postgres;

--
-- Name: submitted_sf2_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.submitted_sf2_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.submitted_sf2_reports_id_seq OWNER TO postgres;

--
-- Name: submitted_sf2_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.submitted_sf2_reports_id_seq OWNED BY public.submitted_sf2_reports.id;


--
-- Name: teacher_section_subject; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.teacher_section_subject (
    id bigint NOT NULL,
    teacher_id bigint NOT NULL,
    section_id bigint NOT NULL,
    subject_id bigint,
    is_primary boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    role character varying(255) DEFAULT 'teacher'::character varying NOT NULL,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT check_subject_id_or_homeroom CHECK (((subject_id IS NOT NULL) OR ((role)::text = 'homeroom'::text)))
);


ALTER TABLE public.teacher_section_subject OWNER TO postgres;

--
-- Name: teacher_section_subject_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.teacher_section_subject_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.teacher_section_subject_id_seq OWNER TO postgres;

--
-- Name: teacher_section_subject_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.teacher_section_subject_id_seq OWNED BY public.teacher_section_subject.id;


--
-- Name: teacher_sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.teacher_sessions (
    id bigint NOT NULL,
    teacher_id bigint NOT NULL,
    user_id bigint NOT NULL,
    token character varying(255) NOT NULL,
    ip_address character varying(45),
    user_agent text,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.teacher_sessions OWNER TO postgres;

--
-- Name: teacher_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.teacher_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.teacher_sessions_id_seq OWNER TO postgres;

--
-- Name: teacher_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.teacher_sessions_id_seq OWNED BY public.teacher_sessions.id;


--
-- Name: teachers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.teachers (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    phone_number character varying(255),
    address text,
    date_of_birth date,
    gender character varying(255),
    is_head_teacher boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT teachers_gender_check CHECK (((gender)::text = ANY ((ARRAY['male'::character varying, 'female'::character varying, 'other'::character varying])::text[])))
);


ALTER TABLE public.teachers OWNER TO postgres;

--
-- Name: teachers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.teachers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.teachers_id_seq OWNER TO postgres;

--
-- Name: teachers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.teachers_id_seq OWNED BY public.teachers.id;


--
-- Name: user_sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.user_sessions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    token character varying(80) NOT NULL,
    role character varying(20) NOT NULL,
    ip_address character varying(45),
    user_agent text,
    last_activity timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.user_sessions OWNER TO postgres;

--
-- Name: user_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.user_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_sessions_id_seq OWNER TO postgres;

--
-- Name: user_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.user_sessions_id_seq OWNED BY public.user_sessions.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    username character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    role character varying(20) DEFAULT 'teacher'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    force_password_reset boolean DEFAULT false NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password_changed_at timestamp(0) without time zone,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT users_role_check CHECK (((role)::text = ANY ((ARRAY['admin'::character varying, 'teacher'::character varying, 'guardhouse'::character varying])::text[])))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: admins id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admins ALTER COLUMN id SET DEFAULT nextval('public.admins_id_seq'::regclass);


--
-- Name: attendance_audit_log id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_audit_log ALTER COLUMN id SET DEFAULT nextval('public.attendance_audit_log_id_seq'::regclass);


--
-- Name: attendance_modifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_modifications ALTER COLUMN id SET DEFAULT nextval('public.attendance_modifications_id_seq'::regclass);


--
-- Name: attendance_policies id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_policies ALTER COLUMN id SET DEFAULT nextval('public.attendance_policies_id_seq'::regclass);


--
-- Name: attendance_reasons id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_reasons ALTER COLUMN id SET DEFAULT nextval('public.attendance_reasons_id_seq'::regclass);


--
-- Name: attendance_records id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records ALTER COLUMN id SET DEFAULT nextval('public.attendance_records_id_seq'::regclass);


--
-- Name: attendance_session_edits id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_session_edits ALTER COLUMN id SET DEFAULT nextval('public.attendance_session_edits_id_seq'::regclass);


--
-- Name: attendance_session_stats id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_session_stats ALTER COLUMN id SET DEFAULT nextval('public.attendance_session_stats_id_seq'::regclass);


--
-- Name: attendance_sessions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_sessions ALTER COLUMN id SET DEFAULT nextval('public.attendance_sessions_id_seq'::regclass);


--
-- Name: attendance_statuses id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_statuses ALTER COLUMN id SET DEFAULT nextval('public.attendance_statuses_id_seq'::regclass);


--
-- Name: attendance_validation_rules id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_validation_rules ALTER COLUMN id SET DEFAULT nextval('public.attendance_validation_rules_id_seq'::regclass);


--
-- Name: attendances id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendances ALTER COLUMN id SET DEFAULT nextval('public.attendances_id_seq'::regclass);


--
-- Name: class_schedules id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.class_schedules ALTER COLUMN id SET DEFAULT nextval('public.class_schedules_id_seq'::regclass);


--
-- Name: curricula id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curricula ALTER COLUMN id SET DEFAULT nextval('public.curricula_id_seq'::regclass);


--
-- Name: curriculum_grade id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade ALTER COLUMN id SET DEFAULT nextval('public.curriculum_grade_id_seq'::regclass);


--
-- Name: curriculum_grade_subject id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade_subject ALTER COLUMN id SET DEFAULT nextval('public.curriculum_grade_subject_id_seq'::regclass);


--
-- Name: gate_attendance id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.gate_attendance ALTER COLUMN id SET DEFAULT nextval('public.gate_attendance_id_seq'::regclass);


--
-- Name: grades id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.grades ALTER COLUMN id SET DEFAULT nextval('public.grades_id_seq'::regclass);


--
-- Name: guardhouse_archive_sessions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_archive_sessions ALTER COLUMN id SET DEFAULT nextval('public.guardhouse_archive_sessions_id_seq'::regclass);


--
-- Name: guardhouse_archived_records id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_archived_records ALTER COLUMN id SET DEFAULT nextval('public.guardhouse_archived_records_id_seq'::regclass);


--
-- Name: guardhouse_attendance id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_attendance ALTER COLUMN id SET DEFAULT nextval('public.guardhouse_attendance_id_seq'::regclass);


--
-- Name: guardhouse_users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_users ALTER COLUMN id SET DEFAULT nextval('public.guardhouse_users_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications ALTER COLUMN id SET DEFAULT nextval('public.notifications_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: schedules id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schedules ALTER COLUMN id SET DEFAULT nextval('public.schedules_id_seq'::regclass);


--
-- Name: school_calendar_events id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_calendar_events ALTER COLUMN id SET DEFAULT nextval('public.school_calendar_events_id_seq'::regclass);


--
-- Name: school_days id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_days ALTER COLUMN id SET DEFAULT nextval('public.school_days_id_seq'::regclass);


--
-- Name: school_holidays id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_holidays ALTER COLUMN id SET DEFAULT nextval('public.school_holidays_id_seq'::regclass);


--
-- Name: school_years id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_years ALTER COLUMN id SET DEFAULT nextval('public.school_years_id_seq'::regclass);


--
-- Name: seating_arrangements id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.seating_arrangements ALTER COLUMN id SET DEFAULT nextval('public.seating_arrangements_id_seq'::regclass);


--
-- Name: section_subject id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.section_subject ALTER COLUMN id SET DEFAULT nextval('public.section_subject_id_seq'::regclass);


--
-- Name: sections id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sections ALTER COLUMN id SET DEFAULT nextval('public.sections_id_seq'::regclass);


--
-- Name: sf2_attendance_edits id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sf2_attendance_edits ALTER COLUMN id SET DEFAULT nextval('public.sf2_attendance_edits_id_seq'::regclass);


--
-- Name: student_details id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_details ALTER COLUMN id SET DEFAULT nextval('public.student_details_id_seq'::regclass);


--
-- Name: student_enrollment_history id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_enrollment_history ALTER COLUMN id SET DEFAULT nextval('public.student_enrollment_history_id_seq'::regclass);


--
-- Name: student_qr_codes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_qr_codes ALTER COLUMN id SET DEFAULT nextval('public.student_qr_codes_id_seq'::regclass);


--
-- Name: student_section id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_section ALTER COLUMN id SET DEFAULT nextval('public.student_section_id_seq'::regclass);


--
-- Name: student_status_history id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_status_history ALTER COLUMN id SET DEFAULT nextval('public.student_status_history_id_seq'::regclass);


--
-- Name: students id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.students ALTER COLUMN id SET DEFAULT nextval('public.students_id_seq'::regclass);


--
-- Name: subject_schedules id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subject_schedules ALTER COLUMN id SET DEFAULT nextval('public.subject_schedules_id_seq'::regclass);


--
-- Name: subjects id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subjects ALTER COLUMN id SET DEFAULT nextval('public.subjects_id_seq'::regclass);


--
-- Name: submitted_sf2_reports id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.submitted_sf2_reports ALTER COLUMN id SET DEFAULT nextval('public.submitted_sf2_reports_id_seq'::regclass);


--
-- Name: teacher_section_subject id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_section_subject ALTER COLUMN id SET DEFAULT nextval('public.teacher_section_subject_id_seq'::regclass);


--
-- Name: teacher_sessions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_sessions ALTER COLUMN id SET DEFAULT nextval('public.teacher_sessions_id_seq'::regclass);


--
-- Name: teachers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teachers ALTER COLUMN id SET DEFAULT nextval('public.teachers_id_seq'::regclass);


--
-- Name: user_sessions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_sessions ALTER COLUMN id SET DEFAULT nextval('public.user_sessions_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: admins; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.admins (id, user_id, first_name, last_name, phone_number, address, date_of_birth, gender, "position", created_at, updated_at, deleted_at) FROM stdin;
2	25	System	Administrator	09123456789	\N	\N	male	School Administrator	2025-10-07 13:25:14	2025-10-07 13:25:14	\N
\.


--
-- Data for Name: attendance_audit_log; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_audit_log (id, entity_type, entity_id, action, performed_by_teacher_id, old_values, new_values, reason, ip_address, user_agent, context, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: attendance_modifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_modifications (id, attendance_record_id, modified_by_teacher_id, old_values, new_values, modification_type, reason, authorized_by_teacher_id, authorized_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: attendance_policies; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_policies (id, policy_name, scope, scope_id, late_threshold_minutes, absent_threshold_minutes, allow_teacher_override, require_verification, allowed_statuses, effective_from, effective_until, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: attendance_reasons; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_reasons (id, reason_name, reason_type, category, display_order, is_active, created_at, updated_at) FROM stdin;
1	Far distance from home to school	late	Transportation	1	t	2025-10-07 19:41:18	2025-10-07 19:41:18
2	Muddy/impassable road	late	Transportation	2	t	2025-10-07 19:41:18	2025-10-07 19:41:18
3	Flooded road/area	late	Transportation	3	t	2025-10-07 19:41:18	2025-10-07 19:41:18
4	No available transportation	late	Transportation	4	t	2025-10-07 19:41:18	2025-10-07 19:41:18
5	Helping with farm/household chores before school	late	Family	5	t	2025-10-07 19:41:18	2025-10-07 19:41:18
6	Heavy rain	late	Weather	6	t	2025-10-07 19:41:18	2025-10-07 19:41:18
7	Strong typhoon/storm	late	Weather	7	t	2025-10-07 19:41:18	2025-10-07 19:41:18
8	Illness (mild)	late	Health	8	t	2025-10-07 19:41:18	2025-10-07 19:41:18
9	Medical appointment	late	Health	9	t	2025-10-07 19:41:18	2025-10-07 19:41:18
10	Family emergency	late	Family	10	t	2025-10-07 19:41:18	2025-10-07 19:41:18
11	Took care of younger sibling	late	Family	11	t	2025-10-07 19:41:18	2025-10-07 19:41:18
12	Other	late	Other	99	t	2025-10-07 19:41:18	2025-10-07 19:41:18
13	Illness	excused	Health	1	t	2025-10-07 19:41:18	2025-10-07 19:41:18
14	Medical appointment	excused	Health	2	t	2025-10-07 19:41:18	2025-10-07 19:41:18
15	Medical procedure/treatment	excused	Health	3	t	2025-10-07 19:41:18	2025-10-07 19:41:18
16	Recovering from illness	excused	Health	4	t	2025-10-07 19:41:18	2025-10-07 19:41:18
17	Family emergency	excused	Family	5	t	2025-10-07 19:41:18	2025-10-07 19:41:18
18	Family bereavement	excused	Family	6	t	2025-10-07 19:41:18	2025-10-07 19:41:18
19	Family obligation/event	excused	Family	7	t	2025-10-07 19:41:18	2025-10-07 19:41:18
20	Taking care of sick family member	excused	Family	8	t	2025-10-07 19:41:18	2025-10-07 19:41:18
21	Typhoon/storm	excused	Weather	9	t	2025-10-07 19:41:18	2025-10-07 19:41:18
22	Flooding (area inaccessible)	excused	Weather	10	t	2025-10-07 19:41:18	2025-10-07 19:41:18
23	Road completely impassable	excused	Weather	11	t	2025-10-07 19:41:18	2025-10-07 19:41:18
24	School-sanctioned activity	excused	School	12	t	2025-10-07 19:41:18	2025-10-07 19:41:18
25	Other	excused	Other	99	t	2025-10-07 19:41:18	2025-10-07 19:41:18
\.


--
-- Data for Name: attendance_records; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_records (id, attendance_session_id, student_id, attendance_status_id, marked_by_teacher_id, marked_at, arrival_time, departure_time, remarks, marking_method, marked_from_ip, location_data, is_verified, verified_by_teacher_id, verified_at, verification_notes, created_at, updated_at, deleted_at, version, original_record_id, is_current_version, data_source, validation_metadata, reason_id, reason_notes) FROM stdin;
442497	17800	3231	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442498	17800	3232	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442499	17800	3233	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442500	17800	3234	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442501	17800	3235	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442502	17800	3236	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442503	17800	3237	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442504	17800	3238	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442505	17800	3239	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442506	17800	3240	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442507	17800	3241	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442508	17800	3242	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442509	17800	3243	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442510	17800	3244	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442511	17800	3245	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442512	17800	3246	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442513	17800	3247	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442514	17800	3248	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442515	17800	3249	2	1	2025-10-07 19:31:11	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 19:31:11	2025-10-07 19:31:11	\N	1	\N	t	manual	\N	\N	\N
442516	17801	3234	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:00	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442517	17801	3239	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442518	17801	3233	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442520	17801	3231	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442521	17801	3240	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442522	17801	3235	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442523	17801	3236	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442524	17801	3246	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442525	17801	3249	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442526	17801	3245	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442527	17801	3237	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442529	17801	3244	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442530	17801	3232	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442531	17801	3243	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442532	17801	3241	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442533	17801	3248	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442534	17801	3242	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442535	17801	3238	1	1	2025-10-07 19:44:03	19:44:02	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:03	\N	1	\N	t	manual	\N	\N	\N
442519	17801	3247	4	1	2025-10-07 19:44:07	19:44:06	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:44:03	2025-10-07 19:44:07	\N	1	\N	t	manual	\N	14	\N
442536	17802	3234	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442537	17802	3239	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442540	17802	3231	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442541	17802	3240	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442542	17802	3235	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442543	17802	3236	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442544	17802	3246	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442545	17802	3249	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442546	17802	3245	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442547	17802	3237	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442538	17802	3233	4	1	2025-10-07 19:51:16	19:51:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:16	\N	1	\N	t	manual	\N	14	\N
442549	17802	3244	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442550	17802	3232	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442551	17802	3243	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442552	17802	3241	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442553	17802	3248	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442554	17802	3242	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442555	17802	3238	1	1	2025-10-07 19:51:07	19:51:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:07	\N	1	\N	t	manual	\N	\N	\N
442539	17802	3247	3	1	2025-10-07 19:51:10	19:51:10	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:51:07	2025-10-07 19:51:10	\N	1	\N	t	manual	\N	1	\N
442556	17803	3234	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442557	17803	3239	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442558	17803	3233	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442559	17803	3247	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442560	17803	3231	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442561	17803	3240	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442562	17803	3235	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442563	17803	3236	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442564	17803	3246	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442565	17803	3249	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442566	17803	3245	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442567	17803	3237	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442569	17803	3244	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442570	17803	3232	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442571	17803	3243	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442572	17803	3241	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442573	17803	3248	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442574	17803	3242	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442575	17803	3238	1	1	2025-10-07 19:55:09	19:55:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 19:55:09	2025-10-07 19:55:09	\N	1	\N	t	manual	\N	\N	\N
442577	17804	3234	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442578	17804	3239	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442576	17804	3233	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:17	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442579	17804	3247	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442580	17804	3231	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442581	17804	3240	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442582	17804	3235	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442583	17804	3236	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442584	17804	3246	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442585	17804	3249	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442586	17804	3245	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442587	17804	3237	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442589	17804	3244	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442590	17804	3232	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442591	17804	3243	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442592	17804	3241	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442593	17804	3248	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442594	17804	3242	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442595	17804	3238	1	1	2025-10-07 23:11:20	23:11:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:11:20	2025-10-07 23:11:20	\N	1	\N	t	manual	\N	\N	\N
442597	17805	3231	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442598	17805	3232	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442599	17805	3233	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442600	17805	3234	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442601	17805	3235	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442602	17805	3236	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442603	17805	3237	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442604	17805	3238	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442605	17805	3239	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442606	17805	3240	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442607	17805	3241	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442608	17805	3242	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442609	17805	3243	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442610	17805	3244	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442611	17805	3245	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442612	17805	3246	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442613	17805	3247	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442614	17805	3248	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442615	17805	3249	2	1	2025-10-07 23:12:12	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:12:12	2025-10-07 23:12:12	\N	1	\N	t	manual	\N	\N	\N
442616	17806	3234	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442617	17806	3239	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442618	17806	3233	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442619	17806	3247	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442620	17806	3231	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442621	17806	3240	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442622	17806	3235	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442623	17806	3236	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442624	17806	3246	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442625	17806	3249	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442626	17806	3245	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442627	17806	3237	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442629	17806	3244	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442630	17806	3232	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442631	17806	3243	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442632	17806	3241	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442633	17806	3248	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442634	17806	3242	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442635	17806	3238	1	1	2025-10-07 23:17:13	23:17:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:17:13	2025-10-07 23:17:13	\N	1	\N	t	manual	\N	\N	\N
442636	17807	3239	1	1	2025-10-07 23:19:28	23:19:28	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:19:28	2025-10-07 23:19:28	\N	1	\N	t	manual	\N	\N	\N
442637	17807	3238	1	1	2025-10-07 23:19:38	23:19:37	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:19:38	2025-10-07 23:19:38	\N	1	\N	t	manual	\N	\N	\N
442638	17807	3240	1	1	2025-10-07 23:19:41	23:19:40	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:19:41	2025-10-07 23:19:41	\N	1	\N	t	manual	\N	\N	\N
442639	17807	3235	1	1	2025-10-07 23:19:55	23:19:55	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:19:55	2025-10-07 23:19:55	\N	1	\N	t	manual	\N	\N	\N
442640	17807	3237	1	1	2025-10-07 23:20:05	23:20:05	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:20:05	2025-10-07 23:20:05	\N	1	\N	t	manual	\N	\N	\N
442641	17807	3248	1	1	2025-10-07 23:20:08	23:20:07	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:20:08	2025-10-07 23:20:08	\N	1	\N	t	manual	\N	\N	\N
442642	17807	3245	1	1	2025-10-07 23:20:19	23:20:19	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:20:19	2025-10-07 23:20:19	\N	1	\N	t	manual	\N	\N	\N
442643	17807	3243	1	1	2025-10-07 23:20:46	23:20:45	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:20:46	2025-10-07 23:20:46	\N	1	\N	t	manual	\N	\N	\N
442644	17807	3244	1	1	2025-10-07 23:20:48	23:20:47	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:20:48	2025-10-07 23:20:48	\N	1	\N	t	manual	\N	\N	\N
442645	17807	3232	1	1	2025-10-07 23:20:53	23:20:53	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:20:53	2025-10-07 23:20:53	\N	1	\N	t	manual	\N	\N	\N
442646	17807	3241	1	1	2025-10-07 23:20:55	23:20:55	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:20:55	2025-10-07 23:20:55	\N	1	\N	t	manual	\N	\N	\N
442647	17807	3242	1	1	2025-10-07 23:20:57	23:20:57	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:20:57	2025-10-07 23:20:57	\N	1	\N	t	manual	\N	\N	\N
442648	17807	3233	1	1	2025-10-07 23:21:22	23:21:22	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:21:22	2025-10-07 23:21:22	\N	1	\N	t	manual	\N	\N	\N
442650	17807	3231	1	1	2025-10-07 23:21:57	23:21:56	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:21:57	2025-10-07 23:21:57	\N	1	\N	t	manual	\N	\N	\N
442651	17807	3247	1	1	2025-10-07 23:21:59	23:21:59	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:21:59	2025-10-07 23:21:59	\N	1	\N	t	manual	\N	\N	\N
442652	17807	3236	1	1	2025-10-07 23:22:01	23:22:01	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:22:01	2025-10-07 23:22:01	\N	1	\N	t	manual	\N	\N	\N
442653	17807	3246	1	1	2025-10-07 23:22:04	23:22:04	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:22:04	2025-10-07 23:22:04	\N	1	\N	t	manual	\N	\N	\N
442654	17807	3249	1	1	2025-10-07 23:22:08	23:22:07	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:22:08	2025-10-07 23:22:08	\N	1	\N	t	manual	\N	\N	\N
442655	17807	3234	1	1	2025-10-07 23:22:10	23:22:09	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:22:10	2025-10-07 23:22:10	\N	1	\N	t	manual	\N	\N	\N
442657	17808	3234	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442658	17808	3239	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442659	17808	3233	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442660	17808	3247	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442661	17808	3231	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442656	17808	3240	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:07	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442662	17808	3235	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442663	17808	3236	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442664	17808	3246	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442665	17808	3249	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442666	17808	3245	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442667	17808	3237	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442669	17808	3244	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442670	17808	3232	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442671	17808	3243	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442672	17808	3241	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442673	17808	3248	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442674	17808	3242	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442675	17808	3238	1	1	2025-10-07 23:25:16	23:25:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:16	2025-10-07 23:25:16	\N	1	\N	t	manual	\N	\N	\N
442676	17809	3234	1	1	2025-10-07 23:25:39	23:25:38	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:25:39	2025-10-07 23:25:39	\N	1	\N	t	manual	\N	\N	\N
442678	17809	3231	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442679	17809	3232	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442680	17809	3233	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442681	17809	3235	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442682	17809	3236	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442683	17809	3237	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442684	17809	3238	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442685	17809	3239	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442686	17809	3240	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442687	17809	3241	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442688	17809	3242	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442689	17809	3243	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442690	17809	3244	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442691	17809	3245	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442692	17809	3246	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442693	17809	3247	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442694	17809	3248	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442695	17809	3249	2	1	2025-10-07 23:25:51	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:25:51	2025-10-07 23:25:51	\N	1	\N	t	manual	\N	\N	\N
442697	17810	3231	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442698	17810	3232	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442699	17810	3233	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442700	17810	3234	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442701	17810	3235	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442702	17810	3236	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442703	17810	3237	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442704	17810	3238	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442705	17810	3239	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442706	17810	3240	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442707	17810	3241	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442708	17810	3242	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442709	17810	3243	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442710	17810	3244	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442711	17810	3245	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442712	17810	3246	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442713	17810	3247	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442714	17810	3248	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442715	17810	3249	2	1	2025-10-07 23:40:40	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:40:40	2025-10-07 23:40:40	\N	1	\N	t	manual	\N	\N	\N
442717	17811	3234	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442718	17811	3239	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442719	17811	3233	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442720	17811	3247	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442721	17811	3231	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442716	17811	3240	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:03	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442722	17811	3235	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442723	17811	3236	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442724	17811	3246	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442725	17811	3249	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442726	17811	3245	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442727	17811	3237	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442729	17811	3244	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442730	17811	3232	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442731	17811	3243	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442732	17811	3241	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442733	17811	3248	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442734	17811	3242	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442735	17811	3238	1	1	2025-10-07 23:41:17	23:41:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:41:17	2025-10-07 23:41:17	\N	1	\N	t	manual	\N	\N	\N
442737	17812	3231	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442738	17812	3232	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442739	17812	3233	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442740	17812	3234	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442741	17812	3235	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442742	17812	3236	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442743	17812	3237	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442744	17812	3238	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442745	17812	3239	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442746	17812	3240	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442747	17812	3241	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442748	17812	3242	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442749	17812	3243	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442750	17812	3244	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442751	17812	3245	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442752	17812	3246	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442753	17812	3247	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442754	17812	3248	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442755	17812	3249	2	1	2025-10-07 23:43:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-07 23:43:04	2025-10-07 23:43:04	\N	1	\N	t	manual	\N	\N	\N
442776	17814	3404	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:30	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442777	17814	3400	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:31	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442778	17814	3411	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442780	17814	3413	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442781	17814	3415	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442782	17814	3401	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442783	17814	3410	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442784	17814	3416	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442785	17814	3406	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442786	17814	3419	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442787	17814	3417	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442788	17814	3407	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442789	17814	3409	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442790	17814	3418	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442791	17814	3397	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442792	17814	3402	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442793	17814	3414	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442794	17814	3405	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442795	17814	3399	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442796	17814	3398	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442797	17814	3412	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442798	17814	3408	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442799	17814	3420	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442800	17814	3403	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442801	17814	3421	1	2	2025-10-13 21:24:33	21:24:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 21:24:33	2025-10-13 21:24:33	\N	1	\N	t	manual	\N	\N	\N
442802	17815	3518	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442803	17815	3500	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442804	17815	3504	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442808	17815	3498	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442809	17815	3507	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442810	17815	3509	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442811	17815	3497	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442812	17815	3502	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442813	17815	3506	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442806	17815	3515	4	2	2025-10-13 22:13:45	22:13:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:45	\N	1	\N	t	manual	\N	15	\N
442807	17815	3495	3	2	2025-10-13 22:13:51	22:13:50	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:51	\N	1	\N	t	manual	\N	2	\N
442766	17813	3245	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:23	\N	1	\N	t	manual	\N	\N	\N
442767	17813	3237	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:24	\N	1	\N	t	manual	\N	\N	\N
442769	17813	3244	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:25	\N	1	\N	t	manual	\N	\N	\N
442770	17813	3232	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:26	\N	1	\N	t	manual	\N	\N	\N
442771	17813	3243	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:26	\N	1	\N	t	manual	\N	\N	\N
442772	17813	3241	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:27	\N	1	\N	t	manual	\N	\N	\N
442773	17813	3248	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:28	\N	1	\N	t	manual	\N	\N	\N
442774	17813	3242	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:29	\N	1	\N	t	manual	\N	\N	\N
442775	17813	3238	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:30	\N	1	\N	t	manual	\N	\N	\N
442758	17813	3239	2	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:41	2025-10-15 14:31:50	\N	1	\N	t	manual	\N	\N	\N
442759	17813	3233	2	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:42	2025-10-15 14:31:51	\N	1	\N	t	manual	\N	\N	\N
442760	17813	3247	2	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:51	\N	1	\N	t	manual	\N	\N	\N
442761	17813	3231	2	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:53	\N	1	\N	t	manual	\N	\N	\N
442756	17813	3240	2	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:32	2025-10-15 14:31:54	\N	1	\N	t	manual	\N	\N	\N
442762	17813	3235	2	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:54	\N	1	\N	t	manual	\N	\N	\N
442763	17813	3236	2	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:56	\N	1	\N	t	manual	\N	\N	\N
442764	17813	3246	2	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:56	\N	1	\N	t	manual	\N	\N	\N
442765	17813	3249	2	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:43	2025-10-15 14:31:57	\N	1	\N	t	manual	\N	\N	\N
442757	17813	3234	3	1	2025-10-07 23:43:43	23:43:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-07 23:43:39	2025-10-15 14:32:08	\N	1	\N	t	manual	\N	3	\N
442814	17815	3499	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442815	17815	3513	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442816	17815	3514	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442817	17815	3496	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442818	17815	3516	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442819	17815	3505	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442820	17815	3503	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442821	17815	3511	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442822	17815	3508	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442823	17815	3512	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442824	17815	3517	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442825	17815	3510	1	2	2025-10-13 22:13:41	22:13:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:41	\N	1	\N	t	manual	\N	\N	\N
442805	17815	3501	2	2	2025-10-13 22:13:42	22:13:42	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:13:41	2025-10-13 22:13:42	\N	1	\N	t	manual	\N	\N	\N
442826	17816	3518	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442827	17816	3500	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442828	17816	3504	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442829	17816	3501	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442830	17816	3515	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442831	17816	3495	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442832	17816	3498	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442833	17816	3507	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442834	17816	3509	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442835	17816	3497	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442836	17816	3502	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442837	17816	3506	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442838	17816	3499	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442839	17816	3513	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442840	17816	3514	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442841	17816	3496	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442842	17816	3516	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442843	17816	3505	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442844	17816	3503	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442845	17816	3511	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442846	17816	3508	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442847	17816	3512	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442848	17816	3517	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442849	17816	3510	1	2	2025-10-13 22:25:08	22:25:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 22:25:08	2025-10-13 22:25:08	\N	1	\N	t	manual	\N	\N	\N
442850	17817	3404	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442851	17817	3400	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442852	17817	3411	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442853	17817	3413	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442854	17817	3415	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442855	17817	3401	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442856	17817	3410	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442857	17817	3416	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442858	17817	3406	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442859	17817	3419	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442860	17817	3417	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442861	17817	3407	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442862	17817	3409	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442863	17817	3418	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442864	17817	3397	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442865	17817	3402	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442866	17817	3414	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442867	17817	3405	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442868	17817	3399	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442869	17817	3398	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442870	17817	3412	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442871	17817	3408	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442872	17817	3420	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442873	17817	3403	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442874	17817	3421	1	2	2025-10-13 23:33:42	23:33:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:33:42	2025-10-13 23:33:42	\N	1	\N	t	manual	\N	\N	\N
442876	17818	3404	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442877	17818	3400	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442878	17818	3411	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442879	17818	3413	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442880	17818	3415	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442881	17818	3401	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442882	17818	3410	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442883	17818	3416	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442884	17818	3406	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442885	17818	3419	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442886	17818	3417	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442887	17818	3407	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442888	17818	3409	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442889	17818	3418	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442890	17818	3397	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442891	17818	3402	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442892	17818	3414	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442893	17818	3405	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442894	17818	3399	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442895	17818	3398	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442896	17818	3412	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442897	17818	3408	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442898	17818	3420	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442899	17818	3403	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442900	17818	3421	1	2	2025-10-13 23:38:34	23:38:33	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:38:34	2025-10-13 23:38:34	\N	1	\N	t	manual	\N	\N	\N
442902	17819	3397	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442903	17819	3398	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442904	17819	3399	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442905	17819	3400	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442906	17819	3401	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442907	17819	3402	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442908	17819	3403	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442909	17819	3404	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442910	17819	3405	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442911	17819	3406	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442912	17819	3407	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442914	17819	3408	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442915	17819	3409	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442916	17819	3410	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442917	17819	3411	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442918	17819	3412	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442919	17819	3413	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442920	17819	3414	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442921	17819	3415	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442922	17819	3416	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442923	17819	3417	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442924	17819	3418	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442925	17819	3419	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442926	17819	3420	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442927	17819	3421	2	2	2025-10-13 23:47:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:47:32	2025-10-13 23:47:32	\N	1	\N	t	manual	\N	\N	\N
442928	17820	3404	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442929	17820	3400	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442930	17820	3411	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442931	17820	3413	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442932	17820	3415	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442933	17820	3401	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442934	17820	3410	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442935	17820	3416	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442936	17820	3406	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442937	17820	3419	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442938	17820	3417	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442939	17820	3407	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442940	17820	3409	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442941	17820	3418	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442942	17820	3397	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442943	17820	3402	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442944	17820	3414	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442945	17820	3405	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442946	17820	3399	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442947	17820	3398	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442948	17820	3412	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442949	17820	3408	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442950	17820	3420	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442951	17820	3403	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442952	17820	3421	1	2	2025-10-13 23:51:20	23:51:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:51:20	2025-10-13 23:51:20	\N	1	\N	t	manual	\N	\N	\N
442953	17820	3396	2	2	2025-10-13 23:51:22	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-13 23:51:22	2025-10-13 23:51:22	\N	1	\N	t	manual	\N	\N	\N
442954	17821	3404	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442955	17821	3400	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442956	17821	3411	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442957	17821	3413	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442958	17821	3415	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442959	17821	3401	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442960	17821	3410	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442961	17821	3416	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442962	17821	3406	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442963	17821	3419	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442964	17821	3417	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442965	17821	3407	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442966	17821	3409	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442967	17821	3418	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442968	17821	3397	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442969	17821	3402	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442970	17821	3414	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442971	17821	3405	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442972	17821	3399	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442973	17821	3398	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442974	17821	3412	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442975	17821	3408	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442976	17821	3420	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442977	17821	3403	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442978	17821	3421	1	2	2025-10-13 23:55:08	23:55:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:55:08	2025-10-13 23:55:08	\N	1	\N	t	manual	\N	\N	\N
442979	17822	3404	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442980	17822	3411	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442981	17822	3413	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442982	17822	3415	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442983	17822	3401	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442984	17822	3410	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442985	17822	3416	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442986	17822	3406	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442987	17822	3419	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442988	17822	3417	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442989	17822	3407	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442990	17822	3409	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442991	17822	3418	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442992	17822	3397	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442993	17822	3402	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442994	17822	3414	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442995	17822	3405	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442996	17822	3399	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442997	17822	3398	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442998	17822	3412	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
442999	17822	3408	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
443000	17822	3420	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
443001	17822	3403	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
443002	17822	3421	1	2	2025-10-13 23:56:35	23:56:35	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:35	2025-10-13 23:56:35	\N	1	\N	t	manual	\N	\N	\N
443003	17823	3518	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443004	17823	3500	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443005	17823	3504	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443006	17823	3501	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443007	17823	3515	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443008	17823	3495	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443009	17823	3498	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443010	17823	3507	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443011	17823	3509	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443012	17823	3497	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443013	17823	3502	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443014	17823	3506	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443015	17823	3499	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443016	17823	3513	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443017	17823	3514	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443018	17823	3496	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443019	17823	3516	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443020	17823	3505	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443021	17823	3503	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443022	17823	3511	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443023	17823	3508	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443024	17823	3512	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443025	17823	3517	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443026	17823	3510	1	2	2025-10-13 23:56:58	23:56:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-13 23:56:58	2025-10-13 23:56:58	\N	1	\N	t	manual	\N	\N	\N
443027	17824	3404	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443028	17824	3400	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443029	17824	3411	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443030	17824	3413	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443031	17824	3415	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443032	17824	3401	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443033	17824	3410	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443034	17824	3416	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443035	17824	3406	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443036	17824	3419	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443037	17824	3417	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443038	17824	3407	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443039	17824	3409	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443040	17824	3418	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443041	17824	3397	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443042	17824	3402	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443043	17824	3414	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443044	17824	3405	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443045	17824	3399	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443046	17824	3398	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443047	17824	3412	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443048	17824	3408	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443049	17824	3420	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443050	17824	3403	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443051	17824	3421	1	2	2025-10-14 02:06:18	02:06:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-14 02:06:18	2025-10-14 02:06:18	\N	1	\N	t	manual	\N	\N	\N
443052	17825	3239	1	1	2025-10-15 14:35:29	14:35:27	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 14:35:29	2025-10-15 14:35:29	\N	1	\N	t	manual	\N	\N	\N
443053	17825	3234	1	1	2025-10-15 14:36:29	14:35:38	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 14:36:29	2025-10-15 14:36:29	\N	1	\N	t	manual	\N	\N	\N
443054	17825	3231	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443055	17825	3232	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443056	17825	3233	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443057	17825	3235	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443058	17825	3236	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443059	17825	3237	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443060	17825	3238	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443061	17825	3240	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443062	17825	3241	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443063	17825	3242	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443064	17825	3243	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443065	17825	3244	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443066	17825	3245	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443067	17825	3246	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443068	17825	3247	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443069	17825	3248	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443070	17825	3249	2	1	2025-10-15 14:36:32	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 14:36:32	2025-10-15 14:36:32	\N	1	\N	t	manual	\N	\N	\N
443071	17826	3233	1	1	2025-10-15 15:06:29	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:26	2025-10-15 15:06:29	\N	1	\N	t	manual	\N	\N	\N
443075	17826	3240	1	1	2025-10-15 15:06:29	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:29	2025-10-15 15:06:29	\N	1	\N	t	manual	\N	\N	\N
443076	17826	3235	1	1	2025-10-15 15:06:29	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:29	2025-10-15 15:06:29	\N	1	\N	t	manual	\N	\N	\N
443077	17826	3236	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443078	17826	3246	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443079	17826	3249	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443080	17826	3245	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443081	17826	3237	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443082	17826	3244	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443083	17826	3232	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443084	17826	3243	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443085	17826	3241	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443086	17826	3248	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443087	17826	3242	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443088	17826	3238	1	1	2025-10-15 15:06:30	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:30	2025-10-15 15:06:30	\N	1	\N	t	manual	\N	\N	\N
443074	17826	3247	1	1	2025-10-15 15:06:35	15:06:34	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:29	2025-10-15 15:08:14	\N	1	\N	t	manual	\N	\N	\N
443072	17826	3234	3	1	2025-10-15 15:06:29	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:29	2025-10-15 15:08:38	\N	1	\N	t	manual	\N	10	\N
443073	17826	3239	2	1	2025-10-15 15:06:29	15:06:28	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:06:29	2025-10-15 15:08:49	\N	1	\N	t	manual	\N	\N	\N
443089	17827	3239	3	1	2025-10-15 15:27:49	15:27:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:27:49	2025-10-15 15:27:49	\N	1	\N	t	manual	\N	2	\N
443090	17827	3234	1	1	2025-10-15 15:27:53	15:27:52	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-15 15:27:53	2025-10-15 15:27:53	\N	1	\N	t	manual	\N	\N	\N
443091	17827	3235	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443092	17827	3236	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443093	17827	3237	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443094	17827	3238	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443095	17827	3240	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443096	17827	3241	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443097	17827	3242	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443098	17827	3243	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443099	17827	3244	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443100	17827	3245	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443101	17827	3246	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443102	17827	3247	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443103	17827	3248	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443104	17827	3249	2	1	2025-10-15 15:28:04	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-15 15:28:04	2025-10-15 15:28:04	\N	1	\N	t	manual	\N	\N	\N
443105	17828	3234	1	1	2025-10-17 12:29:43	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:43	2025-10-17 12:29:43	\N	1	\N	t	manual	\N	\N	\N
443106	17828	3239	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443108	17828	3247	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443109	17828	3240	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443110	17828	3235	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443111	17828	3236	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443112	17828	3246	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443113	17828	3249	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443114	17828	3245	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443115	17828	3237	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443116	17828	3244	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443117	17828	3243	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443118	17828	3241	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443119	17828	3248	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443120	17828	3242	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443121	17828	3238	1	1	2025-10-17 12:29:44	12:29:43	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:44	\N	1	\N	t	manual	\N	\N	\N
443107	17828	3540	2	1	2025-10-17 12:29:45	12:29:44	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-17 12:29:44	2025-10-17 12:29:45	\N	1	\N	t	manual	\N	\N	\N
443122	17829	3234	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443123	17829	3235	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443124	17829	3236	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443125	17829	3237	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443126	17829	3238	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443127	17829	3239	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443128	17829	3240	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443129	17829	3241	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443130	17829	3242	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443131	17829	3243	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443132	17829	3244	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443133	17829	3245	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443134	17829	3246	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443135	17829	3247	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443136	17829	3248	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443137	17829	3249	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443138	17829	3540	2	1	2025-10-22 22:23:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:23:13	2025-10-22 22:23:13	\N	1	\N	t	manual	\N	\N	\N
443139	17830	3234	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443140	17830	3235	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443141	17830	3236	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443142	17830	3237	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443143	17830	3238	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443144	17830	3239	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443145	17830	3240	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443146	17830	3241	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443147	17830	3242	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443148	17830	3243	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443149	17830	3244	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443150	17830	3245	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443151	17830	3246	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443152	17830	3247	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443153	17830	3248	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443154	17830	3249	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443155	17830	3540	2	1	2025-10-22 22:33:08	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:33:08	2025-10-22 22:33:08	\N	1	\N	t	manual	\N	\N	\N
443156	17831	3234	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443157	17831	3235	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443158	17831	3236	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443159	17831	3237	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443160	17831	3238	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443161	17831	3239	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443162	17831	3240	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443163	17831	3241	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443164	17831	3242	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443165	17831	3243	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443166	17831	3244	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443167	17831	3245	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443168	17831	3246	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443169	17831	3247	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443170	17831	3248	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443171	17831	3249	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443172	17831	3540	2	1	2025-10-22 22:39:13	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-22 22:39:13	2025-10-22 22:39:13	\N	1	\N	t	manual	\N	\N	\N
443173	17832	3237	1	1	2025-10-22 22:45:29	22:45:29	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:29	2025-10-22 22:45:29	\N	1	\N	t	manual	\N	\N	\N
443174	17832	3245	1	1	2025-10-22 22:45:31	22:45:30	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:31	2025-10-22 22:45:31	\N	1	\N	t	manual	\N	\N	\N
443175	17832	3249	1	1	2025-10-22 22:45:33	22:45:32	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:33	2025-10-22 22:45:33	\N	1	\N	t	manual	\N	\N	\N
443176	17832	3246	1	1	2025-10-22 22:45:34	22:45:33	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:34	2025-10-22 22:45:34	\N	1	\N	t	manual	\N	\N	\N
443177	17832	3244	1	1	2025-10-22 22:45:38	22:45:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:38	2025-10-22 22:45:38	\N	1	\N	t	manual	\N	\N	\N
443178	17832	3540	1	1	2025-10-22 22:45:40	22:45:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:40	2025-10-22 22:45:40	\N	1	\N	t	manual	\N	\N	\N
443179	17832	3240	1	1	2025-10-22 22:45:41	22:45:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:41	2025-10-22 22:45:41	\N	1	\N	t	manual	\N	\N	\N
443180	17832	3247	1	1	2025-10-22 22:45:45	22:45:44	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:45	2025-10-22 22:45:45	\N	1	\N	t	manual	\N	\N	\N
443181	17832	3239	1	1	2025-10-22 22:45:46	22:45:46	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:46	2025-10-22 22:45:46	\N	1	\N	t	manual	\N	\N	\N
443182	17832	3234	1	1	2025-10-22 22:45:47	22:45:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:47	2025-10-22 22:45:47	\N	1	\N	t	manual	\N	\N	\N
443183	17832	3238	1	1	2025-10-22 22:45:49	22:45:49	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:49	2025-10-22 22:45:49	\N	1	\N	t	manual	\N	\N	\N
443184	17832	3235	1	1	2025-10-22 22:45:51	22:45:51	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:51	2025-10-22 22:45:51	\N	1	\N	t	manual	\N	\N	\N
443185	17832	3243	1	1	2025-10-22 22:45:53	22:45:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:53	2025-10-22 22:45:53	\N	1	\N	t	manual	\N	\N	\N
443186	17832	3241	1	1	2025-10-22 22:45:56	22:45:56	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:56	2025-10-22 22:45:56	\N	1	\N	t	manual	\N	\N	\N
443187	17832	3248	1	1	2025-10-22 22:45:58	22:45:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:58	2025-10-22 22:45:58	\N	1	\N	t	manual	\N	\N	\N
443188	17832	3236	1	1	2025-10-22 22:45:59	22:45:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:45:59	2025-10-22 22:45:59	\N	1	\N	t	manual	\N	\N	\N
443189	17832	3242	1	1	2025-10-22 22:46:00	22:46:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:46:00	2025-10-22 22:46:00	\N	1	\N	t	manual	\N	\N	\N
443190	17833	3237	1	1	2025-10-22 22:48:57	22:48:56	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:48:57	2025-10-22 22:48:57	\N	1	\N	t	manual	\N	\N	\N
443191	17833	3245	1	1	2025-10-22 22:48:58	22:48:58	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:48:58	2025-10-22 22:48:58	\N	1	\N	t	manual	\N	\N	\N
443192	17833	3247	1	1	2025-10-22 22:49:04	22:49:03	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:04	2025-10-22 22:49:04	\N	1	\N	t	manual	\N	\N	\N
443193	17833	3240	1	1	2025-10-22 22:49:05	22:49:04	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:05	2025-10-22 22:49:05	\N	1	\N	t	manual	\N	\N	\N
443194	17833	3540	1	1	2025-10-22 22:49:06	22:49:06	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:06	2025-10-22 22:49:06	\N	1	\N	t	manual	\N	\N	\N
443195	17833	3239	1	1	2025-10-22 22:49:08	22:49:07	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:08	2025-10-22 22:49:08	\N	1	\N	t	manual	\N	\N	\N
443196	17833	3234	1	1	2025-10-22 22:49:09	22:49:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:09	2025-10-22 22:49:09	\N	1	\N	t	manual	\N	\N	\N
443197	17833	3235	1	1	2025-10-22 22:49:10	22:49:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:10	2025-10-22 22:49:10	\N	1	\N	t	manual	\N	\N	\N
443198	17833	3236	1	1	2025-10-22 22:49:11	22:49:11	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:11	2025-10-22 22:49:11	\N	1	\N	t	manual	\N	\N	\N
443199	17833	3246	1	1	2025-10-22 22:49:13	22:49:12	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:13	2025-10-22 22:49:13	\N	1	\N	t	manual	\N	\N	\N
443200	17833	3249	1	1	2025-10-22 22:49:14	22:49:13	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:14	2025-10-22 22:49:14	\N	1	\N	t	manual	\N	\N	\N
443201	17833	3248	1	1	2025-10-22 22:49:15	22:49:14	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:15	2025-10-22 22:49:15	\N	1	\N	t	manual	\N	\N	\N
443202	17833	3241	1	1	2025-10-22 22:49:16	22:49:15	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:16	2025-10-22 22:49:16	\N	1	\N	t	manual	\N	\N	\N
443203	17833	3243	1	1	2025-10-22 22:49:16	22:49:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:16	2025-10-22 22:49:16	\N	1	\N	t	manual	\N	\N	\N
443204	17833	3244	1	1	2025-10-22 22:49:18	22:49:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:18	2025-10-22 22:49:18	\N	1	\N	t	manual	\N	\N	\N
443205	17833	3238	1	1	2025-10-22 22:49:19	22:49:19	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:19	2025-10-22 22:49:19	\N	1	\N	t	manual	\N	\N	\N
443206	17833	3242	1	1	2025-10-22 22:49:20	22:49:20	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-22 22:49:20	2025-10-22 22:49:20	\N	1	\N	t	manual	\N	\N	\N
443207	17834	3234	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443208	17834	3235	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443209	17834	3236	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443210	17834	3237	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443211	17834	3238	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443212	17834	3239	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443213	17834	3240	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443214	17834	3241	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443215	17834	3242	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443216	17834	3243	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443217	17834	3244	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443218	17834	3245	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443219	17834	3246	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443220	17834	3247	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443221	17834	3248	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443222	17834	3249	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443223	17834	3540	2	1	2025-10-23 00:23:57	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:23:57	2025-10-23 00:23:57	\N	1	\N	t	manual	\N	\N	\N
443224	17835	3234	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443225	17835	3239	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443226	17835	3540	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443227	17835	3247	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443228	17835	3240	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443229	17835	3235	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443230	17835	3236	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443231	17835	3246	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443232	17835	3249	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443233	17835	3245	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443234	17835	3237	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443235	17835	3244	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443236	17835	3243	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443237	17835	3241	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443238	17835	3248	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443239	17835	3242	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443240	17835	3238	1	1	2025-10-23 00:24:08	00:24:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 00:24:08	2025-10-23 00:24:08	\N	1	\N	t	manual	\N	\N	\N
443241	17836	3234	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443242	17836	3235	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443243	17836	3236	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443244	17836	3237	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443245	17836	3238	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443246	17836	3239	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443247	17836	3240	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443248	17836	3241	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443249	17836	3242	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443250	17836	3243	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443251	17836	3244	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443252	17836	3245	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443253	17836	3246	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443254	17836	3247	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443255	17836	3248	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443256	17836	3249	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443257	17836	3540	2	1	2025-10-23 00:32:47	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 00:32:47	2025-10-23 00:32:47	\N	1	\N	t	manual	\N	\N	\N
443258	17837	3234	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443260	17837	3540	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443261	17837	3247	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443262	17837	3240	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443263	17837	3235	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443264	17837	3236	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443265	17837	3246	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443266	17837	3249	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443267	17837	3245	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443268	17837	3237	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443269	17837	3244	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443270	17837	3243	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443271	17837	3241	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443272	17837	3248	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443273	17837	3242	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443274	17837	3238	1	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:01:09	\N	1	\N	t	manual	\N	\N	\N
443259	17837	3239	4	1	2025-10-23 11:01:09	11:01:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:01:09	2025-10-23 11:03:41	\N	1	\N	t	manual	\N	\N	\N
443275	17838	3234	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443276	17838	3235	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443277	17838	3236	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443278	17838	3237	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443279	17838	3238	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443280	17838	3239	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443281	17838	3240	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443282	17838	3241	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443283	17838	3242	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443284	17838	3243	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443285	17838	3244	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443286	17838	3245	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443287	17838	3246	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443288	17838	3247	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443289	17838	3248	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443290	17838	3249	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443291	17838	3540	2	1	2025-10-23 11:09:56	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:09:56	2025-10-23 11:09:56	\N	1	\N	t	manual	\N	\N	\N
443292	17839	3234	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443293	17839	3235	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443294	17839	3236	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443295	17839	3237	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443296	17839	3238	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443297	17839	3239	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443298	17839	3240	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443299	17839	3241	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443300	17839	3242	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443301	17839	3243	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443302	17839	3244	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443303	17839	3245	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443304	17839	3246	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443305	17839	3247	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443306	17839	3248	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443307	17839	3249	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443308	17839	3540	2	1	2025-10-23 11:13:29	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:13:29	2025-10-23 11:13:29	\N	1	\N	t	manual	\N	\N	\N
443309	17840	3234	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443310	17840	3235	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443311	17840	3236	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443312	17840	3237	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443313	17840	3238	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443314	17840	3239	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443315	17840	3240	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443316	17840	3241	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443317	17840	3242	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443318	17840	3243	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443319	17840	3244	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443320	17840	3245	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443321	17840	3246	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443322	17840	3247	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443323	17840	3248	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443324	17840	3249	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443325	17840	3540	2	1	2025-10-23 11:16:55	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 11:16:55	2025-10-23 11:16:55	\N	1	\N	t	manual	\N	\N	\N
443331	17841	3234	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:39	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443330	17841	3239	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:38	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443326	17841	3540	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:35	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443327	17841	3247	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:36	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443328	17841	3240	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:37	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443329	17841	3235	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:37	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443333	17841	3236	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:42	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443332	17841	3246	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:41	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443334	17841	3249	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:44	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443335	17841	3245	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:47	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443336	17841	3237	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:47	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443337	17841	3244	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:47	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443338	17841	3243	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:47	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443339	17841	3241	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:47	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443340	17841	3248	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:47	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443341	17841	3242	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:47	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443342	17841	3238	1	1	2025-10-23 11:20:47	11:20:47	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:20:47	2025-10-23 11:20:47	\N	1	\N	t	manual	\N	\N	\N
443343	17842	3234	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:25:57	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443344	17842	3239	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:25:57	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443345	17842	3540	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:25:58	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443346	17842	3247	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:25:58	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443347	17842	3240	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443348	17842	3235	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443349	17842	3236	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443350	17842	3246	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443351	17842	3249	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443352	17842	3245	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443353	17842	3237	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443354	17842	3244	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443355	17842	3243	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443356	17842	3241	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443357	17842	3248	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443358	17842	3242	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443359	17842	3238	1	1	2025-10-23 11:26:00	11:26:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:26:00	2025-10-23 11:26:00	\N	1	\N	t	manual	\N	\N	\N
443360	17843	3234	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:22	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443361	17843	3239	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:23	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443362	17843	3540	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443363	17843	3247	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443364	17843	3240	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443365	17843	3235	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443366	17843	3236	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443367	17843	3246	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443368	17843	3249	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443369	17843	3245	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443370	17843	3237	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443371	17843	3244	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443372	17843	3243	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443373	17843	3241	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443374	17843	3248	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443375	17843	3242	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443376	17843	3238	1	1	2025-10-23 11:30:25	11:30:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:30:25	2025-10-23 11:30:25	\N	1	\N	t	manual	\N	\N	\N
443379	17844	3540	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443380	17844	3247	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443381	17844	3240	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443382	17844	3235	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443383	17844	3236	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443384	17844	3246	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443385	17844	3249	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443386	17844	3245	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443387	17844	3237	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443388	17844	3244	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443389	17844	3243	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443390	17844	3241	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443391	17844	3248	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443378	17844	3239	2	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 14:56:16	\N	1	\N	t	manual	\N	\N	\N
443392	17844	3242	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443393	17844	3238	1	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 11:31:53	\N	1	\N	t	manual	\N	\N	\N
443377	17844	3234	2	1	2025-10-23 11:31:53	11:31:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 11:31:53	2025-10-23 14:55:37	\N	1	\N	t	manual	\N	\N	\N
443394	17845	3235	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443395	17845	3236	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443396	17845	3237	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443397	17845	3238	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443398	17845	3239	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443399	17845	3240	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443400	17845	3241	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443401	17845	3242	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443402	17845	3243	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443403	17845	3244	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443404	17845	3245	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443405	17845	3246	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443406	17845	3247	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443407	17845	3248	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443408	17845	3249	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443409	17845	3540	2	1	2025-10-23 15:05:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 15:05:00	2025-10-23 15:05:00	\N	1	\N	t	manual	\N	\N	\N
443410	17846	3235	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443411	17846	3236	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443412	17846	3237	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443413	17846	3238	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443414	17846	3239	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443415	17846	3240	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443416	17846	3241	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443417	17846	3242	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443418	17846	3243	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443419	17846	3244	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443420	17846	3245	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443421	17846	3246	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443422	17846	3247	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443423	17846	3248	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443424	17846	3249	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443425	17846	3540	2	1	2025-10-23 16:02:37	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:02:37	2025-10-23 16:02:37	\N	1	\N	t	manual	\N	\N	\N
443426	17847	3235	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443427	17847	3236	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443428	17847	3237	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443429	17847	3238	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443430	17847	3239	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443431	17847	3240	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443432	17847	3241	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443433	17847	3242	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443434	17847	3243	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443435	17847	3244	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443436	17847	3245	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443437	17847	3246	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443438	17847	3247	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443439	17847	3248	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443440	17847	3249	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443441	17847	3540	2	1	2025-10-23 16:09:31	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:09:31	2025-10-23 16:09:31	\N	1	\N	t	manual	\N	\N	\N
443442	17848	3247	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443443	17848	3240	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443444	17848	3235	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443445	17848	3236	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443446	17848	3246	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443447	17848	3249	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443448	17848	3245	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443449	17848	3237	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443450	17848	3244	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443451	17848	3243	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443452	17848	3241	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443453	17848	3248	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443454	17848	3242	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443455	17848	3238	1	1	2025-10-23 16:12:16	16:12:16	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-23 16:12:16	2025-10-23 16:12:16	\N	1	\N	t	manual	\N	\N	\N
443456	17848	3239	2	1	2025-10-23 16:12:22	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:12:22	2025-10-23 16:12:22	\N	1	\N	t	manual	\N	\N	\N
443457	17848	3540	2	1	2025-10-23 16:12:22	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-23 16:12:22	2025-10-23 16:12:22	\N	1	\N	t	manual	\N	\N	\N
443459	17849	3540	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:33	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443460	17849	3247	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:34	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443461	17849	3240	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:35	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443462	17849	3235	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:36	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443463	17849	3236	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:38	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443466	17849	3246	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:58	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443467	17849	3249	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:58	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443468	17849	3245	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:58	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443469	17849	3237	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:58	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443465	17849	3244	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:54	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443464	17849	3243	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:50	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443470	17849	3241	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:58	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443471	17849	3248	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:58	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443472	17849	3242	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:58	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443473	17849	3238	1	1	2025-10-24 08:57:58	08:57:57	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:58	2025-10-24 08:57:58	\N	1	\N	t	manual	\N	\N	\N
443458	17849	3239	1	1	2025-10-24 08:58:00	08:57:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 08:57:32	2025-10-24 08:58:00	\N	1	\N	t	manual	\N	\N	\N
443479	17850	3236	1	1	2025-10-24 09:01:47	09:01:11	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:47	2025-10-24 09:01:47	\N	1	\N	t	manual	\N	\N	\N
443485	17850	3243	1	1	2025-10-24 09:01:48	09:01:11	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:48	2025-10-24 09:01:48	\N	1	\N	t	manual	\N	\N	\N
443486	17850	3241	1	1	2025-10-24 09:01:48	09:01:11	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:48	2025-10-24 09:01:48	\N	1	\N	t	manual	\N	\N	\N
443474	17850	3239	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:00:18	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443475	17850	3540	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:47	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443476	17850	3247	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:47	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443477	17850	3240	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:47	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443478	17850	3235	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:47	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443480	17850	3246	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:47	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443481	17850	3249	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:47	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443482	17850	3245	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:47	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443483	17850	3237	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:48	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443484	17850	3244	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:48	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443487	17850	3248	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:48	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443488	17850	3242	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:48	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443489	17850	3238	1	1	2025-10-24 15:04:42	15:04:41	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-24 09:01:48	2025-10-24 15:04:42	\N	1	\N	t	manual	\N	\N	\N
443490	17851	3518	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443491	17851	3500	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443492	17851	3504	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443493	17851	3501	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443494	17851	3515	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443496	17851	3498	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443497	17851	3507	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443498	17851	3509	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443499	17851	3497	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443500	17851	3502	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443501	17851	3506	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443502	17851	3499	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443503	17851	3513	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443504	17851	3514	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443505	17851	3496	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443506	17851	3516	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443507	17851	3505	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443508	17851	3503	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443509	17851	3511	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443510	17851	3508	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443511	17851	3512	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443512	17851	3517	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443513	17851	3510	1	2	2025-10-25 22:25:18	22:25:17	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:18	\N	1	\N	t	manual	\N	\N	\N
443495	17851	3495	2	2	2025-10-25 22:25:26	22:25:26	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:25:18	2025-10-25 22:25:26	\N	1	\N	t	manual	\N	\N	\N
443514	17852	3239	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:32:23	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443517	17852	3540	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:39:56	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443515	17852	3247	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:36:07	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443516	17852	3240	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:38:11	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443518	17852	3235	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:39:56	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443519	17852	3236	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:39:56	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443520	17852	3237	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:39:56	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443521	17852	3244	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:39:56	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443522	17852	3243	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:39:56	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443523	17852	3248	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:39:56	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443524	17852	3242	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:39:56	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443525	17852	3238	1	1	2025-10-25 22:39:56	22:39:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-25 22:39:56	2025-10-25 22:39:56	\N	1	\N	t	manual	\N	\N	\N
443526	17852	3245	2	1	2025-10-25 22:39:58	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-25 22:39:58	2025-10-25 22:39:58	\N	1	\N	t	manual	\N	\N	\N
443527	17852	3246	2	1	2025-10-25 22:39:58	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-25 22:39:58	2025-10-25 22:39:58	\N	1	\N	t	manual	\N	\N	\N
443528	17852	3249	2	1	2025-10-25 22:39:58	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-25 22:39:58	2025-10-25 22:39:58	\N	1	\N	t	manual	\N	\N	\N
443529	17853	3238	1	1	2025-10-26 12:49:54	12:49:53	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:49:54	2025-10-26 12:49:54	\N	1	\N	t	manual	\N	\N	\N
443530	17853	3242	1	1	2025-10-26 12:49:55	12:49:54	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:49:55	2025-10-26 12:49:55	\N	1	\N	t	manual	\N	\N	\N
443531	17853	3248	1	1	2025-10-26 12:49:56	12:49:55	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:49:56	2025-10-26 12:49:56	\N	1	\N	t	manual	\N	\N	\N
443532	17853	3243	1	1	2025-10-26 12:49:57	12:49:56	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:49:57	2025-10-26 12:49:57	\N	1	\N	t	manual	\N	\N	\N
443533	17853	3244	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443534	17853	3237	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443535	17853	3245	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443536	17853	3249	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443537	17853	3246	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443538	17853	3236	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443539	17853	3235	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443540	17853	3240	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443541	17853	3247	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443542	17853	3540	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443543	17853	3239	1	1	2025-10-26 12:50:00	12:49:59	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:00	2025-10-26 12:50:00	\N	1	\N	t	manual	\N	\N	\N
443544	17854	3240	1	1	2025-10-26 12:50:41	12:50:39	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:41	2025-10-26 12:50:41	\N	1	\N	t	manual	\N	\N	\N
443545	17854	3236	1	1	2025-10-26 12:50:47	12:50:46	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:47	2025-10-26 12:50:47	\N	1	\N	t	manual	\N	\N	\N
443546	17854	3246	1	1	2025-10-26 12:50:52	12:50:51	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:52	2025-10-26 12:50:52	\N	1	\N	t	manual	\N	\N	\N
443547	17854	3238	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443548	17854	3242	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443549	17854	3248	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443550	17854	3243	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443551	17854	3244	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443552	17854	3237	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443553	17854	3245	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443554	17854	3249	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443555	17854	3235	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443556	17854	3247	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443557	17854	3540	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443558	17854	3239	1	1	2025-10-26 12:50:59	12:50:58	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 12:50:59	2025-10-26 12:50:59	\N	1	\N	t	manual	\N	\N	\N
443559	17855	3249	1	1	2025-10-26 13:12:41	13:12:40	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:12:41	2025-10-26 13:12:41	\N	1	\N	t	manual	\N	\N	\N
443560	17855	3236	4	1	2025-10-26 13:12:55	13:12:54	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:12:55	2025-10-26 13:12:55	\N	1	\N	t	manual	\N	14	need followup
443561	17855	3244	2	1	2025-10-26 13:12:57	13:12:56	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:12:57	2025-10-26 13:12:57	\N	1	\N	t	manual	\N	\N	\N
443562	17855	3247	2	1	2025-10-26 13:13:01	13:13:00	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:13:01	2025-10-26 13:13:01	\N	1	\N	t	manual	\N	\N	\N
443563	17855	3238	1	1	2025-10-26 13:13:02	13:13:01	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:13:02	2025-10-26 13:13:02	\N	1	\N	t	manual	\N	\N	\N
443564	17855	3248	3	1	2025-10-26 13:13:10	13:13:08	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:13:10	2025-10-26 13:13:10	\N	1	\N	t	manual	\N	2	\N
443565	17855	3235	2	1	2025-10-26 13:13:15	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:13:15	2025-10-26 13:13:15	\N	1	\N	t	manual	\N	\N	\N
443566	17855	3237	2	1	2025-10-26 13:13:15	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:13:15	2025-10-26 13:13:15	\N	1	\N	t	manual	\N	\N	\N
443567	17855	3239	2	1	2025-10-26 13:13:15	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:13:15	2025-10-26 13:13:15	\N	1	\N	t	manual	\N	\N	\N
443568	17855	3240	2	1	2025-10-26 13:13:15	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:13:15	2025-10-26 13:13:15	\N	1	\N	t	manual	\N	\N	\N
443569	17855	3242	2	1	2025-10-26 13:13:15	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:13:15	2025-10-26 13:13:15	\N	1	\N	t	manual	\N	\N	\N
443570	17855	3243	2	1	2025-10-26 13:13:15	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:13:15	2025-10-26 13:13:15	\N	1	\N	t	manual	\N	\N	\N
443571	17855	3245	2	1	2025-10-26 13:13:15	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:13:15	2025-10-26 13:13:15	\N	1	\N	t	manual	\N	\N	\N
443572	17855	3246	2	1	2025-10-26 13:13:15	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:13:15	2025-10-26 13:13:15	\N	1	\N	t	manual	\N	\N	\N
443573	17855	3540	2	1	2025-10-26 13:13:15	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:13:15	2025-10-26 13:13:15	\N	1	\N	t	manual	\N	\N	\N
443574	17856	3247	1	1	2025-10-26 13:13:44	13:13:43	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:13:44	2025-10-26 13:13:44	\N	1	\N	t	manual	\N	\N	\N
443576	17856	3235	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443577	17856	3236	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443578	17856	3237	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443579	17856	3238	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443580	17856	3239	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443581	17856	3242	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443582	17856	3243	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443583	17856	3244	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443584	17856	3245	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443585	17856	3246	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443586	17856	3248	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443587	17856	3249	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443588	17856	3540	2	1	2025-10-26 13:14:00	\N	\N	Auto-marked absent when session completed	manual	\N	\N	f	\N	\N	\N	2025-10-26 13:14:00	2025-10-26 13:14:00	\N	1	\N	t	manual	\N	\N	\N
443575	17856	3240	3	1	2025-10-26 13:13:50	13:13:49	\N	QR Code scan	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:13:50	2025-10-26 13:17:12	\N	1	\N	t	manual	\N	2	\N
443589	17857	3238	2	1	2025-10-26 13:22:10	13:22:09	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:10	2025-10-26 13:22:10	\N	1	\N	t	manual	\N	\N	\N
443590	17857	3242	1	1	2025-10-26 13:22:11	13:22:10	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:11	2025-10-26 13:22:11	\N	1	\N	t	manual	\N	\N	\N
443591	17857	3248	4	1	2025-10-26 13:22:22	13:22:22	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:22	2025-10-26 13:22:22	\N	1	\N	t	manual	\N	14	measles
443592	17857	3243	3	1	2025-10-26 13:22:32	13:22:31	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:32	2025-10-26 13:22:32	\N	1	\N	t	manual	\N	2	\N
443593	17857	3244	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443594	17857	3237	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443595	17857	3245	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443596	17857	3249	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443597	17857	3246	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443598	17857	3236	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443599	17857	3235	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443600	17857	3240	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443601	17857	3247	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443602	17857	3540	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443603	17857	3239	1	1	2025-10-26 13:22:39	13:22:38	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:22:39	2025-10-26 13:22:39	\N	1	\N	t	manual	\N	\N	\N
443605	17858	3242	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443606	17858	3248	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443607	17858	3243	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443608	17858	3244	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443609	17858	3237	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443610	17858	3245	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443611	17858	3249	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443614	17858	3235	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443616	17858	3247	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443617	17858	3540	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443618	17858	3239	1	1	2025-10-26 13:23:46	13:23:45	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:46	\N	1	\N	t	manual	\N	\N	\N
443604	17858	3238	2	1	2025-10-26 13:23:48	13:23:48	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:49	\N	1	\N	t	manual	\N	\N	\N
443612	17858	3246	2	1	2025-10-26 13:23:51	13:23:50	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:51	\N	1	\N	t	manual	\N	\N	\N
443613	17858	3236	3	1	2025-10-26 13:23:57	13:23:56	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:23:57	\N	1	\N	t	manual	\N	2	\N
443615	17858	3240	4	1	2025-10-26 13:24:12	13:24:11	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-10-26 13:23:46	2025-10-26 13:24:12	\N	1	\N	t	manual	\N	14	chicken pox
443619	17860	3238	1	1	2025-11-05 20:16:19	20:16:18	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:19	2025-11-05 20:16:19	\N	1	\N	t	manual	\N	\N	\N
443620	17860	3242	1	1	2025-11-05 20:16:21	20:16:21	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:21	2025-11-05 20:16:21	\N	1	\N	t	manual	\N	\N	\N
443621	17860	3248	1	1	2025-11-05 20:16:22	20:16:22	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:22	2025-11-05 20:16:22	\N	1	\N	t	manual	\N	\N	\N
443622	17860	3243	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443623	17860	3244	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443624	17860	3237	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443625	17860	3245	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443626	17860	3249	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443627	17860	3246	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443628	17860	3236	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443629	17860	3235	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443630	17860	3240	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443631	17860	3247	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443632	17860	3540	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
443633	17860	3239	1	1	2025-11-05 20:16:25	20:16:24	\N	\N	manual	127.0.0.1	\N	f	\N	\N	\N	2025-11-05 20:16:25	2025-11-05 20:16:25	\N	1	\N	t	manual	\N	\N	\N
\.


--
-- Data for Name: attendance_session_edits; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_session_edits (id, session_id, edited_by_teacher_id, changes, edit_type, edit_reason, notes, edited_from_ip, metadata, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: attendance_session_stats; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_session_stats (id, session_id, total_students, marked_students, present_count, absent_count, late_count, excused_count, attendance_rate, detailed_stats, calculated_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: attendance_sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_sessions (id, teacher_id, section_id, subject_id, session_date, session_start_time, session_end_time, session_type, status, metadata, created_at, updated_at, completed_at, version, original_session_id, edit_reason, edit_notes, edited_by_teacher_id, edited_at, is_current_version, school_year_id, is_valid_school_day) FROM stdin;
17800	1	219	1	2025-10-07	19:29:38	19:31:11	regular	completed	[]	2025-10-07 19:29:38	2025-10-07 19:31:11	2025-10-07 19:31:11	1	\N	\N	\N	\N	\N	t	\N	t
17801	1	219	1	2025-10-07	19:43:55	19:44:18	regular	completed	[]	2025-10-07 19:43:55	2025-10-07 19:44:18	2025-10-07 19:44:18	1	\N	\N	\N	\N	\N	t	\N	t
17802	1	219	1	2025-10-07	19:51:04	19:51:20	regular	completed	[]	2025-10-07 19:51:04	2025-10-07 19:51:20	2025-10-07 19:51:20	1	\N	\N	\N	\N	\N	t	\N	t
17803	1	219	5	2025-10-07	19:55:05	19:55:12	regular	completed	[]	2025-10-07 19:55:05	2025-10-07 19:55:12	2025-10-07 19:55:12	1	\N	\N	\N	\N	\N	t	\N	t
17804	1	219	1	2025-10-07	23:10:32	23:11:33	regular	completed	[]	2025-10-07 23:10:32	2025-10-07 23:11:33	2025-10-07 23:11:33	1	\N	\N	\N	\N	\N	t	\N	t
17805	1	219	1	2025-10-07	23:11:57	23:12:12	regular	completed	[]	2025-10-07 23:11:57	2025-10-07 23:12:12	2025-10-07 23:12:12	1	\N	\N	\N	\N	\N	t	\N	t
17806	1	219	1	2025-10-07	23:16:27	23:17:16	regular	completed	[]	2025-10-07 23:16:27	2025-10-07 23:17:16	2025-10-07 23:17:16	1	\N	\N	\N	\N	\N	t	\N	t
17807	1	219	1	2025-10-07	23:19:08	23:22:18	regular	completed	[]	2025-10-07 23:19:08	2025-10-07 23:22:18	2025-10-07 23:22:18	1	\N	\N	\N	\N	\N	t	\N	t
17808	1	219	1	2025-10-07	23:24:53	23:25:19	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-07 23:24:53	2025-10-07 23:25:19	2025-10-07 23:25:19	1	\N	\N	\N	\N	\N	t	\N	t
17809	1	219	1	2025-10-07	23:25:33	23:25:51	regular	completed	{"attendanceMethod":"qr","createdVia":"QR Code Scanner"}	2025-10-07 23:25:33	2025-10-07 23:25:51	2025-10-07 23:25:51	1	\N	\N	\N	\N	\N	t	\N	t
17810	1	219	1	2025-10-07	23:40:27	23:40:40	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-07 23:40:27	2025-10-07 23:40:40	2025-10-07 23:40:40	1	\N	\N	\N	\N	\N	t	\N	t
17811	1	219	1	2025-10-07	23:40:46	23:41:21	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-07 23:40:46	2025-10-07 23:41:21	2025-10-07 23:41:21	1	\N	\N	\N	\N	\N	t	\N	t
17812	1	219	1	2025-10-07	23:41:28	23:43:04	regular	completed	{"attendanceMethod":null,"createdVia":"Manual Entry"}	2025-10-07 23:41:28	2025-10-07 23:43:04	2025-10-07 23:43:04	1	\N	\N	\N	\N	\N	t	\N	t
17813	1	219	1	2025-10-07	23:43:08	23:43:45	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-07 23:43:08	2025-10-07 23:43:45	2025-10-07 23:43:45	1	\N	\N	\N	\N	\N	t	\N	t
17814	2	226	1	2025-10-13	21:24:24	21:24:44	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 21:24:24	2025-10-13 21:24:44	2025-10-13 21:24:44	1	\N	\N	\N	\N	\N	t	\N	t
17815	2	230	3	2025-10-13	22:13:38	22:13:55	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 22:13:38	2025-10-13 22:13:55	2025-10-13 22:13:55	1	\N	\N	\N	\N	\N	t	\N	t
17816	2	230	5	2025-10-13	22:25:05	22:25:11	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 22:25:05	2025-10-13 22:25:11	2025-10-13 22:25:11	1	\N	\N	\N	\N	\N	t	\N	t
17817	2	226	1	2025-10-13	23:33:40	23:33:44	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 23:33:40	2025-10-13 23:33:44	2025-10-13 23:33:44	1	\N	\N	\N	\N	\N	t	\N	t
17818	2	226	1	2025-10-13	23:38:31	23:38:36	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 23:38:31	2025-10-13 23:38:36	2025-10-13 23:38:36	1	\N	\N	\N	\N	\N	t	\N	t
17819	2	226	1	2025-10-13	23:47:30	23:47:32	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 23:47:30	2025-10-13 23:47:32	2025-10-13 23:47:32	1	\N	\N	\N	\N	\N	t	\N	t
17820	2	226	1	2025-10-13	23:51:16	23:51:22	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 23:51:16	2025-10-13 23:51:22	2025-10-13 23:51:22	1	\N	\N	\N	\N	\N	t	\N	t
17821	2	226	1	2025-10-13	23:55:00	23:55:11	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 23:55:00	2025-10-13 23:55:11	2025-10-13 23:55:11	1	\N	\N	\N	\N	\N	t	\N	t
17822	2	226	1	2025-10-13	23:56:33	23:56:38	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 23:56:33	2025-10-13 23:56:38	2025-10-13 23:56:38	1	\N	\N	\N	\N	\N	t	\N	t
17823	2	230	3	2025-10-13	23:56:56	\N	regular	active	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-13 23:56:56	2025-10-13 23:56:56	\N	1	\N	\N	\N	\N	\N	t	\N	t
17824	2	226	1	2025-10-14	02:06:15	02:06:21	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-14 02:06:15	2025-10-14 02:06:21	2025-10-14 02:06:21	1	\N	\N	\N	\N	\N	t	\N	t
17825	1	219	1	2025-10-15	14:33:38	14:36:32	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-15 14:33:38	2025-10-15 14:36:32	2025-10-15 14:36:32	1	\N	\N	\N	\N	\N	t	\N	t
17826	1	219	1	2025-10-15	15:06:21	15:06:59	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-15 15:06:21	2025-10-15 15:06:59	2025-10-15 15:06:59	1	\N	\N	\N	\N	\N	t	\N	t
17827	1	219	1	2025-10-15	15:27:39	15:28:04	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-15 15:27:39	2025-10-15 15:28:04	2025-10-15 15:28:04	1	\N	\N	\N	\N	\N	t	\N	t
17828	1	219	1	2025-10-17	12:29:39	12:29:48	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-17 12:29:39	2025-10-17 12:29:48	2025-10-17 12:29:48	1	\N	\N	\N	\N	\N	t	\N	t
17829	1	219	1	2025-10-22	22:18:31	22:23:13	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-22 22:18:31	2025-10-22 22:23:13	2025-10-22 22:23:13	1	\N	\N	\N	\N	\N	t	\N	t
17830	1	219	1	2025-10-22	22:32:41	22:33:08	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-22 22:32:41	2025-10-22 22:33:08	2025-10-22 22:33:08	1	\N	\N	\N	\N	\N	t	\N	t
17831	1	219	1	2025-10-22	22:37:38	22:39:13	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-22 22:37:38	2025-10-22 22:39:13	2025-10-22 22:39:13	1	\N	\N	\N	\N	\N	t	\N	t
17832	1	219	1	2025-10-22	22:45:10	22:48:42	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-22 22:45:10	2025-10-22 22:48:42	2025-10-22 22:48:42	1	\N	\N	\N	\N	\N	t	\N	t
17833	1	219	1	2025-10-22	22:48:44	22:49:29	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-22 22:48:44	2025-10-22 22:49:29	2025-10-22 22:49:29	1	\N	\N	\N	\N	\N	t	\N	t
17834	1	219	1	2025-10-23	00:23:51	00:23:57	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 00:23:51	2025-10-23 00:23:57	2025-10-23 00:23:57	1	\N	\N	\N	\N	\N	t	\N	t
17835	1	219	1	2025-10-23	00:24:04	00:24:21	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 00:24:04	2025-10-23 00:24:21	2025-10-23 00:24:21	1	\N	\N	\N	\N	\N	t	\N	t
17836	1	219	1	2025-10-23	00:26:42	00:32:47	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 00:26:42	2025-10-23 00:32:47	2025-10-23 00:32:47	1	\N	\N	\N	\N	\N	t	\N	t
17837	1	219	5	2025-10-23	11:00:56	11:01:12	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 11:00:56	2025-10-23 11:01:12	2025-10-23 11:01:12	1	\N	\N	\N	\N	\N	t	\N	t
17838	1	219	5	2025-10-23	11:09:31	11:09:56	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 11:09:31	2025-10-23 11:09:56	2025-10-23 11:09:56	1	\N	\N	\N	\N	\N	t	\N	t
17839	1	219	5	2025-10-23	11:13:02	11:13:29	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 11:13:02	2025-10-23 11:13:29	2025-10-23 11:13:29	1	\N	\N	\N	\N	\N	t	\N	t
17840	1	219	5	2025-10-23	11:16:24	11:16:55	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 11:16:24	2025-10-23 11:16:55	2025-10-23 11:16:55	1	\N	\N	\N	\N	\N	t	\N	t
17841	1	219	5	2025-10-23	11:20:32	11:20:51	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 11:20:32	2025-10-23 11:20:51	2025-10-23 11:20:51	1	\N	\N	\N	\N	\N	t	\N	t
17842	1	219	1	2025-10-23	11:25:55	11:26:07	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 11:25:55	2025-10-23 11:26:07	2025-10-23 11:26:07	1	\N	\N	\N	\N	\N	t	\N	t
17843	1	219	1	2025-10-23	11:30:20	11:30:28	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 11:30:20	2025-10-23 11:30:28	2025-10-23 11:30:28	1	\N	\N	\N	\N	\N	t	\N	t
17844	1	219	1	2025-10-23	11:31:49	11:31:56	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 11:31:49	2025-10-23 11:31:56	2025-10-23 11:31:56	1	\N	\N	\N	\N	\N	t	\N	t
17845	1	219	1	2025-10-23	14:30:01	15:05:00	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 14:30:01	2025-10-23 15:05:00	2025-10-23 15:05:00	1	\N	\N	\N	\N	\N	t	\N	t
17846	1	219	1	2025-10-23	16:02:28	16:02:37	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 16:02:28	2025-10-23 16:02:37	2025-10-23 16:02:37	1	\N	\N	\N	\N	\N	t	\N	t
17847	1	219	1	2025-10-23	16:02:49	16:09:31	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 16:02:49	2025-10-23 16:09:31	2025-10-23 16:09:31	1	\N	\N	\N	\N	\N	t	\N	t
17848	1	219	5	2025-10-23	16:11:59	16:12:22	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-23 16:11:59	2025-10-23 16:12:22	2025-10-23 16:12:22	1	\N	\N	\N	\N	\N	t	\N	t
17849	1	219	1	2025-10-24	08:57:24	08:58:06	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-24 08:57:24	2025-10-24 08:58:06	2025-10-24 08:58:06	1	\N	\N	\N	\N	\N	t	\N	t
17850	1	219	1	2025-10-24	08:59:05	15:04:46	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-24 08:59:05	2025-10-24 15:04:46	2025-10-24 15:04:46	1	\N	\N	\N	\N	\N	t	\N	t
17851	2	230	3	2025-10-25	22:25:12	22:25:31	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-25 22:25:12	2025-10-25 22:25:31	2025-10-25 22:25:31	1	\N	\N	\N	\N	\N	t	\N	t
17852	1	219	1	2025-10-25	22:30:48	22:39:58	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-25 22:30:48	2025-10-25 22:39:58	2025-10-25 22:39:58	1	\N	\N	\N	\N	\N	t	\N	t
17853	1	219	1	2025-10-26	12:49:51	12:50:06	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-26 12:49:51	2025-10-26 12:50:06	2025-10-26 12:50:06	1	\N	\N	\N	\N	\N	t	\N	t
17854	1	219	1	2025-10-26	12:50:31	12:51:03	regular	completed	{"attendanceMethod":null,"createdVia":"Manual Entry"}	2025-10-26 12:50:31	2025-10-26 12:51:03	2025-10-26 12:51:03	1	\N	\N	\N	\N	\N	t	\N	t
17855	1	219	1	2025-10-26	13:12:37	13:13:15	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-26 13:12:37	2025-10-26 13:13:15	2025-10-26 13:13:15	1	\N	\N	\N	\N	\N	t	\N	t
17856	1	219	1	2025-10-26	13:13:32	13:14:00	regular	completed	{"attendanceMethod":null,"createdVia":"Manual Entry"}	2025-10-26 13:13:32	2025-10-26 13:14:00	2025-10-26 13:14:00	1	\N	\N	\N	\N	\N	t	\N	t
17857	1	219	1	2025-10-26	13:22:07	13:22:46	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-26 13:22:07	2025-10-26 13:22:46	2025-10-26 13:22:46	1	\N	\N	\N	\N	\N	t	\N	t
17858	1	219	1	2025-10-26	13:23:36	13:24:17	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-26 13:23:36	2025-10-26 13:24:17	2025-10-26 13:24:17	1	\N	\N	\N	\N	\N	t	\N	t
17859	1	219	1	2025-10-26	15:43:26	\N	regular	active	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-10-26 15:43:26	2025-10-26 15:43:26	\N	1	\N	\N	\N	\N	\N	t	\N	t
17860	1	219	1	2025-11-05	20:16:17	20:16:38	regular	completed	{"attendanceMethod":"seat_plan","createdVia":"Manual Entry"}	2025-11-05 20:16:17	2025-11-05 20:16:38	2025-11-05 20:16:38	1	\N	\N	\N	\N	\N	t	\N	t
\.


--
-- Data for Name: attendance_statuses; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_statuses (id, code, name, description, color, background_color, is_active, sort_order, created_at, updated_at) FROM stdin;
1	P	Present	Student is present in class	#FFFFFF	#10B981	t	1	2025-10-07 13:14:07	2025-10-07 13:14:07
2	A	Absent	Student is absent from class	#FFFFFF	#EF4444	t	2	2025-10-07 13:14:07	2025-10-07 13:14:07
3	L	Late	Student arrived late to class	#FFFFFF	#F59E0B	t	3	2025-10-07 13:14:07	2025-10-07 13:14:07
4	E	Excused	Student has an excused absence	#FFFFFF	#6366F1	t	4	2025-10-07 13:14:07	2025-10-07 13:14:07
\.


--
-- Data for Name: attendance_validation_rules; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance_validation_rules (id, rule_name, rule_type, rule_config, is_active, priority, description, created_at, updated_at) FROM stdin;
1	prevent_future_attendance	time_validation	{"max_future_days":0}	t	10	Prevent marking attendance for future dates	2025-10-07 13:14:07	2025-10-07 13:14:07
2	prevent_duplicate_records	duplicate_check	{"check_fields":["student_id","session_id"]}	t	20	Prevent duplicate attendance records for same student in same session	2025-10-07 13:14:07	2025-10-07 13:14:07
3	validate_session_time_range	time_validation	{"max_session_hours":8,"min_session_minutes":5}	t	30	Validate session duration is within reasonable limits	2025-10-07 13:14:07	2025-10-07 13:14:07
\.


--
-- Data for Name: attendances; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendances (id, student_id, subject_id, teacher_id, date, time_in, status, remarks, created_at, updated_at, marked_at, section_id, attendance_status_id) FROM stdin;
1	3237	1	1	2025-10-22	22:37:53	present	QR Code scan	2025-10-22 22:22:47	2025-10-22 22:37:53	2025-10-22 22:37:53	219	1
2	3245	1	1	2025-10-22	22:37:56	present	QR Code scan	2025-10-22 22:22:56	2025-10-22 22:37:56	2025-10-22 22:37:56	219	1
3	3249	1	1	2025-10-22	22:38:16	present	QR Code scan	2025-10-22 22:23:00	2025-10-22 22:38:16	2025-10-22 22:38:16	219	1
4	3246	1	1	2025-10-22	22:38:17	present	QR Code scan	2025-10-22 22:33:32	2025-10-22 22:38:17	2025-10-22 22:38:17	219	1
5	3236	1	1	2025-10-22	22:38:19	present	QR Code scan	2025-10-22 22:33:39	2025-10-22 22:38:19	2025-10-22 22:38:19	219	1
6	3235	1	1	2025-10-22	22:38:20	present	QR Code scan	2025-10-22 22:38:20	2025-10-22 22:38:20	2025-10-22 22:38:20	219	1
7	3240	1	1	2025-10-22	22:38:22	present	QR Code scan	2025-10-22 22:38:22	2025-10-22 22:38:22	2025-10-22 22:38:22	219	1
8	3247	1	1	2025-10-22	22:38:26	present	QR Code scan	2025-10-22 22:38:26	2025-10-22 22:38:26	2025-10-22 22:38:26	219	1
9	3239	1	1	2025-10-22	22:38:29	present	QR Code scan	2025-10-22 22:38:29	2025-10-22 22:38:29	2025-10-22 22:38:29	219	1
10	3234	1	1	2025-10-22	22:38:32	present	QR Code scan	2025-10-22 22:38:32	2025-10-22 22:38:32	2025-10-22 22:38:32	219	1
11	3238	1	1	2025-10-22	22:38:33	present	QR Code scan	2025-10-22 22:38:34	2025-10-22 22:38:34	2025-10-22 22:38:33	219	1
12	3242	1	1	2025-10-22	22:38:36	present	QR Code scan	2025-10-22 22:38:36	2025-10-22 22:38:36	2025-10-22 22:38:36	219	1
13	3248	1	1	2025-10-22	22:38:38	present	QR Code scan	2025-10-22 22:38:38	2025-10-22 22:38:38	2025-10-22 22:38:38	219	1
14	3241	1	1	2025-10-22	22:38:40	present	QR Code scan	2025-10-22 22:38:40	2025-10-22 22:38:40	2025-10-22 22:38:40	219	1
15	3243	1	1	2025-10-22	22:38:45	present	QR Code scan	2025-10-22 22:38:45	2025-10-22 22:38:45	2025-10-22 22:38:45	219	1
16	3244	1	1	2025-10-22	22:38:47	present	QR Code scan	2025-10-22 22:38:47	2025-10-22 22:38:47	2025-10-22 22:38:47	219	1
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache (key, value, expiration) FROM stdin;
lamms_cache_a75f3f172bfb296f2e10cbfc6dfc1883:timer	i:1762354766;	1762354766
lamms_cache_a75f3f172bfb296f2e10cbfc6dfc1883	i:1;	1762354766
lamms_cache_f7bf8617ef6233bab3da3fd031829e43:timer	i:1761454578;	1761454578
lamms_cache_e9b6cc1432541b9ceebf113eee05eeba:timer	i:1761402462;	1761402462
lamms_cache_e9b6cc1432541b9ceebf113eee05eeba	i:1;	1761402462
lamms_cache_f7bf8617ef6233bab3da3fd031829e43	i:1;	1761454578
lamms_cache_guardhouse_live_feed_2025-10-23_50	YTo0OntzOjc6InN1Y2Nlc3MiO2I6MTtzOjk6ImNoZWNrX2lucyI7TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6MDp7fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9czoxMDoiY2hlY2tfb3V0cyI7TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6MDp7fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9czo1OiJzdGF0cyI7YTo1OntzOjE1OiJ0b3RhbF9jaGVja19pbnMiO2k6MDtzOjE2OiJ0b3RhbF9jaGVja19vdXRzIjtpOjA7czoxNzoic2hvd2luZ19jaGVja19pbnMiO2k6MDtzOjE4OiJzaG93aW5nX2NoZWNrX291dHMiO2k6MDtzOjEzOiJsaW1pdF9hcHBsaWVkIjtpOjUwO319	1761204686
lamms_cache_guardhouse_live_feed_2025-10-24_50	YTo0OntzOjc6InN1Y2Nlc3MiO2I6MTtzOjk6ImNoZWNrX2lucyI7TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6MDp7fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9czoxMDoiY2hlY2tfb3V0cyI7TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6MDp7fXM6Mjg6IgAqAGVzY2FwZVdoZW5DYXN0aW5nVG9TdHJpbmciO2I6MDt9czo1OiJzdGF0cyI7YTo1OntzOjE1OiJ0b3RhbF9jaGVja19pbnMiO2k6MDtzOjE2OiJ0b3RhbF9jaGVja19vdXRzIjtpOjA7czoxNzoic2hvd2luZ19jaGVja19pbnMiO2k6MDtzOjE4OiJzaG93aW5nX2NoZWNrX291dHMiO2k6MDtzOjEzOiJsaW1pdF9hcHBsaWVkIjtpOjUwO319	1761272314
guardhouse_scanner_enabled	b:1;	1792990552
lamms_cache_feddc525b85478ec719eae9c595d27a9:timer	i:1761460937;	1761460937
lamms_cache_feddc525b85478ec719eae9c595d27a9	i:1;	1761460937
lamms_cache_guardhouse_live_feed_2025-10-26_50	YTo0OntzOjc6InN1Y2Nlc3MiO2I6MTtzOjk6ImNoZWNrX2lucyI7TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6Mjp7aTowO086ODoic3RkQ2xhc3MiOjc6e3M6MjoiaWQiO2k6MjU7czoxMDoic3R1ZGVudF9pZCI7aTozMjM5O3M6MTI6InN0dWRlbnRfbmFtZSI7czoxNDoiQW5nZWxvIEFndWlsYXIiO3M6MTE6ImdyYWRlX2xldmVsIjtzOjY6IktpbmRlciI7czo3OiJzZWN0aW9uIjtzOjg6Ikd1bWFtZWxhIjtzOjk6InRpbWVzdGFtcCI7czoxOToiMjAyNS0xMC0yNiAxMjo1Njo0MiI7czoxMToicmVjb3JkX3R5cGUiO3M6ODoiY2hlY2staW4iO31pOjE7Tzo4OiJzdGRDbGFzcyI6Nzp7czoyOiJpZCI7aToyNDtzOjEwOiJzdHVkZW50X2lkIjtpOjMyMzQ7czoxMjoic3R1ZGVudF9uYW1lIjtzOjEyOiJBbmdlbCBDYXN0cm8iO3M6MTE6ImdyYWRlX2xldmVsIjtzOjY6IktpbmRlciI7czo3OiJzZWN0aW9uIjtzOjg6Ikd1bWFtZWxhIjtzOjk6InRpbWVzdGFtcCI7czoxOToiMjAyNS0xMC0yNiAxMjo1NjoyOCI7czoxMToicmVjb3JkX3R5cGUiO3M6ODoiY2hlY2staW4iO319czoyODoiACoAZXNjYXBlV2hlbkNhc3RpbmdUb1N0cmluZyI7YjowO31zOjEwOiJjaGVja19vdXRzIjtPOjI5OiJJbGx1bWluYXRlXFN1cHBvcnRcQ29sbGVjdGlvbiI6Mjp7czo4OiIAKgBpdGVtcyI7YToxOntpOjA7Tzo4OiJzdGRDbGFzcyI6Nzp7czoyOiJpZCI7aToyNjtzOjEwOiJzdHVkZW50X2lkIjtpOjMyMzk7czoxMjoic3R1ZGVudF9uYW1lIjtzOjE0OiJBbmdlbG8gQWd1aWxhciI7czoxMToiZ3JhZGVfbGV2ZWwiO3M6NjoiS2luZGVyIjtzOjc6InNlY3Rpb24iO3M6ODoiR3VtYW1lbGEiO3M6OToidGltZXN0YW1wIjtzOjE5OiIyMDI1LTEwLTI2IDEyOjU3OjI0IjtzOjExOiJyZWNvcmRfdHlwZSI7czo5OiJjaGVjay1vdXQiO319czoyODoiACoAZXNjYXBlV2hlbkNhc3RpbmdUb1N0cmluZyI7YjowO31zOjU6InN0YXRzIjthOjU6e3M6MTU6InRvdGFsX2NoZWNrX2lucyI7aToyO3M6MTY6InRvdGFsX2NoZWNrX291dHMiO2k6MTtzOjE3OiJzaG93aW5nX2NoZWNrX2lucyI7aToyO3M6MTg6InNob3dpbmdfY2hlY2tfb3V0cyI7aToxO3M6MTM6ImxpbWl0X2FwcGxpZWQiO2k6NTA7fX0=	1761460866
lamms_cache_f1f70ec40aaa556905d4a030501c0ba4:timer	i:1762353303;	1762353303
lamms_cache_f1f70ec40aaa556905d4a030501c0ba4	i:1;	1762353303
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: class_schedules; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.class_schedules (id, teacher_id, section_id, subject_id, day_of_week, start_time, end_time, effective_from, effective_until, school_year, semester, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: curricula; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.curricula (id, name, start_year, end_year, is_active, status, description, created_at, updated_at, deleted_at) FROM stdin;
2	Naawan Central School Curriculum	2025	2026	t	Draft	Official curriculum for Naawan Central School following K-12 system	\N	\N	\N
1	DepEd Elementary Curriculum	2024	2025	t	Active	Department of Education Elementary Curriculum (Kindergarten to Grade 6)	2025-10-07 14:31:38	2025-10-07 14:31:38	\N
\.


--
-- Data for Name: curriculum_grade; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.curriculum_grade (id, curriculum_id, grade_id) FROM stdin;
71	1	1
72	1	2
73	1	3
74	1	4
75	1	5
76	1	6
77	1	7
\.


--
-- Data for Name: curriculum_grade_subject; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.curriculum_grade_subject (id, curriculum_id, grade_id, subject_id, units, hours_per_week, sequence_number, status, description, created_at, updated_at) FROM stdin;
417	1	1	157	1	1	1	active	\N	\N	\N
418	1	1	158	1	1	2	active	\N	\N	\N
419	1	1	159	1	1	3	active	\N	\N	\N
420	1	1	160	1	1	4	active	\N	\N	\N
421	1	1	161	1	1	5	active	\N	\N	\N
422	1	1	162	1	1	6	active	\N	\N	\N
423	1	1	163	1	1	7	active	\N	\N	\N
424	1	2	157	1	1	1	active	\N	\N	\N
425	1	2	158	1	1	2	active	\N	\N	\N
426	1	2	159	1	1	3	active	\N	\N	\N
427	1	2	164	1	1	4	active	\N	\N	\N
428	1	2	165	1	1	5	active	\N	\N	\N
429	1	2	166	1	1	6	active	\N	\N	\N
430	1	2	167	1	1	7	active	\N	\N	\N
431	1	3	157	1	1	1	active	\N	\N	\N
432	1	3	158	1	1	2	active	\N	\N	\N
433	1	3	159	1	1	3	active	\N	\N	\N
434	1	3	164	1	1	4	active	\N	\N	\N
435	1	3	165	1	1	5	active	\N	\N	\N
436	1	3	166	1	1	6	active	\N	\N	\N
437	1	3	167	1	1	7	active	\N	\N	\N
438	1	4	157	1	1	1	active	\N	\N	\N
439	1	4	158	1	1	2	active	\N	\N	\N
440	1	4	159	1	1	3	active	\N	\N	\N
441	1	4	164	1	1	4	active	\N	\N	\N
442	1	4	165	1	1	5	active	\N	\N	\N
443	1	4	166	1	1	6	active	\N	\N	\N
444	1	4	167	1	1	7	active	\N	\N	\N
445	1	5	157	1	1	1	active	\N	\N	\N
446	1	5	158	1	1	2	active	\N	\N	\N
447	1	5	159	1	1	3	active	\N	\N	\N
448	1	5	164	1	1	4	active	\N	\N	\N
449	1	5	165	1	1	5	active	\N	\N	\N
450	1	5	166	1	1	6	active	\N	\N	\N
451	1	5	167	1	1	7	active	\N	\N	\N
452	1	5	168	1	1	8	active	\N	\N	\N
453	1	6	157	1	1	1	active	\N	\N	\N
454	1	6	158	1	1	2	active	\N	\N	\N
455	1	6	159	1	1	3	active	\N	\N	\N
456	1	6	164	1	1	4	active	\N	\N	\N
457	1	6	165	1	1	5	active	\N	\N	\N
458	1	6	166	1	1	6	active	\N	\N	\N
459	1	6	167	1	1	7	active	\N	\N	\N
460	1	6	168	1	1	8	active	\N	\N	\N
461	1	7	157	1	1	1	active	\N	\N	\N
462	1	7	158	1	1	2	active	\N	\N	\N
463	1	7	159	1	1	3	active	\N	\N	\N
464	1	7	164	1	1	4	active	\N	\N	\N
465	1	7	165	1	1	5	active	\N	\N	\N
466	1	7	166	1	1	6	active	\N	\N	\N
467	1	7	167	1	1	7	active	\N	\N	\N
468	1	7	168	1	1	8	active	\N	\N	\N
\.


--
-- Data for Name: gate_attendance; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.gate_attendance (id, student_id, student_qr_code, type, scan_time, scan_date, gate_location, scanner_device, metadata, is_valid, remarks, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: grades; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.grades (id, code, name, level, description, display_order, is_active, created_at, updated_at, deleted_at) FROM stdin;
1	K	Kindergarten	Kinder	Kindergarten	0	t	2025-10-07 13:14:27	2025-10-07 13:14:27	\N
2	G1	Grade 1	Grade 1	Grade 1 - Basic Education	0	t	2025-10-07 13:14:27	2025-10-07 13:14:27	\N
3	G2	Grade 2	Grade 2	Grade 2 - Primary Education	0	t	2025-10-07 13:14:27	2025-10-07 13:14:27	\N
4	G3	Grade 3	Grade 3	Grade 3 - Primary Education	0	t	2025-10-07 13:14:27	2025-10-07 13:14:27	\N
5	G4	Grade 4	Grade 4	Grade 4 - Intermediate	0	t	2025-10-07 13:14:27	2025-10-07 13:14:27	\N
6	G5	Grade 5	Grade 5	Grade 5 - Intermediate	0	t	2025-10-07 13:14:27	2025-10-07 13:14:27	\N
7	G6	Grade 6	Grade 6	Grade 6 - Intermediate	0	t	2025-10-07 13:14:27	2025-10-07 13:14:27	\N
\.


--
-- Data for Name: guardhouse_archive_sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.guardhouse_archive_sessions (id, session_date, total_records, archived_at, archived_by, created_at, updated_at) FROM stdin;
1	2025-10-08	8	2025-10-08 01:53:03	1	2025-10-08 01:53:03	2025-10-08 01:53:03
2	2025-10-22	3	2025-10-22 22:58:45	1	2025-10-22 22:58:45	2025-10-22 22:58:45
3	2025-10-24	1	2025-10-24 09:28:45	1	2025-10-24 09:28:45	2025-10-24 09:28:45
\.


--
-- Data for Name: guardhouse_archived_records; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.guardhouse_archived_records (id, session_id, student_id, student_name, grade_level, section, record_type, "timestamp", session_date, created_at, updated_at) FROM stdin;
1	1	3234	Angel Castro	Kinder	Gumamela	check-in	2025-10-08 01:44:11	2025-10-08	2025-10-08 01:53:03	2025-10-08 01:53:03
2	1	3234	Angel Castro	Kinder	Gumamela	check-out	2025-10-08 01:44:35	2025-10-08	2025-10-08 01:53:03	2025-10-08 01:53:03
3	1	3240	Ethan Bautista	Kinder	Gumamela	check-in	2025-10-08 01:39:18	2025-10-08	2025-10-08 01:53:03	2025-10-08 01:53:03
4	1	3240	Ethan Bautista	Kinder	Gumamela	check-out	2025-10-08 01:40:39	2025-10-08	2025-10-08 01:53:03	2025-10-08 01:53:03
5	1	3246	Joshua Navarro	Kinder	Gumamela	check-in	2025-10-08 01:52:03	2025-10-08	2025-10-08 01:53:03	2025-10-08 01:53:03
6	1	3246	Joshua Navarro	Kinder	Gumamela	check-out	2025-10-08 01:52:39	2025-10-08	2025-10-08 01:53:03	2025-10-08 01:53:03
7	1	3249	Joy Torres	Kinder	Gumamela	check-in	2025-10-08 01:45:21	2025-10-08	2025-10-08 01:53:03	2025-10-08 01:53:03
8	1	3249	Joy Torres	Kinder	Gumamela	check-out	2025-10-08 01:46:12	2025-10-08	2025-10-08 01:53:03	2025-10-08 01:53:03
9	2	3230	Manuel Martinez	Kinder	Gumamela	check-in	2025-10-22 22:56:01	2025-10-22	2025-10-22 22:58:45	2025-10-22 22:58:45
10	2	3244	Miguel Diaz	Kinder	Gumamela	check-in	2025-10-22 22:56:42	2025-10-22	2025-10-22 22:58:45	2025-10-22 22:58:45
11	2	3245	Juan Bautista	Kinder	Gumamela	check-in	2025-10-22 22:55:51	2025-10-22	2025-10-22 22:58:45	2025-10-22 22:58:45
12	3	3239	Angelo Aguilar	Kinder	Gumamela	check-in	2025-10-24 09:27:49	2025-10-24	2025-10-24 09:28:45	2025-10-24 09:28:45
\.


--
-- Data for Name: guardhouse_attendance; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.guardhouse_attendance (id, student_id, qr_code_data, record_type, "timestamp", date, guard_name, guard_id, is_manual, notes, created_at, updated_at) FROM stdin;
15	3234	LAMMS_STUDENT_3234_1759843603_3ZSPBwdh	check-in	2025-10-15 14:48:39	2025-10-15	Bread Doe	G-12345	f	\N	2025-10-15 14:48:39	2025-10-15 14:48:39
16	3239	LAMMS_STUDENT_3239_1759843601_mHrWHtXs	check-in	2025-10-15 14:48:51	2025-10-15	Bread Doe	G-12345	f	\N	2025-10-15 14:48:51	2025-10-15 14:48:51
17	3239	LAMMS_STUDENT_3239_1759843601_mHrWHtXs	check-out	2025-10-15 14:49:10	2025-10-15	Bread Doe	G-12345	f	\N	2025-10-15 14:49:10	2025-10-15 14:49:10
18	3234	LAMMS_STUDENT_3234_1759843603_3ZSPBwdh	check-out	2025-10-15 15:17:17	2025-10-15	Bread Doe	G-12345	f	\N	2025-10-15 15:17:17	2025-10-15 15:17:17
19	3234	LAMMS_STUDENT_3234_1759843603_3ZSPBwdh	check-in	2025-10-15 15:17:53	2025-10-15	Bread Doe	G-12345	f	\N	2025-10-15 15:17:53	2025-10-15 15:17:53
24	3234	LAMMS_STUDENT_3234_1759843603_3ZSPBwdh	check-in	2025-10-26 12:56:28	2025-10-26	Bread Doe	G-12345	f	\N	2025-10-26 12:56:28	2025-10-26 12:56:28
25	3239	LAMMS_STUDENT_3239_1759843601_mHrWHtXs	check-in	2025-10-26 12:56:42	2025-10-26	Bread Doe	G-12345	f	\N	2025-10-26 12:56:42	2025-10-26 12:56:42
26	3239	LAMMS_STUDENT_3239_1759843601_mHrWHtXs	check-out	2025-10-26 12:57:24	2025-10-26	Bread Doe	G-12345	f	\N	2025-10-26 12:57:24	2025-10-26 12:57:24
\.


--
-- Data for Name: guardhouse_users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.guardhouse_users (id, user_id, first_name, last_name, phone_number, shift, created_at, updated_at, deleted_at) FROM stdin;
1	26	Security	Guard	09123456788	morning	2025-10-07 13:25:14	2025-10-07 13:25:14	\N
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2019_12_14_000001_create_personal_access_tokens_table	1
2	2024_01_01_000000_create_student_qr_codes_table	1
3	2024_01_15_000000_create_collected_reports_table	1
4	2024_04_07_000001_create_grades_table	1
5	2024_04_07_000002_create_subjects_table	1
6	2024_04_08_000001_create_curriculums_table	1
7	2024_04_08_000002_create_curriculum_grade_table	1
8	2024_04_08_000003_create_curriculum_grade_subject_table	1
9	2024_04_09_000001_add_indexes_to_curriculum_tables	1
10	2024_12_26_180000_create_sections_table	1
11	2024_12_26_190000_fix_students_table	1
12	2024_12_26_210203_create_seating_arrangements_table	1
13	2025_01_04_100000_create_student_section_table	1
14	2025_03_28_084053_create_users_table	1
15	2025_03_28_084212_create_teachers_table	1
16	2025_03_28_084248_create_sections_table	1
17	2025_03_28_084330_create_grade_subject_table	1
18	2025_03_28_084405_create_teacher_section_subject_table	1
19	2025_03_28_111450_create_cache_table	1
20	2025_03_28_114541_add_description_to_sections	1
21	2025_03_28_160721_drop_grade_subject_table	1
22	2025_03_29_115858_add_role_column_to_teacher_section_subject_table	1
23	2025_03_29_130536_add_is_primary_column_to_teacher_section_subject_table	1
24	2025_03_31_160214_add_curriculum_id_to_sections_table	1
25	2025_03_31_160248_create_subject_schedules_table	1
26	2025_04_01_133105_update_sections_table_relationships	1
27	2025_04_02_160519_create_section_subject_table	1
28	2025_04_06_000001_remove_timestamps_from_teacher_section_subject_table	1
29	2025_04_06_000002_remove_timestamps_from_sections_table	1
30	2025_04_06_000003_remove_timestamps_from_curriculum_grade_table	1
31	2025_04_06_065122_update_teacher_section_subject_nullable	1
32	2025_04_13_000001_add_status_to_curricula_table	1
33	2025_04_14_073849_add_deleted_at_to_curricula_table	1
34	2025_04_14_145945_add_unique_constraint_to_curricula_table	1
35	2025_04_14_154812_cleanup_duplicate_curricula	1
36	2025_04_22_144015_update_students_table	1
37	2025_04_22_144202_add_fields_to_students_table	1
38	2025_04_22_144307_create_students_table	1
39	2025_04_22_144308_update_students_table_qr_codes	1
40	2025_04_22_144309_add_photo_qr_fields_to_students_table	1
41	2025_04_22_144310_create_student_section_table	1
42	2025_04_22_144311_create_student_qr_codes_table	1
43	2025_04_22_151758_fix_students_table	1
44	2025_08_26_131812_create_attendances_table	1
45	2025_08_28_141911_add_timestamps_to_sections_table	1
46	2025_09_01_140247_add_is_active_to_students_table	1
47	2025_09_01_161828_fix_profile_photo_column_length	1
48	2025_09_02_000001_add_missing_columns_to_students_table	1
49	2025_09_02_140000_create_schedules_table	1
50	2025_09_02_141000_fix_schedules_subject_nullable	1
51	2025_09_03_143836_rename_students_table_to_student_details	1
52	2025_09_04_044506_add_teacher_fields_to_attendances_table	1
53	2025_09_04_120000_improve_attendances_table	1
54	2025_09_04_120001_create_attendance_statuses_table	1
55	2025_09_04_120002_update_attendances_with_status_reference	1
56	2025_09_04_140000_create_cache_table	1
57	2025_09_04_160000_create_production_attendance_system	1
58	2025_09_08_000001_create_attendance_sessions_table	1
59	2025_09_08_000002_create_attendance_records_table	1
60	2025_09_08_000003_enhance_student_section_table	1
61	2025_09_08_021600_fix_attendance_sessions_unique_constraint	1
62	2025_09_08_022200_enhance_attendance_system_reliability	1
63	2025_09_16_000001_create_teacher_sessions_table	1
64	2025_09_20_221500_add_archive_fields_to_students_table	1
65	2025_09_22_215934_create_submitted_sf2_reports_table	1
66	2025_09_22_220241_create_gate_attendance_table	1
67	2025_09_28_000001_add_performance_indexes	1
68	2025_10_01_000001_create_admins_table	2
69	2025_10_01_000002_create_guardhouse_users_table	3
70	2025_10_01_000004_create_user_sessions_table	4
71	2025_10_01_000003_update_users_table_add_guardhouse_role	5
72	2025_10_01_143800_create_attendance_reasons_table	6
73	2025_10_01_143900_add_reason_to_attendance_records	7
74	2025_09_28_220000_create_notifications_table	8
75	2025_09_28_223000_fix_notifications_foreign_keys	9
76	2025_09_30_create_student_status_history_table	10
77	2025_09_29_084700_create_school_calendar_system	11
78	2025_10_02_004027_create_school_calendar_events_table	12
79	2025_10_03_025217_create_guardhouse_archive_tables	13
80	2025_10_04_210000_add_guardhouse_performance_indexes	14
81	2025_10_15_011000_sync_homeroom_teachers	15
\.


--
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.notifications (id, user_id, type, title, message, data, priority, is_read, read_at, related_student_id, created_by_user_id, created_at, updated_at) FROM stdin;
512	1	session_completed	Attendance Session Completed	English - Gumamela - 15 present, 0 absent	{"session_id":17860,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":15,"absent_count":0,"late_count":0,"excused_count":0,"total_students":15,"teacher_id":1}	medium	f	\N	\N	\N	2025-11-05 20:16:38	2025-11-05 20:16:38
140	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 17 absent	{"session_id":17834,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":17,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 00:56:43	\N	\N	2025-10-23 00:23:57	2025-10-23 00:56:43
147	1	session_completed	Attendance Session Completed	Music - Gumamela - 17 present, 0 absent	{"session_id":17841,"subject_id":5,"subject_name":"Music","section_id":219,"section_name":"Gumamela","present_count":17,"absent_count":0,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 11:32:06	\N	\N	2025-10-23 11:20:51	2025-10-23 11:32:06
174	2	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":2}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
175	3	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":3}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
176	4	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":4}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
177	5	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":5}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
178	6	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":6}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
179	7	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":7}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
173	1	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":1}	high	t	2025-10-26 13:17:39	\N	\N	2025-10-23 15:43:45	2025-10-26 13:17:39
1	1	session_completed	Attendance Session Completed	English - Gumamela - 18 present, 0 absent	{"session_id":17802,"subject_id":1,"subject_name":"English","section_id":219,"present_count":18,"absent_count":0,"late_count":1,"excused_count":1,"total_students":20,"teacher_id":1}	medium	t	2025-10-07 19:55:27	\N	\N	2025-10-07 19:51:20	2025-10-13 22:12:22
2	1	session_completed	Attendance Session Completed	Music - Gumamela - 20 present, 0 absent	{"session_id":17803,"subject_id":5,"subject_name":"Music","section_id":219,"present_count":20,"absent_count":0,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-07 19:55:27	\N	\N	2025-10-07 19:55:12	2025-10-13 22:12:22
3	1	session_completed	Attendance Session Completed	English - Gumamela - 20 present, 0 absent	{"session_id":17804,"subject_id":1,"subject_name":"English","section_id":219,"present_count":20,"absent_count":0,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:11:33	2025-10-13 22:12:22
4	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 20 absent	{"session_id":17805,"subject_id":1,"subject_name":"English","section_id":219,"present_count":0,"absent_count":20,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:12:12	2025-10-13 22:12:22
5	1	session_completed	Attendance Session Completed	English - Gumamela - 20 present, 0 absent	{"session_id":17806,"subject_id":1,"subject_name":"English","section_id":219,"present_count":20,"absent_count":0,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:17:16	2025-10-13 22:12:22
6	1	session_completed	Attendance Session Completed	English - Gumamela - 20 present, 0 absent	{"session_id":17807,"subject_id":1,"subject_name":"English","section_id":219,"present_count":20,"absent_count":0,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:22:18	2025-10-13 22:12:22
7	1	session_completed	Attendance Session Completed	English - Gumamela - 20 present, 0 absent	{"session_id":17808,"subject_id":1,"subject_name":"English","section_id":219,"present_count":20,"absent_count":0,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:25:19	2025-10-13 22:12:22
8	1	session_completed	Attendance Session Completed	English - Gumamela - 1 present, 19 absent	{"session_id":17809,"subject_id":1,"subject_name":"English","section_id":219,"present_count":1,"absent_count":19,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:25:51	2025-10-13 22:12:22
9	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 20 absent	{"session_id":17810,"subject_id":1,"subject_name":"English","section_id":219,"present_count":0,"absent_count":20,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:40:40	2025-10-13 22:12:22
10	1	session_completed	Attendance Session Completed	English - Gumamela - 20 present, 0 absent	{"session_id":17811,"subject_id":1,"subject_name":"English","section_id":219,"present_count":20,"absent_count":0,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:41:21	2025-10-13 22:12:22
11	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 20 absent	{"session_id":17812,"subject_id":1,"subject_name":"English","section_id":219,"present_count":0,"absent_count":20,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:43:04	2025-10-13 22:12:22
12	1	session_completed	Attendance Session Completed	English - Gumamela - 20 present, 0 absent	{"session_id":17813,"subject_id":1,"subject_name":"English","section_id":219,"present_count":20,"absent_count":0,"late_count":0,"excused_count":0,"total_students":20,"teacher_id":1}	medium	t	2025-10-08 00:01:24	\N	\N	2025-10-07 23:43:45	2025-10-13 22:12:22
14	2	session_completed	Attendance Session Completed	English - Silang - 25 present, 1 absent	{"session_id":17814,"subject_id":1,"subject_name":"English","section_id":226,"present_count":25,"absent_count":1,"late_count":0,"excused_count":0,"total_students":26,"teacher_id":2}	medium	t	2025-10-13 22:25:35	\N	\N	2025-10-13 21:24:44	2025-10-13 22:25:35
15	2	session_completed	Attendance Session Completed	Mathematics - Lapu-Lapu - 21 present, 1 absent	{"session_id":17815,"subject_id":3,"subject_name":"Mathematics","section_id":230,"section_name":"Lapu-Lapu","present_count":21,"absent_count":1,"late_count":1,"excused_count":1,"total_students":24,"teacher_id":2}	medium	t	2025-10-13 22:25:35	\N	\N	2025-10-13 22:13:55	2025-10-13 22:25:35
16	2	session_completed	Attendance Session Completed	Music - Lapu-Lapu - 24 present, 0 absent	{"session_id":17816,"subject_id":5,"subject_name":"Music","section_id":230,"section_name":"Lapu-Lapu","present_count":24,"absent_count":0,"late_count":0,"excused_count":0,"total_students":24,"teacher_id":2}	medium	t	2025-10-13 22:25:35	\N	\N	2025-10-13 22:25:11	2025-10-13 22:25:35
17	2	session_completed	Attendance Session Completed	English - Silang - 25 present, 1 absent	{"session_id":17817,"subject_id":1,"subject_name":"English","section_id":226,"section_name":"Silang","present_count":25,"absent_count":1,"late_count":0,"excused_count":0,"total_students":26,"teacher_id":2}	medium	t	2025-10-13 23:57:21	\N	\N	2025-10-13 23:33:44	2025-10-13 23:57:21
18	2	session_completed	Attendance Session Completed	English - Silang - 25 present, 1 absent	{"session_id":17818,"subject_id":1,"subject_name":"English","section_id":226,"section_name":"Silang","present_count":25,"absent_count":1,"late_count":0,"excused_count":0,"total_students":26,"teacher_id":2}	medium	t	2025-10-13 23:57:21	\N	\N	2025-10-13 23:38:36	2025-10-13 23:57:21
19	2	session_completed	Attendance Session Completed	English - Silang - 0 present, 26 absent	{"session_id":17819,"subject_id":1,"subject_name":"English","section_id":226,"section_name":"Silang","present_count":0,"absent_count":26,"late_count":0,"excused_count":0,"total_students":26,"teacher_id":2}	medium	t	2025-10-13 23:57:21	\N	\N	2025-10-13 23:47:32	2025-10-13 23:57:21
20	2	session_completed	Attendance Session Completed	English - Silang - 25 present, 1 absent	{"session_id":17820,"subject_id":1,"subject_name":"English","section_id":226,"section_name":"Silang","present_count":25,"absent_count":1,"late_count":0,"excused_count":0,"total_students":26,"teacher_id":2}	medium	t	2025-10-13 23:57:21	\N	\N	2025-10-13 23:51:22	2025-10-13 23:57:21
21	2	session_completed	Attendance Session Completed	English - Silang - 25 present, 0 absent	{"session_id":17821,"subject_id":1,"subject_name":"English","section_id":226,"section_name":"Silang","present_count":25,"absent_count":0,"late_count":0,"excused_count":0,"total_students":25,"teacher_id":2}	medium	t	2025-10-13 23:57:21	\N	\N	2025-10-13 23:55:11	2025-10-13 23:57:21
22	2	session_completed	Attendance Session Completed	English - Silang - 24 present, 0 absent	{"session_id":17822,"subject_id":1,"subject_name":"English","section_id":226,"section_name":"Silang","present_count":24,"absent_count":0,"late_count":0,"excused_count":0,"total_students":24,"teacher_id":2}	medium	t	2025-10-13 23:57:21	\N	\N	2025-10-13 23:56:38	2025-10-13 23:57:21
23	2	session_completed	Attendance Session Completed	English - Silang - 25 present, 0 absent	{"session_id":17824,"subject_id":1,"subject_name":"English","section_id":226,"section_name":"Silang","present_count":25,"absent_count":0,"late_count":0,"excused_count":0,"total_students":25,"teacher_id":2}	medium	t	2025-10-14 02:06:38	\N	\N	2025-10-14 02:06:21	2025-10-14 02:06:38
24	1	session_completed	Attendance Session Completed	English - Gumamela - 2 present, 17 absent	{"session_id":17825,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":2,"absent_count":17,"late_count":0,"excused_count":0,"total_students":19,"teacher_id":1}	medium	t	2025-10-15 14:45:51	\N	\N	2025-10-15 14:36:33	2025-10-15 14:45:51
25	1	session_completed	Attendance Session Completed	English - Gumamela - 17 present, 1 absent	{"session_id":17826,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":17,"absent_count":1,"late_count":0,"excused_count":0,"total_students":18,"teacher_id":1}	medium	t	2025-10-15 21:43:49	\N	\N	2025-10-15 15:06:59	2025-10-15 21:43:49
26	1	session_completed	Attendance Session Completed	English - Gumamela - 1 present, 14 absent	{"session_id":17827,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":1,"absent_count":14,"late_count":1,"excused_count":0,"total_students":16,"teacher_id":1}	medium	t	2025-10-15 21:43:49	\N	\N	2025-10-15 15:28:04	2025-10-15 21:43:49
27	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-15 21:43:49	\N	1	2025-10-15 21:43:41	2025-10-15 21:43:49
28	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-15 23:31:00	\N	1	2025-10-15 21:44:50	2025-10-15 23:31:00
133	1	schedule	🏁 Class Ended	English in Gumamela has ended.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"session_ended","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-22 22:12:42	2025-10-23 00:23:37
134	1	schedule	🏁 Class Ended	English in Gumamela has ended.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"session_ended","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-22 22:16:42	2025-10-23 00:23:37
135	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 17 absent	{"session_id":17829,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":17,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 00:23:37	\N	\N	2025-10-22 22:23:13	2025-10-23 00:23:37
136	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 17 absent	{"session_id":17830,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":17,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 00:23:37	\N	\N	2025-10-22 22:33:08	2025-10-23 00:23:37
137	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 17 absent	{"session_id":17831,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":17,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 00:23:37	\N	\N	2025-10-22 22:39:13	2025-10-23 00:23:37
29	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-15 23:31:00	\N	1	2025-10-15 21:45:50	2025-10-15 23:31:00
30	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-15 23:31:00	\N	1	2025-10-15 21:46:50	2025-10-15 23:31:00
31	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-15 23:31:00	\N	1	2025-10-15 21:48:00	2025-10-15 23:31:00
32	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-15 23:31:00	\N	1	2025-10-15 21:49:10	2025-10-15 23:31:00
141	1	session_completed	Attendance Session Completed	English - Gumamela - 17 present, 0 absent	{"session_id":17835,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":17,"absent_count":0,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 00:56:43	\N	\N	2025-10-23 00:24:21	2025-10-23 00:56:43
148	1	session_completed	Attendance Session Completed	English - Gumamela - 17 present, 0 absent	{"session_id":17842,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":17,"absent_count":0,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 11:32:06	\N	\N	2025-10-23 11:26:07	2025-10-23 11:32:06
180	8	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":8}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
181	9	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":9}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
182	10	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":10}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
183	11	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":11}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
184	12	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":12}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
185	13	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":13}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
186	14	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":14}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
187	15	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":15}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
188	16	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":16}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
189	17	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":17}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
190	18	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":18}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
191	19	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":19}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
192	20	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":20}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
193	21	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 21 - Oct 22, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-21","end_date":"2025-10-22","affects_attendance":true,"action":"updated","teacher_id":21}	high	f	\N	\N	\N	2025-10-23 15:43:45	2025-10-23 15:43:45
142	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 17 absent	{"session_id":17836,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":17,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 00:56:43	\N	\N	2025-10-23 00:32:47	2025-10-23 00:56:43
149	1	session_completed	Attendance Session Completed	English - Gumamela - 17 present, 0 absent	{"session_id":17843,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":17,"absent_count":0,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 11:32:06	\N	\N	2025-10-23 11:30:28	2025-10-23 11:32:06
195	2	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":2}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
196	3	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":3}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
197	4	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":4}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
198	5	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":5}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
199	6	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":6}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
200	7	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":7}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
201	8	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":8}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
202	9	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":9}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
203	10	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":10}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
204	11	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":11}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
205	12	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":12}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
206	13	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":13}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
207	14	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":14}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
208	15	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":15}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
209	16	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":16}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
210	17	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":17}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
211	18	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":18}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
212	19	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":19}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
194	1	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":1}	high	t	2025-10-26 13:17:39	\N	\N	2025-10-23 15:44:52	2025-10-26 13:17:39
143	1	session_completed	Attendance Session Completed	Music - Gumamela - 17 present, 0 absent	{"session_id":17837,"subject_id":5,"subject_name":"Music","section_id":219,"section_name":"Gumamela","present_count":17,"absent_count":0,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 11:32:06	\N	\N	2025-10-23 11:01:12	2025-10-23 11:32:06
150	1	session_completed	Attendance Session Completed	English - Gumamela - 17 present, 0 absent	{"session_id":17844,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":17,"absent_count":0,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 11:32:06	\N	\N	2025-10-23 11:31:56	2025-10-23 11:32:06
213	20	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":20}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
214	21	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 20 - Oct 21, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-20","end_date":"2025-10-21","affects_attendance":true,"action":"updated","teacher_id":21}	high	f	\N	\N	\N	2025-10-23 15:44:52	2025-10-23 15:44:52
241	2	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":2}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
242	3	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":3}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
243	4	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":4}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
244	5	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":5}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
245	6	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":6}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
246	7	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":7}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
247	8	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":8}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
248	9	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":9}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
249	10	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":10}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
250	11	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":11}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
251	12	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":12}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
252	13	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":13}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
253	14	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":14}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
254	15	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":15}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
255	16	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":16}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
237	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 16 absent	{"session_id":17847,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":16,"late_count":0,"excused_count":0,"total_students":16,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-23 16:09:31	2025-10-26 13:17:39
240	1	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":1}	high	t	2025-10-26 13:17:39	\N	\N	2025-10-24 09:31:58	2025-10-26 13:17:39
144	1	session_completed	Attendance Session Completed	Music - Gumamela - 0 present, 17 absent	{"session_id":17838,"subject_id":5,"subject_name":"Music","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":17,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 11:32:06	\N	\N	2025-10-23 11:09:56	2025-10-23 11:32:06
216	2	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":2}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
217	3	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":3}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
218	4	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":4}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
219	5	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":5}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
220	6	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":6}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
221	7	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":7}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
222	8	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":8}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
223	9	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":9}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
224	10	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":10}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
225	11	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":11}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
226	12	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":12}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
227	13	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":13}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
228	14	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":14}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
229	15	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":15}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
230	16	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":16}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
231	17	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":17}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
232	18	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":18}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
233	19	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":19}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
234	20	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":20}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
151	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 16 absent	{"session_id":17845,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":16,"late_count":0,"excused_count":0,"total_students":16,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-23 15:05:00	2025-10-26 13:17:39
145	1	session_completed	Attendance Session Completed	Music - Gumamela - 0 present, 17 absent	{"session_id":17839,"subject_id":5,"subject_name":"Music","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":17,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 11:32:06	\N	\N	2025-10-23 11:13:29	2025-10-23 11:32:06
153	2	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":2}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
154	3	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":3}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
155	4	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":4}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
156	5	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":5}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
157	6	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":6}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
158	7	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":7}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
159	8	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":8}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
160	9	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":9}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
161	10	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":10}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
162	11	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":11}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
163	12	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":12}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
164	13	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":13}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
33	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-16 21:52:23	2025-10-23 00:23:37
34	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-16 21:53:32	2025-10-23 00:23:37
35	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-16 21:54:42	2025-10-23 00:23:37
36	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-16 21:56:10	2025-10-23 00:23:37
37	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-16 21:57:12	2025-10-23 00:23:37
38	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-16 21:58:13	2025-10-23 00:23:37
39	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-16 21:59:22	2025-10-23 00:23:37
40	1	schedule	⏰ Class Ending Soon	English ends in 10 minutes. Please ensure attendance is taken before the class ends.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"ending_soon","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-16 21:59:52	2025-10-23 00:23:37
152	1	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":1}	high	t	2025-10-26 13:17:39	\N	\N	2025-10-23 15:35:31	2025-10-26 13:17:39
41	1	schedule	⏰ Class Ending Soon	English ends in 6 minutes. Please ensure attendance is taken before the class ends.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"ending_soon","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-16 22:03:57	2025-10-23 00:23:37
42	1	schedule	⏰ Class Ending Soon	English ends in 4 minutes. Please ensure attendance is taken before the class ends.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"ending_soon","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-16 22:06:47	2025-10-23 00:23:37
43	1	schedule	⏰ Class Ending Soon	English ends in 2 minutes. Please ensure attendance is taken before the class ends.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"ending_soon","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-16 22:08:49	2025-10-23 00:23:37
44	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-16 22:09:57	2025-10-23 00:23:37
45	1	schedule	🏁 Class Ended	English in Gumamela has ended.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"session_ended","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-16 22:11:57	2025-10-23 00:23:37
46	1	schedule	🏁 Class Ended	English in Gumamela has ended.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"session_ended","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-16 22:13:52	2025-10-23 00:23:37
47	1	session_completed	Attendance Session Completed	English - Gumamela - 16 present, 1 absent	{"session_id":17828,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":16,"absent_count":1,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 00:23:37	\N	\N	2025-10-17 12:29:48	2025-10-23 00:23:37
48	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:30:19	2025-10-23 00:23:37
49	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:32:06	2025-10-23 00:23:37
50	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:33:06	2025-10-23 00:23:37
51	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:34:07	2025-10-23 00:23:37
52	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:36:07	2025-10-23 00:23:37
53	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:38:07	2025-10-23 00:23:37
54	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:39:06	2025-10-23 00:23:37
55	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:41:06	2025-10-23 00:23:37
56	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:42:06	2025-10-23 00:23:37
57	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:44:06	2025-10-23 00:23:37
58	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:45:07	2025-10-23 00:23:37
59	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:47:07	2025-10-23 00:23:37
60	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:48:07	2025-10-23 00:23:37
61	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:50:07	2025-10-23 00:23:37
62	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:52:06	2025-10-23 00:23:37
63	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:53:07	2025-10-23 00:23:37
64	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:55:07	2025-10-23 00:23:37
65	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:57:07	2025-10-23 00:23:37
66	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 07:59:07	2025-10-23 00:23:37
67	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:01:07	2025-10-23 00:23:37
68	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:03:07	2025-10-23 00:23:37
69	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:05:06	2025-10-23 00:23:37
70	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:06:06	2025-10-23 00:23:37
71	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:08:06	2025-10-23 00:23:37
72	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:09:06	2025-10-23 00:23:37
73	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:10:07	2025-10-23 00:23:37
74	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:12:07	2025-10-23 00:23:37
75	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:14:06	2025-10-23 00:23:37
76	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:15:07	2025-10-23 00:23:37
77	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:17:07	2025-10-23 00:23:37
78	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:18:07	2025-10-23 00:23:37
79	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:19:07	2025-10-23 00:23:37
80	1	schedule	⏰ Class Ending Soon	Music ends in 10 minutes. Please ensure attendance is taken before the class ends.	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"ending_soon","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:20:06	2025-10-23 00:23:37
81	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:30:07	2025-10-23 00:23:37
82	1	schedule	🏁 Class Ended	Music in Gumamela has ended.	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"session_ended","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-20 08:32:06	2025-10-23 00:23:37
83	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:15:28	2025-10-23 00:23:37
84	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:15:51	2025-10-23 00:23:37
85	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:16:59	2025-10-23 00:23:37
86	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:18:26	2025-10-23 00:23:37
87	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:18:42	2025-10-23 00:23:37
88	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:18:57	2025-10-23 00:23:37
89	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:20:12	2025-10-23 00:23:37
90	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:21:21	2025-10-23 00:23:37
91	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:22:01	2025-10-23 00:23:37
92	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:23:08	2025-10-23 00:23:37
93	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:24:09	2025-10-23 00:23:37
94	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:25:17	2025-10-23 00:23:37
95	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:26:18	2025-10-23 00:23:37
96	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:27:28	2025-10-23 00:23:37
97	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:28:18	2025-10-23 00:23:37
98	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:29:24	2025-10-23 00:23:37
99	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:29:44	2025-10-23 00:23:37
100	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:30:01	2025-10-23 00:23:37
101	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:30:12	2025-10-23 00:23:37
102	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:30:20	2025-10-23 00:23:37
103	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:31:29	2025-10-23 00:23:37
104	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:32:55	2025-10-23 00:23:37
105	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:34:16	2025-10-23 00:23:37
106	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:35:08	2025-10-23 00:23:37
107	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:35:26	2025-10-23 00:23:37
108	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:35:43	2025-10-23 00:23:37
109	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:36:04	2025-10-23 00:23:37
110	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:36:21	2025-10-23 00:23:37
111	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:37:24	2025-10-23 00:23:37
112	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:37:36	2025-10-23 00:23:37
113	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:37:47	2025-10-23 00:23:37
114	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:38:56	2025-10-23 00:23:37
115	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:39:39	2025-10-23 00:23:37
116	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:40:48	2025-10-23 00:23:37
117	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:41:09	2025-10-23 00:23:37
118	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:42:18	2025-10-23 00:23:37
119	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:43:54	2025-10-23 00:23:37
120	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:44:58	2025-10-23 00:23:37
121	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:46:00	2025-10-23 00:23:37
122	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:51:56	2025-10-23 00:23:37
123	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:52:05	2025-10-23 00:23:37
124	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:53:15	2025-10-23 00:23:37
125	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:54:15	2025-10-23 00:23:37
126	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:55:56	2025-10-23 00:23:37
127	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:57:13	2025-10-23 00:23:37
128	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:58:20	2025-10-23 00:23:37
129	1	schedule	⏰ Class Ending Soon	English ends in 10 minutes. Please ensure attendance is taken before the class ends.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"ending_soon","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-22 21:59:51	2025-10-23 00:23:37
130	1	schedule	⏰ Class Ending Soon	English ends in 2 minutes. Please ensure attendance is taken before the class ends.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"ending_soon","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-22 22:08:09	2025-10-23 00:23:37
131	1	schedule	📝 No Active Session	English is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-10-23 00:23:37	\N	1	2025-10-22 22:09:59	2025-10-23 00:23:37
132	1	schedule	🏁 Class Ended	English in Gumamela has ended.	{"teacherId":1,"userId":1,"subjectId":1,"sectionId":219,"schedule_type":"session_ended","source":"schedule_notification_service"}	medium	t	2025-10-23 00:23:37	\N	1	2025-10-22 22:12:00	2025-10-23 00:23:37
138	1	session_completed	Attendance Session Completed	English - Gumamela - 17 present, 0 absent	{"session_id":17832,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":17,"absent_count":0,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 00:23:37	\N	\N	2025-10-22 22:48:42	2025-10-23 00:23:37
139	1	session_completed	Attendance Session Completed	English - Gumamela - 17 present, 0 absent	{"session_id":17833,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":17,"absent_count":0,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 00:23:37	\N	\N	2025-10-22 22:49:29	2025-10-23 00:23:37
146	1	session_completed	Attendance Session Completed	Music - Gumamela - 0 present, 17 absent	{"session_id":17840,"subject_id":5,"subject_name":"Music","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":17,"late_count":0,"excused_count":0,"total_students":17,"teacher_id":1}	medium	t	2025-10-23 11:32:06	\N	\N	2025-10-23 11:16:55	2025-10-23 11:32:06
165	14	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":14}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
166	15	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":15}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
167	16	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":16}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
168	17	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":17}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
169	18	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":18}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
170	19	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":19}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
171	20	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":20}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
172	21	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 22 - Oct 23, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-22","end_date":"2025-10-23","affects_attendance":true,"action":"updated","teacher_id":21}	high	f	\N	\N	\N	2025-10-23 15:35:31	2025-10-23 15:35:31
235	21	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":21}	high	f	\N	\N	\N	2025-10-23 15:49:32	2025-10-23 15:49:32
256	17	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":17}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
257	18	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":18}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
258	19	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":19}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
259	20	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":20}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
260	21	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 18 - Oct 24, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-18","end_date":"2025-10-24","affects_attendance":true,"action":"updated","teacher_id":21}	high	f	\N	\N	\N	2025-10-24 09:31:58	2025-10-24 09:31:58
262	2	session_completed	Attendance Session Completed	Mathematics - Lapu-Lapu - 23 present, 1 absent	{"session_id":17851,"subject_id":3,"subject_name":"Mathematics","section_id":230,"section_name":"Lapu-Lapu","present_count":23,"absent_count":1,"late_count":0,"excused_count":0,"total_students":24,"teacher_id":2}	medium	f	\N	\N	\N	2025-10-25 22:25:31	2025-10-25 22:25:31
236	1	session_completed	Attendance Session Completed	English - Gumamela - 0 present, 16 absent	{"session_id":17846,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":0,"absent_count":16,"late_count":0,"excused_count":0,"total_students":16,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-23 16:02:37	2025-10-26 13:17:39
239	1	session_completed	Attendance Session Completed	English - Gumamela - 16 present, 0 absent	{"session_id":17849,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":16,"absent_count":0,"late_count":0,"excused_count":0,"total_students":16,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-24 08:58:06	2025-10-26 13:17:39
261	1	session_completed	Attendance Session Completed	English - Gumamela - 15 present, 0 absent	{"session_id":17850,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":15,"absent_count":0,"late_count":0,"excused_count":0,"total_students":15,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-24 15:04:46	2025-10-26 13:17:39
215	1	calendar_event	📅 Calendar Event Updated	🎄 Jolly Event - Oct 19 - Oct 20, 2025 (No attendance required)	{"event_id":1,"event_type":"holiday","start_date":"2025-10-19","end_date":"2025-10-20","affects_attendance":true,"action":"updated","teacher_id":1}	high	t	2025-10-26 13:17:39	\N	\N	2025-10-23 15:49:32	2025-10-26 13:17:39
238	1	session_completed	Attendance Session Completed	Music - Gumamela - 14 present, 2 absent	{"session_id":17848,"subject_id":5,"subject_name":"Music","section_id":219,"section_name":"Gumamela","present_count":14,"absent_count":2,"late_count":0,"excused_count":0,"total_students":16,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-23 16:12:22	2025-10-26 13:17:39
263	1	session_completed	Attendance Session Completed	English - Gumamela - 12 present, 3 absent	{"session_id":17852,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":12,"absent_count":3,"late_count":0,"excused_count":0,"total_students":15,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-25 22:39:58	2025-10-26 13:17:39
264	1	session_completed	Attendance Session Completed	English - Gumamela - 15 present, 0 absent	{"session_id":17853,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":15,"absent_count":0,"late_count":0,"excused_count":0,"total_students":15,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-26 12:50:06	2025-10-26 13:17:39
265	1	session_completed	Attendance Session Completed	English - Gumamela - 15 present, 0 absent	{"session_id":17854,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":15,"absent_count":0,"late_count":0,"excused_count":0,"total_students":15,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-26 12:51:03	2025-10-26 13:17:39
266	1	session_completed	Attendance Session Completed	English - Gumamela - 2 present, 11 absent	{"session_id":17855,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":2,"absent_count":11,"late_count":1,"excused_count":1,"total_students":15,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-26 13:13:15	2025-10-26 13:17:39
267	1	session_completed	Attendance Session Completed	English - Gumamela - 2 present, 13 absent	{"session_id":17856,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":2,"absent_count":13,"late_count":0,"excused_count":0,"total_students":15,"teacher_id":1}	medium	t	2025-10-26 13:17:39	\N	\N	2025-10-26 13:14:00	2025-10-26 13:17:39
268	1	session_completed	Attendance Session Completed	English - Gumamela - 12 present, 1 absent	{"session_id":17857,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":12,"absent_count":1,"late_count":1,"excused_count":1,"total_students":15,"teacher_id":1}	medium	t	2025-11-05 20:09:31	\N	\N	2025-10-26 13:22:46	2025-11-05 20:09:31
269	1	session_completed	Attendance Session Completed	English - Gumamela - 11 present, 2 absent	{"session_id":17858,"subject_id":1,"subject_name":"English","section_id":219,"section_name":"Gumamela","present_count":11,"absent_count":2,"late_count":1,"excused_count":1,"total_students":15,"teacher_id":1}	medium	t	2025-11-05 20:09:31	\N	\N	2025-10-26 13:24:17	2025-11-05 20:09:31
270	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:04:58	2025-11-05 20:09:31
271	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:06:07	2025-11-05 20:09:31
272	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:07:17	2025-11-05 20:09:31
273	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:08:18	2025-11-05 20:09:31
274	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:09:18	2025-11-05 20:09:31
275	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:10:18	2025-11-05 20:09:31
276	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:11:28	2025-11-05 20:09:31
277	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:12:29	2025-11-05 20:09:31
278	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:13:50	2025-11-05 20:09:31
279	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:15:50	2025-11-05 20:09:31
280	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:17:50	2025-11-05 20:09:31
281	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:18:59	2025-11-05 20:09:31
282	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:20:50	2025-11-05 20:09:31
283	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:22:50	2025-11-05 20:09:31
284	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:23:50	2025-11-05 20:09:31
285	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:24:58	2025-11-05 20:09:31
286	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:26:08	2025-11-05 20:09:31
287	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:27:18	2025-11-05 20:09:31
288	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:28:28	2025-11-05 20:09:31
289	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:29:28	2025-11-05 20:09:31
290	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:30:38	2025-11-05 20:09:31
291	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:31:48	2025-11-05 20:09:31
292	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:32:48	2025-11-05 20:09:31
293	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:33:58	2025-11-05 20:09:31
294	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:34:58	2025-11-05 20:09:31
295	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:35:58	2025-11-05 20:09:31
296	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:37:08	2025-11-05 20:09:31
297	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:38:18	2025-11-05 20:09:31
298	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:39:18	2025-11-05 20:09:31
299	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:40:28	2025-11-05 20:09:31
300	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:41:28	2025-11-05 20:09:31
301	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:42:38	2025-11-05 20:09:31
302	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:43:48	2025-11-05 20:09:31
303	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:44:48	2025-11-05 20:09:31
304	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:45:58	2025-11-05 20:09:31
305	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:47:08	2025-11-05 20:09:31
306	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:48:08	2025-11-05 20:09:31
307	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:49:18	2025-11-05 20:09:31
308	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:50:18	2025-11-05 20:09:31
309	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:51:28	2025-11-05 20:09:31
310	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:52:38	2025-11-05 20:09:31
311	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:53:38	2025-11-05 20:09:31
312	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:54:38	2025-11-05 20:09:31
313	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:55:38	2025-11-05 20:09:31
314	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:56:38	2025-11-05 20:09:31
315	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:57:48	2025-11-05 20:09:31
316	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:58:48	2025-11-05 20:09:31
317	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 08:59:48	2025-11-05 20:09:31
318	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:00:48	2025-11-05 20:09:31
319	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:01:58	2025-11-05 20:09:31
320	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:02:58	2025-11-05 20:09:31
321	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:03:58	2025-11-05 20:09:31
322	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:05:08	2025-11-05 20:09:31
323	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:06:08	2025-11-05 20:09:31
324	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:07:08	2025-11-05 20:09:31
325	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:08:18	2025-11-05 20:09:31
326	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:09:28	2025-11-05 20:09:31
327	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:10:38	2025-11-05 20:09:31
328	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:11:38	2025-11-05 20:09:31
329	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:12:48	2025-11-05 20:09:31
330	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:13:58	2025-11-05 20:09:31
331	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:14:58	2025-11-05 20:09:31
332	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:15:58	2025-11-05 20:09:31
333	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:17:08	2025-11-05 20:09:31
334	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:18:08	2025-11-05 20:09:31
335	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:19:18	2025-11-05 20:09:31
336	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:20:28	2025-11-05 20:09:31
337	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:21:28	2025-11-05 20:09:31
338	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:22:38	2025-11-05 20:09:31
339	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:23:48	2025-11-05 20:09:31
340	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:24:58	2025-11-05 20:09:31
341	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:25:58	2025-11-05 20:09:31
342	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:26:58	2025-11-05 20:09:31
343	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:28:08	2025-11-05 20:09:31
344	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:29:08	2025-11-05 20:09:31
345	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:30:08	2025-11-05 20:09:31
346	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:31:18	2025-11-05 20:09:31
347	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:32:18	2025-11-05 20:09:31
348	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:33:28	2025-11-05 20:09:31
349	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:34:28	2025-11-05 20:09:31
350	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:35:38	2025-11-05 20:09:31
351	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:36:48	2025-11-05 20:09:31
352	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:37:58	2025-11-05 20:09:31
353	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:39:08	2025-11-05 20:09:31
354	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:40:18	2025-11-05 20:09:31
355	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:41:28	2025-11-05 20:09:31
356	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:42:28	2025-11-05 20:09:31
357	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:43:38	2025-11-05 20:09:31
358	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:44:38	2025-11-05 20:09:31
359	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:45:38	2025-11-05 20:09:31
360	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:46:38	2025-11-05 20:09:31
361	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:47:38	2025-11-05 20:09:31
362	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:48:48	2025-11-05 20:09:31
363	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:49:48	2025-11-05 20:09:31
364	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:50:48	2025-11-05 20:09:31
365	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:51:58	2025-11-05 20:09:31
366	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:53:08	2025-11-05 20:09:31
367	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:54:18	2025-11-05 20:09:31
368	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:55:28	2025-11-05 20:09:31
369	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:56:28	2025-11-05 20:09:31
370	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:57:38	2025-11-05 20:09:31
371	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:58:38	2025-11-05 20:09:31
372	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 09:59:48	2025-11-05 20:09:31
373	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:00:48	2025-11-05 20:09:31
374	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:01:48	2025-11-05 20:09:31
375	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:02:58	2025-11-05 20:09:31
376	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:04:08	2025-11-05 20:09:31
377	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:05:18	2025-11-05 20:09:31
378	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:06:18	2025-11-05 20:09:31
379	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:07:28	2025-11-05 20:09:31
380	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:08:38	2025-11-05 20:09:31
381	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:09:38	2025-11-05 20:09:31
382	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:10:48	2025-11-05 20:09:31
383	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:11:48	2025-11-05 20:09:31
384	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:12:58	2025-11-05 20:09:31
385	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:14:08	2025-11-05 20:09:31
386	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:15:18	2025-11-05 20:09:31
387	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:16:28	2025-11-05 20:09:31
388	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:17:28	2025-11-05 20:09:31
389	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:18:28	2025-11-05 20:09:31
390	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:19:28	2025-11-05 20:09:31
391	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:20:38	2025-11-05 20:09:31
392	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:21:38	2025-11-05 20:09:31
393	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:22:48	2025-11-05 20:09:31
394	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:23:48	2025-11-05 20:09:31
395	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:24:58	2025-11-05 20:09:31
396	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:25:58	2025-11-05 20:09:31
397	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:26:58	2025-11-05 20:09:31
398	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:28:08	2025-11-05 20:09:31
399	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:29:08	2025-11-05 20:09:31
400	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:30:18	2025-11-05 20:09:31
401	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:31:18	2025-11-05 20:09:31
402	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:32:28	2025-11-05 20:09:31
403	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:33:38	2025-11-05 20:09:31
404	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:34:48	2025-11-05 20:09:31
405	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:35:58	2025-11-05 20:09:31
406	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:37:08	2025-11-05 20:09:31
407	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:38:08	2025-11-05 20:09:31
408	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:39:18	2025-11-05 20:09:31
409	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:40:18	2025-11-05 20:09:31
410	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:41:28	2025-11-05 20:09:31
411	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:42:38	2025-11-05 20:09:31
412	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:43:38	2025-11-05 20:09:31
413	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:44:48	2025-11-05 20:09:31
414	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:45:48	2025-11-05 20:09:31
415	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:46:58	2025-11-05 20:09:31
416	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:47:58	2025-11-05 20:09:31
417	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:49:08	2025-11-05 20:09:31
418	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:50:17	2025-11-05 20:09:31
419	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:51:27	2025-11-05 20:09:31
420	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:52:27	2025-11-05 20:09:31
421	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:53:27	2025-11-05 20:09:31
422	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:54:37	2025-11-05 20:09:31
423	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:55:38	2025-11-05 20:09:31
424	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:56:47	2025-11-05 20:09:31
425	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:57:47	2025-11-05 20:09:31
426	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:58:57	2025-11-05 20:09:31
427	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 10:59:57	2025-11-05 20:09:31
428	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:01:07	2025-11-05 20:09:31
429	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:02:17	2025-11-05 20:09:31
430	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:03:27	2025-11-05 20:09:31
431	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:04:27	2025-11-05 20:09:31
432	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:05:37	2025-11-05 20:09:31
433	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:06:37	2025-11-05 20:09:31
434	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:07:47	2025-11-05 20:09:31
435	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:08:57	2025-11-05 20:09:31
436	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:09:57	2025-11-05 20:09:31
437	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:11:07	2025-11-05 20:09:31
438	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:12:07	2025-11-05 20:09:31
439	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:13:07	2025-11-05 20:09:31
440	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:14:17	2025-11-05 20:09:31
441	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:15:27	2025-11-05 20:09:31
442	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:16:37	2025-11-05 20:09:31
443	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:17:47	2025-11-05 20:09:31
444	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:18:57	2025-11-05 20:09:31
445	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:20:07	2025-11-05 20:09:31
446	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:21:07	2025-11-05 20:09:31
447	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:22:17	2025-11-05 20:09:31
448	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:23:17	2025-11-05 20:09:31
449	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:24:27	2025-11-05 20:09:31
450	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:25:28	2025-11-05 20:09:31
451	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:26:39	2025-11-05 20:09:31
452	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:27:53	2025-11-05 20:09:31
453	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:28:57	2025-11-05 20:09:31
454	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:30:07	2025-11-05 20:09:31
455	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:31:17	2025-11-05 20:09:31
456	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:32:27	2025-11-05 20:09:31
457	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:33:37	2025-11-05 20:09:31
458	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:34:37	2025-11-05 20:09:31
459	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:35:37	2025-11-05 20:09:31
460	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:36:47	2025-11-05 20:09:31
461	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:37:47	2025-11-05 20:09:31
462	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:38:57	2025-11-05 20:09:31
463	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:40:07	2025-11-05 20:09:31
464	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:41:17	2025-11-05 20:09:31
465	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:42:17	2025-11-05 20:09:31
466	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:43:17	2025-11-05 20:09:31
467	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:44:27	2025-11-05 20:09:31
468	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:45:27	2025-11-05 20:09:31
469	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:46:27	2025-11-05 20:09:31
470	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:47:37	2025-11-05 20:09:31
471	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:48:37	2025-11-05 20:09:31
472	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:49:47	2025-11-05 20:09:31
473	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:50:47	2025-11-05 20:09:31
474	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:51:57	2025-11-05 20:09:31
475	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:52:57	2025-11-05 20:09:31
476	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:54:07	2025-11-05 20:09:31
477	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:55:17	2025-11-05 20:09:31
478	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:56:27	2025-11-05 20:09:31
479	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:57:37	2025-11-05 20:09:31
480	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:58:47	2025-11-05 20:09:31
481	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 11:59:57	2025-11-05 20:09:31
482	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:01:07	2025-11-05 20:09:31
483	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:02:07	2025-11-05 20:09:31
484	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:03:07	2025-11-05 20:09:31
485	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:04:17	2025-11-05 20:09:31
486	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:05:17	2025-11-05 20:09:31
487	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:06:27	2025-11-05 20:09:31
488	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:07:37	2025-11-05 20:09:31
489	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:08:37	2025-11-05 20:09:31
490	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:09:47	2025-11-05 20:09:31
491	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:10:48	2025-11-05 20:09:31
492	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:12:49	2025-11-05 20:09:31
493	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:13:49	2025-11-05 20:09:31
494	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:15:49	2025-11-05 20:09:31
495	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:17:49	2025-11-05 20:09:31
496	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:18:49	2025-11-05 20:09:31
497	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:19:49	2025-11-05 20:09:31
498	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:21:49	2025-11-05 20:09:31
499	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:22:49	2025-11-05 20:09:31
500	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:23:49	2025-11-05 20:09:31
501	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:24:49	2025-11-05 20:09:31
502	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:26:49	2025-11-05 20:09:31
503	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:27:49	2025-11-05 20:09:31
504	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:28:49	2025-11-05 20:09:31
505	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:30:12	2025-11-05 20:09:31
506	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:31:17	2025-11-05 20:09:31
507	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:32:17	2025-11-05 20:09:31
508	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:33:27	2025-11-05 20:09:31
509	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:34:27	2025-11-05 20:09:31
510	1	schedule	⏰ Class Ending Soon	Music ends in 10 minutes. Please ensure attendance is taken before the class ends.	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"ending_soon","source":"schedule_notification_service"}	medium	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:35:07	2025-11-05 20:09:31
511	1	schedule	📝 No Active Session	Music is scheduled now but no attendance session is active. Start taking attendance?	{"teacherId":1,"userId":1,"subjectId":5,"sectionId":219,"schedule_type":"no_active_session","source":"schedule_notification_service"}	high	t	2025-11-05 20:09:31	\N	1	2025-10-27 12:45:07	2025-11-05 20:09:31
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
101	App\\Models\\User	26	guardhouse_26_1761454517	b061b4ca40d33fffa55d64298d32993f7047c74364d93470e7c9768617942c79	["*"]	2025-10-26 12:55:18	\N	2025-10-26 12:55:17	2025-10-26 12:55:18
108	App\\Models\\User	1	teacher_1_1762345602	589cc452a72c851aaf9f700515127c6536a0f74853b67c4d102cf1912c06d7f9	["*"]	2025-11-05 22:34:03	\N	2025-11-05 20:26:42	2025-11-05 22:34:03
11	App\\Models\\User	8	teacher_8_1759816232	44ce2eafff4f1855457d8dba94b1f177221e095817320e78dec4b1014ea7a506	["*"]	2025-10-07 14:22:39	\N	2025-10-07 13:50:32	2025-10-07 14:22:39
\.


--
-- Data for Name: schedules; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.schedules (id, section_id, subject_id, teacher_id, day_of_week, start_time, end_time, period_type, room_number, notes, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: school_calendar_events; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.school_calendar_events (id, title, description, start_date, end_date, event_type, affects_attendance, modified_start_time, modified_end_time, affected_sections, affected_grade_levels, is_recurring, recurrence_pattern, is_active, created_by, created_at, updated_at, deleted_at) FROM stdin;
1	Jolly Event	DONT JOB BECAUSE IF YOU DO U FIRED	2025-10-18	2025-10-24	holiday	t	\N	\N	\N	\N	f	\N	t	\N	2025-10-08 00:13:05	2025-10-24 09:31:58	\N
\.


--
-- Data for Name: school_days; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.school_days (id, date, school_year_id, is_class_day, day_type, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: school_holidays; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.school_holidays (id, name, date, type, description, is_active, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: school_years; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.school_years (id, name, start_date, end_date, is_active, quarters, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: seating_arrangements; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.seating_arrangements (id, section_id, subject_id, teacher_id, layout, created_at, updated_at) FROM stdin;
1	59	\N	8	{"rows":9,"columns":9,"seatPlan":[[{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null}],[{"id":211,"name":"Mason H. Ramos","studentId":"NCS-2025-00211","isOccupied":true,"status":null},{"id":213,"name":"Angel C. Chavez","studentId":"NCS-2025-00213","isOccupied":true,"status":null},{"id":216,"name":"Jose U. Rodriguez","studentId":"NCS-2025-00216","isOccupied":true,"status":null},{"id":218,"name":"Gabriela N. Martinez","studentId":"NCS-2025-00218","isOccupied":true,"status":null},{"id":220,"name":"Crystal J. Santos","studentId":"NCS-2025-00220","isOccupied":true,"status":null},{"id":222,"name":"Noah P. Valdez","studentId":"NCS-2025-00222","isOccupied":true,"status":null},{"id":224,"name":"Paula B. Rodriguez","studentId":"NCS-2025-00224","isOccupied":true,"status":null},{"id":227,"name":"Pedro C. Chavez","studentId":"NCS-2025-00227","isOccupied":true,"status":null},{"id":230,"name":"Jose H. Torres","studentId":"NCS-2025-00230","isOccupied":true,"status":null}],[{"id":232,"name":"Isaiah B. Castro","studentId":"NCS-2025-00232","isOccupied":true,"status":null},{"id":234,"name":"Michelle B. Santos","studentId":"NCS-2025-00234","isOccupied":true,"status":null},{"id":235,"name":"Angela H. Perez","studentId":"NCS-2025-00235","isOccupied":true,"status":null},{"id":237,"name":"Faith C. Villanueva","studentId":"NCS-2025-00237","isOccupied":true,"status":null},{"id":239,"name":"Crystal Q. Reyes","studentId":"NCS-2025-00239","isOccupied":true,"status":null},{"id":240,"name":"Grace G. Dela Cruz","studentId":"NCS-2025-00240","isOccupied":true,"status":null},{"id":243,"name":"Angela Z. Castillo","studentId":"NCS-2025-00243","isOccupied":true,"status":null},{"id":246,"name":"Camila E. Alvarez","studentId":"NCS-2025-00246","isOccupied":true,"status":null},{"id":248,"name":"Ana Q. Valdez","studentId":"NCS-2025-00248","isOccupied":true,"status":null}],[{"id":250,"name":"Nicole M. Gonzales","studentId":"NCS-2025-00250","isOccupied":true,"status":null},{"id":252,"name":"Marco V. Velasquez","studentId":"NCS-2025-00252","isOccupied":true,"status":null},{"id":254,"name":"Angela R. Flores","studentId":"NCS-2025-00254","isOccupied":true,"status":null},{"id":256,"name":"Camila R. Reyes","studentId":"NCS-2025-00256","isOccupied":true,"status":null},{"id":259,"name":"Joy K. Diaz","studentId":"NCS-2025-00259","isOccupied":true,"status":null},{"id":225,"name":"Diego X. Castro","studentId":"NCS-2025-00225","isOccupied":true,"status":null},{"id":241,"name":"Carlos G. Castillo","studentId":"NCS-2025-00241","isOccupied":true,"status":null},{"id":257,"name":"Nathan F. Gomez","studentId":"NCS-2025-00257","isOccupied":true,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null}],[{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null}],[{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null}],[{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null}],[{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null}],[{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null},{"id":null,"name":null,"studentId":null,"isOccupied":false,"status":null}]],"showTeacherDesk":true,"showStudentIds":true}	2025-10-07 13:48:49	2025-10-07 13:51:22
3	226	\N	2	{"rows":3,"columns":9,"seatPlan":[[{"isOccupied":true,"studentId":"NCS-2025-03404","studentName":"Andrea Morales","status":null},{"isOccupied":true,"studentId":"NCS-2025-03400","studentName":"Andres Chavez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03411","studentName":"Angelo Rivera","status":null},{"isOccupied":true,"studentId":"NCS-2025-03413","studentName":"Caleb Reyes","status":null},{"isOccupied":true,"studentId":"NCS-2025-03415","studentName":"Daniel Pascual","status":null},{"isOccupied":true,"studentId":"NCS-2025-03401","studentName":"Diego Rivera","status":null},{"isOccupied":true,"studentId":"NCS-2025-03410","studentName":"Fernando Cruz","status":null},{"isOccupied":true,"studentId":"NCS-2025-03416","studentName":"Hope Fernandez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03406","studentName":"Hope Navarro","status":null}],[{"isOccupied":true,"studentId":"NCS-2025-03419","studentName":"Isabella Castillo","status":null},{"isOccupied":true,"studentId":"NCS-2025-03417","studentName":"Isabella Navarro","status":null},{"isOccupied":true,"studentId":"NCS-2025-03407","studentName":"Joshua Hernandez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03409","studentName":"Joshua Villanueva","status":null},{"isOccupied":true,"studentId":"NCS-2025-03418","studentName":"Joy Vargas","status":null},{"isOccupied":true,"studentId":"NCS-2025-03397","studentName":"Lucas Pascual","status":null},{"isOccupied":true,"studentId":"NCS-2025-03402","studentName":"Nathan Pascual","status":null},{"isOccupied":true,"studentId":"NCS-2025-03414","studentName":"Nicole Bautista","status":null},{"isOccupied":true,"studentId":"NCS-2025-03405","studentName":"Nicole Lopez","status":null}],[{"isOccupied":true,"studentId":"NCS-2025-03399","studentName":"Paolo Gomez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03398","studentName":"Paula Chavez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03412","studentName":"Pearl Velasquez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03408","studentName":"Rafael Fernandez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03420","studentName":"Rosa Pascual","status":null},{"isOccupied":true,"studentId":"NCS-2025-03403","studentName":"Ruby Chavez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03421","studentName":"Valentina Chavez","status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null}]],"showTeacherDesk":true,"showStudentIds":true}	2025-10-13 21:09:47	2025-10-14 02:06:13
4	230	\N	2	{"rows":3,"columns":9,"seatPlan":[[{"isOccupied":true,"studentId":"NCS-2025-03518","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03500","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03504","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03501","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03515","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03495","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03498","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03507","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03509","studentName":null,"status":null}],[{"isOccupied":true,"studentId":"NCS-2025-03497","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03502","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03506","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03499","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03513","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03514","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03496","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03516","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03505","studentName":null,"status":null}],[{"isOccupied":true,"studentId":"NCS-2025-03503","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03511","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03508","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03512","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03517","studentName":null,"status":null},{"isOccupied":true,"studentId":"NCS-2025-03510","studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null}]],"showTeacherDesk":true,"showStudentIds":true}	2025-10-13 21:22:54	2025-10-13 21:23:50
2	219	\N	1	{"rows":5,"columns":5,"seatPlan":[[{"isOccupied":true,"studentId":"NCS-2025-03239","studentName":"Angelo Aguilar","status":null},{"isOccupied":true,"studentId":"NCS-2025-03540","studentName":"Buotan Cris John","status":null},{"isOccupied":true,"studentId":"NCS-2025-03247","studentName":"Carlos Ortiz","status":null},{"isOccupied":true,"studentId":"NCS-2025-03240","studentName":"Ethan Bautista","status":null},{"isOccupied":true,"studentId":"NCS-2025-03235","studentName":"Gabriel Vargas","status":null}],[{"isOccupied":true,"studentId":"NCS-2025-03236","studentName":"Isabella Ortiz","status":null},{"isOccupied":true,"studentId":"NCS-2025-03246","studentName":"Joshua Navarro","status":null},{"isOccupied":true,"studentId":"NCS-2025-03249","studentName":"Joy Torres","status":null},{"isOccupied":true,"studentId":"NCS-2025-03245","studentName":"Juan Bautista","status":null},{"isOccupied":true,"studentId":"NCS-2025-03237","studentName":"Liam Fernandez","status":null}],[{"isOccupied":true,"studentId":"NCS-2025-03244","studentName":"Miguel Diaz","status":null},{"isOccupied":true,"studentId":"NCS-2025-03243","studentName":"Oliver Gonzales","status":null},{"isOccupied":true,"studentId":"NCS-2025-03248","studentName":"Rafael Valdez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03242","studentName":"Stephanie Velasquez","status":null},{"isOccupied":true,"studentId":"NCS-2025-03238","studentName":"Valentina Rivera","status":null}],[{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null}],[{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null},{"isOccupied":false,"studentId":null,"studentName":null,"status":null}]],"showTeacherDesk":false,"showStudentIds":true}	2025-10-07 14:58:15	2025-11-05 20:17:41
\.


--
-- Data for Name: section_subject; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.section_subject (id, section_id, subject_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: sections; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sections (id, name, description, capacity, is_active, deleted_at, curriculum_id, curriculum_grade_id, homeroom_teacher_id, created_at, updated_at) FROM stdin;
218	Sampaguita	Sampaguita - Kindergarten	29	t	\N	\N	71	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
220	Mabini	Mabini - Grade 1	35	t	\N	\N	72	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
221	Bonifacio	Bonifacio - Grade 1	34	t	\N	\N	72	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
223	Luna	Luna - Grade 2	25	t	\N	\N	73	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
222	Rizal	Rizal - Grade 2	25	t	\N	\N	73	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
225	Jacinto	Jacinto - Grade 3	33	t	\N	\N	74	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
224	Aguinaldo	Aguinaldo - Grade 3	32	t	\N	\N	74	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
227	Dagohoy	Dagohoy - Grade 4	30	t	\N	\N	75	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
228	Tandang Sora	Tandang Sora - Grade 5	30	t	\N	\N	76	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
229	Gabriela	Gabriela - Grade 5	27	t	\N	\N	76	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
231	Magat Salamat	Magat Salamat - Grade 6	32	t	\N	\N	77	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
230	Lapu-Lapu	Lapu-Lapu - Grade 6	25	t	\N	\N	77	\N	2025-10-07 14:31:38	2025-10-07 14:31:38
219	Gumamela	Gumamela - Kindergarten	34	t	\N	\N	71	1	2025-10-07 14:31:38	2025-10-07 14:31:38
226	Silang	Silang - Grade 4	29	t	\N	\N	75	2	2025-10-07 14:31:38	2025-10-07 14:31:38
\.


--
-- Data for Name: sf2_attendance_edits; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sf2_attendance_edits (id, student_id, section_id, date, month, status, created_at, updated_at) FROM stdin;
1	3239	219	2025-10-14	2025-10	present	2025-10-23 14:48:32	2025-10-23 14:48:32
2	3240	219	2025-10-15	2025-10	present	2025-10-24 09:06:14	2025-10-24 09:06:14
3	3239	219	2025-10-17	2025-10	absent	2025-10-26 13:15:17	2025-10-26 13:15:17
\.


--
-- Data for Name: student_details; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.student_details (id, "studentId", name, "firstName", "lastName", "middleName", "extensionName", email, "gradeLevel", section, lrn, "schoolYearStart", "schoolYearEnd", gender, sex, birthdate, birthplace, age, "psaBirthCertNo", "motherTongue", "profilePhoto", "currentAddress", "permanentAddress", "contactInfo", father, mother, "parentName", "parentContact", status, "enrollmentDate", "admissionDate", requirements, "isIndigenous", "indigenousCommunity", "is4PsBeneficiary", "householdID", "hasDisability", disabilities, created_at, updated_at, qr_code, student_id, photo, qr_code_path, address, "isActive", is_active, enrollment_status, dropout_reason, dropout_reason_category, status_effective_date) FROM stdin;
3210	NCS-2025-00001	Carmen Z. Hernandez	Carmen	Hernandez	Z	\N	\N	Kinder	Sampaguita	151723719111	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Hernandez	+63 9620386992	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00001	\N	\N	\N	t	t	active	\N	\N	\N
3211	NCS-2025-00002	Caleb G. Villanueva	Caleb	Villanueva	G	\N	\N	Kinder	Sampaguita	113816261082	2024	2025	Male	Male	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Villanueva	+63 9509595924	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00002	\N	\N	\N	t	t	active	\N	\N	\N
3212	NCS-2025-00003	Mason U. Martinez	Mason	Martinez	U	\N	\N	Kinder	Sampaguita	170377327787	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Martinez	+63 9792168744	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00003	\N	\N	\N	t	t	active	\N	\N	\N
3213	NCS-2025-00004	Grace L. Aguilar	Grace	Aguilar	L	\N	\N	Kinder	Sampaguita	111605296856	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Aguilar	+63 9846068597	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00004	\N	\N	\N	t	t	active	\N	\N	\N
3214	NCS-2025-00005	Miguel R. Torres	Miguel	Torres	R	\N	\N	Kinder	Sampaguita	143357603479	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Torres	+63 9665382573	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00005	\N	\N	\N	t	t	active	\N	\N	\N
3215	NCS-2025-00006	Oliver E. Castillo	Oliver	Castillo	E	\N	\N	Kinder	Sampaguita	148954962951	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Castillo	+63 9900800008	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00006	\N	\N	\N	t	t	active	\N	\N	\N
3216	NCS-2025-00007	Angel B. Gutierrez	Angel	Gutierrez	B	\N	\N	Kinder	Sampaguita	113924009092	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Gutierrez	+63 9922385167	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00007	\N	\N	\N	t	t	active	\N	\N	\N
3217	NCS-2025-00008	Gabriel W. Valdez	Gabriel	Valdez	W	\N	\N	Kinder	Sampaguita	161609946243	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Valdez	+63 9587641176	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00008	\N	\N	\N	t	t	active	\N	\N	\N
3218	NCS-2025-00009	Angelo Y. Gutierrez	Angelo	Gutierrez	Y	\N	\N	Kinder	Sampaguita	138320873784	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Gutierrez	+63 9114712061	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00009	\N	\N	\N	t	t	active	\N	\N	\N
3219	NCS-2025-00010	Caleb K. Romero	Caleb	Romero	K	\N	\N	Kinder	Sampaguita	158574498304	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Romero	+63 9219179961	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00010	\N	\N	\N	t	t	active	\N	\N	\N
3220	NCS-2025-00011	Elena C. Morales	Elena	Morales	C	\N	\N	Kinder	Sampaguita	153425372432	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Morales	+63 9189150561	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00011	\N	\N	\N	t	t	active	\N	\N	\N
3221	NCS-2025-00012	Angelo S. Morales	Angelo	Morales	S	\N	\N	Kinder	Sampaguita	190515166306	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Morales	+63 9962364949	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00012	\N	\N	\N	t	t	active	\N	\N	\N
3222	NCS-2025-00013	Maria U. Rivera	Maria	Rivera	U	\N	\N	Kinder	Sampaguita	143673851749	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Rivera	+63 9315429787	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00013	\N	\N	\N	t	t	active	\N	\N	\N
3223	NCS-2025-00014	Stephanie H. Chavez	Stephanie	Chavez	H	\N	\N	Kinder	Sampaguita	160657147830	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Chavez	+63 9711831156	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00014	\N	\N	\N	t	t	active	\N	\N	\N
3224	NCS-2025-00015	Christian L. Gomez	Christian	Gomez	L	\N	\N	Kinder	Sampaguita	162126767979	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Gomez	+63 9305142473	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00015	\N	\N	\N	t	t	active	\N	\N	\N
3225	NCS-2025-00016	Jasmine M. Lopez	Jasmine	Lopez	M	\N	\N	Kinder	Sampaguita	178152851673	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Lopez	+63 9478708794	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00016	\N	\N	\N	t	t	active	\N	\N	\N
3226	NCS-2025-00017	Angelo H. Cruz	Angelo	Cruz	H	\N	\N	Kinder	Sampaguita	175144449675	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Cruz	+63 9320800590	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00017	\N	\N	\N	t	t	active	\N	\N	\N
3227	NCS-2025-00018	Jasmine X. Sanchez	Jasmine	Sanchez	X	\N	\N	Kinder	Sampaguita	126821433797	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Sanchez	+63 9385500233	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00018	\N	\N	\N	t	t	active	\N	\N	\N
3228	NCS-2025-00019	Nicole V. Dela Cruz	Nicole	Dela Cruz	V	\N	\N	Kinder	Sampaguita	151619402789	2024	2025	Female	Female	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Dela Cruz	+63 9197345620	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00019	\N	\N	\N	t	t	active	\N	\N	\N
3229	NCS-2025-00020	Noah T. Vargas	Noah	Vargas	T	\N	\N	Kinder	Sampaguita	194076464413	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Vargas	+63 9833322166	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00020	\N	\N	\N	t	t	active	\N	\N	\N
3235	NCS-2025-00026	Gabriel J. Vargas	Gabriel	Vargas	J	\N	\N	Kinder	Gumamela	122875231460	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Vargas	+63 9490877669	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00026	\N	\N	\N	t	t	active	\N	\N	\N
3236	NCS-2025-00027	Isabella S. Ortiz	Isabella	Ortiz	S	\N	\N	Kinder	Gumamela	123164811629	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Ortiz	+63 9565498189	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00027	\N	\N	\N	t	t	active	\N	\N	\N
3237	NCS-2025-00028	Liam X. Fernandez	Liam	Fernandez	X	\N	\N	Kinder	Gumamela	196556317781	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Fernandez	+63 9738135953	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00028	\N	\N	\N	t	t	active	\N	\N	\N
3238	NCS-2025-00029	Valentina D. Rivera	Valentina	Rivera	D	\N	\N	Kinder	Gumamela	121803037506	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Rivera	+63 9859071668	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00029	\N	\N	\N	t	t	active	\N	\N	\N
3239	NCS-2025-00030	Angelo N. Aguilar	Angelo	Aguilar	N	\N	\N	Kinder	Gumamela	123352483603	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Aguilar	+63 9660927887	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00030	\N	\N	\N	t	t	active	\N	\N	\N
3240	NCS-2025-00031	Ethan O. Bautista	Ethan	Bautista	O	\N	\N	Kinder	Gumamela	192500370652	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Bautista	+63 9935598885	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00031	\N	\N	\N	t	t	active	\N	\N	\N
3231	NCS-2025-00022	Daniel G. Sanchez	Daniel	Sanchez	G	\N	\N	Kinder	Gumamela	169687078030	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Sanchez	+63 9133897009	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-15 14:45:30	\N	NCS-2025-00022	\N	\N	\N	t	t	dropped_out	a1	domestic	2025-10-15
3232	NCS-2025-00023	Oliver G. Gonzales	Oliver	Gonzales	G	\N	\N	Kinder	Gumamela	175713052384	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Gonzales	+63 9315478336	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-15 15:12:13	\N	NCS-2025-00023	\N	\N	\N	t	t	transferred_out	a1	domestic	2025-10-15
3233	NCS-2025-00024	Carlos H. Jimenez	Carlos	Jimenez	H	\N	\N	Kinder	Gumamela	134600893812	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Jimenez	+63 9902040198	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-15 15:12:28	\N	NCS-2025-00024	\N	\N	\N	t	t	dropped_out	a1	domestic	2025-10-15
3241	NCS-2025-00032	Pedro Y. Castro	Pedro	Castro	Y	\N	\N	Kinder	Gumamela	175885491892	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Castro	+63 9126057818	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-24 09:13:16	\N	NCS-2025-00032	\N	\N	\N	t	t	dropped_out	b3	individual	2025-10-24
3242	NCS-2025-00033	Stephanie G. Velasquez	Stephanie	Velasquez	G	\N	\N	Kinder	Gumamela	186932703355	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Velasquez	+63 9102745892	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00033	\N	\N	\N	t	t	active	\N	\N	\N
3243	NCS-2025-00034	Oliver E. Gonzales	Oliver	Gonzales	E	\N	\N	Kinder	Gumamela	135153326139	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Gonzales	+63 9364341165	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00034	\N	\N	\N	t	t	active	\N	\N	\N
3244	NCS-2025-00035	Miguel N. Diaz	Miguel	Diaz	N	\N	\N	Kinder	Gumamela	179712727932	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Diaz	+63 9592251290	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00035	\N	\N	\N	t	t	active	\N	\N	\N
3245	NCS-2025-00036	Juan V. Bautista	Juan	Bautista	V	\N	\N	Kinder	Gumamela	132145132285	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Bautista	+63 9947313577	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00036	\N	\N	\N	t	t	active	\N	\N	\N
3246	NCS-2025-00037	Joshua N. Navarro	Joshua	Navarro	N	\N	\N	Kinder	Gumamela	124481643417	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Navarro	+63 9824125703	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00037	\N	\N	\N	t	t	active	\N	\N	\N
3247	NCS-2025-00038	Carlos F. Ortiz	Carlos	Ortiz	F	\N	\N	Kinder	Gumamela	170154825263	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Ortiz	+63 9193004322	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00038	\N	\N	\N	t	t	active	\N	\N	\N
3248	NCS-2025-00039	Rafael W. Valdez	Rafael	Valdez	W	\N	\N	Kinder	Gumamela	166993950661	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Valdez	+63 9931928617	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00039	\N	\N	\N	t	t	active	\N	\N	\N
3249	NCS-2025-00040	Joy R. Torres	Joy	Torres	R	\N	\N	Kinder	Gumamela	139008006656	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Torres	+63 9917547117	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00040	\N	\N	\N	t	t	active	\N	\N	\N
3250	NCS-2025-00041	Camila O. Reyes	Camila	Reyes	O	\N	\N	Grade 1	Mabini	176539894945	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Reyes	+63 9735835318	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00041	\N	\N	\N	t	t	active	\N	\N	\N
3251	NCS-2025-00042	Gabriel T. Santos	Gabriel	Santos	T	\N	\N	Grade 1	Mabini	159984275798	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Santos	+63 9778229906	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00042	\N	\N	\N	t	t	active	\N	\N	\N
3252	NCS-2025-00043	Camila I. Torres	Camila	Torres	I	\N	\N	Grade 1	Mabini	145168269217	2024	2025	Female	Female	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Torres	+63 9691439482	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00043	\N	\N	\N	t	t	active	\N	\N	\N
3253	NCS-2025-00044	Jasmine J. Gonzales	Jasmine	Gonzales	J	\N	\N	Grade 1	Mabini	188472833126	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Gonzales	+63 9695102602	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00044	\N	\N	\N	t	t	active	\N	\N	\N
3254	NCS-2025-00045	Joy B. Aquino	Joy	Aquino	B	\N	\N	Grade 1	Mabini	123748121472	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Aquino	+63 9475248036	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00045	\N	\N	\N	t	t	active	\N	\N	\N
3255	NCS-2025-00046	Pedro E. Sanchez	Pedro	Sanchez	E	\N	\N	Grade 1	Mabini	196759047506	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Sanchez	+63 9340719207	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00046	\N	\N	\N	t	t	active	\N	\N	\N
3256	NCS-2025-00047	Hope W. Alvarez	Hope	Alvarez	W	\N	\N	Grade 1	Mabini	199975365548	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Alvarez	+63 9477648779	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00047	\N	\N	\N	t	t	active	\N	\N	\N
3257	NCS-2025-00048	Noah H. Gonzales	Noah	Gonzales	H	\N	\N	Grade 1	Mabini	176516143803	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Gonzales	+63 9111957832	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00048	\N	\N	\N	t	t	active	\N	\N	\N
3230	NCS-2025-00021	Manuel B. Martinez	Manuel	Martinez	B	\N	\N	Kinder	Gumamela	163658305817	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Martinez	+63 9668870996	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 23:57:37	\N	NCS-2025-00021	\N	\N	\N	t	t	dropped_out	b3	individual	2025-10-07
3258	NCS-2025-00049	Isaiah Q. Perez	Isaiah	Perez	Q	\N	\N	Grade 1	Mabini	140416736786	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Perez	+63 9722175674	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00049	\N	\N	\N	t	t	active	\N	\N	\N
3259	NCS-2025-00050	Andres Z. Santiago	Andres	Santiago	Z	\N	\N	Grade 1	Mabini	124524009457	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Santiago	+63 9415146851	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00050	\N	\N	\N	t	t	active	\N	\N	\N
3260	NCS-2025-00051	Victoria B. Alvarez	Victoria	Alvarez	B	\N	\N	Grade 1	Mabini	150425621371	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Alvarez	+63 9119875641	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00051	\N	\N	\N	t	t	active	\N	\N	\N
3261	NCS-2025-00052	Manuel P. Ortiz	Manuel	Ortiz	P	\N	\N	Grade 1	Mabini	125592616431	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Ortiz	+63 9270551312	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00052	\N	\N	\N	t	t	active	\N	\N	\N
3262	NCS-2025-00053	Nathan K. Navarro	Nathan	Navarro	K	\N	\N	Grade 1	Mabini	117563441998	2024	2025	Male	Male	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Navarro	+63 9216021846	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00053	\N	\N	\N	t	t	active	\N	\N	\N
3263	NCS-2025-00054	Liam D. Ramos	Liam	Ramos	D	\N	\N	Grade 1	Mabini	154393119810	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Ramos	+63 9876161585	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00054	\N	\N	\N	t	t	active	\N	\N	\N
3264	NCS-2025-00055	Ricardo M. Sanchez	Ricardo	Sanchez	M	\N	\N	Grade 1	Mabini	164045977381	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Sanchez	+63 9950352724	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00055	\N	\N	\N	t	t	active	\N	\N	\N
3265	NCS-2025-00056	Angelo E. Chavez	Angelo	Chavez	E	\N	\N	Grade 1	Mabini	185727041691	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Chavez	+63 9136541763	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00056	\N	\N	\N	t	t	active	\N	\N	\N
3266	NCS-2025-00057	Carlos W. Sanchez	Carlos	Sanchez	W	\N	\N	Grade 1	Mabini	162848352704	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Sanchez	+63 9773038532	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00057	\N	\N	\N	t	t	active	\N	\N	\N
3267	NCS-2025-00058	Ruby C. Lopez	Ruby	Lopez	C	\N	\N	Grade 1	Mabini	183595489710	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Lopez	+63 9955537302	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00058	\N	\N	\N	t	t	active	\N	\N	\N
3268	NCS-2025-00059	Liam C. Chavez	Liam	Chavez	C	\N	\N	Grade 1	Mabini	133202314108	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Chavez	+63 9711711025	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00059	\N	\N	\N	t	t	active	\N	\N	\N
3269	NCS-2025-00060	Christian P. Reyes	Christian	Reyes	P	\N	\N	Grade 1	Mabini	140740261846	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Reyes	+63 9891389325	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00060	\N	\N	\N	t	t	active	\N	\N	\N
3270	NCS-2025-00061	Carlos A. Perez	Carlos	Perez	A	\N	\N	Grade 1	Mabini	178172362832	2024	2025	Male	Male	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Perez	+63 9143788155	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00061	\N	\N	\N	t	t	active	\N	\N	\N
3271	NCS-2025-00062	Pearl V. Villanueva	Pearl	Villanueva	V	\N	\N	Grade 1	Mabini	197924503825	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Villanueva	+63 9581234598	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00062	\N	\N	\N	t	t	active	\N	\N	\N
3272	NCS-2025-00063	Mason T. Diaz	Mason	Diaz	T	\N	\N	Grade 1	Mabini	188825241034	2024	2025	Male	Male	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Diaz	+63 9952717757	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00063	\N	\N	\N	t	t	active	\N	\N	\N
3234	NCS-2025-00025	Angel X. Castro	Angel	Castro	X	\N	\N	Kinder	Gumamela	189883800230	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Castro	+63 9478293190	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-23 15:04:31	\N	NCS-2025-00025	\N	\N	\N	t	t	dropped_out	b2	individual	2025-10-23
3273	NCS-2025-00064	Grace K. Ortiz	Grace	Ortiz	K	\N	\N	Grade 1	Mabini	133628777678	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Ortiz	+63 9580494145	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00064	\N	\N	\N	t	t	active	\N	\N	\N
3274	NCS-2025-00065	Jose Q. Perez	Jose	Perez	Q	\N	\N	Grade 1	Bonifacio	184870663628	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Perez	+63 9119146882	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00065	\N	\N	\N	t	t	active	\N	\N	\N
3275	NCS-2025-00066	Pedro V. Rivera	Pedro	Rivera	V	\N	\N	Grade 1	Bonifacio	136320991859	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Rivera	+63 9182163542	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00066	\N	\N	\N	t	t	active	\N	\N	\N
3276	NCS-2025-00067	Camila G. Fernandez	Camila	Fernandez	G	\N	\N	Grade 1	Bonifacio	135954719938	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Fernandez	+63 9753038349	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00067	\N	\N	\N	t	t	active	\N	\N	\N
3277	NCS-2025-00068	Nicole O. Perez	Nicole	Perez	O	\N	\N	Grade 1	Bonifacio	150949932661	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Perez	+63 9603442122	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00068	\N	\N	\N	t	t	active	\N	\N	\N
3278	NCS-2025-00069	Angela G. Flores	Angela	Flores	G	\N	\N	Grade 1	Bonifacio	127580358575	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Flores	+63 9784935884	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00069	\N	\N	\N	t	t	active	\N	\N	\N
3279	NCS-2025-00070	Ana R. Bautista	Ana	Bautista	R	\N	\N	Grade 1	Bonifacio	163626307633	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Bautista	+63 9951051499	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00070	\N	\N	\N	t	t	active	\N	\N	\N
3280	NCS-2025-00071	Andres I. Valdez	Andres	Valdez	I	\N	\N	Grade 1	Bonifacio	153413520976	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Valdez	+63 9908620750	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00071	\N	\N	\N	t	t	active	\N	\N	\N
3281	NCS-2025-00072	Faith R. Ortiz	Faith	Ortiz	R	\N	\N	Grade 1	Bonifacio	184352706886	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Ortiz	+63 9939621105	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00072	\N	\N	\N	t	t	active	\N	\N	\N
3282	NCS-2025-00073	Ethan T. Jimenez	Ethan	Jimenez	T	\N	\N	Grade 1	Bonifacio	154610598981	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Jimenez	+63 9978284893	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00073	\N	\N	\N	t	t	active	\N	\N	\N
3283	NCS-2025-00074	Rafael A. Reyes	Rafael	Reyes	A	\N	\N	Grade 1	Bonifacio	110172872483	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Reyes	+63 9813527951	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00074	\N	\N	\N	t	t	active	\N	\N	\N
3284	NCS-2025-00075	Miguel Q. Rivera	Miguel	Rivera	Q	\N	\N	Grade 1	Bonifacio	119351324664	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Rivera	+63 9359339335	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00075	\N	\N	\N	t	t	active	\N	\N	\N
3285	NCS-2025-00076	Noah L. Reyes	Noah	Reyes	L	\N	\N	Grade 1	Bonifacio	160844996617	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Reyes	+63 9771457136	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00076	\N	\N	\N	t	t	active	\N	\N	\N
3286	NCS-2025-00077	Mason E. Flores	Mason	Flores	E	\N	\N	Grade 1	Bonifacio	175542894589	2024	2025	Male	Male	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Flores	+63 9517349698	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00077	\N	\N	\N	t	t	active	\N	\N	\N
3287	NCS-2025-00078	Crystal G. Aquino	Crystal	Aquino	G	\N	\N	Grade 1	Bonifacio	144038834891	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Aquino	+63 9427716427	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00078	\N	\N	\N	t	t	active	\N	\N	\N
3288	NCS-2025-00079	Ana F. Bautista	Ana	Bautista	F	\N	\N	Grade 1	Bonifacio	182348186471	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Bautista	+63 9547735996	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00079	\N	\N	\N	t	t	active	\N	\N	\N
3289	NCS-2025-00080	Michelle V. Mendoza	Michelle	Mendoza	V	\N	\N	Grade 1	Bonifacio	179008142896	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Mendoza	+63 9575263057	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00080	\N	\N	\N	t	t	active	\N	\N	\N
3290	NCS-2025-00081	Patricia V. Gomez	Patricia	Gomez	V	\N	\N	Grade 1	Bonifacio	149724672919	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Gomez	+63 9363813414	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00081	\N	\N	\N	t	t	active	\N	\N	\N
3291	NCS-2025-00082	Carlos A. Castro	Carlos	Castro	A	\N	\N	Grade 1	Bonifacio	185114858590	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Castro	+63 9231610208	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00082	\N	\N	\N	t	t	active	\N	\N	\N
3292	NCS-2025-00083	Camila B. Romero	Camila	Romero	B	\N	\N	Grade 1	Bonifacio	172218711512	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Romero	+63 9604410132	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00083	\N	\N	\N	t	t	active	\N	\N	\N
3293	NCS-2025-00084	Elena M. Cruz	Elena	Cruz	M	\N	\N	Grade 1	Bonifacio	159167279323	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Cruz	+63 9416539923	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00084	\N	\N	\N	t	t	active	\N	\N	\N
3294	NCS-2025-00085	Mason C. Aguilar	Mason	Aguilar	C	\N	\N	Grade 1	Bonifacio	149930060564	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Aguilar	+63 9852940420	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00085	\N	\N	\N	t	t	active	\N	\N	\N
3295	NCS-2025-00086	Valentina K. Sanchez	Valentina	Sanchez	K	\N	\N	Grade 1	Bonifacio	142230139362	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Sanchez	+63 9812591951	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00086	\N	\N	\N	t	t	active	\N	\N	\N
3296	NCS-2025-00087	Elena W. Fernandez	Elena	Fernandez	W	\N	\N	Grade 1	Bonifacio	190258632644	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Fernandez	+63 9194118186	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00087	\N	\N	\N	t	t	active	\N	\N	\N
3297	NCS-2025-00088	Nathan A. Garcia	Nathan	Garcia	A	\N	\N	Grade 1	Bonifacio	158765898253	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Garcia	+63 9170202426	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00088	\N	\N	\N	t	t	active	\N	\N	\N
3298	NCS-2025-00089	Rosa U. Rivera	Rosa	Rivera	U	\N	\N	Grade 2	Rizal	173533333478	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Rivera	+63 9348645667	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00089	\N	\N	\N	t	t	active	\N	\N	\N
3299	NCS-2025-00090	Jasmine B. Torres	Jasmine	Torres	B	\N	\N	Grade 2	Rizal	131782395896	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Torres	+63 9601187396	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00090	\N	\N	\N	t	t	active	\N	\N	\N
3300	NCS-2025-00091	Angel N. Castro	Angel	Castro	N	\N	\N	Grade 2	Rizal	127779644238	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Castro	+63 9199639239	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00091	\N	\N	\N	t	t	active	\N	\N	\N
3301	NCS-2025-00092	Diego Z. Aguilar	Diego	Aguilar	Z	\N	\N	Grade 2	Rizal	181654695747	2024	2025	Male	Male	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Aguilar	+63 9554259345	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00092	\N	\N	\N	t	t	active	\N	\N	\N
3302	NCS-2025-00093	Grace K. Perez	Grace	Perez	K	\N	\N	Grade 2	Rizal	174141952849	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Perez	+63 9701367134	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00093	\N	\N	\N	t	t	active	\N	\N	\N
3303	NCS-2025-00094	Juan W. Jimenez	Juan	Jimenez	W	\N	\N	Grade 2	Rizal	137410091094	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Jimenez	+63 9103539046	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00094	\N	\N	\N	t	t	active	\N	\N	\N
3304	NCS-2025-00095	Caleb X. Torres	Caleb	Torres	X	\N	\N	Grade 2	Rizal	129897421302	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Torres	+63 9106038181	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00095	\N	\N	\N	t	t	active	\N	\N	\N
3305	NCS-2025-00096	Stephanie U. Jimenez	Stephanie	Jimenez	U	\N	\N	Grade 2	Rizal	135871269726	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Jimenez	+63 9825837386	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00096	\N	\N	\N	t	t	active	\N	\N	\N
3306	NCS-2025-00097	Sofia X. Dela Cruz	Sofia	Dela Cruz	X	\N	\N	Grade 2	Rizal	165941565926	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Dela Cruz	+63 9628568172	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00097	\N	\N	\N	t	t	active	\N	\N	\N
3307	NCS-2025-00098	Elijah N. Dela Cruz	Elijah	Dela Cruz	N	\N	\N	Grade 2	Rizal	145182496684	2024	2025	Male	Male	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Dela Cruz	+63 9412566407	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00098	\N	\N	\N	t	t	active	\N	\N	\N
3308	NCS-2025-00099	Valentina Y. Alvarez	Valentina	Alvarez	Y	\N	\N	Grade 2	Rizal	131091332509	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Alvarez	+63 9875135744	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00099	\N	\N	\N	t	t	active	\N	\N	\N
3309	NCS-2025-00100	Juan U. Alvarez	Juan	Alvarez	U	\N	\N	Grade 2	Rizal	132829179440	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Alvarez	+63 9821823881	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00100	\N	\N	\N	t	t	active	\N	\N	\N
3310	NCS-2025-00101	Elijah P. Fernandez	Elijah	Fernandez	P	\N	\N	Grade 2	Rizal	115352340042	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Fernandez	+63 9758241451	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00101	\N	\N	\N	t	t	active	\N	\N	\N
3311	NCS-2025-00102	Isabella S. Castillo	Isabella	Castillo	S	\N	\N	Grade 2	Rizal	118691335240	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Castillo	+63 9932786968	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00102	\N	\N	\N	t	t	active	\N	\N	\N
3312	NCS-2025-00103	Caleb B. Fernandez	Caleb	Fernandez	B	\N	\N	Grade 2	Rizal	181521033031	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Fernandez	+63 9279587795	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00103	\N	\N	\N	t	t	active	\N	\N	\N
3313	NCS-2025-00104	Caleb T. Reyes	Caleb	Reyes	T	\N	\N	Grade 2	Rizal	196038929198	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Reyes	+63 9753379565	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00104	\N	\N	\N	t	t	active	\N	\N	\N
3314	NCS-2025-00105	Isabella S. Perez	Isabella	Perez	S	\N	\N	Grade 2	Rizal	143485722594	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Perez	+63 9635376215	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00105	\N	\N	\N	t	t	active	\N	\N	\N
3315	NCS-2025-00106	Carmen W. Vargas	Carmen	Vargas	W	\N	\N	Grade 2	Rizal	156108706729	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Vargas	+63 9374323559	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00106	\N	\N	\N	t	t	active	\N	\N	\N
3316	NCS-2025-00107	Hope E. Alvarez	Hope	Alvarez	E	\N	\N	Grade 2	Rizal	117024120914	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Alvarez	+63 9151804341	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00107	\N	\N	\N	t	t	active	\N	\N	\N
3317	NCS-2025-00108	Rafael M. Aguilar	Rafael	Aguilar	M	\N	\N	Grade 2	Rizal	198626553569	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Aguilar	+63 9963229649	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00108	\N	\N	\N	t	t	active	\N	\N	\N
3318	NCS-2025-00109	Crystal A. Vargas	Crystal	Vargas	A	\N	\N	Grade 2	Rizal	178931992695	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Vargas	+63 9601974169	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00109	\N	\N	\N	t	t	active	\N	\N	\N
3319	NCS-2025-00110	Rosa E. Morales	Rosa	Morales	E	\N	\N	Grade 2	Rizal	140788455009	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Morales	+63 9969224476	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00110	\N	\N	\N	t	t	active	\N	\N	\N
3320	NCS-2025-00111	Daniel C. Cruz	Daniel	Cruz	C	\N	\N	Grade 2	Rizal	144326735903	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Cruz	+63 9290885131	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00111	\N	\N	\N	t	t	active	\N	\N	\N
3321	NCS-2025-00112	Isaiah H. Gutierrez	Isaiah	Gutierrez	H	\N	\N	Grade 2	Rizal	186170590160	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Gutierrez	+63 9504562891	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00112	\N	\N	\N	t	t	active	\N	\N	\N
3322	NCS-2025-00113	Isaiah P. Dela Cruz	Isaiah	Dela Cruz	P	\N	\N	Grade 2	Rizal	184642847867	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Dela Cruz	+63 9491037177	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00113	\N	\N	\N	t	t	active	\N	\N	\N
3323	NCS-2025-00114	Andres Q. Santos	Andres	Santos	Q	\N	\N	Grade 2	Rizal	119919914790	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Santos	+63 9563533070	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00114	\N	\N	\N	t	t	active	\N	\N	\N
3324	NCS-2025-00115	Lucas M. Lopez	Lucas	Lopez	M	\N	\N	Grade 2	Luna	113123508177	2024	2025	Male	Male	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Lopez	+63 9258628659	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00115	\N	\N	\N	t	t	active	\N	\N	\N
3325	NCS-2025-00116	Caleb M. Navarro	Caleb	Navarro	M	\N	\N	Grade 2	Luna	129394988149	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Navarro	+63 9327204827	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00116	\N	\N	\N	t	t	active	\N	\N	\N
3326	NCS-2025-00117	Valentina I. Navarro	Valentina	Navarro	I	\N	\N	Grade 2	Luna	167757582757	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Navarro	+63 9296342093	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00117	\N	\N	\N	t	t	active	\N	\N	\N
3327	NCS-2025-00118	Joy S. Morales	Joy	Morales	S	\N	\N	Grade 2	Luna	161939157442	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Morales	+63 9485685200	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00118	\N	\N	\N	t	t	active	\N	\N	\N
3328	NCS-2025-00119	Miguel R. Rodriguez	Miguel	Rodriguez	R	\N	\N	Grade 2	Luna	186981645042	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Rodriguez	+63 9971162871	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00119	\N	\N	\N	t	t	active	\N	\N	\N
3329	NCS-2025-00120	Pearl U. Vargas	Pearl	Vargas	U	\N	\N	Grade 2	Luna	173785271422	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Vargas	+63 9624418386	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00120	\N	\N	\N	t	t	active	\N	\N	\N
3330	NCS-2025-00121	Elena A. Gutierrez	Elena	Gutierrez	A	\N	\N	Grade 2	Luna	143662940857	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Gutierrez	+63 9188531270	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00121	\N	\N	\N	t	t	active	\N	\N	\N
3331	NCS-2025-00122	Lucia W. Sanchez	Lucia	Sanchez	W	\N	\N	Grade 2	Luna	194073649175	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Sanchez	+63 9980784696	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00122	\N	\N	\N	t	t	active	\N	\N	\N
3332	NCS-2025-00123	Daniel Y. Gomez	Daniel	Gomez	Y	\N	\N	Grade 2	Luna	117680182517	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Gomez	+63 9613480176	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00123	\N	\N	\N	t	t	active	\N	\N	\N
3333	NCS-2025-00124	Nicole G. Pascual	Nicole	Pascual	G	\N	\N	Grade 2	Luna	130872410350	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Pascual	+63 9896032751	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00124	\N	\N	\N	t	t	active	\N	\N	\N
3334	NCS-2025-00125	Lucia I. Aguilar	Lucia	Aguilar	I	\N	\N	Grade 2	Luna	160404747457	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Aguilar	+63 9564217384	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00125	\N	\N	\N	t	t	active	\N	\N	\N
3335	NCS-2025-00126	Rosa M. Castillo	Rosa	Castillo	M	\N	\N	Grade 2	Luna	124483446159	2024	2025	Female	Female	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Castillo	+63 9632562566	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00126	\N	\N	\N	t	t	active	\N	\N	\N
3336	NCS-2025-00127	Paolo P. Santiago	Paolo	Santiago	P	\N	\N	Grade 2	Luna	112515978647	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Santiago	+63 9857165467	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00127	\N	\N	\N	t	t	active	\N	\N	\N
3337	NCS-2025-00128	Christian R. Jimenez	Christian	Jimenez	R	\N	\N	Grade 2	Luna	131611150419	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Jimenez	+63 9431014136	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00128	\N	\N	\N	t	t	active	\N	\N	\N
3338	NCS-2025-00129	Joy R. Torres	Joy	Torres	R	\N	\N	Grade 2	Luna	165650149669	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Torres	+63 9750277701	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00129	\N	\N	\N	t	t	active	\N	\N	\N
3339	NCS-2025-00130	Angela K. Gomez	Angela	Gomez	K	\N	\N	Grade 2	Luna	116685563567	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Gomez	+63 9537990501	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00130	\N	\N	\N	t	t	active	\N	\N	\N
3340	NCS-2025-00131	Nathan M. Villanueva	Nathan	Villanueva	M	\N	\N	Grade 2	Luna	190340823343	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Villanueva	+63 9668715709	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00131	\N	\N	\N	t	t	active	\N	\N	\N
3341	NCS-2025-00132	Diego Q. Dela Cruz	Diego	Dela Cruz	Q	\N	\N	Grade 2	Luna	140644319987	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Dela Cruz	+63 9216380795	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00132	\N	\N	\N	t	t	active	\N	\N	\N
3342	NCS-2025-00133	Lucas J. Villanueva	Lucas	Villanueva	J	\N	\N	Grade 2	Luna	117760303333	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Villanueva	+63 9491154881	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00133	\N	\N	\N	t	t	active	\N	\N	\N
3343	NCS-2025-00134	Maria N. Valdez	Maria	Valdez	N	\N	\N	Grade 2	Luna	181202087488	2024	2025	Female	Female	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Valdez	+63 9954721584	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00134	\N	\N	\N	t	t	active	\N	\N	\N
3344	NCS-2025-00135	Angela Q. Villanueva	Angela	Villanueva	Q	\N	\N	Grade 2	Luna	155167961987	2024	2025	Female	Female	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Villanueva	+63 9844683089	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00135	\N	\N	\N	t	t	active	\N	\N	\N
3345	NCS-2025-00136	Luis A. Torres	Luis	Torres	A	\N	\N	Grade 2	Luna	151505266873	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Torres	+63 9787504191	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00136	\N	\N	\N	t	t	active	\N	\N	\N
3346	NCS-2025-00137	Carmen B. Villanueva	Carmen	Villanueva	B	\N	\N	Grade 2	Luna	175383579046	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Villanueva	+63 9140607211	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00137	\N	\N	\N	t	t	active	\N	\N	\N
3347	NCS-2025-00138	Gabriela F. Alvarez	Gabriela	Alvarez	F	\N	\N	Grade 2	Luna	154045231766	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Alvarez	+63 9518217182	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00138	\N	\N	\N	t	t	active	\N	\N	\N
3348	NCS-2025-00139	Valentina J. Villanueva	Valentina	Villanueva	J	\N	\N	Grade 2	Luna	161135964558	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Villanueva	+63 9120714933	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00139	\N	\N	\N	t	t	active	\N	\N	\N
3349	NCS-2025-00140	Stephanie U. Pascual	Stephanie	Pascual	U	\N	\N	Grade 3	Aguinaldo	181437635936	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Pascual	+63 9369446912	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00140	\N	\N	\N	t	t	active	\N	\N	\N
3350	NCS-2025-00141	Michelle R. Aquino	Michelle	Aquino	R	\N	\N	Grade 3	Aguinaldo	122636868503	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Aquino	+63 9271567954	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00141	\N	\N	\N	t	t	active	\N	\N	\N
3351	NCS-2025-00142	Elena P. Reyes	Elena	Reyes	P	\N	\N	Grade 3	Aguinaldo	121687763563	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Reyes	+63 9672085006	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00142	\N	\N	\N	t	t	active	\N	\N	\N
3352	NCS-2025-00143	Ana O. Navarro	Ana	Navarro	O	\N	\N	Grade 3	Aguinaldo	194668725474	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Navarro	+63 9480624464	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00143	\N	\N	\N	t	t	active	\N	\N	\N
3353	NCS-2025-00144	Stephanie Y. Dela Cruz	Stephanie	Dela Cruz	Y	\N	\N	Grade 3	Aguinaldo	136325192417	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Dela Cruz	+63 9101528233	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00144	\N	\N	\N	t	t	active	\N	\N	\N
3354	NCS-2025-00145	Paula A. Martinez	Paula	Martinez	A	\N	\N	Grade 3	Aguinaldo	178300059953	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Martinez	+63 9514204247	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00145	\N	\N	\N	t	t	active	\N	\N	\N
3355	NCS-2025-00146	Manuel Q. Mendoza	Manuel	Mendoza	Q	\N	\N	Grade 3	Aguinaldo	189499598485	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Mendoza	+63 9206756532	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00146	\N	\N	\N	t	t	active	\N	\N	\N
3356	NCS-2025-00147	Paolo S. Flores	Paolo	Flores	S	\N	\N	Grade 3	Aguinaldo	113426914851	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Flores	+63 9335468607	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00147	\N	\N	\N	t	t	active	\N	\N	\N
3357	NCS-2025-00148	Miguel G. Torres	Miguel	Torres	G	\N	\N	Grade 3	Aguinaldo	167815356124	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Torres	+63 9669004739	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00148	\N	\N	\N	t	t	active	\N	\N	\N
3358	NCS-2025-00149	Michelle R. Sanchez	Michelle	Sanchez	R	\N	\N	Grade 3	Aguinaldo	155964542226	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Sanchez	+63 9169140001	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00149	\N	\N	\N	t	t	active	\N	\N	\N
3359	NCS-2025-00150	Elijah T. Jimenez	Elijah	Jimenez	T	\N	\N	Grade 3	Aguinaldo	185581215921	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Jimenez	+63 9121044893	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00150	\N	\N	\N	t	t	active	\N	\N	\N
3360	NCS-2025-00151	Lucas F. Romero	Lucas	Romero	F	\N	\N	Grade 3	Aguinaldo	130050627900	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Romero	+63 9633415055	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00151	\N	\N	\N	t	t	active	\N	\N	\N
3361	NCS-2025-00152	Victoria G. Alvarez	Victoria	Alvarez	G	\N	\N	Grade 3	Aguinaldo	138747280289	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Alvarez	+63 9146093591	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00152	\N	\N	\N	t	t	active	\N	\N	\N
3362	NCS-2025-00153	Maria X. Cruz	Maria	Cruz	X	\N	\N	Grade 3	Aguinaldo	179427915196	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Cruz	+63 9400209509	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00153	\N	\N	\N	t	t	active	\N	\N	\N
3363	NCS-2025-00154	Oliver V. Sanchez	Oliver	Sanchez	V	\N	\N	Grade 3	Aguinaldo	122517266521	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Sanchez	+63 9937635341	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00154	\N	\N	\N	t	t	active	\N	\N	\N
3364	NCS-2025-00155	Joshua K. Santos	Joshua	Santos	K	\N	\N	Grade 3	Aguinaldo	150433636250	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Santos	+63 9459268156	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00155	\N	\N	\N	t	t	active	\N	\N	\N
3365	NCS-2025-00156	Nicole C. Gonzales	Nicole	Gonzales	C	\N	\N	Grade 3	Aguinaldo	122020442019	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Gonzales	+63 9360868581	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00156	\N	\N	\N	t	t	active	\N	\N	\N
3366	NCS-2025-00157	Faith A. Perez	Faith	Perez	A	\N	\N	Grade 3	Aguinaldo	155450634326	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Perez	+63 9967018312	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00157	\N	\N	\N	t	t	active	\N	\N	\N
3367	NCS-2025-00158	Ruby N. Romero	Ruby	Romero	N	\N	\N	Grade 3	Aguinaldo	174676583214	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Romero	+63 9427639990	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00158	\N	\N	\N	t	t	active	\N	\N	\N
3368	NCS-2025-00159	Miguel D. Martinez	Miguel	Martinez	D	\N	\N	Grade 3	Aguinaldo	181296294349	2024	2025	Male	Male	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Martinez	+63 9990312127	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00159	\N	\N	\N	t	t	active	\N	\N	\N
3369	NCS-2025-00160	Camila G. Rodriguez	Camila	Rodriguez	G	\N	\N	Grade 3	Aguinaldo	121209098030	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Rodriguez	+63 9350923590	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00160	\N	\N	\N	t	t	active	\N	\N	\N
3370	NCS-2025-00161	Maria E. Morales	Maria	Morales	E	\N	\N	Grade 3	Aguinaldo	183354546462	2024	2025	Female	Female	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Morales	+63 9718670341	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00161	\N	\N	\N	t	t	active	\N	\N	\N
3371	NCS-2025-00162	Carlos S. Romero	Carlos	Romero	S	\N	\N	Grade 3	Aguinaldo	142661164396	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Romero	+63 9373659089	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00162	\N	\N	\N	t	t	active	\N	\N	\N
3372	NCS-2025-00163	Faith P. Torres	Faith	Torres	P	\N	\N	Grade 3	Jacinto	148699511056	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Torres	+63 9468738759	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00163	\N	\N	\N	t	t	active	\N	\N	\N
3373	NCS-2025-00164	Gabriela L. Gomez	Gabriela	Gomez	L	\N	\N	Grade 3	Jacinto	170266001381	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Gomez	+63 9390671741	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00164	\N	\N	\N	t	t	active	\N	\N	\N
3374	NCS-2025-00165	Isabella F. Rivera	Isabella	Rivera	F	\N	\N	Grade 3	Jacinto	160523772480	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Rivera	+63 9748700929	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00165	\N	\N	\N	t	t	active	\N	\N	\N
3375	NCS-2025-00166	Grace T. Flores	Grace	Flores	T	\N	\N	Grade 3	Jacinto	148607174875	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Flores	+63 9216337637	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00166	\N	\N	\N	t	t	active	\N	\N	\N
3376	NCS-2025-00167	Lucas U. Ramos	Lucas	Ramos	U	\N	\N	Grade 3	Jacinto	190151202598	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Ramos	+63 9485464688	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00167	\N	\N	\N	t	t	active	\N	\N	\N
3377	NCS-2025-00168	Gabriela N. Gomez	Gabriela	Gomez	N	\N	\N	Grade 3	Jacinto	137289690160	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Gomez	+63 9830617776	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00168	\N	\N	\N	t	t	active	\N	\N	\N
3378	NCS-2025-00169	Andres W. Flores	Andres	Flores	W	\N	\N	Grade 3	Jacinto	110183775996	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Flores	+63 9899772779	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00169	\N	\N	\N	t	t	active	\N	\N	\N
3379	NCS-2025-00170	Luis Q. Reyes	Luis	Reyes	Q	\N	\N	Grade 3	Jacinto	129213206100	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Reyes	+63 9997761154	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00170	\N	\N	\N	t	t	active	\N	\N	\N
3380	NCS-2025-00171	Elijah B. Garcia	Elijah	Garcia	B	\N	\N	Grade 3	Jacinto	156264875603	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Garcia	+63 9211970949	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00171	\N	\N	\N	t	t	active	\N	\N	\N
3381	NCS-2025-00172	Gabriela J. Navarro	Gabriela	Navarro	J	\N	\N	Grade 3	Jacinto	155831762746	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Navarro	+63 9204691570	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00172	\N	\N	\N	t	t	active	\N	\N	\N
3382	NCS-2025-00173	Juan Y. Aguilar	Juan	Aguilar	Y	\N	\N	Grade 3	Jacinto	126037643252	2024	2025	Male	Male	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Aguilar	+63 9327588434	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00173	\N	\N	\N	t	t	active	\N	\N	\N
3383	NCS-2025-00174	Camila U. Mendoza	Camila	Mendoza	U	\N	\N	Grade 3	Jacinto	166964248632	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Mendoza	+63 9184537505	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00174	\N	\N	\N	t	t	active	\N	\N	\N
3384	NCS-2025-00175	Patricia R. Castillo	Patricia	Castillo	R	\N	\N	Grade 3	Jacinto	195605106771	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Castillo	+63 9341517100	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00175	\N	\N	\N	t	t	active	\N	\N	\N
3385	NCS-2025-00176	Caleb B. Aquino	Caleb	Aquino	B	\N	\N	Grade 3	Jacinto	124050313536	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Aquino	+63 9907919728	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00176	\N	\N	\N	t	t	active	\N	\N	\N
3386	NCS-2025-00177	Fernando F. Santos	Fernando	Santos	F	\N	\N	Grade 3	Jacinto	172514476575	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Santos	+63 9794733417	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00177	\N	\N	\N	t	t	active	\N	\N	\N
3387	NCS-2025-00178	Isabella R. Alvarez	Isabella	Alvarez	R	\N	\N	Grade 3	Jacinto	156483184739	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Alvarez	+63 9725702311	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00178	\N	\N	\N	t	t	active	\N	\N	\N
3388	NCS-2025-00179	Sofia D. Santos	Sofia	Santos	D	\N	\N	Grade 3	Jacinto	173642120057	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Santos	+63 9824635449	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00179	\N	\N	\N	t	t	active	\N	\N	\N
3389	NCS-2025-00180	Sofia T. Torres	Sofia	Torres	T	\N	\N	Grade 3	Jacinto	141444491980	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Torres	+63 9185015527	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00180	\N	\N	\N	t	t	active	\N	\N	\N
3390	NCS-2025-00181	Elena T. Gonzales	Elena	Gonzales	T	\N	\N	Grade 3	Jacinto	198628235736	2024	2025	Female	Female	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Gonzales	+63 9731686191	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00181	\N	\N	\N	t	t	active	\N	\N	\N
3391	NCS-2025-00182	Andrea W. Fernandez	Andrea	Fernandez	W	\N	\N	Grade 3	Jacinto	153402124006	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Fernandez	+63 9183424740	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00182	\N	\N	\N	t	t	active	\N	\N	\N
3392	NCS-2025-00183	Elena Y. Flores	Elena	Flores	Y	\N	\N	Grade 3	Jacinto	116071230035	2024	2025	Female	Female	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Flores	+63 9787048275	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00183	\N	\N	\N	t	t	active	\N	\N	\N
3393	NCS-2025-00184	Liam G. Reyes	Liam	Reyes	G	\N	\N	Grade 3	Jacinto	128154864326	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Reyes	+63 9564453276	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00184	\N	\N	\N	t	t	active	\N	\N	\N
3394	NCS-2025-00185	Manuel A. Santos	Manuel	Santos	A	\N	\N	Grade 3	Jacinto	115683072439	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Santos	+63 9902044767	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00185	\N	\N	\N	t	t	active	\N	\N	\N
3395	NCS-2025-00186	Stephanie N. Ortiz	Stephanie	Ortiz	N	\N	\N	Grade 3	Jacinto	192754583494	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Ortiz	+63 9859491239	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00186	\N	\N	\N	t	t	active	\N	\N	\N
3397	NCS-2025-00188	Lucas R. Pascual	Lucas	Pascual	R	\N	\N	Grade 4	Silang	122074173008	2024	2025	Male	Male	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Pascual	+63 9736929435	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00188	\N	\N	\N	t	t	active	\N	\N	\N
3398	NCS-2025-00189	Paula X. Chavez	Paula	Chavez	X	\N	\N	Grade 4	Silang	112286536942	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Chavez	+63 9815287670	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00189	\N	\N	\N	t	t	active	\N	\N	\N
3399	NCS-2025-00190	Paolo H. Gomez	Paolo	Gomez	H	\N	\N	Grade 4	Silang	130199024579	2024	2025	Male	Male	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Gomez	+63 9985904552	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00190	\N	\N	\N	t	t	active	\N	\N	\N
3401	NCS-2025-00192	Diego H. Rivera	Diego	Rivera	H	\N	\N	Grade 4	Silang	156966500733	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Rivera	+63 9387031842	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00192	\N	\N	\N	t	t	active	\N	\N	\N
3402	NCS-2025-00193	Nathan B. Pascual	Nathan	Pascual	B	\N	\N	Grade 4	Silang	165939893896	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Pascual	+63 9468204777	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00193	\N	\N	\N	t	t	active	\N	\N	\N
3403	NCS-2025-00194	Ruby I. Chavez	Ruby	Chavez	I	\N	\N	Grade 4	Silang	198704577024	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Chavez	+63 9607239993	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00194	\N	\N	\N	t	t	active	\N	\N	\N
3404	NCS-2025-00195	Andrea W. Morales	Andrea	Morales	W	\N	\N	Grade 4	Silang	179021017544	2024	2025	Female	Female	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Morales	+63 9152285242	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00195	\N	\N	\N	t	t	active	\N	\N	\N
3405	NCS-2025-00196	Nicole H. Lopez	Nicole	Lopez	H	\N	\N	Grade 4	Silang	162285129709	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Lopez	+63 9767566057	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00196	\N	\N	\N	t	t	active	\N	\N	\N
3406	NCS-2025-00197	Hope U. Navarro	Hope	Navarro	U	\N	\N	Grade 4	Silang	188543391036	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Navarro	+63 9508811840	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00197	\N	\N	\N	t	t	active	\N	\N	\N
3407	NCS-2025-00198	Joshua X. Hernandez	Joshua	Hernandez	X	\N	\N	Grade 4	Silang	191069140142	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Hernandez	+63 9852227406	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00198	\N	\N	\N	t	t	active	\N	\N	\N
3396	NCS-2025-00187	Angelo O. Vargas	Angelo	Vargas	O	\N	\N	Grade 4	Silang	181922044111	2024	2025	Male	Male	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Vargas	+63 9552074869	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-13 23:24:26	\N	NCS-2025-00187	\N	\N	\N	t	t	dropped_out	b3	individual	2025-10-08
3400	NCS-2025-00191	Andres G. Chavez	Andres	Chavez	G	\N	\N	Grade 4	Silang	164491423352	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Chavez	+63 9165885142	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-14 02:05:41	\N	NCS-2025-00191	\N	\N	\N	t	t	active	a1	domestic	2025-10-12
3408	NCS-2025-00199	Rafael Q. Fernandez	Rafael	Fernandez	Q	\N	\N	Grade 4	Silang	162790908475	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Fernandez	+63 9491745059	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00199	\N	\N	\N	t	t	active	\N	\N	\N
3409	NCS-2025-00200	Joshua H. Villanueva	Joshua	Villanueva	H	\N	\N	Grade 4	Silang	144118974649	2024	2025	Male	Male	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Villanueva	+63 9852534404	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00200	\N	\N	\N	t	t	active	\N	\N	\N
3410	NCS-2025-00201	Fernando J. Cruz	Fernando	Cruz	J	\N	\N	Grade 4	Silang	141997259283	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Cruz	+63 9288619234	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00201	\N	\N	\N	t	t	active	\N	\N	\N
3411	NCS-2025-00202	Angelo S. Rivera	Angelo	Rivera	S	\N	\N	Grade 4	Silang	194851969087	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Rivera	+63 9794914831	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00202	\N	\N	\N	t	t	active	\N	\N	\N
3412	NCS-2025-00203	Pearl B. Velasquez	Pearl	Velasquez	B	\N	\N	Grade 4	Silang	153850542336	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Velasquez	+63 9300751355	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00203	\N	\N	\N	t	t	active	\N	\N	\N
3413	NCS-2025-00204	Caleb H. Reyes	Caleb	Reyes	H	\N	\N	Grade 4	Silang	153161626836	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Reyes	+63 9311142837	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00204	\N	\N	\N	t	t	active	\N	\N	\N
3414	NCS-2025-00205	Nicole A. Bautista	Nicole	Bautista	A	\N	\N	Grade 4	Silang	110423582289	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Bautista	+63 9474395692	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00205	\N	\N	\N	t	t	active	\N	\N	\N
3415	NCS-2025-00206	Daniel R. Pascual	Daniel	Pascual	R	\N	\N	Grade 4	Silang	151451439917	2024	2025	Male	Male	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Pascual	+63 9259316927	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00206	\N	\N	\N	t	t	active	\N	\N	\N
3416	NCS-2025-00207	Hope X. Fernandez	Hope	Fernandez	X	\N	\N	Grade 4	Silang	166217074303	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Fernandez	+63 9572150540	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00207	\N	\N	\N	t	t	active	\N	\N	\N
3417	NCS-2025-00208	Isabella O. Navarro	Isabella	Navarro	O	\N	\N	Grade 4	Silang	132138999701	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Navarro	+63 9759840260	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00208	\N	\N	\N	t	t	active	\N	\N	\N
3418	NCS-2025-00209	Joy V. Vargas	Joy	Vargas	V	\N	\N	Grade 4	Silang	113003252205	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Vargas	+63 9427477833	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00209	\N	\N	\N	t	t	active	\N	\N	\N
3419	NCS-2025-00210	Isabella P. Castillo	Isabella	Castillo	P	\N	\N	Grade 4	Silang	121185399459	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Castillo	+63 9106944694	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00210	\N	\N	\N	t	t	active	\N	\N	\N
3420	NCS-2025-00211	Rosa B. Pascual	Rosa	Pascual	B	\N	\N	Grade 4	Silang	195596102070	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Pascual	+63 9691083222	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00211	\N	\N	\N	t	t	active	\N	\N	\N
3421	NCS-2025-00212	Valentina S. Chavez	Valentina	Chavez	S	\N	\N	Grade 4	Silang	142694102278	2024	2025	Female	Female	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Chavez	+63 9462080840	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00212	\N	\N	\N	t	t	active	\N	\N	\N
3422	NCS-2025-00213	Nathan G. Ortiz	Nathan	Ortiz	G	\N	\N	Grade 4	Dagohoy	198478540946	2024	2025	Male	Male	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Ortiz	+63 9601225377	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00213	\N	\N	\N	t	t	active	\N	\N	\N
3423	NCS-2025-00214	Michelle S. Morales	Michelle	Morales	S	\N	\N	Grade 4	Dagohoy	185494262023	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Morales	+63 9240413925	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00214	\N	\N	\N	t	t	active	\N	\N	\N
3424	NCS-2025-00215	Victoria J. Dela Cruz	Victoria	Dela Cruz	J	\N	\N	Grade 4	Dagohoy	176369459844	2024	2025	Female	Female	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Dela Cruz	+63 9484969837	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00215	\N	\N	\N	t	t	active	\N	\N	\N
3425	NCS-2025-00216	Camila O. Castillo	Camila	Castillo	O	\N	\N	Grade 4	Dagohoy	151426633322	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Castillo	+63 9586592925	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00216	\N	\N	\N	t	t	active	\N	\N	\N
3426	NCS-2025-00217	Pearl R. Dela Cruz	Pearl	Dela Cruz	R	\N	\N	Grade 4	Dagohoy	178139299170	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Dela Cruz	+63 9876238422	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00217	\N	\N	\N	t	t	active	\N	\N	\N
3427	NCS-2025-00218	Miguel A. Sanchez	Miguel	Sanchez	A	\N	\N	Grade 4	Dagohoy	116557733305	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Sanchez	+63 9786785808	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00218	\N	\N	\N	t	t	active	\N	\N	\N
3428	NCS-2025-00219	Antonio V. Castillo	Antonio	Castillo	V	\N	\N	Grade 4	Dagohoy	118233889121	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Castillo	+63 9153383416	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00219	\N	\N	\N	t	t	active	\N	\N	\N
3429	NCS-2025-00220	Ricardo Z. Velasquez	Ricardo	Velasquez	Z	\N	\N	Grade 4	Dagohoy	167398290072	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Velasquez	+63 9708181237	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00220	\N	\N	\N	t	t	active	\N	\N	\N
3430	NCS-2025-00221	Lucas J. Romero	Lucas	Romero	J	\N	\N	Grade 4	Dagohoy	177303208780	2024	2025	Male	Male	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Romero	+63 9933095389	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00221	\N	\N	\N	t	t	active	\N	\N	\N
3431	NCS-2025-00222	Pearl J. Ortiz	Pearl	Ortiz	J	\N	\N	Grade 4	Dagohoy	146600861436	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Ortiz	+63 9849045927	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00222	\N	\N	\N	t	t	active	\N	\N	\N
3432	NCS-2025-00223	Juan A. Gutierrez	Juan	Gutierrez	A	\N	\N	Grade 4	Dagohoy	146476236226	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Gutierrez	+63 9274086005	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00223	\N	\N	\N	t	t	active	\N	\N	\N
3433	NCS-2025-00224	Joy K. Velasquez	Joy	Velasquez	K	\N	\N	Grade 4	Dagohoy	159451369423	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Velasquez	+63 9482149451	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00224	\N	\N	\N	t	t	active	\N	\N	\N
3434	NCS-2025-00225	Paolo E. Valdez	Paolo	Valdez	E	\N	\N	Grade 4	Dagohoy	160719294167	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Valdez	+63 9878780156	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00225	\N	\N	\N	t	t	active	\N	\N	\N
3435	NCS-2025-00226	Rosa T. Dela Cruz	Rosa	Dela Cruz	T	\N	\N	Grade 4	Dagohoy	121029438251	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Dela Cruz	+63 9614765545	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00226	\N	\N	\N	t	t	active	\N	\N	\N
3436	NCS-2025-00227	Jose C. Velasquez	Jose	Velasquez	C	\N	\N	Grade 4	Dagohoy	186226905774	2024	2025	Male	Male	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Velasquez	+63 9451964969	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00227	\N	\N	\N	t	t	active	\N	\N	\N
3437	NCS-2025-00228	Gabriel H. Valdez	Gabriel	Valdez	H	\N	\N	Grade 4	Dagohoy	176144654043	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Valdez	+63 9266429795	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00228	\N	\N	\N	t	t	active	\N	\N	\N
3438	NCS-2025-00229	Gabriel M. Reyes	Gabriel	Reyes	M	\N	\N	Grade 4	Dagohoy	190424874410	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Reyes	+63 9722852852	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00229	\N	\N	\N	t	t	active	\N	\N	\N
3439	NCS-2025-00230	Jose W. Castillo	Jose	Castillo	W	\N	\N	Grade 4	Dagohoy	138328820181	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Castillo	+63 9372849199	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00230	\N	\N	\N	t	t	active	\N	\N	\N
3440	NCS-2025-00231	Gabriela C. Romero	Gabriela	Romero	C	\N	\N	Grade 4	Dagohoy	163919232327	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Romero	+63 9932874220	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00231	\N	\N	\N	t	t	active	\N	\N	\N
3441	NCS-2025-00232	Ethan Z. Velasquez	Ethan	Velasquez	Z	\N	\N	Grade 4	Dagohoy	160903764322	2024	2025	Male	Male	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Velasquez	+63 9593775463	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00232	\N	\N	\N	t	t	active	\N	\N	\N
3442	NCS-2025-00233	Faith T. Gutierrez	Faith	Gutierrez	T	\N	\N	Grade 4	Dagohoy	184217917213	2024	2025	Female	Female	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Gutierrez	+63 9640428324	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00233	\N	\N	\N	t	t	active	\N	\N	\N
3443	NCS-2025-00234	Carmen N. Gomez	Carmen	Gomez	N	\N	\N	Grade 4	Dagohoy	190678660314	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Gomez	+63 9743831681	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00234	\N	\N	\N	t	t	active	\N	\N	\N
3444	NCS-2025-00235	Lucia A. Sanchez	Lucia	Sanchez	A	\N	\N	Grade 4	Dagohoy	136510939371	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Sanchez	+63 9412539016	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00235	\N	\N	\N	t	t	active	\N	\N	\N
3445	NCS-2025-00236	Oliver G. Rivera	Oliver	Rivera	G	\N	\N	Grade 4	Dagohoy	128698248215	2024	2025	Male	Male	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Rivera	+63 9678197570	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00236	\N	\N	\N	t	t	active	\N	\N	\N
3446	NCS-2025-00237	Sofia T. Pascual	Sofia	Pascual	T	\N	\N	Grade 4	Dagohoy	129974617072	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Pascual	+63 9722903441	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00237	\N	\N	\N	t	t	active	\N	\N	\N
3447	NCS-2025-00238	Andres O. Castillo	Andres	Castillo	O	\N	\N	Grade 5	Tandang Sora	123798004001	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Castillo	+63 9163632185	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00238	\N	\N	\N	t	t	active	\N	\N	\N
3448	NCS-2025-00239	Oliver T. Pascual	Oliver	Pascual	T	\N	\N	Grade 5	Tandang Sora	156251340280	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Pascual	+63 9365535029	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00239	\N	\N	\N	t	t	active	\N	\N	\N
3449	NCS-2025-00240	Daniel E. Rivera	Daniel	Rivera	E	\N	\N	Grade 5	Tandang Sora	161186590579	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Rivera	+63 9436695198	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00240	\N	\N	\N	t	t	active	\N	\N	\N
3450	NCS-2025-00241	Angel I. Alvarez	Angel	Alvarez	I	\N	\N	Grade 5	Tandang Sora	143244478500	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Alvarez	+63 9842907588	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00241	\N	\N	\N	t	t	active	\N	\N	\N
3451	NCS-2025-00242	Fernando E. Gonzales	Fernando	Gonzales	E	\N	\N	Grade 5	Tandang Sora	179277892657	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Gonzales	+63 9928106460	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00242	\N	\N	\N	t	t	active	\N	\N	\N
3452	NCS-2025-00243	Nathan E. Lopez	Nathan	Lopez	E	\N	\N	Grade 5	Tandang Sora	185873790801	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Lopez	+63 9572206024	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00243	\N	\N	\N	t	t	active	\N	\N	\N
3453	NCS-2025-00244	Camila M. Mendoza	Camila	Mendoza	M	\N	\N	Grade 5	Tandang Sora	156657393743	2024	2025	Female	Female	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Mendoza	+63 9877086364	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00244	\N	\N	\N	t	t	active	\N	\N	\N
3454	NCS-2025-00245	Liam C. Flores	Liam	Flores	C	\N	\N	Grade 5	Tandang Sora	126707044996	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Flores	+63 9946583922	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00245	\N	\N	\N	t	t	active	\N	\N	\N
3455	NCS-2025-00246	Valentina C. Vargas	Valentina	Vargas	C	\N	\N	Grade 5	Tandang Sora	134876261766	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Vargas	+63 9369774364	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00246	\N	\N	\N	t	t	active	\N	\N	\N
3456	NCS-2025-00247	Hope Y. Chavez	Hope	Chavez	Y	\N	\N	Grade 5	Tandang Sora	196736329985	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Chavez	+63 9481103965	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00247	\N	\N	\N	t	t	active	\N	\N	\N
3457	NCS-2025-00248	Daniel N. Gomez	Daniel	Gomez	N	\N	\N	Grade 5	Tandang Sora	194985807187	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Gomez	+63 9680947704	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00248	\N	\N	\N	t	t	active	\N	\N	\N
3458	NCS-2025-00249	Patricia F. Sanchez	Patricia	Sanchez	F	\N	\N	Grade 5	Tandang Sora	135139855422	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Sanchez	+63 9244205467	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00249	\N	\N	\N	t	t	active	\N	\N	\N
3459	NCS-2025-00250	Ethan T. Herrera	Ethan	Herrera	T	\N	\N	Grade 5	Tandang Sora	150867250834	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Herrera	+63 9952259816	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00250	\N	\N	\N	t	t	active	\N	\N	\N
3460	NCS-2025-00251	Victoria C. Gomez	Victoria	Gomez	C	\N	\N	Grade 5	Tandang Sora	172758074454	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Gomez	+63 9699606016	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00251	\N	\N	\N	t	t	active	\N	\N	\N
3461	NCS-2025-00252	Fernando M. Morales	Fernando	Morales	M	\N	\N	Grade 5	Tandang Sora	184853542140	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Morales	+63 9893634399	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00252	\N	\N	\N	t	t	active	\N	\N	\N
3462	NCS-2025-00253	Carmen Z. Alvarez	Carmen	Alvarez	Z	\N	\N	Grade 5	Tandang Sora	157591641993	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Alvarez	+63 9489524698	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00253	\N	\N	\N	t	t	active	\N	\N	\N
3463	NCS-2025-00254	Isabella D. Bautista	Isabella	Bautista	D	\N	\N	Grade 5	Tandang Sora	129892376536	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Bautista	+63 9854745930	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00254	\N	\N	\N	t	t	active	\N	\N	\N
3464	NCS-2025-00255	Patricia K. Dela Cruz	Patricia	Dela Cruz	K	\N	\N	Grade 5	Tandang Sora	186006004807	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Dela Cruz	+63 9960297518	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00255	\N	\N	\N	t	t	active	\N	\N	\N
3465	NCS-2025-00256	Joshua W. Reyes	Joshua	Reyes	W	\N	\N	Grade 5	Tandang Sora	131720329487	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Reyes	+63 9639327132	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00256	\N	\N	\N	t	t	active	\N	\N	\N
3466	NCS-2025-00257	Sofia K. Torres	Sofia	Torres	K	\N	\N	Grade 5	Tandang Sora	160461162766	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Torres	+63 9237139723	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00257	\N	\N	\N	t	t	active	\N	\N	\N
3467	NCS-2025-00258	Joy I. Gutierrez	Joy	Gutierrez	I	\N	\N	Grade 5	Tandang Sora	133598506854	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Gutierrez	+63 9337358285	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00258	\N	\N	\N	t	t	active	\N	\N	\N
3540	STU2025665985433	Buotan Cris John	Buotan	Cris John	\N	\N	crawad@pl.com	K	\N	12312412312	\N	\N	Male	Male	2012-01-25	\N	13	\N	\N	/demo/images/student-photo.jpg	{"house_no":"123123","street":"Timuga","barangay":"Timuga","city_municipality":"Iligan City","province":"Misamis Oriental","country":"Philippines","zip_code":"9012"}	{"house_no":"123123","street":"Timuga","barangay":"Timuga","city_municipality":"Iligan City","province":"Misamis Oriental","country":"Philippines","zip_code":"9012"}	\N	{"first_name":null,"last_name":null,"middle_name":null,"contact_number":null}	{"first_name":null,"last_name":null,"middle_name":null,"contact_number":null}	N/A	Father: N/A, Mother: N/A	Enrolled	2025-10-15 16:30:04	2025-10-15 16:30:04	[]	f	\N	f	\N	f	[]	2025-10-16 00:30:06	2025-10-16 00:30:06	\N	STU2025665985433	/demo/images/student-photo.jpg	qr-codes/STU2025665985433_qr.svg	123123 Timuga, Timuga, Iligan City, Misamis Oriental	t	t	active	\N	\N	\N
3468	NCS-2025-00259	Paula V. Santos	Paula	Santos	V	\N	\N	Grade 5	Tandang Sora	137636840587	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Santos	+63 9149544504	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00259	\N	\N	\N	t	t	active	\N	\N	\N
3469	NCS-2025-00260	Rosa Q. Aguilar	Rosa	Aguilar	Q	\N	\N	Grade 5	Tandang Sora	143133164428	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Aguilar	+63 9523536391	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00260	\N	\N	\N	t	t	active	\N	\N	\N
3470	NCS-2025-00261	Nicole O. Cruz	Nicole	Cruz	O	\N	\N	Grade 5	Tandang Sora	190493415251	2024	2025	Female	Female	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Cruz	+63 9234296970	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00261	\N	\N	\N	t	t	active	\N	\N	\N
3471	NCS-2025-00262	Ricardo E. Rodriguez	Ricardo	Rodriguez	E	\N	\N	Grade 5	Gabriela	174308623775	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Rodriguez	+63 9566421649	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00262	\N	\N	\N	t	t	active	\N	\N	\N
3472	NCS-2025-00263	Miguel M. Garcia	Miguel	Garcia	M	\N	\N	Grade 5	Gabriela	182056452739	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Garcia	+63 9548024381	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00263	\N	\N	\N	t	t	active	\N	\N	\N
3473	NCS-2025-00264	Carmen L. Gonzales	Carmen	Gonzales	L	\N	\N	Grade 5	Gabriela	189533322151	2024	2025	Female	Female	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Gonzales	+63 9261174659	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00264	\N	\N	\N	t	t	active	\N	\N	\N
3474	NCS-2025-00265	Jasmine S. Diaz	Jasmine	Diaz	S	\N	\N	Grade 5	Gabriela	197571449189	2024	2025	Female	Female	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Diaz	+63 9664134067	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00265	\N	\N	\N	t	t	active	\N	\N	\N
3475	NCS-2025-00266	Grace T. Sanchez	Grace	Sanchez	T	\N	\N	Grade 5	Gabriela	115004523983	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Sanchez	+63 9295923825	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00266	\N	\N	\N	t	t	active	\N	\N	\N
3476	NCS-2025-00267	Noah I. Chavez	Noah	Chavez	I	\N	\N	Grade 5	Gabriela	140407756311	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Chavez	+63 9457509729	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00267	\N	\N	\N	t	t	active	\N	\N	\N
3477	NCS-2025-00268	Jasmine I. Dela Cruz	Jasmine	Dela Cruz	I	\N	\N	Grade 5	Gabriela	196478733812	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Dela Cruz	+63 9532743918	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00268	\N	\N	\N	t	t	active	\N	\N	\N
3478	NCS-2025-00269	Carmen T. Chavez	Carmen	Chavez	T	\N	\N	Grade 5	Gabriela	129948710843	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Chavez	+63 9972052280	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00269	\N	\N	\N	t	t	active	\N	\N	\N
3479	NCS-2025-00270	Sofia D. Diaz	Sofia	Diaz	D	\N	\N	Grade 5	Gabriela	175054084266	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Diaz	+63 9814089898	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00270	\N	\N	\N	t	t	active	\N	\N	\N
3480	NCS-2025-00271	Juan G. Bautista	Juan	Bautista	G	\N	\N	Grade 5	Gabriela	193696344910	2024	2025	Male	Male	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Bautista	+63 9788989673	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00271	\N	\N	\N	t	t	active	\N	\N	\N
3481	NCS-2025-00272	Victoria F. Torres	Victoria	Torres	F	\N	\N	Grade 5	Gabriela	129533698721	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Torres	+63 9109146752	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00272	\N	\N	\N	t	t	active	\N	\N	\N
3482	NCS-2025-00273	Juan E. Valdez	Juan	Valdez	E	\N	\N	Grade 5	Gabriela	120475026468	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Valdez	+63 9715607821	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00273	\N	\N	\N	t	t	active	\N	\N	\N
3483	NCS-2025-00274	Diego D. Rivera	Diego	Rivera	D	\N	\N	Grade 5	Gabriela	155669429226	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Rivera	+63 9431069383	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00274	\N	\N	\N	t	t	active	\N	\N	\N
3484	NCS-2025-00275	Mason S. Valdez	Mason	Valdez	S	\N	\N	Grade 5	Gabriela	134857669828	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Valdez	+63 9945401081	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00275	\N	\N	\N	t	t	active	\N	\N	\N
3485	NCS-2025-00276	Sofia S. Ramos	Sofia	Ramos	S	\N	\N	Grade 5	Gabriela	154750890610	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Ramos	+63 9488598642	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00276	\N	\N	\N	t	t	active	\N	\N	\N
3486	NCS-2025-00277	Maria G. Ramos	Maria	Ramos	G	\N	\N	Grade 5	Gabriela	186628156643	2024	2025	Female	Female	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Ramos	+63 9195079828	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00277	\N	\N	\N	t	t	active	\N	\N	\N
3487	NCS-2025-00278	Diego J. Rodriguez	Diego	Rodriguez	J	\N	\N	Grade 5	Gabriela	142869315309	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Rodriguez	+63 9913024873	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00278	\N	\N	\N	t	t	active	\N	\N	\N
3488	NCS-2025-00279	Joshua A. Hernandez	Joshua	Hernandez	A	\N	\N	Grade 5	Gabriela	150201721004	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Hernandez	+63 9900110471	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00279	\N	\N	\N	t	t	active	\N	\N	\N
3489	NCS-2025-00280	Crystal E. Ortiz	Crystal	Ortiz	E	\N	\N	Grade 5	Gabriela	138399216653	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Ortiz	+63 9241942827	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00280	\N	\N	\N	t	t	active	\N	\N	\N
3490	NCS-2025-00281	Gabriel D. Ortiz	Gabriel	Ortiz	D	\N	\N	Grade 5	Gabriela	159095766943	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Ortiz	+63 9580623927	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00281	\N	\N	\N	t	t	active	\N	\N	\N
3491	NCS-2025-00282	Marco B. Fernandez	Marco	Fernandez	B	\N	\N	Grade 5	Gabriela	137176603042	2024	2025	Male	Male	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Fernandez	+63 9217990365	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00282	\N	\N	\N	t	t	active	\N	\N	\N
3492	NCS-2025-00283	Ethan Q. Torres	Ethan	Torres	Q	\N	\N	Grade 5	Gabriela	192472015682	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Torres	+63 9443235478	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	t	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00283	\N	\N	\N	t	t	active	\N	\N	\N
3493	NCS-2025-00284	Gabriel T. Flores	Gabriel	Flores	T	\N	\N	Grade 5	Gabriela	172171724918	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Flores	+63 9423599323	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00284	\N	\N	\N	t	t	active	\N	\N	\N
3494	NCS-2025-00285	Elijah C. Ramos	Elijah	Ramos	C	\N	\N	Grade 5	Gabriela	175064255221	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Ramos	+63 9363651088	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00285	\N	\N	\N	t	t	active	\N	\N	\N
3495	NCS-2025-00286	Diego O. Santiago	Diego	Santiago	O	\N	\N	Grade 6	Lapu-Lapu	133778114063	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Santiago	+63 9811076640	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00286	\N	\N	\N	t	t	active	\N	\N	\N
3496	NCS-2025-00287	Mason I. Velasquez	Mason	Velasquez	I	\N	\N	Grade 6	Lapu-Lapu	136088827034	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Velasquez	+63 9686427355	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00287	\N	\N	\N	t	t	active	\N	\N	\N
3497	NCS-2025-00288	Isaiah F. Dela Cruz	Isaiah	Dela Cruz	F	\N	\N	Grade 6	Lapu-Lapu	145337722165	2024	2025	Male	Male	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Dela Cruz	+63 9667770881	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00288	\N	\N	\N	t	t	active	\N	\N	\N
3498	NCS-2025-00289	Gabriel Z. Aquino	Gabriel	Aquino	Z	\N	\N	Grade 6	Lapu-Lapu	150416674484	2024	2025	Male	Male	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Aquino	+63 9349644431	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00289	\N	\N	\N	t	t	active	\N	\N	\N
3499	NCS-2025-00290	Maria Y. Navarro	Maria	Navarro	Y	\N	\N	Grade 6	Lapu-Lapu	122834761568	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Navarro	+63 9662985161	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00290	\N	\N	\N	t	t	active	\N	\N	\N
3500	NCS-2025-00291	Angela J. Martinez	Angela	Martinez	J	\N	\N	Grade 6	Lapu-Lapu	152250557819	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Martinez	+63 9273874719	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00291	\N	\N	\N	t	t	active	\N	\N	\N
3501	NCS-2025-00292	Crystal O. Jimenez	Crystal	Jimenez	O	\N	\N	Grade 6	Lapu-Lapu	163457302992	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Jimenez	+63 9490036541	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00292	\N	\N	\N	t	t	active	\N	\N	\N
3502	NCS-2025-00293	Joy I. Chavez	Joy	Chavez	I	\N	\N	Grade 6	Lapu-Lapu	199958718066	2024	2025	Female	Female	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Chavez	+63 9518871517	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00293	\N	\N	\N	t	t	active	\N	\N	\N
3503	NCS-2025-00294	Nathan O. Chavez	Nathan	Chavez	O	\N	\N	Grade 6	Lapu-Lapu	127653588531	2024	2025	Male	Male	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Chavez	+63 9515955157	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00294	\N	\N	\N	t	t	active	\N	\N	\N
3504	NCS-2025-00295	Camila F. Chavez	Camila	Chavez	F	\N	\N	Grade 6	Lapu-Lapu	144959700755	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Chavez	+63 9120257077	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00295	\N	\N	\N	t	t	active	\N	\N	\N
3505	NCS-2025-00296	Michelle F. Flores	Michelle	Flores	F	\N	\N	Grade 6	Lapu-Lapu	175442372221	2024	2025	Female	Female	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Flores	+63 9102990643	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00296	\N	\N	\N	t	t	active	\N	\N	\N
3506	NCS-2025-00297	Joy Z. Torres	Joy	Torres	Z	\N	\N	Grade 6	Lapu-Lapu	131289164993	2024	2025	Female	Female	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Torres	+63 9717752993	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00297	\N	\N	\N	t	t	active	\N	\N	\N
3507	NCS-2025-00298	Gabriel F. Torres	Gabriel	Torres	F	\N	\N	Grade 6	Lapu-Lapu	114897181448	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Torres	+63 9561445382	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00298	\N	\N	\N	t	t	active	\N	\N	\N
3508	NCS-2025-00299	Noah R. Gomez	Noah	Gomez	R	\N	\N	Grade 6	Lapu-Lapu	125644210036	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Gomez	+63 9362932239	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00299	\N	\N	\N	t	t	active	\N	\N	\N
3509	NCS-2025-00300	Gabriela J. Vargas	Gabriela	Vargas	J	\N	\N	Grade 6	Lapu-Lapu	164686340519	2024	2025	Female	Female	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Vargas	+63 9890714602	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00300	\N	\N	\N	t	t	active	\N	\N	\N
3510	NCS-2025-00301	Stephanie E. Perez	Stephanie	Perez	E	\N	\N	Grade 6	Lapu-Lapu	197376239077	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Perez	+63 9835911296	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00301	\N	\N	\N	t	t	active	\N	\N	\N
3511	NCS-2025-00302	Nicole P. Flores	Nicole	Flores	P	\N	\N	Grade 6	Lapu-Lapu	123033230445	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Flores	+63 9402568759	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00302	\N	\N	\N	t	t	active	\N	\N	\N
3512	NCS-2025-00303	Paolo C. Castro	Paolo	Castro	C	\N	\N	Grade 6	Lapu-Lapu	195318287182	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Castro	+63 9666037737	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00303	\N	\N	\N	t	t	active	\N	\N	\N
3513	NCS-2025-00304	Maria J. Santiago	Maria	Santiago	J	\N	\N	Grade 6	Lapu-Lapu	144615228061	2024	2025	Female	Female	2009-10-07	Naawan, Misamis Oriental	16	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Santiago	+63 9879153254	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00304	\N	\N	\N	t	t	active	\N	\N	\N
3514	NCS-2025-00305	Mason I. Reyes	Mason	Reyes	I	\N	\N	Grade 6	Lapu-Lapu	117325553978	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Reyes	+63 9127280698	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00305	\N	\N	\N	t	t	active	\N	\N	\N
3515	NCS-2025-00306	Daniel M. Jimenez	Daniel	Jimenez	M	\N	\N	Grade 6	Lapu-Lapu	112175129116	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Jimenez	+63 9146425153	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00306	\N	\N	\N	t	t	active	\N	\N	\N
3516	NCS-2025-00307	Michelle V. Dela Cruz	Michelle	Dela Cruz	V	\N	\N	Grade 6	Lapu-Lapu	153261701339	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Dela Cruz	+63 9353179713	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00307	\N	\N	\N	t	t	active	\N	\N	\N
3517	NCS-2025-00308	Patricia H. Fernandez	Patricia	Fernandez	H	\N	\N	Grade 6	Lapu-Lapu	185675020685	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Fernandez	+63 9939607546	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00308	\N	\N	\N	t	t	active	\N	\N	\N
3518	NCS-2025-00309	Angel S. Gutierrez	Angel	Gutierrez	S	\N	\N	Grade 6	Lapu-Lapu	120294926087	2024	2025	Female	Female	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Gutierrez	+63 9719401845	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00309	\N	\N	\N	t	t	active	\N	\N	\N
3519	NCS-2025-00310	Mason Y. Aguilar	Mason	Aguilar	Y	\N	\N	Grade 6	Magat Salamat	128561384399	2024	2025	Male	Male	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Aguilar	+63 9604159669	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00310	\N	\N	\N	t	t	active	\N	\N	\N
3520	NCS-2025-00311	Andres Y. Mendoza	Andres	Mendoza	Y	\N	\N	Grade 6	Magat Salamat	147206511729	2024	2025	Male	Male	2015-10-07	Naawan, Misamis Oriental	10	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Mendoza	+63 9606916096	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00311	\N	\N	\N	t	t	active	\N	\N	\N
3521	NCS-2025-00312	Elijah Y. Valdez	Elijah	Valdez	Y	\N	\N	Grade 6	Magat Salamat	114956380482	2024	2025	Male	Male	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Valdez	+63 9963037110	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00312	\N	\N	\N	t	t	active	\N	\N	\N
3522	NCS-2025-00313	Manuel W. Gonzales	Manuel	Gonzales	W	\N	\N	Grade 6	Magat Salamat	194227323953	2024	2025	Male	Male	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Rodrigo Gonzales	+63 9566575964	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00313	\N	\N	\N	t	t	active	\N	\N	\N
3523	NCS-2025-00314	Lucia P. Jimenez	Lucia	Jimenez	P	\N	\N	Grade 6	Magat Salamat	186280895519	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Jimenez	+63 9866778813	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00314	\N	\N	\N	t	t	active	\N	\N	\N
3524	NCS-2025-00315	Rosa B. Garcia	Rosa	Garcia	B	\N	\N	Grade 6	Magat Salamat	112698761136	2024	2025	Female	Female	2008-10-07	Naawan, Misamis Oriental	17	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Garcia	+63 9565878466	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00315	\N	\N	\N	t	t	active	\N	\N	\N
3525	NCS-2025-00316	Ricardo N. Aquino	Ricardo	Aquino	N	\N	\N	Grade 6	Magat Salamat	113410674292	2024	2025	Male	Male	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Benjamin Aquino	+63 9808654352	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00316	\N	\N	\N	t	t	active	\N	\N	\N
3526	NCS-2025-00317	Paolo D. Aguilar	Paolo	Aguilar	D	\N	\N	Grade 6	Magat Salamat	171953843922	2024	2025	Male	Male	2014-10-07	Naawan, Misamis Oriental	11	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Aguilar	+63 9322268308	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00317	\N	\N	\N	t	t	active	\N	\N	\N
3527	NCS-2025-00318	Liam E. Castillo	Liam	Castillo	E	\N	\N	Grade 6	Magat Salamat	149603028878	2024	2025	Male	Male	2019-10-07	Naawan, Misamis Oriental	6	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Castillo	+63 9139025938	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00318	\N	\N	\N	t	t	active	\N	\N	\N
3528	NCS-2025-00319	Victoria X. Rivera	Victoria	Rivera	X	\N	\N	Grade 6	Magat Salamat	127835007249	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Rivera	+63 9936189857	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00319	\N	\N	\N	t	t	active	\N	\N	\N
3529	NCS-2025-00320	Joshua U. Aguilar	Joshua	Aguilar	U	\N	\N	Grade 6	Magat Salamat	116715858049	2024	2025	Male	Male	2016-10-07	Naawan, Misamis Oriental	9	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Aguilar	+63 9948557752	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00320	\N	\N	\N	t	t	active	\N	\N	\N
3530	NCS-2025-00321	Isabella I. Ramos	Isabella	Ramos	I	\N	\N	Grade 6	Magat Salamat	186017843540	2024	2025	Female	Female	2013-10-07	Naawan, Misamis Oriental	12	\N	Cebuano	\N	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Lorna Ramos	+63 9530911371	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00321	\N	\N	\N	t	t	active	\N	\N	\N
3531	NCS-2025-00322	Caleb G. Villanueva	Caleb	Villanueva	G	\N	\N	Grade 6	Magat Salamat	178786810088	2024	2025	Male	Male	2012-10-07	Naawan, Misamis Oriental	13	\N	Cebuano	\N	{"street":"Purok 2","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 4","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Villanueva	+63 9659757889	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00322	\N	\N	\N	t	t	active	\N	\N	\N
3532	NCS-2025-00323	Isabella T. Pascual	Isabella	Pascual	T	\N	\N	Grade 6	Magat Salamat	117300340281	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Pascual	+63 9113947117	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00323	\N	\N	\N	t	t	active	\N	\N	\N
3533	NCS-2025-00324	Camila O. Velasquez	Camila	Velasquez	O	\N	\N	Grade 6	Magat Salamat	180191261881	2024	2025	Female	Female	2018-10-07	Naawan, Misamis Oriental	7	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 7","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Velasquez	+63 9458389179	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00324	\N	\N	\N	t	t	active	\N	\N	\N
3534	NCS-2025-00325	Joy A. Diaz	Joy	Diaz	A	\N	\N	Grade 6	Magat Salamat	190377526407	2024	2025	Female	Female	2011-10-07	Naawan, Misamis Oriental	14	\N	Cebuano	\N	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Maricel Diaz	+63 9302560271	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00325	\N	\N	\N	t	t	active	\N	\N	\N
3535	NCS-2025-00326	Andres G. Fernandez	Andres	Fernandez	G	\N	\N	Grade 6	Magat Salamat	113917499918	2024	2025	Male	Male	2017-10-07	Naawan, Misamis Oriental	8	\N	Cebuano	\N	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Fernandez	+63 9294919851	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	t	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00326	\N	\N	\N	t	t	active	\N	\N	\N
3536	NCS-2025-00327	Joy I. Martinez	Joy	Martinez	I	\N	\N	Grade 6	Magat Salamat	158639482892	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Roberto Martinez	+63 9332024025	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00327	\N	\N	\N	t	t	active	\N	\N	\N
3537	NCS-2025-00328	Camila L. Perez	Camila	Perez	L	\N	\N	Grade 6	Magat Salamat	177147177018	2024	2025	Female	Female	2007-10-07	Naawan, Misamis Oriental	18	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Eduardo Perez	+63 9895348636	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	t	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00328	\N	\N	\N	t	t	active	\N	\N	\N
3538	NCS-2025-00329	Faith L. Ramos	Faith	Ramos	L	\N	\N	Grade 6	Magat Salamat	141997646716	2024	2025	Female	Female	2020-10-07	Naawan, Misamis Oriental	5	\N	Cebuano	\N	{"street":"Purok 1","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 3","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Alma Ramos	+63 9104800147	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00329	\N	\N	\N	t	t	active	\N	\N	\N
3539	NCS-2025-00330	Gabriela B. Vargas	Gabriela	Vargas	B	\N	\N	Grade 6	Magat Salamat	168418370883	2024	2025	Female	Female	2010-10-07	Naawan, Misamis Oriental	15	\N	Cebuano	\N	{"street":"Purok 5","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	{"street":"Purok 6","barangay":"Naawan","city":"Naawan","province":"Misamis Oriental","zipCode":"9023"}	\N	\N	\N	Cynthia Vargas	+63 9174409639	Enrolled	2024-06-01 00:00:00	2024-06-01 00:00:00	\N	f	\N	f	\N	f	\N	2025-10-07 14:31:38	2025-10-07 14:31:38	\N	NCS-2025-00330	\N	\N	\N	t	t	active	\N	\N	\N
\.


--
-- Data for Name: student_enrollment_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.student_enrollment_history (id, student_id, section_id, enrolled_date, unenrolled_date, enrollment_status, school_year, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: student_qr_codes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.student_qr_codes (id, student_id, qr_code_data, qr_code_hash, is_active, generated_at, last_used_at, created_at, updated_at) FROM stdin;
10	3239	LAMMS_STUDENT_3239_1759843601_mHrWHtXs	53da3ffbd4ebbe2dd029a3fb58736dc075fdf680b11fbc5be2340f11e58a49cc	t	2025-10-07 21:26:41	\N	2025-10-07 21:26:41	2025-10-07 21:26:41
1	3240	LAMMS_STUDENT_3240_1759843578_xHPwW6oe	6b5c09b050d02d7b727e7f114177e5b9d045ccabfe7d4673a2f4bcaa30887ec5	f	2025-10-07 21:26:18	\N	2025-10-07 21:26:18	2025-10-07 21:26:42
11	3240	LAMMS_STUDENT_3240_1759843602_8rI4FJsW	41609794b5211d2b78c2b80c309634bd51358751d6fe674a04425f1a210d8f71	t	2025-10-07 21:26:42	\N	2025-10-07 21:26:42	2025-10-07 21:26:42
3	3234	LAMMS_STUDENT_3234_1759843580_0bxDNbxk	52c4ae2ab50c6c29e2a0de39a6bd657423f3d5de32a0da889fa3f130771c202d	f	2025-10-07 21:26:20	\N	2025-10-07 21:26:20	2025-10-07 21:26:43
13	3234	LAMMS_STUDENT_3234_1759843603_3ZSPBwdh	8ab52647720ea7344970531ed390472e18090d495754cffef39a762eb6410f29	t	2025-10-07 21:26:43	\N	2025-10-07 21:26:43	2025-10-07 21:26:43
4	3241	LAMMS_STUDENT_3241_1759843581_pRz4dpiF	a83aa98b7eccd97fc36e52543a4988c0076ac3800d8f243a9db5254b80e16368	f	2025-10-07 21:26:21	\N	2025-10-07 21:26:21	2025-10-07 21:26:44
14	3241	LAMMS_STUDENT_3241_1759843604_5cdDGfIv	af8f615d155c96b5d583eb13e948096a5bffc50c383626b39015f448c4617b2d	t	2025-10-07 21:26:44	\N	2025-10-07 21:26:44	2025-10-07 21:26:44
5	3244	LAMMS_STUDENT_3244_1759843582_jpl1vv7c	ac518f913d46983711bc4e20e715a44dd1fc1c7e7f4b0520b2ca497f70fae9cc	f	2025-10-07 21:26:22	\N	2025-10-07 21:26:22	2025-10-07 21:26:44
15	3244	LAMMS_STUDENT_3244_1759843604_rZm2hX2d	33c22e172fdb4d415a1f4db88e7e0c3415eb627d7f76a0c108cccfc17030d27a	t	2025-10-07 21:26:44	\N	2025-10-07 21:26:44	2025-10-07 21:26:44
6	3237	LAMMS_STUDENT_3237_1759843582_eehmWZwW	22442bec900afb055d3527268c955c60a5e2ab4d95985d0e72cf5832d1003e90	f	2025-10-07 21:26:22	\N	2025-10-07 21:26:22	2025-10-07 21:26:45
16	3237	LAMMS_STUDENT_3237_1759843605_Lyye887R	bd845f0a21d37c131c194bb79c90c34034dd62cadbc3c0cfd8909bf864c59400	t	2025-10-07 21:26:45	\N	2025-10-07 21:26:45	2025-10-07 21:26:45
7	3232	LAMMS_STUDENT_3232_1759843583_VtvRuSbW	eec7666bc2bcb9cbe9590987b169ab2c10ffc64cc6e107cfd8cb036c0c54907b	f	2025-10-07 21:26:23	\N	2025-10-07 21:26:23	2025-10-07 21:26:46
17	3232	LAMMS_STUDENT_3232_1759843606_fPYOUjPQ	0746664a22dad2e0099f267593135a1fc5a12b382b05153fe242681aaaf50f26	t	2025-10-07 21:26:46	\N	2025-10-07 21:26:46	2025-10-07 21:26:46
8	3243	LAMMS_STUDENT_3243_1759843583_qVUIw8vB	a2f5cf3e5c7d24f5de8e20da8067eb630b258d1432a9f2ce671ac4adc16a09c5	f	2025-10-07 21:26:23	\N	2025-10-07 21:26:23	2025-10-07 21:26:47
18	3243	LAMMS_STUDENT_3243_1759843607_vT5z73fg	801740de71e6af09d89b1cdbe17350cb7f8b1c907ed0520830f3145709ef270e	t	2025-10-07 21:26:47	\N	2025-10-07 21:26:47	2025-10-07 21:26:47
9	3233	LAMMS_STUDENT_3233_1759843584_vBGnYTab	5ddc94f8b01fc94c47af06249f683f19b6312611abb1180c91c8141c5cb31cca	f	2025-10-07 21:26:24	\N	2025-10-07 21:26:24	2025-10-07 21:26:47
19	3233	LAMMS_STUDENT_3233_1759843607_gwGKyvSH	7442b0c1042306a25ef6a4ff9b6a80ca8ae89c8b21db37e04ff11fa4bb399e46	t	2025-10-07 21:26:47	\N	2025-10-07 21:26:47	2025-10-07 21:26:47
20	3230	LAMMS_STUDENT_3230_1759843608_RqyvN4mz	4225dc1fc7e5a0478524b449562d4dc9bc7e7596216f1e5451244721dd4fd1f1	t	2025-10-07 21:26:48	\N	2025-10-07 21:26:48	2025-10-07 21:26:48
23	3236	LAMMS_STUDENT_3236_1759843610_RXWqnRbJ	8b28e7bcb6ea7e3db9b14e27f1bd9c0b5e54479fa28ad225f9711a270fa97f01	t	2025-10-07 21:26:50	\N	2025-10-07 21:26:50	2025-10-07 21:26:50
24	3238	LAMMS_STUDENT_3238_1759843611_M4wikPX7	30415998354223a87ecef14ef24651209ad1498b7dd2c22c02b7dfbb81d33219	t	2025-10-07 21:26:51	\N	2025-10-07 21:26:51	2025-10-07 21:26:51
25	3231	LAMMS_STUDENT_3231_1759843611_Aza5OHHQ	f223eac9a3c0db634c5c3bc0ded30e182f41c060c3cd9f4a49b402fd0ed0ca8f	t	2025-10-07 21:26:51	\N	2025-10-07 21:26:51	2025-10-07 21:26:51
27	3248	LAMMS_STUDENT_3248_1759843613_FdyvkZXK	6f538368873e4566fad867bf4c72a8cb24f0e76043985a5e9b73b31a615df1f1	t	2025-10-07 21:26:53	\N	2025-10-07 21:26:53	2025-10-07 21:26:53
28	3235	LAMMS_STUDENT_3235_1759843614_suPpvkwJ	b7915a805fba7344777cb25805d1be729e7cc6c937d9b246a2b06b699e5a715d	t	2025-10-07 21:26:54	\N	2025-10-07 21:26:54	2025-10-07 21:26:54
29	3242	LAMMS_STUDENT_3242_1759843614_h0RPffWr	2d2cd2ed318bca9f7cf28d33f0dca58046a956bb0000818033d6dfbe460581d7	t	2025-10-07 21:26:54	\N	2025-10-07 21:26:54	2025-10-07 21:26:54
30	3404	LAMMS_STUDENT_3404_1760373794_1KpV2KHo	68781472a091e55399ba0309724bdb40f248ca1f3b33796001faa65c90a54953	t	2025-10-14 00:43:14	\N	2025-10-14 00:43:14	2025-10-14 00:43:14
31	3411	LAMMS_STUDENT_3411_1760373796_bwTEqx3l	9e3a4a9138ee89854262c72c6811e1941e23eac1d673880b48d9e025fec1e953	t	2025-10-14 00:43:16	\N	2025-10-14 00:43:16	2025-10-14 00:43:16
32	3413	LAMMS_STUDENT_3413_1760373797_DBAjoY67	aa50919b6bcaf920217a850e6fae1e2d78db142c08226455c52d3299bc938e51	t	2025-10-14 00:43:17	\N	2025-10-14 00:43:17	2025-10-14 00:43:17
33	3415	LAMMS_STUDENT_3415_1760373800_BSrh42Qq	bac99cc31b74952c5f7913086f486bba93e5c1ca2266a91e3032fa9ce9e72011	t	2025-10-14 00:43:20	\N	2025-10-14 00:43:20	2025-10-14 00:43:20
34	3401	LAMMS_STUDENT_3401_1760373801_bNX0PWMr	089a836b3c0d6779c416a5c4d5ef640b5e10630ffafd0b8e8bb5cbd44f453aa3	t	2025-10-14 00:43:21	\N	2025-10-14 00:43:21	2025-10-14 00:43:21
35	3410	LAMMS_STUDENT_3410_1760373802_aPzMiI9J	1a2fbc72275e429c58c62008a82751596343691807893b3a398267c529828a81	t	2025-10-14 00:43:22	\N	2025-10-14 00:43:22	2025-10-14 00:43:22
36	3416	LAMMS_STUDENT_3416_1760373804_MEZXUMtr	c97dd6938bb86c886f592b65b158f86de1a91e11c0db82ca441a9c59ebd2a0fa	t	2025-10-14 00:43:24	\N	2025-10-14 00:43:24	2025-10-14 00:43:24
37	3406	LAMMS_STUDENT_3406_1760373805_rh4N3Qs3	737e1b0cc6cfbad80236f3155cef2d5daab460c40028b5f2b2305c85ee9cbc7f	t	2025-10-14 00:43:25	\N	2025-10-14 00:43:25	2025-10-14 00:43:25
38	3409	LAMMS_STUDENT_3409_1760373807_MY9UMhhZ	b418ea52ad671db6a90b5e8919e21b6e6d51d182a6ea7247dd9ae9829b17319c	t	2025-10-14 00:43:27	\N	2025-10-14 00:43:27	2025-10-14 00:43:27
39	3407	LAMMS_STUDENT_3407_1760373808_jr9NDne1	f5a4564bf2f80f3e06ee25687bf46c42a7a90ddbd295311367d5bcbb73b01031	t	2025-10-14 00:43:28	\N	2025-10-14 00:43:28	2025-10-14 00:43:28
40	3417	LAMMS_STUDENT_3417_1760373809_ZfqsLBIA	64d142b431dc2744814e90a5b4d40ddd4c121bef9e299582896d564677565f7c	t	2025-10-14 00:43:29	\N	2025-10-14 00:43:29	2025-10-14 00:43:29
41	3419	LAMMS_STUDENT_3419_1760373811_Iw5HjB3W	ccdc052e6784fabacf3b2244a3968707af70cba4730e2566891a0aa20fee63e2	t	2025-10-14 00:43:31	\N	2025-10-14 00:43:31	2025-10-14 00:43:31
42	3418	LAMMS_STUDENT_3418_1760373812_gwhXJIsn	85d91f89040e6c91dc0421bd546cfc17854197050c7e22718b43fc897a5345fc	t	2025-10-14 00:43:32	\N	2025-10-14 00:43:32	2025-10-14 00:43:32
43	3397	LAMMS_STUDENT_3397_1760373813_QOa8oStd	bb9bc1ca2256c4ac3c1be7725b988a3485de3654bc34e5780365a96273ee887f	t	2025-10-14 00:43:33	\N	2025-10-14 00:43:33	2025-10-14 00:43:33
26	3249	LAMMS_STUDENT_3249_1759843612_fETTxbP3	46da94d6d635d715dacc65be0c375a1230cc6b27f6b7f11a8001f02128dc7a34	f	2025-10-07 21:26:52	\N	2025-10-07 21:26:52	2025-10-24 10:05:07
2	3245	LAMMS_STUDENT_3245_1759843579_shi8r3cI	1386b0491e76df029ba4214691d8cc628127efb58c3af611fff5aa563faa6d39	f	2025-10-07 21:26:19	\N	2025-10-07 21:26:19	2025-10-24 10:05:19
21	3246	LAMMS_STUDENT_3246_1759843609_aXDZQfAr	b35a5c3af610f8f4ba1a544e067fd2d42eb67835847d86d27ba22a426ad657b8	f	2025-10-07 21:26:49	\N	2025-10-07 21:26:49	2025-10-24 10:05:11
44	3402	LAMMS_STUDENT_3402_1760373814_9DBpJnoH	1453f2af384c2646455560016364a4b79fe25ec0a7f982df82b66ab719cb6001	t	2025-10-14 00:43:34	\N	2025-10-14 00:43:34	2025-10-14 00:43:34
45	3414	LAMMS_STUDENT_3414_1760373814_lOxOTKvf	5f749d5835f901d99d57f48efe617fa8853e2dbee41c1fa2fbeca0da51dff1b3	t	2025-10-14 00:43:34	\N	2025-10-14 00:43:34	2025-10-14 00:43:34
46	3412	LAMMS_STUDENT_3412_1760373815_WPJu3xMS	9791f0f4508df5af40b877b4579235165f4f3ae7e41c236ae00702887c76f38f	t	2025-10-14 00:43:35	\N	2025-10-14 00:43:35	2025-10-14 00:43:35
47	3398	LAMMS_STUDENT_3398_1760373820_DqCeuFtY	68a5b0e46b6c3d93b1914ebb3b0422f07bb855b89cf9a2c0d694e0b107fad08e	t	2025-10-14 00:43:40	\N	2025-10-14 00:43:40	2025-10-14 00:43:40
48	3399	LAMMS_STUDENT_3399_1760373821_RkkZlOjP	a14d5f251b85809ce1b27324d3bca27115d53c2568dde8c717da389362b1677d	t	2025-10-14 00:43:41	\N	2025-10-14 00:43:41	2025-10-14 00:43:41
49	3405	LAMMS_STUDENT_3405_1760373823_ehIL1MQ6	aac02cb1b5f18615bcb970d8db2818c7d58ffcd2e2d07e6aa41f57be4ae79b14	t	2025-10-14 00:43:43	\N	2025-10-14 00:43:43	2025-10-14 00:43:43
50	3408	LAMMS_STUDENT_3408_1760373824_SWTpYydT	bc0053201270ca608458848670a29dd60a6649ebe2b523322f019b83d8fc1233	t	2025-10-14 00:43:44	\N	2025-10-14 00:43:44	2025-10-14 00:43:44
51	3420	LAMMS_STUDENT_3420_1760373825_5zlbdTTy	7e7654ca9f257552737bc564f7bc3662b0f65531e3fb20e31fc3438edb6dcf3d	f	2025-10-14 00:43:45	\N	2025-10-14 00:43:45	2025-10-14 00:43:50
54	3420	LAMMS_STUDENT_3420_1760373830_cv74R3LM	768033983d004f5ad775580e56358cde9ffd4ecc1323a3b93ca01c6f4675f496	t	2025-10-14 00:43:50	\N	2025-10-14 00:43:50	2025-10-14 00:43:50
52	3403	LAMMS_STUDENT_3403_1760373825_UENCLgkt	b9cda9de5bf920a7760266cde546fd40720154cc1baf718600af1494f9046331	f	2025-10-14 00:43:45	\N	2025-10-14 00:43:45	2025-10-14 00:43:51
55	3403	LAMMS_STUDENT_3403_1760373831_SqDxtG6k	5d89a8813b6932491d2e2e2a5d6976bf27d80690622cddb2a27d8252cdabbd0d	t	2025-10-14 00:43:51	\N	2025-10-14 00:43:51	2025-10-14 00:43:51
53	3421	LAMMS_STUDENT_3421_1760373826_oOSgDPjF	dff11ed66a02bde77b4b50f99940cdc2764bcc2e2a7e2ee318adac35f96e913b	f	2025-10-14 00:43:46	\N	2025-10-14 00:43:46	2025-10-14 00:43:53
56	3421	LAMMS_STUDENT_3421_1760373833_uUh0bKLg	bfbe84f9bb9103d81a1226e7a0103cee60726a260a3246bd1edc0b8aedd0e3d2	t	2025-10-14 00:43:53	\N	2025-10-14 00:43:53	2025-10-14 00:43:53
57	3540	LAMMS_STUDENT_3540_1761268647_oMiIiIE4	f34356b19fbbc1f2e8792cf5b9173b963be6f40b93fcd3cac0ef5dc0a46b414c	f	2025-10-24 09:17:27	\N	2025-10-24 09:17:27	2025-10-24 09:17:46
58	3540	LAMMS_STUDENT_3540_1761268654_2k8DPmcO	a198705e5d47a14be9bd76dcffe83279f68add978872489952a931e590ac3922	f	2025-10-24 09:17:34	\N	2025-10-24 09:17:34	2025-10-24 09:17:46
59	3540	LAMMS_STUDENT_3540_1761268666_Sjd5w1eM	936df90c46fe0e801d533dc911285a6de2b0269cd36cfbd245991c15d52f53d1	t	2025-10-24 09:17:46	\N	2025-10-24 09:17:46	2025-10-24 09:17:46
22	3247	LAMMS_STUDENT_3247_1759843610_iLuVzJoA	3ff9218248faab3c64e133fc9923f68ea68a91954fd3254d1bedf7871ab6248c	f	2025-10-07 21:26:50	\N	2025-10-07 21:26:50	2025-10-24 10:04:59
60	3247	LAMMS_STUDENT_3247_1761271499_ywVRjIxI	62d781ba3bde26380dc1c36e4a8936cbb00bd7520af96efabe050da69bb1eca6	t	2025-10-24 10:04:59	\N	2025-10-24 10:04:59	2025-10-24 10:04:59
61	3249	LAMMS_STUDENT_3249_1761271507_lQ756fM9	c1123ac17a783279c7571b975f8ab587082cd332d07dd9a212db92fb0c18f41d	t	2025-10-24 10:05:07	\N	2025-10-24 10:05:07	2025-10-24 10:05:07
63	3246	LAMMS_STUDENT_3246_1761271511_4TsSSWXj	d64232848f99f387e32fee86ebc9eed72e301ce60fdae78efa909847444b4045	t	2025-10-24 10:05:11	\N	2025-10-24 10:05:11	2025-10-24 10:05:11
12	3245	LAMMS_STUDENT_3245_1759843603_sZa0NhRi	efd8be111184c0d06857378dad054861346127e0068bc2e3405ba6dbe0fdac2c	f	2025-10-07 21:26:43	\N	2025-10-07 21:26:43	2025-10-24 10:05:19
62	3245	LAMMS_STUDENT_3245_1761271509_i5NRLWr2	ca3f97febd6eb6e1e7f63e9c5485efa3f638f506642d0e4ef746cd9e72fec2be	f	2025-10-24 10:05:09	\N	2025-10-24 10:05:09	2025-10-24 10:05:19
64	3245	LAMMS_STUDENT_3245_1761271516_8QzirNO9	b772ed7f90d2250eb04086c9dcce43ce61b1ca968fcbf462602ebfe87e909cf7	f	2025-10-24 10:05:16	\N	2025-10-24 10:05:16	2025-10-24 10:05:19
65	3245	LAMMS_STUDENT_3245_1761271519_vMe7350u	c1d47636483ae27645af11bdd36ecb8c9d9b9ba7eedb99b7d3b1d690aacc6595	t	2025-10-24 10:05:19	\N	2025-10-24 10:05:19	2025-10-24 10:05:19
\.


--
-- Data for Name: student_section; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.student_section (id, student_id, section_id, school_year, is_active, created_at, updated_at, enrollment_date, status) FROM stdin;
3207	3210	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3208	3211	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3209	3212	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3210	3213	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3211	3214	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3212	3215	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3213	3216	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3214	3217	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3215	3218	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3216	3219	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3217	3220	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3218	3221	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3219	3222	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3220	3223	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3221	3224	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3222	3225	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3223	3226	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3224	3227	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3225	3228	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3226	3229	218	2025-2026	t	\N	\N	2025-10-07	enrolled
3227	3230	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3228	3231	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3229	3232	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3230	3233	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3231	3234	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3232	3235	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3233	3236	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3234	3237	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3235	3238	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3236	3239	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3237	3240	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3238	3241	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3239	3242	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3240	3243	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3241	3244	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3242	3245	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3243	3246	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3244	3247	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3245	3248	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3246	3249	219	2025-2026	t	\N	\N	2025-10-07	enrolled
3247	3250	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3248	3251	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3249	3252	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3250	3253	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3251	3254	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3252	3255	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3253	3256	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3254	3257	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3255	3258	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3256	3259	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3257	3260	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3258	3261	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3259	3262	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3260	3263	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3261	3264	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3262	3265	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3263	3266	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3264	3267	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3265	3268	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3266	3269	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3267	3270	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3268	3271	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3269	3272	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3270	3273	220	2025-2026	t	\N	\N	2025-10-07	enrolled
3271	3274	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3272	3275	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3273	3276	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3274	3277	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3275	3278	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3276	3279	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3277	3280	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3278	3281	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3279	3282	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3280	3283	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3281	3284	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3282	3285	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3283	3286	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3284	3287	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3285	3288	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3286	3289	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3287	3290	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3288	3291	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3289	3292	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3290	3293	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3291	3294	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3292	3295	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3293	3296	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3294	3297	221	2025-2026	t	\N	\N	2025-10-07	enrolled
3295	3298	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3296	3299	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3297	3300	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3298	3301	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3299	3302	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3300	3303	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3301	3304	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3302	3305	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3303	3306	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3304	3307	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3305	3308	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3306	3309	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3307	3310	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3308	3311	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3309	3312	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3310	3313	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3311	3314	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3312	3315	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3313	3316	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3314	3317	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3315	3318	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3316	3319	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3317	3320	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3318	3321	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3319	3322	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3320	3323	222	2025-2026	t	\N	\N	2025-10-07	enrolled
3321	3324	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3322	3325	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3323	3326	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3324	3327	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3325	3328	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3326	3329	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3327	3330	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3328	3331	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3329	3332	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3330	3333	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3331	3334	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3332	3335	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3333	3336	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3334	3337	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3335	3338	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3336	3339	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3337	3340	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3338	3341	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3339	3342	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3340	3343	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3341	3344	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3342	3345	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3343	3346	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3344	3347	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3345	3348	223	2025-2026	t	\N	\N	2025-10-07	enrolled
3346	3349	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3347	3350	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3348	3351	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3349	3352	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3350	3353	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3351	3354	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3352	3355	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3353	3356	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3354	3357	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3355	3358	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3356	3359	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3357	3360	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3358	3361	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3359	3362	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3360	3363	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3361	3364	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3362	3365	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3363	3366	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3364	3367	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3365	3368	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3366	3369	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3367	3370	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3368	3371	224	2025-2026	t	\N	\N	2025-10-07	enrolled
3369	3372	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3370	3373	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3371	3374	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3372	3375	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3373	3376	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3374	3377	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3375	3378	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3376	3379	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3377	3380	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3378	3381	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3379	3382	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3380	3383	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3381	3384	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3382	3385	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3383	3386	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3384	3387	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3385	3388	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3386	3389	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3387	3390	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3388	3391	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3389	3392	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3390	3393	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3391	3394	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3392	3395	225	2025-2026	t	\N	\N	2025-10-07	enrolled
3393	3396	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3394	3397	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3395	3398	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3396	3399	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3397	3400	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3398	3401	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3399	3402	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3400	3403	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3401	3404	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3402	3405	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3403	3406	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3404	3407	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3405	3408	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3406	3409	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3407	3410	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3408	3411	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3409	3412	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3410	3413	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3411	3414	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3412	3415	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3413	3416	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3414	3417	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3415	3418	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3416	3419	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3417	3420	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3418	3421	226	2025-2026	t	\N	\N	2025-10-07	enrolled
3419	3422	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3420	3423	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3421	3424	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3422	3425	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3423	3426	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3424	3427	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3425	3428	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3426	3429	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3427	3430	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3428	3431	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3429	3432	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3430	3433	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3431	3434	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3432	3435	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3433	3436	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3434	3437	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3435	3438	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3436	3439	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3437	3440	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3438	3441	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3439	3442	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3440	3443	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3441	3444	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3442	3445	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3443	3446	227	2025-2026	t	\N	\N	2025-10-07	enrolled
3444	3447	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3445	3448	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3446	3449	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3447	3450	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3448	3451	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3449	3452	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3450	3453	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3451	3454	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3452	3455	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3453	3456	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3454	3457	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3455	3458	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3456	3459	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3457	3460	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3458	3461	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3459	3462	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3460	3463	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3461	3464	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3462	3465	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3463	3466	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3464	3467	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3465	3468	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3466	3469	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3467	3470	228	2025-2026	t	\N	\N	2025-10-07	enrolled
3468	3471	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3469	3472	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3470	3473	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3471	3474	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3472	3475	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3473	3476	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3474	3477	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3475	3478	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3476	3479	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3477	3480	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3478	3481	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3479	3482	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3480	3483	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3481	3484	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3482	3485	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3483	3486	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3484	3487	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3485	3488	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3486	3489	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3487	3490	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3488	3491	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3489	3492	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3490	3493	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3491	3494	229	2025-2026	t	\N	\N	2025-10-07	enrolled
3492	3495	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3493	3496	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3494	3497	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3495	3498	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3496	3499	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3497	3500	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3498	3501	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3499	3502	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3500	3503	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3501	3504	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3502	3505	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3503	3506	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3504	3507	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3505	3508	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3506	3509	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3507	3510	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3508	3511	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3509	3512	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3510	3513	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3511	3514	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3512	3515	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3513	3516	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3514	3517	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3515	3518	230	2025-2026	t	\N	\N	2025-10-07	enrolled
3516	3519	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3517	3520	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3518	3521	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3519	3522	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3520	3523	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3521	3524	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3522	3525	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3523	3526	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3524	3527	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3525	3528	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3526	3529	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3527	3530	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3528	3531	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3529	3532	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3530	3533	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3531	3534	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3532	3535	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3533	3536	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3534	3537	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3535	3538	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3536	3539	231	2025-2026	t	\N	\N	2025-10-07	enrolled
3537	3540	219	2025-2026	t	2025-10-16 00:36:09	2025-10-16 00:36:09	2025-10-07	enrolled
\.


--
-- Data for Name: student_status_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.student_status_history (id, student_id, previous_status, new_status, reason, reason_category, effective_date, changed_by_teacher_id, notes, created_at, updated_at) FROM stdin;
1	3230	active	dropped_out	b3	individual	2025-10-07	1	\N	2025-10-07 23:57:37	2025-10-07 23:57:37
2	3396	active	dropped_out	b3	individual	2025-10-13	2	Dead	2025-10-13 22:41:17	2025-10-13 22:41:17
3	3396	dropped_out	dropped_out	b3	individual	2025-10-12	2	\N	2025-10-13 22:46:18	2025-10-13 22:46:18
4	3396	dropped_out	dropped_out	b3	individual	2025-10-11	2	\N	2025-10-13 22:51:56	2025-10-13 22:51:56
5	3396	dropped_out	dropped_out	b3	individual	2025-10-10	2	\N	2025-10-13 22:57:54	2025-10-13 22:57:54
6	3396	dropped_out	dropped_out	b3	individual	2025-10-09	2	\N	2025-10-13 23:05:17	2025-10-13 23:05:17
7	3396	dropped_out	dropped_out	b3	individual	2025-10-08	2	\N	2025-10-13 23:24:26	2025-10-13 23:24:26
8	3400	active	transferred_out	a1	domestic	2025-10-13	2	\N	2025-10-13 23:55:51	2025-10-13 23:55:51
9	3400	transferred_out	active	a1	domestic	2025-10-12	2	\N	2025-10-14 02:05:41	2025-10-14 02:05:41
10	3231	active	dropped_out	a1	domestic	2025-10-15	1	\N	2025-10-15 14:45:30	2025-10-15 14:45:30
11	3232	active	transferred_out	a1	domestic	2025-10-15	1	\N	2025-10-15 15:12:13	2025-10-15 15:12:13
12	3233	active	dropped_out	a1	domestic	2025-10-15	1	\N	2025-10-15 15:12:28	2025-10-15 15:12:28
13	3234	active	dropped_out	b2	individual	2025-10-23	1	\N	2025-10-23 15:04:31	2025-10-23 15:04:31
14	3241	active	dropped_out	b3	individual	2025-10-24	1	\N	2025-10-24 09:13:16	2025-10-24 09:13:16
15	3241	dropped_out	dropped_out	b3	individual	2025-10-24	1	\N	2025-10-24 09:13:17	2025-10-24 09:13:17
\.


--
-- Data for Name: students; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.students (id, "studentId", name, "firstName", "lastName", "middleName", "extensionName", email, "gradeLevel", section, lrn, "schoolYearStart", "schoolYearEnd", gender, sex, birthdate, birthplace, age, "psaBirthCertNo", "motherTongue", "profilePhoto", "currentAddress", "permanentAddress", "contactInfo", father, mother, "parentName", "parentContact", status, "enrollmentDate", "admissionDate", requirements, "isIndigenous", "indigenousCommunity", "is4PsBeneficiary", "householdID", "hasDisability", disabilities, created_at, updated_at, archive_reason, archive_notes, archived_at, archived_by) FROM stdin;
\.


--
-- Data for Name: subject_schedules; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.subject_schedules (id, section_id, subject_id, teacher_id, day, start_time, end_time, is_active, deleted_at, created_at, updated_at) FROM stdin;
1284	219	1	1	Monday	21:10:50	22:10:50	t	\N	2025-10-07 21:01:02	2025-10-07 21:01:02
1285	219	1	1	Tuesday	21:10:50	22:10:50	t	\N	2025-10-07 21:01:03	2025-10-07 21:01:03
1286	219	1	1	Wednesday	21:10:50	22:10:50	t	\N	2025-10-07 21:01:04	2025-10-07 21:01:04
1287	219	1	1	Thursday	21:10:50	22:10:50	t	\N	2025-10-07 21:01:04	2025-10-07 21:01:04
1288	219	1	1	Friday	21:10:50	22:10:50	t	\N	2025-10-07 21:01:05	2025-10-07 21:01:05
1290	219	5	1	Tuesday	07:30:08	08:30:08	t	\N	2025-10-07 21:16:33	2025-10-07 21:16:33
1291	219	5	1	Wednesday	07:30:08	08:30:08	t	\N	2025-10-07 21:16:34	2025-10-07 21:16:34
1292	219	5	1	Thursday	07:30:08	08:30:08	t	\N	2025-10-07 21:16:34	2025-10-07 21:16:34
1293	219	5	1	Friday	07:30:08	08:30:08	t	\N	2025-10-07 21:16:35	2025-10-07 21:16:35
1294	230	3	2	Monday	19:30:46	20:30:46	t	\N	2025-10-13 22:26:09	2025-10-13 22:26:09
1295	230	3	2	Tuesday	19:30:46	20:30:46	t	\N	2025-10-13 22:26:10	2025-10-13 22:26:10
1296	230	3	2	Wednesday	19:30:46	20:30:46	t	\N	2025-10-13 22:26:11	2025-10-13 22:26:11
1297	230	3	2	Thursday	19:30:46	20:30:46	t	\N	2025-10-13 22:26:12	2025-10-13 22:26:12
1298	230	3	2	Friday	19:30:46	20:30:46	t	\N	2025-10-13 22:26:12	2025-10-13 22:26:12
1299	230	5	2	Wednesday	20:31:18	21:31:18	t	\N	2025-10-13 22:26:44	2025-10-13 22:26:44
1300	230	5	2	Thursday	20:31:18	21:31:18	t	\N	2025-10-13 22:26:45	2025-10-13 22:26:45
1301	230	5	2	Friday	20:31:18	21:31:18	t	\N	2025-10-13 22:26:46	2025-10-13 22:26:46
1302	230	5	2	Monday	08:31:38	09:31:38	t	\N	2025-10-13 22:36:05	2025-10-13 22:36:05
1303	230	5	2	Tuesday	08:31:38	09:31:38	t	\N	2025-10-13 22:36:05	2025-10-13 22:36:05
1289	219	5	1	Monday	07:30:08	12:46:00	t	\N	2025-10-07 21:16:32	2025-10-24 09:46:58
\.


--
-- Data for Name: subjects; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.subjects (id, name, code, description, credits, is_active, created_at, updated_at, deleted_at) FROM stdin;
1	English	ENGLIS658	English - Kinder	\N	t	\N	\N	\N
2	Filipino	FILIPI173	Filipino - Kinder	\N	t	\N	\N	\N
3	Mathematics	MATHEM731	Mathematics - Kinder	\N	t	\N	\N	\N
4	Arts	ARTS810	Arts - Kinder	\N	t	\N	\N	\N
5	Music	MUSIC161	Music - Kinder	\N	t	\N	\N	\N
6	Physical Education	PHYSIC801	Physical Education - Kinder	\N	t	\N	\N	\N
7	Good Manners and Right Conduct	GOODMA200	Good Manners and Right Conduct - Kinder	\N	t	\N	\N	\N
8	Araling Panlipunan	ARALIN198	Araling Panlipunan - Grade 1-3	\N	t	\N	\N	\N
9	Science	SCIENC960	Science - Grade 1-3	\N	t	\N	\N	\N
10	MAPEH	MAPEH618	MAPEH - Grade 1-3	\N	t	\N	\N	\N
11	Edukasyon sa Pagpapakatao	EDUKAS726	Edukasyon sa Pagpapakatao - Grade 1-3	\N	t	\N	\N	\N
12	Technology and Livelihood Education	TECHNO518	Technology and Livelihood Education - Grade 4-6	\N	t	\N	\N	\N
121	English	ENGLIS662	English - Kinder	\N	t	\N	\N	\N
122	Filipino	FILIPI929	Filipino - Kinder	\N	t	\N	\N	\N
123	Mathematics	MATHEM396	Mathematics - Kinder	\N	t	\N	\N	\N
124	Arts	ARTS803	Arts - Kinder	\N	t	\N	\N	\N
125	Music	MUSIC981	Music - Kinder	\N	t	\N	\N	\N
126	Physical Education	PHYSIC466	Physical Education - Kinder	\N	t	\N	\N	\N
127	Good Manners and Right Conduct	GOODMA718	Good Manners and Right Conduct - Kinder	\N	t	\N	\N	\N
128	Araling Panlipunan	ARALIN482	Araling Panlipunan - Grade 1-3	\N	t	\N	\N	\N
129	Science	SCIENC456	Science - Grade 1-3	\N	t	\N	\N	\N
130	MAPEH	MAPEH507	MAPEH - Grade 1-3	\N	t	\N	\N	\N
131	Edukasyon sa Pagpapakatao	EDUKAS221	Edukasyon sa Pagpapakatao - Grade 1-3	\N	t	\N	\N	\N
132	Technology and Livelihood Education	TECHNO376	Technology and Livelihood Education - Grade 4-6	\N	t	\N	\N	\N
145	English	ENGLIS651	English - Kinder	\N	t	\N	\N	\N
146	Filipino	FILIPI671	Filipino - Kinder	\N	t	\N	\N	\N
147	Mathematics	MATHEM247	Mathematics - Kinder	\N	t	\N	\N	\N
148	Arts	ARTS674	Arts - Kinder	\N	t	\N	\N	\N
149	Music	MUSIC966	Music - Kinder	\N	t	\N	\N	\N
150	Physical Education	PHYSIC196	Physical Education - Kinder	\N	t	\N	\N	\N
151	Good Manners and Right Conduct	GOODMA825	Good Manners and Right Conduct - Kinder	\N	t	\N	\N	\N
152	Araling Panlipunan	ARALIN444	Araling Panlipunan - Grade 1-3	\N	t	\N	\N	\N
153	Science	SCIENC762	Science - Grade 1-3	\N	t	\N	\N	\N
154	MAPEH	MAPEH622	MAPEH - Grade 1-3	\N	t	\N	\N	\N
155	Edukasyon sa Pagpapakatao	EDUKAS456	Edukasyon sa Pagpapakatao - Grade 1-3	\N	t	\N	\N	\N
156	Technology and Livelihood Education	TECHNO796	Technology and Livelihood Education - Grade 4-6	\N	t	\N	\N	\N
61	English	ENGLIS279	English - Kinder	\N	t	\N	\N	\N
62	Filipino	FILIPI430	Filipino - Kinder	\N	t	\N	\N	\N
63	Mathematics	MATHEM818	Mathematics - Kinder	\N	t	\N	\N	\N
64	Arts	ARTS675	Arts - Kinder	\N	t	\N	\N	\N
65	Music	MUSIC212	Music - Kinder	\N	t	\N	\N	\N
66	Physical Education	PHYSIC598	Physical Education - Kinder	\N	t	\N	\N	\N
67	Good Manners and Right Conduct	GOODMA603	Good Manners and Right Conduct - Kinder	\N	t	\N	\N	\N
68	Araling Panlipunan	ARALIN559	Araling Panlipunan - Grade 1-3	\N	t	\N	\N	\N
69	Science	SCIENC454	Science - Grade 1-3	\N	t	\N	\N	\N
70	MAPEH	MAPEH526	MAPEH - Grade 1-3	\N	t	\N	\N	\N
71	Edukasyon sa Pagpapakatao	EDUKAS419	Edukasyon sa Pagpapakatao - Grade 1-3	\N	t	\N	\N	\N
72	Technology and Livelihood Education	TECHNO999	Technology and Livelihood Education - Grade 4-6	\N	t	\N	\N	\N
73	English	ENGLIS336	English - Kinder	\N	t	\N	\N	\N
74	Filipino	FILIPI184	Filipino - Kinder	\N	t	\N	\N	\N
75	Mathematics	MATHEM158	Mathematics - Kinder	\N	t	\N	\N	\N
76	Arts	ARTS243	Arts - Kinder	\N	t	\N	\N	\N
77	Music	MUSIC545	Music - Kinder	\N	t	\N	\N	\N
78	Physical Education	PHYSIC335	Physical Education - Kinder	\N	t	\N	\N	\N
79	Good Manners and Right Conduct	GOODMA688	Good Manners and Right Conduct - Kinder	\N	t	\N	\N	\N
80	Araling Panlipunan	ARALIN120	Araling Panlipunan - Grade 1-3	\N	t	\N	\N	\N
81	Science	SCIENC961	Science - Grade 1-3	\N	t	\N	\N	\N
82	MAPEH	MAPEH509	MAPEH - Grade 1-3	\N	t	\N	\N	\N
83	Edukasyon sa Pagpapakatao	EDUKAS524	Edukasyon sa Pagpapakatao - Grade 1-3	\N	t	\N	\N	\N
84	Technology and Livelihood Education	TECHNO981	Technology and Livelihood Education - Grade 4-6	\N	t	\N	\N	\N
109	English	ENGLIS862	English - Kinder	\N	t	\N	\N	\N
110	Filipino	FILIPI816	Filipino - Kinder	\N	t	\N	\N	\N
111	Mathematics	MATHEM970	Mathematics - Kinder	\N	t	\N	\N	\N
112	Arts	ARTS988	Arts - Kinder	\N	t	\N	\N	\N
113	Music	MUSIC459	Music - Kinder	\N	t	\N	\N	\N
114	Physical Education	PHYSIC467	Physical Education - Kinder	\N	t	\N	\N	\N
115	Good Manners and Right Conduct	GOODMA393	Good Manners and Right Conduct - Kinder	\N	t	\N	\N	\N
116	Araling Panlipunan	ARALIN741	Araling Panlipunan - Grade 1-3	\N	t	\N	\N	\N
117	Science	SCIENC823	Science - Grade 1-3	\N	t	\N	\N	\N
118	MAPEH	MAPEH415	MAPEH - Grade 1-3	\N	t	\N	\N	\N
119	Edukasyon sa Pagpapakatao	EDUKAS208	Edukasyon sa Pagpapakatao - Grade 1-3	\N	t	\N	\N	\N
120	Technology and Livelihood Education	TECHNO270	Technology and Livelihood Education - Grade 4-6	\N	t	\N	\N	\N
157	English	ENGLIS392	English - Kinder	\N	t	\N	\N	\N
158	Filipino	FILIPI591	Filipino - Kinder	\N	t	\N	\N	\N
159	Mathematics	MATHEM335	Mathematics - Kinder	\N	t	\N	\N	\N
160	Arts	ARTS971	Arts - Kinder	\N	t	\N	\N	\N
161	Music	MUSIC777	Music - Kinder	\N	t	\N	\N	\N
162	Physical Education	PHYSIC774	Physical Education - Kinder	\N	t	\N	\N	\N
163	Good Manners and Right Conduct	GOODMA891	Good Manners and Right Conduct - Kinder	\N	t	\N	\N	\N
164	Araling Panlipunan	ARALIN859	Araling Panlipunan - Grade 1-3	\N	t	\N	\N	\N
165	Science	SCIENC592	Science - Grade 1-3	\N	t	\N	\N	\N
166	MAPEH	MAPEH730	MAPEH - Grade 1-3	\N	t	\N	\N	\N
167	Edukasyon sa Pagpapakatao	EDUKAS724	Edukasyon sa Pagpapakatao - Grade 1-3	\N	t	\N	\N	\N
168	Technology and Livelihood Education	TECHNO345	Technology and Livelihood Education - Grade 4-6	\N	t	\N	\N	\N
\.


--
-- Data for Name: submitted_sf2_reports; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.submitted_sf2_reports (id, section_id, section_name, grade_level, month, month_name, report_type, status, submitted_by, submitted_at, reviewed_at, reviewed_by, admin_notes, created_at, updated_at) FROM stdin;
1	226	Silang	Grade 4	2025-10	October 2025	SF2	submitted	2	2025-10-14 02:08:04	\N	\N	\N	2025-10-14 02:08:04	2025-10-14 02:08:04
2	219	Gumamela	Grade 1	2025-10	October 2025	SF2	submitted	1	2025-10-26 13:15:24	\N	\N	\N	2025-10-15 15:13:36	2025-10-26 13:15:24
\.


--
-- Data for Name: teacher_section_subject; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.teacher_section_subject (id, teacher_id, section_id, subject_id, is_primary, is_active, role, deleted_at) FROM stdin;
661	1	219	\N	t	t	homeroom	\N
662	1	219	1	f	t	subject	\N
663	1	219	5	f	t	subject	\N
664	2	226	\N	t	t	homeroom	\N
665	2	230	3	f	t	subject	\N
666	2	230	5	f	t	subject	\N
667	2	226	1	f	t	subject	\N
\.


--
-- Data for Name: teacher_sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.teacher_sessions (id, teacher_id, user_id, token, ip_address, user_agent, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: teachers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.teachers (id, user_id, first_name, last_name, phone_number, address, date_of_birth, gender, is_head_teacher, created_at, updated_at, deleted_at) FROM stdin;
1	1	Maria	Santos	+63 9257496119	Naawan, Misamis Oriental, Philippines	1984-10-07	male	f	2025-10-07 13:15:57	2025-10-07 13:15:57	\N
2	2	Ana	Cruz	+63 9319622299	Naawan, Misamis Oriental, Philippines	1974-10-07	male	f	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
3	3	Rosa	Garcia	+63 9544404699	Naawan, Misamis Oriental, Philippines	1999-10-07	male	f	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
4	4	Carmen	Reyes	+63 9687912758	Naawan, Misamis Oriental, Philippines	1971-10-07	female	f	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
5	5	Elena	Morales	+63 9619046335	Naawan, Misamis Oriental, Philippines	1999-10-07	male	f	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
6	6	Roberto	Dela Cruz	+63 9788241312	Naawan, Misamis Oriental, Philippines	1995-10-07	male	f	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
7	7	Gloria	Villanueva	+63 9451503188	Naawan, Misamis Oriental, Philippines	1992-10-07	male	f	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
8	8	Jose	Ramos	+63 9728722571	Naawan, Misamis Oriental, Philippines	1993-10-07	male	f	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
9	9	Luz	Fernandez	+63 9808541995	Naawan, Misamis Oriental, Philippines	1984-10-07	male	f	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
10	10	Pedro	Gonzales	+63 9872773306	Naawan, Misamis Oriental, Philippines	1983-10-07	male	f	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
11	11	Esperanza	Torres	+63 9648594735	Naawan, Misamis Oriental, Philippines	1986-10-07	female	f	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
12	12	Antonio	Mendoza	+63 9305427770	Naawan, Misamis Oriental, Philippines	1994-10-07	female	f	2025-10-07 13:16:00	2025-10-07 13:16:00	\N
13	13	Cristina	Aquino	+63 9204330510	Naawan, Misamis Oriental, Philippines	1982-10-07	female	f	2025-10-07 13:16:00	2025-10-07 13:16:00	\N
14	14	Miguel	Rivera	+63 9785961414	Naawan, Misamis Oriental, Philippines	1974-10-07	male	f	2025-10-07 13:16:00	2025-10-07 13:16:00	\N
15	15	Teresita	Bautista	+63 9613361373	Naawan, Misamis Oriental, Philippines	1971-10-07	male	f	2025-10-07 13:16:00	2025-10-07 13:16:00	\N
16	16	Ricardo	Pascual	+63 9753617912	Naawan, Misamis Oriental, Philippines	1989-10-07	male	f	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
17	17	Melody	Santiago	+63 9802704127	Naawan, Misamis Oriental, Philippines	1975-10-07	male	f	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
18	18	Arturo	Valdez	+63 9795363320	Naawan, Misamis Oriental, Philippines	1974-10-07	female	f	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
19	19	Remedios	Castro	+63 9593930764	Naawan, Misamis Oriental, Philippines	1983-10-07	male	f	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
20	20	Benjamin	Flores	+63 9687568931	Naawan, Misamis Oriental, Philippines	2000-10-07	female	f	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
21	21	Rosario	Herrera	+63 9573564299	Naawan, Misamis Oriental, Philippines	1990-10-07	male	f	2025-10-07 13:16:02	2025-10-07 13:16:02	\N
\.


--
-- Data for Name: user_sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.user_sessions (id, user_id, token, role, ip_address, user_agent, last_activity, expires_at, created_at, updated_at) FROM stdin;
101	26	guardhouse_26_1761454517	guardhouse	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36	2025-10-26 12:55:18	2025-10-26 20:55:17	2025-10-26 12:55:17	2025-10-26 12:55:18
11	8	teacher_8_1759816232	teacher	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36	2025-10-07 14:22:39	2025-10-07 21:50:32	2025-10-07 13:50:32	2025-10-07 14:22:39
108	1	teacher_1_1762345602	teacher	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36	2025-11-05 22:34:03	2025-11-06 04:26:42	2025-11-05 20:26:42	2025-11-05 22:34:03
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, username, email, password, role, is_active, force_password_reset, email_verified_at, password_changed_at, remember_token, created_at, updated_at, deleted_at) FROM stdin;
1	maria.santos	maria.santos@naawan.edu.ph	$2y$12$AOf.b9ESHXlZhrG41dtu2.HPprJDjFPC3aJruZaED1CNd.EZDPXNC	teacher	t	f	2025-10-07 13:15:57	\N	\N	2025-10-07 13:15:57	2025-10-07 13:15:57	\N
2	ana.cruz	ana.cruz@naawan.edu.ph	$2y$12$.AaACPLFVJjTTAayCdxBZe6dPWWATbl.QAtut99wyE8SdrwLORtqG	teacher	t	f	2025-10-07 13:15:57	\N	\N	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
3	rosa.garcia	rosa.garcia@naawan.edu.ph	$2y$12$RH5m.pGdUi.0pK0obGKUWOTbd92Fb1Q0xGIosN8V.CT3Vp3gwFFGy	teacher	t	f	2025-10-07 13:15:58	\N	\N	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
4	carmen.reyes	carmen.reyes@naawan.edu.ph	$2y$12$YTTwweQCQFztoKS1D2X1/.M24FQgX.ioReUdao5PN7x5aWC.J.cB.	teacher	t	f	2025-10-07 13:15:58	\N	\N	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
5	elena.morales	elena.morales@naawan.edu.ph	$2y$12$2JtRqjOByfJcZC7YHHqMROubSGV79LNLy7swOGVYqdrnPfE38gGga	teacher	t	f	2025-10-07 13:15:58	\N	\N	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
6	roberto.dela cruz	roberto.delacruz@naawan.edu.ph	$2y$12$R6S8q1D9QVMLG2VUb5GClekcYrNm.31WHUQ5eAHGFeWSJBtxXW6Yy	teacher	t	f	2025-10-07 13:15:58	\N	\N	2025-10-07 13:15:58	2025-10-07 13:15:58	\N
7	gloria.villanueva	gloria.villanueva@naawan.edu.ph	$2y$12$DHFzn5/xlC0TRV332SrUS.YhAwJgJVKhXcvggHq8K2sVaGppmzPRG	teacher	t	f	2025-10-07 13:15:59	\N	\N	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
8	jose.ramos	jose.ramos@naawan.edu.ph	$2y$12$TusB8VWZXHCwrgjCAysewOoLzObpk9RteeC7pyQhIjXEJO/0gL2K.	teacher	t	f	2025-10-07 13:15:59	\N	\N	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
9	luz.fernandez	luz.fernandez@naawan.edu.ph	$2y$12$iA9ewmMFdkfWR4JYJ6v52uIAqPNRRVQhc0z.8jImL/59s5uc4O1WO	teacher	t	f	2025-10-07 13:15:59	\N	\N	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
10	pedro.gonzales	pedro.gonzales@naawan.edu.ph	$2y$12$IGt0P/pdLe7NKyIt.oeaHu8qOFgUxSJgYcus56xfYZAWQiUabyZMG	teacher	t	f	2025-10-07 13:15:59	\N	\N	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
11	esperanza.torres	esperanza.torres@naawan.edu.ph	$2y$12$y3Oeit8hix3SAhXqgpErP.SL11IAzmE65yAWSYunyWlawYS95iKP.	teacher	t	f	2025-10-07 13:15:59	\N	\N	2025-10-07 13:15:59	2025-10-07 13:15:59	\N
12	antonio.mendoza	antonio.mendoza@naawan.edu.ph	$2y$12$GzO2qO6bvX9ivtrMRfOmoOZUTGae4SBfeWQfJy8ngCK2CAwQn9Lgy	teacher	t	f	2025-10-07 13:16:00	\N	\N	2025-10-07 13:16:00	2025-10-07 13:16:00	\N
13	cristina.aquino	cristina.aquino@naawan.edu.ph	$2y$12$WYetaptIy1RZ3MBqlDt9J.w4qK0Fo/Iy.8WKs5JaYEiJkYQ/rIOey	teacher	t	f	2025-10-07 13:16:00	\N	\N	2025-10-07 13:16:00	2025-10-07 13:16:00	\N
14	miguel.rivera	miguel.rivera@naawan.edu.ph	$2y$12$2uOxAjHu1rT9.25.jVY68O3oeP82ntumCHJL58Z0JVU17J15ExPVK	teacher	t	f	2025-10-07 13:16:00	\N	\N	2025-10-07 13:16:00	2025-10-07 13:16:00	\N
15	teresita.bautista	teresita.bautista@naawan.edu.ph	$2y$12$pt12EdRYo6j05JShr/5Cx./HOXnPD73PH6BHpwgsjLAEZuOMmMEcS	teacher	t	f	2025-10-07 13:16:00	\N	\N	2025-10-07 13:16:00	2025-10-07 13:16:00	\N
16	ricardo.pascual	ricardo.pascual@naawan.edu.ph	$2y$12$bAXUtwzLDHkWBWzyytxsZulj7VEClbqP0iiZ0Wn1QQpjgbWQvMxv.	teacher	t	f	2025-10-07 13:16:01	\N	\N	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
17	melody.santiago	melody.santiago@naawan.edu.ph	$2y$12$jjtaK8GEV6KtQxOZWipgS.ysK.prJaaKfBV1.UXhsWgB0qbPipKai	teacher	t	f	2025-10-07 13:16:01	\N	\N	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
18	arturo.valdez	arturo.valdez@naawan.edu.ph	$2y$12$O9wvFAX5hxSN9tmRbrM72OYZ9icL3gV.XWM8vyqizEB1VqTcFDH9C	teacher	t	f	2025-10-07 13:16:01	\N	\N	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
19	remedios.castro	remedios.castro@naawan.edu.ph	$2y$12$cVXVGdJPtApcMjpIvulq2uGIM4a7ndAOMZHAroKusvQJAvDxXftLW	teacher	t	f	2025-10-07 13:16:01	\N	\N	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
20	benjamin.flores	benjamin.flores@naawan.edu.ph	$2y$12$JaatrRORe8ZF/7kmeNYJveoWtAUYBvfAyDboJmB6lVaKiMR4P.qUy	teacher	t	f	2025-10-07 13:16:01	\N	\N	2025-10-07 13:16:01	2025-10-07 13:16:01	\N
21	rosario.herrera	rosario.herrera@naawan.edu.ph	$2y$12$grqhzG54CePCPbvehL93X.9tg8N/8pffdu8cu8oY5OasGXKUwDMaC	teacher	t	f	2025-10-07 13:16:02	\N	\N	2025-10-07 13:16:02	2025-10-07 13:16:02	\N
25	admin	admin@school.edu	$2y$12$e.4jZNXtrsE0ARqA3Tnh1.5yS7yiI6/KBVzAEw38OMvTATiQ8P7Xm	admin	t	f	2025-10-07 13:25:14	\N	\N	2025-10-07 13:25:14	2025-10-07 13:25:14	\N
26	guard	guard@school.edu	$2y$12$z7Jms1x73NTrZakI0FWC9.czeCQf2J.aRUgaNKHIbfygTDqNMZt/G	guardhouse	t	f	2025-10-07 13:25:14	\N	\N	2025-10-07 13:25:14	2025-10-07 13:25:14	\N
\.


--
-- Name: admins_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.admins_id_seq', 2, true);


--
-- Name: attendance_audit_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_audit_log_id_seq', 1, false);


--
-- Name: attendance_modifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_modifications_id_seq', 1, false);


--
-- Name: attendance_policies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_policies_id_seq', 1, false);


--
-- Name: attendance_reasons_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_reasons_id_seq', 25, true);


--
-- Name: attendance_records_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_records_id_seq', 443633, true);


--
-- Name: attendance_session_edits_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_session_edits_id_seq', 1, false);


--
-- Name: attendance_session_stats_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_session_stats_id_seq', 1, false);


--
-- Name: attendance_sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_sessions_id_seq', 17860, true);


--
-- Name: attendance_statuses_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_statuses_id_seq', 4, true);


--
-- Name: attendance_validation_rules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_validation_rules_id_seq', 3, true);


--
-- Name: attendances_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendances_id_seq', 16, true);


--
-- Name: class_schedules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.class_schedules_id_seq', 1, false);


--
-- Name: curricula_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.curricula_id_seq', 2, true);


--
-- Name: curriculum_grade_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.curriculum_grade_id_seq', 77, true);


--
-- Name: curriculum_grade_subject_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.curriculum_grade_subject_id_seq', 468, true);


--
-- Name: gate_attendance_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.gate_attendance_id_seq', 1, false);


--
-- Name: grades_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.grades_id_seq', 7, true);


--
-- Name: guardhouse_archive_sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.guardhouse_archive_sessions_id_seq', 3, true);


--
-- Name: guardhouse_archived_records_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.guardhouse_archived_records_id_seq', 12, true);


--
-- Name: guardhouse_attendance_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.guardhouse_attendance_id_seq', 26, true);


--
-- Name: guardhouse_users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.guardhouse_users_id_seq', 1, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.migrations_id_seq', 81, true);


--
-- Name: notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.notifications_id_seq', 512, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 108, true);


--
-- Name: schedules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.schedules_id_seq', 1, false);


--
-- Name: school_calendar_events_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.school_calendar_events_id_seq', 1, true);


--
-- Name: school_days_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.school_days_id_seq', 1, false);


--
-- Name: school_holidays_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.school_holidays_id_seq', 1, false);


--
-- Name: school_years_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.school_years_id_seq', 1, false);


--
-- Name: seating_arrangements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.seating_arrangements_id_seq', 4, true);


--
-- Name: section_subject_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.section_subject_id_seq', 1, false);


--
-- Name: sections_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sections_id_seq', 231, true);


--
-- Name: sf2_attendance_edits_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sf2_attendance_edits_id_seq', 3, true);


--
-- Name: student_details_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.student_details_id_seq', 3540, true);


--
-- Name: student_enrollment_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.student_enrollment_history_id_seq', 1, false);


--
-- Name: student_qr_codes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.student_qr_codes_id_seq', 65, true);


--
-- Name: student_section_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.student_section_id_seq', 3537, true);


--
-- Name: student_status_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.student_status_history_id_seq', 15, true);


--
-- Name: students_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.students_id_seq', 1, false);


--
-- Name: subject_schedules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.subject_schedules_id_seq', 1303, true);


--
-- Name: subjects_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.subjects_id_seq', 168, true);


--
-- Name: submitted_sf2_reports_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.submitted_sf2_reports_id_seq', 2, true);


--
-- Name: teacher_section_subject_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.teacher_section_subject_id_seq', 667, true);


--
-- Name: teacher_sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.teacher_sessions_id_seq', 1, false);


--
-- Name: teachers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.teachers_id_seq', 21, true);


--
-- Name: user_sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.user_sessions_id_seq', 108, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 26, true);


--
-- Name: admins admins_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_pkey PRIMARY KEY (id);


--
-- Name: attendance_audit_log attendance_audit_log_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_audit_log
    ADD CONSTRAINT attendance_audit_log_pkey PRIMARY KEY (id);


--
-- Name: attendance_modifications attendance_modifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_modifications
    ADD CONSTRAINT attendance_modifications_pkey PRIMARY KEY (id);


--
-- Name: attendance_policies attendance_policies_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_policies
    ADD CONSTRAINT attendance_policies_pkey PRIMARY KEY (id);


--
-- Name: attendance_reasons attendance_reasons_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_reasons
    ADD CONSTRAINT attendance_reasons_pkey PRIMARY KEY (id);


--
-- Name: attendance_records attendance_records_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_pkey PRIMARY KEY (id);


--
-- Name: attendance_session_edits attendance_session_edits_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_session_edits
    ADD CONSTRAINT attendance_session_edits_pkey PRIMARY KEY (id);


--
-- Name: attendance_session_stats attendance_session_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_session_stats
    ADD CONSTRAINT attendance_session_stats_pkey PRIMARY KEY (id);


--
-- Name: attendance_session_stats attendance_session_stats_session_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_session_stats
    ADD CONSTRAINT attendance_session_stats_session_id_unique UNIQUE (session_id);


--
-- Name: attendance_sessions attendance_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_sessions
    ADD CONSTRAINT attendance_sessions_pkey PRIMARY KEY (id);


--
-- Name: attendance_statuses attendance_statuses_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_statuses
    ADD CONSTRAINT attendance_statuses_code_unique UNIQUE (code);


--
-- Name: attendance_statuses attendance_statuses_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_statuses
    ADD CONSTRAINT attendance_statuses_pkey PRIMARY KEY (id);


--
-- Name: attendance_validation_rules attendance_validation_rules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_validation_rules
    ADD CONSTRAINT attendance_validation_rules_pkey PRIMARY KEY (id);


--
-- Name: attendances attendances_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: curriculum_grade_subject cgs_sequence; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade_subject
    ADD CONSTRAINT cgs_sequence UNIQUE (curriculum_id, grade_id, sequence_number);


--
-- Name: curriculum_grade_subject cgs_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade_subject
    ADD CONSTRAINT cgs_unique UNIQUE (curriculum_id, grade_id, subject_id);


--
-- Name: class_schedules class_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.class_schedules
    ADD CONSTRAINT class_schedules_pkey PRIMARY KEY (id);


--
-- Name: curricula curricula_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curricula
    ADD CONSTRAINT curricula_pkey PRIMARY KEY (id);


--
-- Name: curricula curricula_unique_year_range; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curricula
    ADD CONSTRAINT curricula_unique_year_range UNIQUE (name, start_year, end_year);


--
-- Name: curriculum_grade curriculum_grade_curriculum_id_grade_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade
    ADD CONSTRAINT curriculum_grade_curriculum_id_grade_id_unique UNIQUE (curriculum_id, grade_id);


--
-- Name: curriculum_grade curriculum_grade_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade
    ADD CONSTRAINT curriculum_grade_pkey PRIMARY KEY (id);


--
-- Name: curriculum_grade_subject curriculum_grade_subject_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade_subject
    ADD CONSTRAINT curriculum_grade_subject_pkey PRIMARY KEY (id);


--
-- Name: gate_attendance gate_attendance_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.gate_attendance
    ADD CONSTRAINT gate_attendance_pkey PRIMARY KEY (id);


--
-- Name: grades grades_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.grades
    ADD CONSTRAINT grades_code_unique UNIQUE (code);


--
-- Name: grades grades_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.grades
    ADD CONSTRAINT grades_pkey PRIMARY KEY (id);


--
-- Name: guardhouse_archive_sessions guardhouse_archive_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_archive_sessions
    ADD CONSTRAINT guardhouse_archive_sessions_pkey PRIMARY KEY (id);


--
-- Name: guardhouse_archived_records guardhouse_archived_records_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_archived_records
    ADD CONSTRAINT guardhouse_archived_records_pkey PRIMARY KEY (id);


--
-- Name: guardhouse_attendance guardhouse_attendance_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_attendance
    ADD CONSTRAINT guardhouse_attendance_pkey PRIMARY KEY (id);


--
-- Name: guardhouse_users guardhouse_users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_users
    ADD CONSTRAINT guardhouse_users_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: schedules schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schedules
    ADD CONSTRAINT schedules_pkey PRIMARY KEY (id);


--
-- Name: school_calendar_events school_calendar_events_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_calendar_events
    ADD CONSTRAINT school_calendar_events_pkey PRIMARY KEY (id);


--
-- Name: school_days school_days_date_school_year_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_days
    ADD CONSTRAINT school_days_date_school_year_id_unique UNIQUE (date, school_year_id);


--
-- Name: school_days school_days_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_days
    ADD CONSTRAINT school_days_pkey PRIMARY KEY (id);


--
-- Name: school_holidays school_holidays_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_holidays
    ADD CONSTRAINT school_holidays_pkey PRIMARY KEY (id);


--
-- Name: school_years school_years_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_years
    ADD CONSTRAINT school_years_pkey PRIMARY KEY (id);


--
-- Name: seating_arrangements seating_arrangements_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.seating_arrangements
    ADD CONSTRAINT seating_arrangements_pkey PRIMARY KEY (id);


--
-- Name: seating_arrangements seating_arrangements_section_id_subject_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.seating_arrangements
    ADD CONSTRAINT seating_arrangements_section_id_subject_id_unique UNIQUE (section_id, subject_id);


--
-- Name: schedules section_schedule_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schedules
    ADD CONSTRAINT section_schedule_unique UNIQUE (section_id, day_of_week, start_time);


--
-- Name: section_subject section_subject_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.section_subject
    ADD CONSTRAINT section_subject_pkey PRIMARY KEY (id);


--
-- Name: section_subject section_subject_section_id_subject_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.section_subject
    ADD CONSTRAINT section_subject_section_id_subject_id_unique UNIQUE (section_id, subject_id);


--
-- Name: sections sections_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sections
    ADD CONSTRAINT sections_pkey PRIMARY KEY (id);


--
-- Name: sf2_attendance_edits sf2_attendance_edits_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sf2_attendance_edits
    ADD CONSTRAINT sf2_attendance_edits_pkey PRIMARY KEY (id);


--
-- Name: sf2_attendance_edits sf2_attendance_edits_student_id_date_section_id_month_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sf2_attendance_edits
    ADD CONSTRAINT sf2_attendance_edits_student_id_date_section_id_month_unique UNIQUE (student_id, date, section_id, month);


--
-- Name: student_details student_details_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_details
    ADD CONSTRAINT student_details_pkey PRIMARY KEY (id);


--
-- Name: student_details student_details_qr_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_details
    ADD CONSTRAINT student_details_qr_code_unique UNIQUE (qr_code);


--
-- Name: student_details student_details_student_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_details
    ADD CONSTRAINT student_details_student_id_unique UNIQUE (student_id);


--
-- Name: student_details student_details_studentid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_details
    ADD CONSTRAINT student_details_studentid_unique UNIQUE ("studentId");


--
-- Name: student_enrollment_history student_enrollment_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_enrollment_history
    ADD CONSTRAINT student_enrollment_history_pkey PRIMARY KEY (id);


--
-- Name: student_qr_codes student_qr_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_qr_codes
    ADD CONSTRAINT student_qr_codes_pkey PRIMARY KEY (id);


--
-- Name: student_qr_codes student_qr_codes_qr_code_data_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_qr_codes
    ADD CONSTRAINT student_qr_codes_qr_code_data_unique UNIQUE (qr_code_data);


--
-- Name: student_qr_codes student_qr_codes_qr_code_hash_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_qr_codes
    ADD CONSTRAINT student_qr_codes_qr_code_hash_unique UNIQUE (qr_code_hash);


--
-- Name: student_section student_section_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_section
    ADD CONSTRAINT student_section_pkey PRIMARY KEY (id);


--
-- Name: student_section student_section_student_id_school_year_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_section
    ADD CONSTRAINT student_section_student_id_school_year_unique UNIQUE (student_id, school_year);


--
-- Name: student_status_history student_status_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_status_history
    ADD CONSTRAINT student_status_history_pkey PRIMARY KEY (id);


--
-- Name: students students_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.students
    ADD CONSTRAINT students_pkey PRIMARY KEY (id);


--
-- Name: students students_studentid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.students
    ADD CONSTRAINT students_studentid_unique UNIQUE ("studentId");


--
-- Name: subject_schedules subject_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subject_schedules
    ADD CONSTRAINT subject_schedules_pkey PRIMARY KEY (id);


--
-- Name: subjects subjects_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subjects
    ADD CONSTRAINT subjects_code_unique UNIQUE (code);


--
-- Name: subjects subjects_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subjects
    ADD CONSTRAINT subjects_pkey PRIMARY KEY (id);


--
-- Name: submitted_sf2_reports submitted_sf2_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.submitted_sf2_reports
    ADD CONSTRAINT submitted_sf2_reports_pkey PRIMARY KEY (id);


--
-- Name: subject_schedules teacher_schedule_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subject_schedules
    ADD CONSTRAINT teacher_schedule_unique UNIQUE (teacher_id, day, start_time);


--
-- Name: teacher_section_subject teacher_section_subject_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_section_subject
    ADD CONSTRAINT teacher_section_subject_pkey PRIMARY KEY (id);


--
-- Name: teacher_section_subject teacher_section_subject_teacher_id_section_id_subject_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_section_subject
    ADD CONSTRAINT teacher_section_subject_teacher_id_section_id_subject_id_unique UNIQUE (teacher_id, section_id, subject_id);


--
-- Name: teacher_sessions teacher_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_sessions
    ADD CONSTRAINT teacher_sessions_pkey PRIMARY KEY (id);


--
-- Name: teacher_sessions teacher_sessions_token_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_sessions
    ADD CONSTRAINT teacher_sessions_token_unique UNIQUE (token);


--
-- Name: teachers teachers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teachers
    ADD CONSTRAINT teachers_pkey PRIMARY KEY (id);


--
-- Name: student_enrollment_history unique_active_enrollment; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_enrollment_history
    ADD CONSTRAINT unique_active_enrollment UNIQUE (student_id, school_year, enrollment_status);


--
-- Name: class_schedules unique_class_schedule; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.class_schedules
    ADD CONSTRAINT unique_class_schedule UNIQUE (teacher_id, section_id, subject_id, day_of_week, school_year, semester, effective_from);


--
-- Name: attendances unique_student_section_subject_date; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT unique_student_section_subject_date UNIQUE (student_id, section_id, subject_id, date);


--
-- Name: attendance_records unique_student_session_attendance; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT unique_student_session_attendance UNIQUE (attendance_session_id, student_id);


--
-- Name: user_sessions user_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_sessions
    ADD CONSTRAINT user_sessions_pkey PRIMARY KEY (id);


--
-- Name: user_sessions user_sessions_token_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_sessions
    ADD CONSTRAINT user_sessions_token_unique UNIQUE (token);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_username_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_unique UNIQUE (username);


--
-- Name: attendance_audit_log_action_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_audit_log_action_created_at_index ON public.attendance_audit_log USING btree (action, created_at);


--
-- Name: attendance_audit_log_entity_type_entity_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_audit_log_entity_type_entity_id_created_at_index ON public.attendance_audit_log USING btree (entity_type, entity_id, created_at);


--
-- Name: attendance_audit_log_performed_by_teacher_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_audit_log_performed_by_teacher_id_created_at_index ON public.attendance_audit_log USING btree (performed_by_teacher_id, created_at);


--
-- Name: attendance_modifications_attendance_record_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_modifications_attendance_record_id_created_at_index ON public.attendance_modifications USING btree (attendance_record_id, created_at);


--
-- Name: attendance_modifications_modified_by_teacher_id_created_at_inde; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_modifications_modified_by_teacher_id_created_at_inde ON public.attendance_modifications USING btree (modified_by_teacher_id, created_at);


--
-- Name: attendance_policies_effective_from_effective_until_is_active_in; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_policies_effective_from_effective_until_is_active_in ON public.attendance_policies USING btree (effective_from, effective_until, is_active);


--
-- Name: attendance_policies_scope_scope_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_policies_scope_scope_id_is_active_index ON public.attendance_policies USING btree (scope, scope_id, is_active);


--
-- Name: attendance_reasons_reason_type_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_reasons_reason_type_is_active_index ON public.attendance_reasons USING btree (reason_type, is_active);


--
-- Name: attendance_records_attendance_session_id_attendance_status_id_i; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_records_attendance_session_id_attendance_status_id_i ON public.attendance_records USING btree (attendance_session_id, attendance_status_id);


--
-- Name: attendance_records_data_source_marked_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_records_data_source_marked_at_index ON public.attendance_records USING btree (data_source, marked_at);


--
-- Name: attendance_records_is_current_version_attendance_session_id_ind; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_records_is_current_version_attendance_session_id_ind ON public.attendance_records USING btree (is_current_version, attendance_session_id);


--
-- Name: attendance_records_is_verified_verified_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_records_is_verified_verified_at_index ON public.attendance_records USING btree (is_verified, verified_at);


--
-- Name: attendance_records_marked_by_teacher_id_marked_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_records_marked_by_teacher_id_marked_at_index ON public.attendance_records USING btree (marked_by_teacher_id, marked_at);


--
-- Name: attendance_records_original_record_id_version_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_records_original_record_id_version_index ON public.attendance_records USING btree (original_record_id, version);


--
-- Name: attendance_records_student_id_marked_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_records_student_id_marked_at_index ON public.attendance_records USING btree (student_id, marked_at);


--
-- Name: attendance_session_edits_edit_type_edit_reason_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_session_edits_edit_type_edit_reason_index ON public.attendance_session_edits USING btree (edit_type, edit_reason);


--
-- Name: attendance_session_edits_edited_by_teacher_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_session_edits_edited_by_teacher_id_created_at_index ON public.attendance_session_edits USING btree (edited_by_teacher_id, created_at);


--
-- Name: attendance_session_edits_session_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_session_edits_session_id_created_at_index ON public.attendance_session_edits USING btree (session_id, created_at);


--
-- Name: attendance_session_stats_attendance_rate_calculated_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_session_stats_attendance_rate_calculated_at_index ON public.attendance_session_stats USING btree (attendance_rate, calculated_at);


--
-- Name: attendance_sessions_edited_by_teacher_id_edited_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_sessions_edited_by_teacher_id_edited_at_index ON public.attendance_sessions USING btree (edited_by_teacher_id, edited_at);


--
-- Name: attendance_sessions_is_current_version_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_sessions_is_current_version_status_index ON public.attendance_sessions USING btree (is_current_version, status);


--
-- Name: attendance_sessions_original_session_id_version_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_sessions_original_session_id_version_index ON public.attendance_sessions USING btree (original_session_id, version);


--
-- Name: attendance_sessions_section_id_session_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_sessions_section_id_session_date_index ON public.attendance_sessions USING btree (section_id, session_date);


--
-- Name: attendance_sessions_session_date_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_sessions_session_date_status_index ON public.attendance_sessions USING btree (session_date, status);


--
-- Name: attendance_sessions_teacher_id_session_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_sessions_teacher_id_session_date_index ON public.attendance_sessions USING btree (teacher_id, session_date);


--
-- Name: attendance_validation_rules_rule_type_is_active_priority_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX attendance_validation_rules_rule_type_is_active_priority_index ON public.attendance_validation_rules USING btree (rule_type, is_active, priority);


--
-- Name: class_schedules_day_of_week_start_time_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX class_schedules_day_of_week_start_time_is_active_index ON public.class_schedules USING btree (day_of_week, start_time, is_active);


--
-- Name: class_schedules_school_year_semester_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX class_schedules_school_year_semester_is_active_index ON public.class_schedules USING btree (school_year, semester, is_active);


--
-- Name: curriculum_grade_curriculum_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX curriculum_grade_curriculum_id_index ON public.curriculum_grade USING btree (curriculum_id);


--
-- Name: curriculum_grade_grade_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX curriculum_grade_grade_id_index ON public.curriculum_grade USING btree (grade_id);


--
-- Name: curriculum_grade_subject_curriculum_id_grade_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX curriculum_grade_subject_curriculum_id_grade_id_status_index ON public.curriculum_grade_subject USING btree (curriculum_id, grade_id, status);


--
-- Name: curriculum_grade_subject_curriculum_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX curriculum_grade_subject_curriculum_id_index ON public.curriculum_grade_subject USING btree (curriculum_id);


--
-- Name: curriculum_grade_subject_grade_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX curriculum_grade_subject_grade_id_index ON public.curriculum_grade_subject USING btree (grade_id);


--
-- Name: curriculum_grade_subject_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX curriculum_grade_subject_status_index ON public.curriculum_grade_subject USING btree (status);


--
-- Name: curriculum_grade_subject_subject_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX curriculum_grade_subject_subject_id_index ON public.curriculum_grade_subject USING btree (subject_id);


--
-- Name: gate_attendance_scan_date_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX gate_attendance_scan_date_type_index ON public.gate_attendance USING btree (scan_date, type);


--
-- Name: gate_attendance_scan_time_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX gate_attendance_scan_time_index ON public.gate_attendance USING btree (scan_time);


--
-- Name: gate_attendance_student_id_scan_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX gate_attendance_student_id_scan_date_index ON public.gate_attendance USING btree (student_id, scan_date);


--
-- Name: guardhouse_archive_sessions_archived_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX guardhouse_archive_sessions_archived_at_index ON public.guardhouse_archive_sessions USING btree (archived_at);


--
-- Name: guardhouse_archive_sessions_session_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX guardhouse_archive_sessions_session_date_index ON public.guardhouse_archive_sessions USING btree (session_date);


--
-- Name: guardhouse_archived_records_record_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX guardhouse_archived_records_record_type_index ON public.guardhouse_archived_records USING btree (record_type);


--
-- Name: guardhouse_archived_records_session_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX guardhouse_archived_records_session_date_index ON public.guardhouse_archived_records USING btree (session_date);


--
-- Name: guardhouse_archived_records_session_date_record_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX guardhouse_archived_records_session_date_record_type_index ON public.guardhouse_archived_records USING btree (session_date, record_type);


--
-- Name: guardhouse_archived_records_session_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX guardhouse_archived_records_session_id_index ON public.guardhouse_archived_records USING btree (session_id);


--
-- Name: guardhouse_archived_records_student_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX guardhouse_archived_records_student_id_index ON public.guardhouse_archived_records USING btree (student_id);


--
-- Name: guardhouse_archived_records_student_id_session_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX guardhouse_archived_records_student_id_session_date_index ON public.guardhouse_archived_records USING btree (student_id, session_date);


--
-- Name: guardhouse_archived_records_student_name_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX guardhouse_archived_records_student_name_index ON public.guardhouse_archived_records USING btree (student_name);


--
-- Name: idx_archive_sessions_archived_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archive_sessions_archived_at ON public.guardhouse_archive_sessions USING btree (archived_at DESC);


--
-- Name: idx_archive_sessions_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archive_sessions_date ON public.guardhouse_archive_sessions USING btree (session_date DESC);


--
-- Name: idx_archived_records_grade_level; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_grade_level ON public.guardhouse_archived_records USING btree (grade_level);


--
-- Name: idx_archived_records_record_type; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_record_type ON public.guardhouse_archived_records USING btree (record_type);


--
-- Name: idx_archived_records_section; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_section ON public.guardhouse_archived_records USING btree (section);


--
-- Name: idx_archived_records_session; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_session ON public.guardhouse_archived_records USING btree (session_id);


--
-- Name: idx_archived_records_session_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_session_date ON public.guardhouse_archived_records USING btree (session_date);


--
-- Name: idx_archived_records_session_grade; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_session_grade ON public.guardhouse_archived_records USING btree (session_id, grade_level);


--
-- Name: idx_archived_records_session_section; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_session_section ON public.guardhouse_archived_records USING btree (session_id, section);


--
-- Name: idx_archived_records_session_type; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_session_type ON public.guardhouse_archived_records USING btree (session_id, record_type);


--
-- Name: idx_archived_records_student_name; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_student_name ON public.guardhouse_archived_records USING btree (student_name);


--
-- Name: idx_archived_records_student_name_gin; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_student_name_gin ON public.guardhouse_archived_records USING gin (to_tsvector('english'::regconfig, (student_name)::text));


--
-- Name: idx_archived_records_timestamp; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_archived_records_timestamp ON public.guardhouse_archived_records USING btree ("timestamp" DESC);


--
-- Name: idx_attendance_records_marked_at_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_records_marked_at_status ON public.attendance_records USING btree (marked_at, attendance_status_id) WHERE (is_current_version = true);


--
-- Name: idx_attendance_records_session; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_records_session ON public.attendance_records USING btree (attendance_session_id);


--
-- Name: idx_attendance_records_session_student; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_records_session_student ON public.attendance_records USING btree (attendance_session_id, student_id);


--
-- Name: idx_attendance_records_session_student_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_records_session_student_status ON public.attendance_records USING btree (attendance_session_id, student_id, attendance_status_id);


--
-- Name: idx_attendance_records_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_records_status ON public.attendance_records USING btree (attendance_status_id);


--
-- Name: idx_attendance_records_student; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_records_student ON public.attendance_records USING btree (student_id);


--
-- Name: idx_attendance_sessions_composite; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_sessions_composite ON public.attendance_sessions USING btree (teacher_id, section_id, subject_id);


--
-- Name: idx_attendance_sessions_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_sessions_date ON public.attendance_sessions USING btree (session_date);


--
-- Name: idx_attendance_sessions_section; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_sessions_section ON public.attendance_sessions USING btree (section_id);


--
-- Name: idx_attendance_sessions_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_sessions_status ON public.attendance_sessions USING btree (status);


--
-- Name: idx_attendance_sessions_subject; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_sessions_subject ON public.attendance_sessions USING btree (subject_id);


--
-- Name: idx_attendance_sessions_teacher; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_sessions_teacher ON public.attendance_sessions USING btree (teacher_id);


--
-- Name: idx_attendance_sessions_teacher_date_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendance_sessions_teacher_date_status ON public.attendance_sessions USING btree (teacher_id, session_date, status);


--
-- Name: idx_attendances_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendances_date ON public.attendances USING btree (date);


--
-- Name: idx_attendances_section_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendances_section_date ON public.attendances USING btree (section_id, date);


--
-- Name: idx_attendances_section_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendances_section_id ON public.attendances USING btree (section_id);


--
-- Name: idx_attendances_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendances_status ON public.attendances USING btree (status);


--
-- Name: idx_attendances_student_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendances_student_date ON public.attendances USING btree (student_id, date);


--
-- Name: idx_attendances_student_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendances_student_id ON public.attendances USING btree (student_id);


--
-- Name: idx_attendances_subject_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendances_subject_id ON public.attendances USING btree (subject_id);


--
-- Name: idx_attendances_teacher_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendances_teacher_date ON public.attendances USING btree (teacher_id, date);


--
-- Name: idx_attendances_teacher_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_attendances_teacher_id ON public.attendances USING btree (teacher_id);


--
-- Name: idx_completed_sessions; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_completed_sessions ON public.attendance_sessions USING btree (teacher_id, section_id, subject_id, session_date, status) WHERE ((status)::text = 'completed'::text);


--
-- Name: idx_guardhouse_attendance_date_type; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_guardhouse_attendance_date_type ON public.guardhouse_attendance USING btree (date, record_type);


--
-- Name: idx_guardhouse_attendance_student_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_guardhouse_attendance_student_date ON public.guardhouse_attendance USING btree (student_id, date);


--
-- Name: idx_guardhouse_attendance_timestamp; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_guardhouse_attendance_timestamp ON public.guardhouse_attendance USING btree ("timestamp" DESC);


--
-- Name: idx_priority_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_priority_user ON public.notifications USING btree (priority, user_id);


--
-- Name: idx_sections_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sections_active ON public.sections USING btree (is_active);


--
-- Name: idx_sections_curriculum_grade; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sections_curriculum_grade ON public.sections USING btree (curriculum_grade_id);


--
-- Name: idx_sections_homeroom_teacher; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sections_homeroom_teacher ON public.sections USING btree (homeroom_teacher_id);


--
-- Name: idx_sections_name; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sections_name ON public.sections USING btree (name);


--
-- Name: idx_student_details_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_details_active ON public.student_details USING btree ("isActive");


--
-- Name: idx_student_details_grade_section; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_details_grade_section ON public.student_details USING btree ("gradeLevel", section);


--
-- Name: idx_student_details_lrn; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_details_lrn ON public.student_details USING btree (lrn);


--
-- Name: idx_student_details_names; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_details_names ON public.student_details USING btree ("firstName", "lastName");


--
-- Name: idx_student_details_student_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_details_student_id ON public.student_details USING btree (student_id);


--
-- Name: idx_student_qr_codes_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_qr_codes_active ON public.student_qr_codes USING btree (is_active);


--
-- Name: idx_student_qr_codes_data; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_qr_codes_data ON public.student_qr_codes USING btree (qr_code_data);


--
-- Name: idx_student_qr_codes_data_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_qr_codes_data_active ON public.student_qr_codes USING btree (qr_code_data, is_active);


--
-- Name: idx_student_qr_codes_student; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_qr_codes_student ON public.student_qr_codes USING btree (student_id);


--
-- Name: idx_student_section_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_section_active ON public.student_section USING btree (is_active);


--
-- Name: idx_student_section_composite; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_section_composite ON public.student_section USING btree (student_id, section_id, is_active);


--
-- Name: idx_student_section_section; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_section_section ON public.student_section USING btree (section_id);


--
-- Name: idx_student_section_student; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_student_section_student ON public.student_section USING btree (student_id);


--
-- Name: idx_subject_schedules_section; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subject_schedules_section ON public.subject_schedules USING btree (section_id);


--
-- Name: idx_subject_schedules_section_subject; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subject_schedules_section_subject ON public.subject_schedules USING btree (section_id, subject_id);


--
-- Name: idx_subject_schedules_subject; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subject_schedules_subject ON public.subject_schedules USING btree (subject_id);


--
-- Name: idx_subject_schedules_teacher; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subject_schedules_teacher ON public.subject_schedules USING btree (teacher_id);


--
-- Name: idx_subjects_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subjects_active ON public.subjects USING btree (is_active);


--
-- Name: idx_subjects_name; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subjects_name ON public.subjects USING btree (name);


--
-- Name: idx_teachers_head_teacher; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_teachers_head_teacher ON public.teachers USING btree (is_head_teacher);


--
-- Name: idx_teachers_names; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_teachers_names ON public.teachers USING btree (first_name, last_name);


--
-- Name: idx_teachers_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_teachers_user_id ON public.teachers USING btree (user_id);


--
-- Name: idx_tss_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tss_active ON public.teacher_section_subject USING btree (is_active);


--
-- Name: idx_tss_composite; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tss_composite ON public.teacher_section_subject USING btree (teacher_id, section_id, subject_id);


--
-- Name: idx_tss_section_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tss_section_id ON public.teacher_section_subject USING btree (section_id);


--
-- Name: idx_tss_subject_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tss_subject_id ON public.teacher_section_subject USING btree (subject_id);


--
-- Name: idx_tss_teacher_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tss_teacher_id ON public.teacher_section_subject USING btree (teacher_id);


--
-- Name: idx_tss_teacher_role; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tss_teacher_role ON public.teacher_section_subject USING btree (teacher_id, role);


--
-- Name: idx_type_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_type_user ON public.notifications USING btree (type, user_id);


--
-- Name: idx_user_created; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_created ON public.notifications USING btree (user_id, created_at);


--
-- Name: idx_user_read; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_read ON public.notifications USING btree (user_id, is_read);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: schedules_section_id_day_of_week_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX schedules_section_id_day_of_week_index ON public.schedules USING btree (section_id, day_of_week);


--
-- Name: schedules_teacher_id_day_of_week_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX schedules_teacher_id_day_of_week_index ON public.schedules USING btree (teacher_id, day_of_week);


--
-- Name: school_calendar_events_event_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX school_calendar_events_event_type_index ON public.school_calendar_events USING btree (event_type);


--
-- Name: school_calendar_events_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX school_calendar_events_is_active_index ON public.school_calendar_events USING btree (is_active);


--
-- Name: school_calendar_events_start_date_end_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX school_calendar_events_start_date_end_date_index ON public.school_calendar_events USING btree (start_date, end_date);


--
-- Name: sf2_attendance_edits_date_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sf2_attendance_edits_date_index ON public.sf2_attendance_edits USING btree (date);


--
-- Name: sf2_attendance_edits_month_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sf2_attendance_edits_month_index ON public.sf2_attendance_edits USING btree (month);


--
-- Name: sf2_attendance_edits_section_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sf2_attendance_edits_section_id_index ON public.sf2_attendance_edits USING btree (section_id);


--
-- Name: sf2_attendance_edits_student_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sf2_attendance_edits_student_id_index ON public.sf2_attendance_edits USING btree (student_id);


--
-- Name: student_enrollment_history_section_id_school_year_enrollment_st; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX student_enrollment_history_section_id_school_year_enrollment_st ON public.student_enrollment_history USING btree (section_id, school_year, enrollment_status);


--
-- Name: student_enrollment_history_student_id_enrolled_date_unenrolled_; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX student_enrollment_history_student_id_enrolled_date_unenrolled_ ON public.student_enrollment_history USING btree (student_id, enrolled_date, unenrolled_date);


--
-- Name: student_qr_codes_qr_code_data_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX student_qr_codes_qr_code_data_index ON public.student_qr_codes USING btree (qr_code_data);


--
-- Name: student_qr_codes_student_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX student_qr_codes_student_id_is_active_index ON public.student_qr_codes USING btree (student_id, is_active);


--
-- Name: student_section_section_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX student_section_section_id_is_active_index ON public.student_section USING btree (section_id, is_active);


--
-- Name: student_section_status_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX student_section_status_is_active_index ON public.student_section USING btree (status, is_active);


--
-- Name: student_section_student_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX student_section_student_id_is_active_index ON public.student_section USING btree (student_id, is_active);


--
-- Name: student_status_history_changed_by_teacher_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX student_status_history_changed_by_teacher_id_index ON public.student_status_history USING btree (changed_by_teacher_id);


--
-- Name: student_status_history_student_id_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX student_status_history_student_id_created_at_index ON public.student_status_history USING btree (student_id, created_at);


--
-- Name: students_status_archived_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX students_status_archived_at_index ON public.students USING btree (status, archived_at);


--
-- Name: submitted_sf2_reports_section_id_month_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX submitted_sf2_reports_section_id_month_index ON public.submitted_sf2_reports USING btree (section_id, month);


--
-- Name: submitted_sf2_reports_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX submitted_sf2_reports_status_index ON public.submitted_sf2_reports USING btree (status);


--
-- Name: submitted_sf2_reports_submitted_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX submitted_sf2_reports_submitted_at_index ON public.submitted_sf2_reports USING btree (submitted_at);


--
-- Name: teacher_sessions_teacher_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX teacher_sessions_teacher_id_index ON public.teacher_sessions USING btree (teacher_id);


--
-- Name: teacher_sessions_token_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX teacher_sessions_token_index ON public.teacher_sessions USING btree (token);


--
-- Name: unique_active_session_only; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX unique_active_session_only ON public.attendance_sessions USING btree (teacher_id, section_id, subject_id, session_date) WHERE ((status)::text = 'active'::text);


--
-- Name: user_sessions_last_activity_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX user_sessions_last_activity_index ON public.user_sessions USING btree (last_activity);


--
-- Name: user_sessions_token_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX user_sessions_token_index ON public.user_sessions USING btree (token);


--
-- Name: user_sessions_user_id_role_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX user_sessions_user_id_role_index ON public.user_sessions USING btree (user_id, role);


--
-- Name: admins admins_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: attendance_audit_log attendance_audit_log_performed_by_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_audit_log
    ADD CONSTRAINT attendance_audit_log_performed_by_teacher_id_foreign FOREIGN KEY (performed_by_teacher_id) REFERENCES public.teachers(id) ON DELETE SET NULL;


--
-- Name: attendance_modifications attendance_modifications_attendance_record_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_modifications
    ADD CONSTRAINT attendance_modifications_attendance_record_id_foreign FOREIGN KEY (attendance_record_id) REFERENCES public.attendance_records(id) ON DELETE CASCADE;


--
-- Name: attendance_modifications attendance_modifications_authorized_by_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_modifications
    ADD CONSTRAINT attendance_modifications_authorized_by_teacher_id_foreign FOREIGN KEY (authorized_by_teacher_id) REFERENCES public.teachers(id) ON DELETE SET NULL;


--
-- Name: attendance_modifications attendance_modifications_modified_by_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_modifications
    ADD CONSTRAINT attendance_modifications_modified_by_teacher_id_foreign FOREIGN KEY (modified_by_teacher_id) REFERENCES public.teachers(id) ON DELETE RESTRICT;


--
-- Name: attendance_records attendance_records_attendance_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_attendance_session_id_foreign FOREIGN KEY (attendance_session_id) REFERENCES public.attendance_sessions(id) ON DELETE CASCADE;


--
-- Name: attendance_records attendance_records_attendance_status_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_attendance_status_id_foreign FOREIGN KEY (attendance_status_id) REFERENCES public.attendance_statuses(id) ON DELETE RESTRICT;


--
-- Name: attendance_records attendance_records_marked_by_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_marked_by_teacher_id_foreign FOREIGN KEY (marked_by_teacher_id) REFERENCES public.teachers(id) ON DELETE RESTRICT;


--
-- Name: attendance_records attendance_records_original_record_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_original_record_id_foreign FOREIGN KEY (original_record_id) REFERENCES public.attendance_records(id) ON DELETE SET NULL;


--
-- Name: attendance_records attendance_records_reason_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_reason_id_foreign FOREIGN KEY (reason_id) REFERENCES public.attendance_reasons(id) ON DELETE SET NULL;


--
-- Name: attendance_records attendance_records_student_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_student_id_foreign FOREIGN KEY (student_id) REFERENCES public.student_details(id) ON DELETE CASCADE;


--
-- Name: attendance_records attendance_records_verified_by_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_verified_by_teacher_id_foreign FOREIGN KEY (verified_by_teacher_id) REFERENCES public.teachers(id) ON DELETE SET NULL;


--
-- Name: attendance_session_edits attendance_session_edits_edited_by_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_session_edits
    ADD CONSTRAINT attendance_session_edits_edited_by_teacher_id_foreign FOREIGN KEY (edited_by_teacher_id) REFERENCES public.teachers(id) ON DELETE RESTRICT;


--
-- Name: attendance_session_edits attendance_session_edits_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_session_edits
    ADD CONSTRAINT attendance_session_edits_session_id_foreign FOREIGN KEY (session_id) REFERENCES public.attendance_sessions(id) ON DELETE CASCADE;


--
-- Name: attendance_session_stats attendance_session_stats_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_session_stats
    ADD CONSTRAINT attendance_session_stats_session_id_foreign FOREIGN KEY (session_id) REFERENCES public.attendance_sessions(id) ON DELETE CASCADE;


--
-- Name: attendance_sessions attendance_sessions_edited_by_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_sessions
    ADD CONSTRAINT attendance_sessions_edited_by_teacher_id_foreign FOREIGN KEY (edited_by_teacher_id) REFERENCES public.teachers(id) ON DELETE SET NULL;


--
-- Name: attendance_sessions attendance_sessions_original_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_sessions
    ADD CONSTRAINT attendance_sessions_original_session_id_foreign FOREIGN KEY (original_session_id) REFERENCES public.attendance_sessions(id) ON DELETE SET NULL;


--
-- Name: attendance_sessions attendance_sessions_school_year_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_sessions
    ADD CONSTRAINT attendance_sessions_school_year_id_foreign FOREIGN KEY (school_year_id) REFERENCES public.school_years(id);


--
-- Name: attendance_sessions attendance_sessions_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_sessions
    ADD CONSTRAINT attendance_sessions_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: attendance_sessions attendance_sessions_subject_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_sessions
    ADD CONSTRAINT attendance_sessions_subject_id_foreign FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE SET NULL;


--
-- Name: attendance_sessions attendance_sessions_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_sessions
    ADD CONSTRAINT attendance_sessions_teacher_id_foreign FOREIGN KEY (teacher_id) REFERENCES public.teachers(id) ON DELETE CASCADE;


--
-- Name: attendances attendances_attendance_status_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_attendance_status_id_foreign FOREIGN KEY (attendance_status_id) REFERENCES public.attendance_statuses(id) ON DELETE SET NULL;


--
-- Name: attendances attendances_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: attendances attendances_student_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_student_id_foreign FOREIGN KEY (student_id) REFERENCES public.student_details(id) ON DELETE CASCADE;


--
-- Name: attendances attendances_subject_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_subject_id_foreign FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE SET NULL;


--
-- Name: attendances attendances_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_teacher_id_foreign FOREIGN KEY (teacher_id) REFERENCES public.teachers(id) ON DELETE SET NULL;


--
-- Name: curriculum_grade_subject cgs_curriculum_grade_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade_subject
    ADD CONSTRAINT cgs_curriculum_grade_foreign FOREIGN KEY (curriculum_id, grade_id) REFERENCES public.curriculum_grade(curriculum_id, grade_id) ON DELETE CASCADE;


--
-- Name: class_schedules class_schedules_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.class_schedules
    ADD CONSTRAINT class_schedules_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: class_schedules class_schedules_subject_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.class_schedules
    ADD CONSTRAINT class_schedules_subject_id_foreign FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE CASCADE;


--
-- Name: class_schedules class_schedules_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.class_schedules
    ADD CONSTRAINT class_schedules_teacher_id_foreign FOREIGN KEY (teacher_id) REFERENCES public.teachers(id) ON DELETE CASCADE;


--
-- Name: curriculum_grade curriculum_grade_curriculum_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade
    ADD CONSTRAINT curriculum_grade_curriculum_id_foreign FOREIGN KEY (curriculum_id) REFERENCES public.curricula(id) ON DELETE CASCADE;


--
-- Name: curriculum_grade curriculum_grade_grade_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade
    ADD CONSTRAINT curriculum_grade_grade_id_foreign FOREIGN KEY (grade_id) REFERENCES public.grades(id) ON DELETE CASCADE;


--
-- Name: curriculum_grade_subject curriculum_grade_subject_curriculum_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade_subject
    ADD CONSTRAINT curriculum_grade_subject_curriculum_id_foreign FOREIGN KEY (curriculum_id) REFERENCES public.curricula(id) ON DELETE CASCADE;


--
-- Name: curriculum_grade_subject curriculum_grade_subject_grade_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade_subject
    ADD CONSTRAINT curriculum_grade_subject_grade_id_foreign FOREIGN KEY (grade_id) REFERENCES public.grades(id) ON DELETE CASCADE;


--
-- Name: curriculum_grade_subject curriculum_grade_subject_subject_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.curriculum_grade_subject
    ADD CONSTRAINT curriculum_grade_subject_subject_id_foreign FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE CASCADE;


--
-- Name: gate_attendance gate_attendance_student_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.gate_attendance
    ADD CONSTRAINT gate_attendance_student_id_foreign FOREIGN KEY (student_id) REFERENCES public.student_details(id) ON DELETE CASCADE;


--
-- Name: guardhouse_attendance guardhouse_attendance_student_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_attendance
    ADD CONSTRAINT guardhouse_attendance_student_id_fkey FOREIGN KEY (student_id) REFERENCES public.student_details(id) ON DELETE CASCADE;


--
-- Name: guardhouse_users guardhouse_users_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.guardhouse_users
    ADD CONSTRAINT guardhouse_users_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: schedules schedules_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schedules
    ADD CONSTRAINT schedules_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: schedules schedules_subject_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schedules
    ADD CONSTRAINT schedules_subject_id_foreign FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE CASCADE;


--
-- Name: schedules schedules_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schedules
    ADD CONSTRAINT schedules_teacher_id_foreign FOREIGN KEY (teacher_id) REFERENCES public.teachers(id) ON DELETE CASCADE;


--
-- Name: school_days school_days_school_year_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.school_days
    ADD CONSTRAINT school_days_school_year_id_foreign FOREIGN KEY (school_year_id) REFERENCES public.school_years(id);


--
-- Name: section_subject section_subject_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.section_subject
    ADD CONSTRAINT section_subject_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: section_subject section_subject_subject_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.section_subject
    ADD CONSTRAINT section_subject_subject_id_foreign FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE CASCADE;


--
-- Name: sections sections_curriculum_grade_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sections
    ADD CONSTRAINT sections_curriculum_grade_id_foreign FOREIGN KEY (curriculum_grade_id) REFERENCES public.curriculum_grade(id) ON DELETE CASCADE;


--
-- Name: sections sections_curriculum_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sections
    ADD CONSTRAINT sections_curriculum_id_foreign FOREIGN KEY (curriculum_id) REFERENCES public.curricula(id) ON DELETE SET NULL;


--
-- Name: sections sections_homeroom_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sections
    ADD CONSTRAINT sections_homeroom_teacher_id_foreign FOREIGN KEY (homeroom_teacher_id) REFERENCES public.teachers(id) ON DELETE SET NULL;


--
-- Name: student_enrollment_history student_enrollment_history_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_enrollment_history
    ADD CONSTRAINT student_enrollment_history_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: student_enrollment_history student_enrollment_history_student_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_enrollment_history
    ADD CONSTRAINT student_enrollment_history_student_id_foreign FOREIGN KEY (student_id) REFERENCES public.student_details(id) ON DELETE CASCADE;


--
-- Name: student_qr_codes student_qr_codes_student_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_qr_codes
    ADD CONSTRAINT student_qr_codes_student_id_foreign FOREIGN KEY (student_id) REFERENCES public.student_details(id) ON DELETE CASCADE;


--
-- Name: student_section student_section_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_section
    ADD CONSTRAINT student_section_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: student_section student_section_student_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_section
    ADD CONSTRAINT student_section_student_id_foreign FOREIGN KEY (student_id) REFERENCES public.student_details(id) ON DELETE CASCADE;


--
-- Name: student_status_history student_status_history_changed_by_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_status_history
    ADD CONSTRAINT student_status_history_changed_by_teacher_id_foreign FOREIGN KEY (changed_by_teacher_id) REFERENCES public.teachers(id) ON DELETE CASCADE;


--
-- Name: student_status_history student_status_history_student_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.student_status_history
    ADD CONSTRAINT student_status_history_student_id_foreign FOREIGN KEY (student_id) REFERENCES public.student_details(id) ON DELETE CASCADE;


--
-- Name: students students_archived_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.students
    ADD CONSTRAINT students_archived_by_foreign FOREIGN KEY (archived_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: subject_schedules subject_schedules_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subject_schedules
    ADD CONSTRAINT subject_schedules_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: subject_schedules subject_schedules_subject_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subject_schedules
    ADD CONSTRAINT subject_schedules_subject_id_foreign FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE CASCADE;


--
-- Name: subject_schedules subject_schedules_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subject_schedules
    ADD CONSTRAINT subject_schedules_teacher_id_foreign FOREIGN KEY (teacher_id) REFERENCES public.teachers(id) ON DELETE SET NULL;


--
-- Name: submitted_sf2_reports submitted_sf2_reports_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.submitted_sf2_reports
    ADD CONSTRAINT submitted_sf2_reports_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: submitted_sf2_reports submitted_sf2_reports_submitted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.submitted_sf2_reports
    ADD CONSTRAINT submitted_sf2_reports_submitted_by_foreign FOREIGN KEY (submitted_by) REFERENCES public.teachers(id) ON DELETE CASCADE;


--
-- Name: teacher_section_subject teacher_section_subject_section_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_section_subject
    ADD CONSTRAINT teacher_section_subject_section_id_foreign FOREIGN KEY (section_id) REFERENCES public.sections(id) ON DELETE CASCADE;


--
-- Name: teacher_section_subject teacher_section_subject_subject_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_section_subject
    ADD CONSTRAINT teacher_section_subject_subject_id_foreign FOREIGN KEY (subject_id) REFERENCES public.subjects(id) ON DELETE CASCADE;


--
-- Name: teacher_section_subject teacher_section_subject_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_section_subject
    ADD CONSTRAINT teacher_section_subject_teacher_id_foreign FOREIGN KEY (teacher_id) REFERENCES public.teachers(id) ON DELETE CASCADE;


--
-- Name: teacher_sessions teacher_sessions_teacher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_sessions
    ADD CONSTRAINT teacher_sessions_teacher_id_foreign FOREIGN KEY (teacher_id) REFERENCES public.teachers(id) ON DELETE CASCADE;


--
-- Name: teacher_sessions teacher_sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teacher_sessions
    ADD CONSTRAINT teacher_sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: teachers teachers_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teachers
    ADD CONSTRAINT teachers_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_sessions user_sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_sessions
    ADD CONSTRAINT user_sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

