import axios from 'axios';

// Base URL for the API
const API_URL = 'http://localhost:8000/api';

// Create an in-memory cache for performance
let gradesCache = null;
let cacheTimestamp = null;
const CACHE_TTL = 60000; // 1 minute cache lifetime

export const GradesService = {
    // Get all grades
    async getGrades() {
        try {
            // Check if we have a valid cache
            const now = Date.now();
            if (gradesCache && cacheTimestamp && now - cacheTimestamp < CACHE_TTL) {
                console.log('Using cached grades data, age:', Math.round((now - cacheTimestamp) / 1000), 'seconds');
                return gradesCache;
            }

            console.log('Cache expired or not available, fetching grades from API...');
            const response = await axios.get(`${API_URL}/grades`);
            console.log('API response received:', response.status, 'with', response.data.length, 'grades');

            // Update cache
            gradesCache = response.data;
            cacheTimestamp = now;

            return response.data;
        } catch (error) {
            console.error('Error fetching grades:', error);
            if (error.response) {
                console.error('Server responded with error:', error.response.status, error.response.data);
            } else if (error.request) {
                console.error('No response received from server');
            } else {
                console.error('Error setting up request:', error.message);
            }

            // Return empty array for error case
            return [];
        }
    },

    // Get only active grades
    async getActiveGrades() {
        try {
            const response = await axios.get(`${API_URL}/grades/active`);
            return response.data;
        } catch (error) {
            console.error('Error fetching active grades:', error);
            return [];
        }
    },

    // Get a specific grade by ID
    async getGradeById(id) {
        try {
            const response = await axios.get(`${API_URL}/grades/${id}`);
            return response.data;
        } catch (error) {
            console.error(`Error fetching grade with id ${id}:`, error);
            return null;
        }
    },

    // Create a new grade
    async createGrade(gradeData) {
        try {
            const response = await axios.post(`${API_URL}/grades`, gradeData);
            // Invalidate cache
            this.clearCache();
            return response.data;
        } catch (error) {
            console.error('Error creating grade:', error);
            throw error;
        }
    },

    // Update an existing grade
    async updateGrade(id, gradeData) {
        try {
            const response = await axios.put(`${API_URL}/grades/${id}`, gradeData);
            // Invalidate cache
            this.clearCache();
            return response.data;
        } catch (error) {
            console.error(`Error updating grade with id ${id}:`, error);
            throw error;
        }
    },

    // Delete a grade
    async deleteGrade(id) {
        try {
            await axios.delete(`${API_URL}/grades/${id}`);
            // Invalidate cache
            this.clearCache();
            return true;
        } catch (error) {
            console.error(`Error deleting grade with id ${id}:`, error);
            throw error;
        }
    },

    // Toggle grade active status
    async toggleGradeStatus(id) {
        try {
            const response = await axios.patch(`${API_URL}/grades/${id}/toggle-status`);
            // Invalidate cache
            this.clearCache();
            return response.data;
        } catch (error) {
            console.error(`Error toggling status for grade with id ${id}:`, error);
            throw error;
        }
    },

    // Clear cache method
    clearCache() {
        console.log('Clearing grades cache');
        gradesCache = null;
        cacheTimestamp = null;
        return true;
    }
};
