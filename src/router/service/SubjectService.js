// Subject Service - Handles all operations related to subjects
import api from '@/config/axios';

// Cache settings
let subjectCache = null;
let cacheTimestamp = null;
const CACHE_TTL = 60000; // 1 minute cache lifetime

export const SubjectService = {
    // Get all subjects
    async getSubjects() {
        try {
            // Check if we have a valid cache
            const now = Date.now();
            if (subjectCache && cacheTimestamp && now - cacheTimestamp < CACHE_TTL) {
                console.log('Using cached subjects data');
                return subjectCache;
            }

            console.log('Fetching subjects from API...');
            const response = await api.get('/api/subjects');

            // Update cache
            subjectCache = response.data;
            cacheTimestamp = now;

            return response.data;
        } catch (error) {
            console.error('Error fetching subjects:', error);
            // Return empty array or mock data for development
            return [];
        }
    },

    // Get subject by ID
    async getSubjectById(id) {
        try {
            const response = await api.get(`/api/subjects/${id}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching subject by ID:', error);
            return null;
        }
    },

    // Create a new subject
    async createSubject(subject) {
        try {
            console.log('Creating new subject:', subject);
            const response = await api.post('/api/subjects', subject);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error creating subject:', error);
            throw error;
        }
    },

    // Update a subject
    async updateSubject(id, subject) {
        try {
            console.log('Updating subject:', id, subject);
            const response = await api.put(`/api/subjects/${id}`, subject);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error updating subject:', error);
            throw error;
        }
    },

    // Delete/Archive a subject
    async archiveSubject(id) {
        try {
            console.log('Archiving subject:', id);
            const response = await api.put(`/api/subjects/${id}/archive`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error archiving subject:', error);
            throw error;
        }
    },

    // Restore an archived subject
    async restoreSubject(id) {
        try {
            console.log('Restoring subject:', id);
            const response = await api.put(`/api/subjects/${id}/restore`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error restoring subject:', error);
            throw error;
        }
    },

    // Get subjects by grade
    async getSubjectsByGrade(gradeId) {
        try {
            console.log('Fetching subjects for grade:', gradeId);
            const response = await api.get(`/api/grades/${gradeId}/subjects`);
            return response.data;
        } catch (error) {
            console.error('Error fetching subjects by grade:', error);
            return [];
        }
    },

    // Assign teacher to subject
    async assignTeacher(subjectId, teacherId) {
        try {
            console.log('Assigning teacher to subject:', subjectId, teacherId);
            const response = await api.post(`/api/subjects/${subjectId}/teachers`, { teacher_id: teacherId });
            return response.data;
        } catch (error) {
            console.error('Error assigning teacher to subject:', error);
            throw error;
        }
    },

    // Remove teacher from subject
    async removeTeacher(subjectId, teacherId) {
        try {
            console.log('Removing teacher from subject:', subjectId, teacherId);
            const response = await api.delete(`/api/subjects/${subjectId}/teachers/${teacherId}`);
            return response.data;
        } catch (error) {
            console.error('Error removing teacher from subject:', error);
            throw error;
        }
    },

    // Set subject schedule
    async setSchedule(subjectId, schedule) {
        try {
            console.log('Setting schedule for subject:', subjectId, schedule);
            const response = await api.post(`/api/subjects/${subjectId}/schedule`, schedule);
            return response.data;
        } catch (error) {
            console.error('Error setting subject schedule:', error);
            throw error;
        }
    },

    // Check for schedule conflicts
    async checkScheduleConflicts(gradeId, sectionId, schedule) {
        try {
            console.log('Checking schedule conflicts for:', gradeId, sectionId, schedule);
            const response = await api.post('/api/schedule/check-conflicts', {
                grade_id: gradeId,
                section_id: sectionId,
                schedule: schedule
            });
            return response.data;
        } catch (error) {
            console.error('Error checking schedule conflicts:', error);
            return { hasConflicts: false, conflicts: [] }; // Default to no conflicts in case of error
        }
    },

    // Clear the cache
    clearCache() {
        console.log('Clearing subject cache');
        subjectCache = null;
        cacheTimestamp = null;
    }
};
