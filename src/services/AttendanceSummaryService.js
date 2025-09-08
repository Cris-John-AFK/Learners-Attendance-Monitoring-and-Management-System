import api from '@/config/axios';

export const AttendanceSummaryService = {
    /**
     * Get attendance summary for teacher's students
     */
    async getTeacherAttendanceSummary(teacherId, options = {}) {
        try {
            const params = {
                teacher_id: teacherId,
                period: options.period || 'month', // day, week, month
                view_type: options.viewType || 'subject', // subject, all_students
                subject_id: options.subjectId || null
            };

            const response = await api.get('/api/attendance/summary', { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching attendance summary:', error);
            throw error;
        }
    },

    /**
     * Get attendance trends for charts
     */
    async getAttendanceTrends(teacherId, options = {}) {
        try {
            const params = {
                teacher_id: teacherId,
                period: options.period || 'week', // day, week, month
                view_type: options.viewType || 'subject', // subject, all_students
                subject_id: options.subjectId || null
            };

            const response = await api.get('/api/attendance/trends', { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching attendance trends:', error);
            throw error;
        }
    }
};
