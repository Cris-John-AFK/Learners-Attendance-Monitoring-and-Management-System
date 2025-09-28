import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

class AdminStudentService {
    constructor() {
        this.api = axios.create({
            baseURL: API_BASE_URL,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
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
     * Get all students with enhanced information for admin
     */
    async getStudents(filters = {}) {
        try {
            const response = await this.api.get('/admin/students', { params: filters });
            return response.data;
        } catch (error) {
            console.error('Error fetching students:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Change student status (replaces delete functionality)
     */
    async changeStudentStatus(studentId, statusData) {
        try {
            const response = await this.api.post(`/admin/students/${studentId}/change-status`, statusData);
            return response.data;
        } catch (error) {
            console.error('Error changing student status:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Get student status history
     */
    async getStatusHistory(studentId) {
        try {
            const response = await this.api.get(`/admin/students/${studentId}/status-history`);
            return response.data;
        } catch (error) {
            console.error('Error fetching status history:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Manually archive a student
     */
    async archiveStudent(studentId, reason) {
        try {
            const response = await this.api.post(`/admin/students/${studentId}/archive`, {
                reason: reason
            });
            return response.data;
        } catch (error) {
            console.error('Error archiving student:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Get archived students
     */
    async getArchivedStudents(filters = {}) {
        try {
            const response = await this.api.get('/admin/archive/students', { params: filters });
            return response.data;
        } catch (error) {
            console.error('Error fetching archived students:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Restore student from archive
     */
    async restoreStudent(archiveId) {
        try {
            const response = await this.api.post(`/admin/archive/students/${archiveId}/restore`);
            return response.data;
        } catch (error) {
            console.error('Error restoring student:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Get available student statuses
     */
    getAvailableStatuses() {
        return {
            'active': { label: 'Active', color: 'text-green-600 bg-green-100', icon: '✅' },
            'dropped_out': { label: 'Dropped Out', color: 'text-red-600 bg-red-100', icon: '❌' },
            'transferred_out': { label: 'Transferred Out', color: 'text-blue-600 bg-blue-100', icon: '🔄' },
            'suspended': { label: 'Suspended', color: 'text-orange-600 bg-orange-100', icon: '⏸️' },
            'medical_leave': { label: 'Medical Leave', color: 'text-purple-600 bg-purple-100', icon: '🏥' }
        };
    }

    /**
     * Get available reason categories
     */
    getReasonCategories() {
        return {
            'suspended': 'Suspended',
            'medical_reasons': 'Medical Reasons',
            'moving_away': 'Moving Away',
            'others': 'Others'
        };
    }

    /**
     * Get status color class
     */
    getStatusColorClass(status) {
        const statuses = this.getAvailableStatuses();
        return statuses[status]?.color || 'text-gray-600 bg-gray-100';
    }

    /**
     * Get status icon
     */
    getStatusIcon(status) {
        const statuses = this.getAvailableStatuses();
        return statuses[status]?.icon || '❓';
    }

    /**
     * Check if status requires archiving
     */
    requiresArchiving(status) {
        return ['dropped_out', 'transferred_out'].includes(status);
    }

    /**
     * Check if status is reversible
     */
    isReversible(status) {
        return ['suspended', 'medical_leave'].includes(status);
    }

    /**
     * Handle API errors consistently
     */
    handleError(error) {
        if (error.response) {
            const message = error.response.data?.message || 'An error occurred';
            return {
                success: false,
                message,
                status: error.response.status,
                data: error.response.data
            };
        } else if (error.request) {
            return {
                success: false,
                message: 'Network error - please check your connection',
                status: 0
            };
        } else {
            return {
                success: false,
                message: error.message || 'An unexpected error occurred',
                status: 0
            };
        }
    }

    /**
     * Format student name
     */
    formatStudentName(student) {
        if (!student) return 'Unknown Student';
        
        const parts = [];
        if (student.firstName) parts.push(student.firstName);
        if (student.middleName) parts.push(student.middleName);
        if (student.lastName) parts.push(student.lastName);
        
        return parts.length > 0 ? parts.join(' ') : student.name || 'Unknown Student';
    }

    /**
     * Get days since status change
     */
    getDaysSinceStatusChange(statusChangeDate) {
        if (!statusChangeDate) return null;
        
        const changeDate = new Date(statusChangeDate);
        const now = new Date();
        const diffTime = Math.abs(now - changeDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        return diffDays;
    }

    /**
     * Format status change date
     */
    formatStatusChangeDate(statusChangeDate) {
        if (!statusChangeDate) return 'Unknown';
        
        const date = new Date(statusChangeDate);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays === 0) return 'Today';
        if (diffDays === 1) return 'Yesterday';
        if (diffDays <= 7) return `${diffDays} days ago`;
        if (diffDays <= 30) return `${Math.ceil(diffDays / 7)} weeks ago`;
        
        return date.toLocaleDateString();
    }
}

export default new AdminStudentService();
