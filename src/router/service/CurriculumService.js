import axios from 'axios';
import { reactive } from 'vue';

// Curriculum State Management
const state = reactive({
    curriculums: [],
    currentCurriculum: null,
    loading: false,
    error: null
});

// Base URL for the API
const API_URL = 'http://localhost:8000/api';

// Cache settings
let curriculumCache = null;
let cacheTimestamp = null;
const CACHE_TTL = 60000; // 1 minute cache lifetime

export const CurriculumService = {
    // Get all curriculums
    async getCurriculums() {
        try {
            // Check if we have a valid cache
            const now = Date.now();
            if (curriculumCache && cacheTimestamp && now - cacheTimestamp < CACHE_TTL) {
                console.log('Using cached curriculum data');
                return curriculumCache;
            }

            console.log('Fetching curriculum data from API...');
            const response = await axios.get(`${API_URL}/curriculums`);
            console.log('Raw API response:', response);

            // Check if we have any data
            if (!response.data || !Array.isArray(response.data)) {
                console.error('Invalid response from API - expected array:', response.data);
                return [];
            }

            // Normalize data to ensure yearRange property exists and status is properly set
            const normalizedData = response.data.map((curriculum) => {
                // Deep clone to avoid mutation issues
                const normalizedCurriculum = { ...curriculum };

                // If yearRange is missing but start_year/end_year exist, create it
                if (!normalizedCurriculum.yearRange && (normalizedCurriculum.start_year || normalizedCurriculum.end_year)) {
                    normalizedCurriculum.yearRange = {
                        start: normalizedCurriculum.start_year,
                        end: normalizedCurriculum.end_year
                    };
                }

                // If yearRange doesn't exist at all, create an empty one
                if (!normalizedCurriculum.yearRange) {
                    normalizedCurriculum.yearRange = { start: '', end: '' };
                }

                // Make sure status is set properly based on is_active field
                if (!normalizedCurriculum.status) {
                    // Set status based on is_active flag
                    normalizedCurriculum.status = normalizedCurriculum.is_active === true || normalizedCurriculum.is_active === 1 ? 'Active' : 'Draft';
                    console.log(`Set status to ${normalizedCurriculum.status} for curriculum ${normalizedCurriculum.id} based on is_active=${normalizedCurriculum.is_active}`);
                }

                return normalizedCurriculum;
            });

            console.log('Normalized curriculum data:', normalizedData);

            // Update cache
            curriculumCache = normalizedData;
            cacheTimestamp = now;

            return normalizedData;
        } catch (error) {
            console.error('Error fetching curriculums:', error);
            throw error;
        }
    },

    // Get curriculum by ID
    async getCurriculumById(id) {
        try {
            const response = await axios.get(`${API_URL}/curriculums/${id}`);

            // Normalize data to ensure yearRange property exists
            const curriculum = response.data;
            if (!curriculum.yearRange && (curriculum.start_year || curriculum.end_year)) {
                curriculum.yearRange = {
                    start: curriculum.start_year,
                    end: curriculum.end_year
                };
            }
            if (!curriculum.yearRange) {
                curriculum.yearRange = { start: '', end: '' };
            }

            return curriculum;
        } catch (error) {
            console.error('Error fetching curriculum by ID:', error);
            throw error;
        }
    },

    // Create a new curriculum
    async createCurriculum(curriculum) {
        try {
            console.log('Creating new curriculum:', curriculum);
            const response = await axios.post(`${API_URL}/curriculums`, curriculum);

            // Normalize the response data
            const newCurriculum = response.data;
            if (!newCurriculum.yearRange && (newCurriculum.start_year || newCurriculum.end_year)) {
                newCurriculum.yearRange = {
                    start: newCurriculum.start_year,
                    end: newCurriculum.end_year
                };
            }
            if (!newCurriculum.yearRange) {
                newCurriculum.yearRange = { start: '', end: '' };
            }

            // Clear cache
            this.clearCache();

            return newCurriculum;
        } catch (error) {
            console.error('Error creating curriculum:', error);
            throw error;
        }
    },

    // Update a curriculum
    async updateCurriculum(id, curriculum) {
        try {
            console.log('Updating curriculum:', id, curriculum);
            const response = await axios.put(`${API_URL}/curriculums/${id}`, curriculum);

            // Clear cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error updating curriculum:', error);
            throw error;
        }
    },

    // Archive a curriculum
    async archiveCurriculum(id) {
        try {
            console.log('Archiving curriculum:', id);
            const response = await axios.put(`${API_URL}/curriculums/${id}/archive`);

            // Clear cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error archiving curriculum:', error);
            throw error;
        }
    },

    // Restore a curriculum
    async restoreCurriculum(id) {
        try {
            console.log('Restoring curriculum:', id);
            const response = await axios.put(`${API_URL}/curriculums/${id}/restore`);

            // Clear cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error restoring curriculum:', error);
            throw error;
        }
    },

    // Activate a curriculum (set as active)
    async activateCurriculum(id) {
        try {
            console.log('Activating curriculum:', id);
            const response = await axios.put(`${API_URL}/curriculums/${id}/activate`);

            // Clear cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error activating curriculum:', error);
            throw error;
        }
    },

    // Get grades for a curriculum
    async getGradesByCurriculum(curriculumId) {
        try {
            console.log('Fetching grades for curriculum:', curriculumId);
            const response = await axios.get(`${API_URL}/curriculums/${curriculumId}/grades`);
            return response.data;
        } catch (error) {
            console.error('Error fetching grades for curriculum:', error);
            throw error;
        }
    },

    // Add grade to curriculum
    async addGradeToCurriculum(curriculumId, grade) {
        try {
            console.log('Adding grade to curriculum:', curriculumId, grade);
            const response = await axios.post(`${API_URL}/curriculums/${curriculumId}/grades`, grade);
            return response.data;
        } catch (error) {
            console.error('Error adding grade to curriculum:', error);
            throw error;
        }
    },

    // Remove grade from curriculum
    async removeGradeFromCurriculum(curriculumId, gradeId) {
        try {
            console.log('Removing grade from curriculum:', curriculumId, gradeId);
            const response = await axios.delete(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}`);
            return response.data;
        } catch (error) {
            console.error('Error removing grade from curriculum:', error);
            throw error;
        }
    },

    // Get sections by grade for a curriculum
    async getSectionsByGrade(curriculumId, gradeId) {
        try {
            console.log('Fetching sections for grade:', curriculumId, gradeId);

            // Validate parameters
            if (!curriculumId || !gradeId) {
                console.error('Missing required parameters:', { curriculumId, gradeId });
                throw new Error('Missing required parameters for fetching sections');
            }

            // First, verify the curriculum-grade relationship exists
            let curriculumGrade;
            try {
                curriculumGrade = await this.getCurriculumGrade(curriculumId, gradeId);
                if (!curriculumGrade || (!curriculumGrade.id && !curriculumGrade._fallback)) {
                    throw new Error('Invalid curriculum-grade relationship');
                }
            } catch (error) {
                console.error('Error verifying curriculum-grade relationship:', error);
                throw new Error('Failed to verify curriculum-grade relationship');
            }

            // Try the direct sections endpoint first
            try {
                const response = await axios.get(`${API_URL}/sections`, {
                    params: {
                        curriculum_id: curriculumId,
                        grade_id: gradeId
                    }
                });

                if (response.data && Array.isArray(response.data)) {
                    console.log('Successfully fetched sections:', response.data.length);
                    return response.data;
                }
            } catch (directError) {
                console.warn('Direct sections endpoint failed:', directError.message);
            }

            // Try the nested endpoint as fallback
            try {
                const response = await axios.get(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/sections`);
                if (response.data && Array.isArray(response.data)) {
                    console.log('Successfully fetched sections from nested endpoint:', response.data.length);
                    return response.data;
                }
            } catch (nestedError) {
                console.warn('Nested sections endpoint failed:', nestedError.message);
            }

            // If both attempts fail, return empty array with warning
            console.warn('All section fetch attempts failed, returning empty array');
            return [];
        } catch (error) {
            console.error('Error in getSectionsByGrade:', error);
            throw error;
        }
    },

    // Get curriculum grade relationship
    async getCurriculumGrade(curriculumId, gradeId) {
        try {
            console.log('Fetching curriculum grade relationship:', curriculumId, gradeId);

            // Try the relationship endpoint first
            try {
                const response = await axios.get(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/relationship`);
                return response.data;
            } catch (error) {
                console.log('Relationship endpoint not available, trying curriculum_grade table directly');
                // Try direct query to curriculum_grade table as fallback
                try {
                    const response = await axios.get(`${API_URL}/curriculum_grade`, {
                        params: { curriculum_id: curriculumId, grade_id: gradeId }
                    });

                    // If we got data back, return it
                    if (response.data) {
                        return response.data;
                    }
                } catch (innerError) {
                    console.log('Direct curriculum_grade query failed too:', innerError.message);
                }

                // Last attempt - try the show endpoint
                try {
                    const response = await axios.get(`${API_URL}/curriculum-grade/${curriculumId}/${gradeId}`);
                    if (response.data) {
                        return response.data;
                    }
                } catch (finalError) {
                    console.log('All endpoints failed, returning fallback object');
                }

                // Last resort: create a fallback object
                return {
                    id: null,
                    curriculum_id: parseInt(curriculumId),
                    grade_id: parseInt(gradeId),
                    _fallback: true
                };
            }
        } catch (error) {
            console.error('Error fetching curriculum grade relationship:', error);
            // If all else fails, create a fallback
            return {
                id: null,
                curriculum_id: parseInt(curriculumId),
                grade_id: parseInt(gradeId),
                _fallback: true
            };
        }
    },

    // Add section directly
    async addSection(sectionData) {
        try {
            console.log('Adding section:', sectionData);

            // Ensure we have curriculum_grade_id or generate it
            if (!sectionData.curriculum_grade_id && sectionData.curriculum_id && sectionData.grade_id) {
                try {
                    // Try to get the curriculum_grade relationship
                    const curriculumGrade = await this.getCurriculumGrade(sectionData.curriculum_id, sectionData.grade_id);

                    if (curriculumGrade && curriculumGrade.id) {
                        sectionData.curriculum_grade_id = curriculumGrade.id;
                    }
                } catch (error) {
                    console.warn('Could not get curriculum_grade_id for section creation');
                }
            }

            // Try to add via direct sections endpoint
            try {
                const response = await axios.post(`${API_URL}/sections`, sectionData);
                return response.data;
            } catch (directError) {
                console.log('Direct section creation failed, trying nested endpoint');

                // If direct creation fails, try the nested endpoint
                if (sectionData.curriculum_id && sectionData.grade_id) {
                    const response = await axios.post(`${API_URL}/curriculums/${sectionData.curriculum_id}/grades/${sectionData.grade_id}/sections`, sectionData);
                    return response.data;
                } else {
                    // If we don't have curriculum_id and grade_id, we can't use nested endpoint
                    throw directError;
                }
            }
        } catch (error) {
            console.error('Error adding section:', error);
            throw error;
        }
    },

    // Add section to grade
    async addSectionToGrade(curriculumId, gradeId, section) {
        try {
            console.log('Adding section to grade:', curriculumId, gradeId, section);
            const response = await axios.post(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/sections`, section);
            return response.data;
        } catch (error) {
            console.error('Error adding section to grade:', error);
            throw error;
        }
    },

    // Remove section
    async removeSection(curriculumId, gradeId, sectionId) {
        try {
            console.log('Removing section:', curriculumId, gradeId, sectionId);

            // Try nested endpoint first
            try {
                const response = await axios.delete(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}`);
                return response.data;
            } catch (nestedError) {
                console.log('Nested section delete failed, trying direct endpoint');

                // If nested endpoint fails, try direct endpoint
                const response = await axios.delete(`${API_URL}/sections/${sectionId}`);
                return response.data;
            }
        } catch (error) {
            console.error('Error removing section:', error);
            throw error;
        }
    },

    // Get subjects for a section
    async getSubjectsBySection(curriculumId, gradeId, sectionId) {
        try {
            console.log('Fetching subjects for section:', curriculumId, gradeId, sectionId);

            // Try nested endpoint first
            try {
                const response = await axios.get(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/subjects`);
                if (response.data && Array.isArray(response.data)) {
                    console.log('Found subjects using nested endpoint');
                    return response.data;
                }
            } catch (nestedError) {
                console.log('Nested subjects endpoint failed, trying direct subjects endpoint');
            }

            // Try direct section subjects endpoint
            try {
                const response = await axios.get(`${API_URL}/sections/${sectionId}/subjects`);
                if (response.data && Array.isArray(response.data)) {
                    console.log('Found subjects using direct endpoint');
                    return response.data;
                }
            } catch (directError) {
                console.log('Direct subjects endpoint failed, trying all subjects');
            }

            // Try to get all subjects and filter
            try {
                const response = await axios.get(`${API_URL}/subjects`);

                // If successful, try to filter by section id
                if (response.data && Array.isArray(response.data)) {
                    console.log('Got all subjects, filtering for section', sectionId);

                    // Filter for this section if possible
                    const filtered = response.data.filter((subject) => subject.section_id === parseInt(sectionId) || (subject.sections && subject.sections.includes(parseInt(sectionId))));

                    // If we found some, return them
                    if (filtered.length > 0) {
                        console.log('Found subjects by filtering', filtered.length);
                        return filtered;
                    }

                    // Otherwise return empty array to prevent errors
                    console.log('No subjects found for this section after filtering');
                    return [];
                }
            } catch (allSubjectsError) {
                console.log('Error fetching all subjects:', allSubjectsError.message);
            }

            // Last resort: return empty array
            console.log('All attempts failed, returning empty array');
            return [];
        } catch (error) {
            console.error('Error fetching subjects for section:', error);
            // Return empty array if all else fails
            return [];
        }
    },

    // Add subject to section
    async addSubjectToSection(curriculumId, gradeId, sectionId, subject) {
        try {
            console.log('Adding subject to section:', curriculumId, gradeId, sectionId, subject);
            const response = await axios.post(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/subjects`, subject);
            return response.data;
        } catch (error) {
            console.error('Error adding subject to section:', error);
            throw error;
        }
    },

    // Remove subject from section
    async removeSubjectFromSection(curriculumId, gradeId, sectionId, subjectId) {
        try {
            console.log('Removing subject from section:', curriculumId, gradeId, sectionId, subjectId);
            const response = await axios.delete(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/subjects/${subjectId}`);
            return response.data;
        } catch (error) {
            console.error('Error removing subject from section:', error);
            throw error;
        }
    },

    // Assign teacher to section (homeroom teacher)
    async assignTeacherToSection(curriculumId, gradeId, sectionId, teacherData) {
        try {
            console.log('Assigning teacher to section:', curriculumId, gradeId, sectionId, teacherData);
            const response = await axios.post(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/teacher`, teacherData);
            return response.data;
        } catch (error) {
            console.error('Error assigning teacher to section:', error);
            throw error;
        }
    },

    // Assign teacher to subject
    async assignTeacherToSubject(curriculumId, gradeId, sectionId, subjectId, teacherData) {
        try {
            console.log('Assigning teacher to subject:', curriculumId, gradeId, sectionId, subjectId, teacherData);
            const response = await axios.post(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/subjects/${subjectId}/teacher`, teacherData);
            return response.data;
        } catch (error) {
            console.error('Error assigning teacher to subject:', error);
            throw error;
        }
    },

    // Set subject schedule
    async setSubjectSchedule(curriculumId, gradeId, sectionId, subjectId, schedule) {
        try {
            console.log('Setting subject schedule:', curriculumId, gradeId, sectionId, subjectId, schedule);
            const response = await axios.post(`${API_URL}/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/subjects/${subjectId}/schedule`, schedule);
            return response.data;
        } catch (error) {
            console.error('Error setting subject schedule:', error);
            throw error;
        }
    },

    // Check for teacher schedule conflicts
    async checkTeacherScheduleConflicts(teacherId, schedule) {
        try {
            console.log('Checking teacher schedule conflicts:', teacherId, schedule);
            const response = await axios.post(`${API_URL}/teachers/${teacherId}/schedule/check-conflicts`, schedule);
            return response.data;
        } catch (error) {
            console.error('Error checking teacher schedule conflicts:', error);
            throw error;
        }
    },

    // Get sections by curriculum grade
    async getSectionsByCurriculumGrade(curriculumId, gradeId) {
        try {
            console.log('Fetching sections by curriculum grade:', curriculumId, gradeId);

            // Try to get the curriculum_grade relationship
            const curriculumGrade = await this.getCurriculumGrade(curriculumId, gradeId);

            // If we have a curriculum_grade id, try to get sections by that
            if (curriculumGrade && curriculumGrade.id) {
                try {
                    const response = await axios.get(`${API_URL}/sections`, {
                        params: { curriculum_grade_id: curriculumGrade.id }
                    });
                    return response.data;
                } catch (error) {
                    console.log('Failed to get sections by curriculum_grade_id, trying nested endpoint');
                }
            }

            // Fallback to getSectionsByGrade
            return await this.getSectionsByGrade(curriculumId, gradeId);
        } catch (error) {
            console.error('Error fetching sections by curriculum grade:', error);
            return [];
        }
    },

    // Clear cache
    clearCache() {
        console.log('Clearing curriculum cache');
        curriculumCache = null;
        cacheTimestamp = null;
    }
};
