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
            // Make a copy of the grade to avoid modifying the original
            const gradeData = { ...grade };

            // Check and ensure all required fields are present and properly formatted
            if (!gradeData.code) {
                throw new Error('Grade code is required');
            }

            if (!gradeData.name) {
                throw new Error('Grade name is required');
            }

            // Make sure level field is set and is a string
            if (!gradeData.level && gradeData.level !== 0) {
                // Set level based on code value (defaults to code value if not a recognizable format)
                let level = 0;
                if (gradeData.code.startsWith('K')) {
                    // Kinder grade levels are level 0
                    level = 0;
                } else if (!isNaN(gradeData.code)) {
                    // Regular grade levels (1-12) are themselves
                    level = parseInt(gradeData.code);
                } else if (gradeData.code.startsWith('ALS')) {
                    // ALS grades are set to level 100+
                    const alsLevel = gradeData.code.replace('ALS', '');
                    level = 100 + (!isNaN(alsLevel) ? parseInt(alsLevel) : 0);
                }

                gradeData.level = level.toString();
            }

            // Set display_order if not provided
            if ((!gradeData.display_order && gradeData.display_order !== 0) || isNaN(gradeData.display_order)) {
                // Convert level to integer for ordering
                const levelNum = parseInt(gradeData.level) || 0;
                gradeData.display_order = levelNum;
            }

            // Ensure is_active is boolean
            if (gradeData.is_active === undefined) {
                gradeData.is_active = true;
            }

            // Ensure proper data types for all fields
            gradeData.code = String(gradeData.code).trim();
            gradeData.name = String(gradeData.name).trim();
            gradeData.level = String(gradeData.level).trim();
            gradeData.display_order = Number(gradeData.display_order);
            gradeData.is_active = Boolean(gradeData.is_active);

            // If description is empty, set to null or empty string
            if (!gradeData.description) {
                gradeData.description = '';
            }

            console.log('Creating new grade with validated data:', gradeData);

            // Make the API call with a longer timeout
            const response = await api.post('/api/grades', gradeData, {
                timeout: 30000 // 30 seconds timeout
            });

            console.log('Grade created successfully:', response.data);
            return response.data;
        } catch (error) {
            console.error('Error creating grade:', error);

            // Detailed error logging for debugging
            if (error.response) {
                console.error('Server responded with:', {
                    status: error.response.status,
                    data: error.response.data
                });

                // Get detailed error message from response if available
                if (error.response.data) {
                    if (error.response.data.message) {
                        console.error('Error message:', error.response.data.message);
                    }
                    if (error.response.data.errors) {
                        console.error('Validation errors:', error.response.data.errors);
                    }
                    // Log full response data for more context
                    console.error('Full response data:', JSON.stringify(error.response.data));
                }

                // If it's a server error (500), try to get more detailed message
                if (error.response.status === 500 && error.response.data) {
                    console.error('Server error details:', error.response.data);
                    if (error.response.data.exception) {
                        console.error('Exception:', error.response.data.exception);
                    }
                    if (error.response.data.file) {
                        console.error('Error in file:', error.response.data.file, 'line:', error.response.data.line);
                    }
                    if (error.response.data.trace) {
                        console.error('Error trace (first few items):', error.response.data.trace.slice(0, 3));
                    }
                }
            } else if (error.request) {
                console.error('No response received from server, request was:', error.request);
            } else {
                console.error('Error setting up request:', error.message);
            }

            throw error;
        }
    },

    // Update a grade
    async updateGrade(id, grade) {
        try {
            // Make a copy of the grade to avoid modifying the original
            const gradeData = { ...grade };

            // Check and ensure level field is set properly
            if (!gradeData.level && gradeData.level !== 0) {
                // Set level based on code value (defaults to code value if not a recognizable format)
                let level = 0;
                if (gradeData.code.startsWith('K')) {
                    // Kinder grade levels are level 0
                    level = 0;
                } else if (!isNaN(gradeData.code)) {
                    // Regular grade levels (1-12) are themselves
                    level = parseInt(gradeData.code);
                } else if (gradeData.code.startsWith('ALS')) {
                    // ALS grades are set to level 100+
                    const alsLevel = gradeData.code.replace('ALS', '');
                    level = 100 + (!isNaN(alsLevel) ? parseInt(alsLevel) : 0);
                }

                gradeData.level = level.toString();
            }

            // Ensure proper data types for all fields
            if (gradeData.code) gradeData.code = String(gradeData.code).trim();
            if (gradeData.name) gradeData.name = String(gradeData.name).trim();
            if (gradeData.level) gradeData.level = String(gradeData.level).trim();
            if (gradeData.display_order !== undefined) gradeData.display_order = Number(gradeData.display_order);
            if (gradeData.is_active !== undefined) gradeData.is_active = Boolean(gradeData.is_active);

            console.log('Updating grade:', id, gradeData);
            const response = await api.put(`/api/grades/${id}`, gradeData);
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
