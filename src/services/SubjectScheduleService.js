import api from '@/config/axios';

export const SubjectScheduleService = {
    /**
     * Get predefined time slots
     */
    async getTimeSlots() {
        try {
            const response = await api.get('/api/subject-schedules/time-slots');
            return response.data;
        } catch (error) {
            console.error('Error fetching time slots:', error);
            throw error;
        }
    },

    /**
     * Get all schedules (admin view)
     */
    async getAllSchedules(filters = {}) {
        try {
            const response = await api.get('/api/subject-schedules/all', { params: filters });
            return response.data;
        } catch (error) {
            console.error('Error fetching all schedules:', error);
            throw error;
        }
    },

    /**
     * Get schedules for a specific teacher
     */
    async getTeacherSchedules(teacherId) {
        try {
            const response = await api.get(`/api/subject-schedules/teacher/${teacherId}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching teacher schedules:', error);
            throw error;
        }
    },

    /**
     * Get available time slots for a teacher on a specific day
     */
    async getAvailableTimeSlots(params) {
        try {
            const response = await api.get('/api/subject-schedules/available-slots', { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching available time slots:', error);
            throw error;
        }
    },

    /**
     * Check for time conflicts
     */
    async checkTimeConflict(conflictData) {
        try {
            const response = await api.post('/api/subject-schedules/check-conflict', conflictData);
            return response.data;
        } catch (error) {
            console.error('Error checking time conflict:', error);
            throw error;
        }
    },

    /**
     * Save a schedule (create or update)
     */
    async saveSchedule(scheduleData) {
        try {
            const response = await api.post('/api/subject-schedules/save', scheduleData);
            return response.data;
        } catch (error) {
            console.error('Error saving schedule:', error);
            throw error;
        }
    },

    /**
     * Delete a schedule
     */
    async deleteSchedule(scheduleId) {
        try {
            const response = await api.delete(`/api/subject-schedules/${scheduleId}`);
            return response.data;
        } catch (error) {
            console.error('Error deleting schedule:', error);
            throw error;
        }
    },

    /**
     * Format time for display
     */
    formatTime(time) {
        if (!time) return '';
        
        // Convert 24-hour format to 12-hour format
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
        
        return `${displayHour}:${minutes} ${ampm}`;
    },

    /**
     * Format time range for display
     */
    formatTimeRange(startTime, endTime) {
        return `${this.formatTime(startTime)} - ${this.formatTime(endTime)}`;
    },

    /**
     * Get day display name
     */
    getDayDisplayName(day) {
        const dayNames = {
            'Monday': 'Monday',
            'Tuesday': 'Tuesday',
            'Wednesday': 'Wednesday',
            'Thursday': 'Thursday',
            'Friday': 'Friday'
        };
        return dayNames[day] || day;
    },

    /**
     * Get all weekdays
     */
    getWeekdays() {
        return [
            { value: 'Monday', label: 'Monday' },
            { value: 'Tuesday', label: 'Tuesday' },
            { value: 'Wednesday', label: 'Wednesday' },
            { value: 'Thursday', label: 'Thursday' },
            { value: 'Friday', label: 'Friday' }
        ];
    },

    /**
     * Validate schedule data
     */
    validateSchedule(schedule) {
        const errors = [];

        if (!schedule.teacher_id) {
            errors.push('Teacher is required');
        }

        if (!schedule.section_id) {
            errors.push('Section is required');
        }

        if (!schedule.subject_id) {
            errors.push('Subject is required');
        }

        if (!schedule.day) {
            errors.push('Day is required');
        }

        if (!schedule.start_time) {
            errors.push('Start time is required');
        }

        if (!schedule.end_time) {
            errors.push('End time is required');
        }

        if (schedule.start_time && schedule.end_time) {
            if (schedule.start_time >= schedule.end_time) {
                errors.push('End time must be after start time');
            }
        }

        return {
            isValid: errors.length === 0,
            errors
        };
    }
};

export default SubjectScheduleService;
