import axios from 'axios';

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || '') + '/api';

class SmartAnalyticsService {
    constructor() {
        this.api = axios.create({
            baseURL: API_BASE_URL,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            }
        });

        // Add request interceptor for authentication
        this.api.interceptors.request.use(
            (config) => {
                const token = localStorage.getItem('auth_token');
                if (token) {
                    config.headers.Authorization = `Bearer ${token}`;
                }
                return config;
            },
            (error) => {
                return Promise.reject(error);
            }
        );
    }

    /**
     * Get comprehensive analytics for a specific student
     */
    async getStudentAnalytics(studentId) {
        try {
            const response = await this.api.get(`/analytics/student/${studentId}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching student analytics:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Get analytics for all students assigned to a teacher
     */
    async getTeacherStudentAnalytics(teacherId = null) {
        try {
            const params = teacherId ? { teacher_id: teacherId } : {};
            const response = await this.api.get('/analytics/teacher/students', { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching teacher student analytics:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Get students exceeding 18 absence limit (critical cases)
     */
    async getCriticalAbsenteeism(teacherId = null) {
        try {
            const params = teacherId ? { teacher_id: teacherId } : {};
            const response = await this.api.get('/analytics/critical-absenteeism', { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching critical absenteeism cases:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Get REAL weekly attendance data from database records
     * @param {number} studentId - Student ID
     * @param {Date} month - Month to fetch data for (optional, defaults to last 4 weeks)
     * @param {number} subjectId - Subject ID to filter by (optional, defaults to all subjects)
     * @param {number} teacherId - Teacher ID to filter by (optional, defaults to all teachers)
     */
    async getStudentWeeklyAttendance(studentId, month = null, subjectId = null, teacherId = null) {
        try {
            const params = {};
            if (month) {
                params.year = month.getFullYear();
                params.month = month.getMonth() + 1; // JavaScript months are 0-indexed
            }
            if (subjectId) {
                params.subject_id = subjectId;
            }
            if (teacherId) {
                params.teacher_id = teacherId;
            }

            const response = await this.api.get(`/analytics/student/${studentId}/weekly`, { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching weekly attendance:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Get attendance patterns and trends for a student
     */
    async getAttendancePatterns(studentId, days = 30) {
        try {
            const response = await this.api.get(`/analytics/student/${studentId}/patterns`, {
                params: { days }
            });
            return response.data;
        } catch (error) {
            console.error('Error fetching attendance patterns:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Refresh analytics cache for a student
     */
    async refreshStudentAnalytics(studentId) {
        try {
            const response = await this.api.post(`/analytics/student/${studentId}/refresh`);
            return response.data;
        } catch (error) {
            console.error('Error refreshing student analytics:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Bulk refresh analytics for multiple students
     */
    async bulkRefreshAnalytics(studentIds) {
        try {
            const response = await this.api.post('/analytics/bulk-refresh', {
                student_ids: studentIds
            });
            return response.data;
        } catch (error) {
            console.error('Error bulk refreshing analytics:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Get urgency legend for UI color coding
     */
    async getUrgencyLegend() {
        try {
            const response = await this.api.get('/analytics/urgency-legend');
            return response.data;
        } catch (error) {
            console.error('Error fetching urgency legend:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Handle API errors consistently
     */
    handleError(error) {
        if (error.response) {
            // Server responded with error status
            const message = error.response.data?.message || 'An error occurred';
            return {
                success: false,
                message,
                status: error.response.status,
                data: error.response.data
            };
        } else if (error.request) {
            // Request was made but no response received
            return {
                success: false,
                message: 'Network error - please check your connection',
                status: 0
            };
        } else {
            // Something else happened
            return {
                success: false,
                message: error.message || 'An unexpected error occurred',
                status: 0
            };
        }
    }

    /**
     * Get risk level color class for UI
     */
    getRiskColorClass(riskLevel) {
        const colors = {
            low: 'text-green-600 bg-green-100 border-green-200',
            medium: 'text-yellow-600 bg-yellow-100 border-yellow-200',
            high: 'text-orange-600 bg-orange-100 border-orange-200',
            critical: 'text-red-600 bg-red-100 border-red-200'
        };
        return colors[riskLevel] || 'text-gray-500 bg-gray-100 border-gray-200';
    }

    /**
     * Get urgency color class for recommendations
     */
    getUrgencyColorClass(urgency) {
        const colors = {
            low: 'text-green-600 bg-green-50 border-green-200',
            medium: 'text-yellow-600 bg-yellow-50 border-yellow-200',
            high: 'text-orange-600 bg-orange-50 border-orange-200',
            critical: 'text-red-600 bg-red-50 border-red-200'
        };
        return colors[urgency] || 'text-gray-500 bg-gray-50 border-gray-200';
    }

    /**
     * Format attendance percentage for display
     */
    formatAttendancePercentage(percentage) {
        if (percentage === null || percentage === undefined) {
            return 'N/A';
        }
        return `${parseFloat(percentage).toFixed(1)}%`;
    }

    /**
     * Get risk level icon
     */
    getRiskIcon(riskLevel) {
        const icons = {
            low: '✅',
            medium: '⚠️',
            high: '🔶',
            critical: '🚨'
        };
        return icons[riskLevel] || '❓';
    }
}

export default new SmartAnalyticsService();
