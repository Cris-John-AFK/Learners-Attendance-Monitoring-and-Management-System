import axios from 'axios';

// Base URL for the API
const API_URL = 'http://localhost:8000/api';

// Cache settings
let gradeCache = null;
let cacheTimestamp = null;
const CACHE_TTL = 60000; // 1 minute cache lifetime

export const GradeService = {
    // Get all grades
    async getGrades() {
        try {
            // Check if we have a valid cache
            const now = Date.now();
            if (gradeCache && cacheTimestamp && now - cacheTimestamp < CACHE_TTL) {
                console.log('Using cached grade data');
                return gradeCache;
            }

            console.log('Fetching grades from API...');
            const response = await axios.get(`${API_URL}/grades`);

            // Update cache
            gradeCache = response.data;
            cacheTimestamp = now;

            return response.data;
        } catch (error) {
            console.error('Error fetching grades:', error);
            throw error;
        }
    },

    // Get grade by ID
    async getGradeById(id) {
        try {
            const response = await axios.get(`${API_URL}/grades/${id}`);
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
            const response = await axios.post(`${API_URL}/grades`, grade);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error creating grade:', error);
            throw error;
        }
    },

    // Update a grade
    async updateGrade(id, grade) {
        try {
            console.log('Updating grade:', id, grade);
            const response = await axios.put(`${API_URL}/grades/${id}`, grade);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error updating grade:', error);
            throw error;
        }
    },

    // Delete/Archive a grade
    async archiveGrade(id) {
        try {
            console.log('Archiving grade:', id);
            const response = await axios.put(`${API_URL}/grades/${id}/archive`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error archiving grade:', error);
            throw error;
        }
    },

    // Restore an archived grade
    async restoreGrade(id) {
        try {
            console.log('Restoring grade:', id);
            const response = await axios.put(`${API_URL}/grades/${id}/restore`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error restoring grade:', error);
            throw error;
        }
    },

    // Get sections for a grade
    async getSectionsByGrade(gradeId) {
        try {
            console.log('Fetching sections for grade:', gradeId);
            const response = await axios.get(`${API_URL}/grades/${gradeId}/sections`);
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
            const response = await axios.post(`${API_URL}/grades/${gradeId}/sections`, section);
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
            const response = await axios.put(`${API_URL}/grades/${gradeId}/sections/${sectionId}`, section);
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
            const response = await axios.put(`${API_URL}/grades/${gradeId}/sections/${sectionId}/archive`);
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
            const response = await axios.get(`${API_URL}/grades/${gradeId}/subjects`);
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
    }
};

// Add an export alias after the main export to make both names available
export const GradesService = GradeService;
