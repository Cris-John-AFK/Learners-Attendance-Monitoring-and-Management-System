import api from '@/config/axios';
import { reactive } from 'vue';

// Create a reactive state
const state = reactive({
    grades: [],
    loading: false,
    error: null
});

export const GradesService = {
    // Get all grades - only from API, no fallback data
    async getGrades() {
        try {
            state.loading = true;
            console.log('Fetching grades from API...');

            const response = await api.get('/api/grades');

            // Process response
            let data;
            if (response.data && Array.isArray(response.data)) {
                data = response.data;
            } else if (response.data) {
                data = [response.data];
            } else {
                console.warn('Invalid API response');
                return [];
            }

            // Update state
            state.grades = data;
            console.log('Successfully fetched grades from API:', data);
            return data;
        } catch (error) {
            console.error('Error fetching grades from API:', error);
            state.error = error;
            // Return empty array instead of fallback data
            return [];
        } finally {
            state.loading = false;
        }
    },

    // Get grade by ID
    async getGradeById(id) {
        try {
            const response = await api.get(`/api/grades/${id}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching grade by ID:', error);
            throw error;
        }
    },

    // Create a new grade
    async createGrade(grade) {
        try {
            console.log('Creating new grade:', grade);
            const response = await api.post('/api/grades', grade);
            console.log('Grade created successfully:', response.data);
            return response.data;
        } catch (error) {
            console.error('Error creating grade:', error);
            if (error.response) {
                console.error('Server responded with:', {
                    status: error.response.status,
                    data: error.response.data
                });

                // If the error is 422 but the grade was created, log this unusual situation
                if (error.response.status === 422) {
                    console.warn('Received 422 error but grade might have been created');
                }
            }
            throw error;
        }
    },

    // Update a grade
    async updateGrade(id, grade) {
        try {
            console.log('Updating grade:', id, grade);
            const response = await api.put(`/api/grades/${id}`, grade);
            return response.data;
        } catch (error) {
            console.error('Error updating grade:', error);
            throw error;
        }
    },

    // Delete a grade
    async deleteGrade(id) {
        try {
            console.log('Deleting grade:', id);
            const response = await api.delete(`/api/grades/${id}`);
            return response.data;
        } catch (error) {
            console.error('Error deleting grade:', error);
            throw error;
        }
    },

    // Toggle grade status
    async toggleGradeStatus(id) {
        try {
            console.log('Toggling grade status:', id);
            const response = await api.patch(`/api/grades/${id}/toggle-status`);
            return response.data;
        } catch (error) {
            console.error('Error toggling grade status:', error);
            throw error;
        }
    },

    // Get sections for a grade
    async getSectionsByGrade(gradeId) {
        try {
            console.log('Fetching sections for grade:', gradeId);
            const response = await api.get(`/api/grades/${gradeId}/sections`);
            return response.data;
        } catch (error) {
            console.error('Error fetching sections for grade:', error);
            throw error;
        }
    },

    // Create a section in a grade
    async createSection(gradeId, section) {
        try {
            console.log('Creating section in grade:', gradeId, section);
            const response = await api.post(`/api/grades/${gradeId}/sections`, section);
            return response.data;
        } catch (error) {
            console.error('Error creating section:', error);
            throw error;
        }
    },

    // Update a section
    async updateSection(gradeId, sectionId, section) {
        try {
            console.log('Updating section:', gradeId, sectionId, section);
            const response = await api.put(`/api/grades/${gradeId}/sections/${sectionId}`, section);
            return response.data;
        } catch (error) {
            console.error('Error updating section:', error);
            throw error;
        }
    },

    // Delete/Archive a section
    async archiveSection(gradeId, sectionId) {
        try {
            console.log('Archiving section:', gradeId, sectionId);
            const response = await api.put(`/api/grades/${gradeId}/sections/${sectionId}/archive`);
            return response.data;
        } catch (error) {
            console.error('Error archiving section:', error);
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
            console.error('Error fetching subjects for grade:', error);
            throw error;
        }
    },

    // Clear cache
    clearCache() {
        console.log('Clearing grade cache');
        gradeCache = null;
        cacheTimestamp = null;
        localStorage.removeItem('gradeData');
        localStorage.removeItem('gradeCacheTimestamp');
    }
};

// Add an export alias for backward compatibility
export const GradeService = GradesService;
