import api from '@/config/axios';

export const TeacherAttendanceService = {
    /**
     * Get students for a specific teacher's section and subject
     */
    async getStudentsForTeacherSubject(teacherId, sectionId, subjectId) {
        try {
            const response = await api.get(`/api/teachers/${teacherId}/sections/${sectionId}/subjects/${subjectId}/students`);
            return response.data;
        } catch (error) {
            console.error('Error loading students for teacher subject:', error);
            throw error;
        }
    },

    /**
     * Get teacher's assigned sections and subjects
     */
    async getTeacherAssignments(teacherId) {
        try {
            const response = await api.get(`/api/teachers/${teacherId}/assignments`);
            return response.data;
        } catch (error) {
            console.error('Error loading teacher assignments:', error);
            throw error;
        }
    },

    /**
     * Get attendance records for a specific date, section, and subject
     */
    async getAttendanceByDateAndSubject(teacherId, sectionId, subjectId, date) {
        try {
            const response = await api.get('/api/attendance', {
                params: {
                    teacher_id: teacherId,
                    section_id: sectionId,
                    subject_id: subjectId,
                    date: date
                }
            });
            return response.data;
        } catch (error) {
            console.error('Error loading attendance records:', error);
            throw error;
        }
    },

    /**
     * Mark attendance for a student
     */
    async markAttendance(attendanceData) {
        try {
            const response = await api.post('/api/attendance', attendanceData);
            return response.data;
        } catch (error) {
            console.error('Error marking attendance:', error);
            throw error;
        }
    },

    /**
     * Update attendance record
     */
    async updateAttendance(attendanceId, attendanceData) {
        try {
            const response = await api.put(`/api/attendance/${attendanceId}`, attendanceData);
            return response.data;
        } catch (error) {
            console.error('Error updating attendance:', error);
            throw error;
        }
    },

    /**
     * Get attendance summary for teacher's dashboard
     */
    async getAttendanceSummary(teacherId, dateRange = null) {
        try {
            const params = { teacher_id: teacherId };
            if (dateRange) {
                params.start_date = dateRange.start;
                params.end_date = dateRange.end;
            }
            
            const response = await api.get('/api/attendance/summary', { params });
            return response.data;
        } catch (error) {
            console.error('Error loading attendance summary:', error);
            throw error;
        }
    }
};
