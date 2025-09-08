import api from '@/config/axios';

export const TeacherAttendanceService = {
    /**
     * Get students for a specific teacher's section and subject
     */
    async getStudentsForTeacherSubject(teacherId, sectionId, subjectId) {
        try {
            const response = await api.get('/api/attendance-sessions/students', {
                params: {
                    teacher_id: teacherId,
                    section_id: sectionId,
                    subject_id: subjectId
                }
            });
            console.log('Students API response:', response.data);
            return response.data;
        } catch (error) {
            console.error('Error loading students for teacher subject:', error);
            throw error;
        }
    },

    /**
     * Create or get attendance session
     */
    async createAttendanceSession(sessionData) {
        try {
            const response = await api.post('/api/attendance-sessions', sessionData);
            return response.data;
        } catch (error) {
            console.error('Error creating attendance session:', error);
            throw error;
        }
    },

    /**
     * Mark attendance for session
     */
    async markSessionAttendance(sessionId, attendanceData) {
        try {
            const response = await api.post('/api/attendance-sessions/mark', {
                session_id: sessionId,
                attendance: attendanceData
            });
            return response.data;
        } catch (error) {
            console.error('Error marking session attendance:', error);
            throw error;
        }
    },

    /**
     * Complete attendance session
     */
    async completeAttendanceSession(sessionId) {
        try {
            const response = await api.post(`/api/attendance-sessions/${sessionId}/complete`);
            return response.data;
        } catch (error) {
            console.error('Error completing attendance session:', error);
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
     * Get teacher data by ID
     */
    async getTeacherData(teacherId) {
        try {
            const response = await api.get(`/api/teachers/${teacherId}`);
            return response.data;
        } catch (error) {
            console.error('Error loading teacher data:', error);
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
