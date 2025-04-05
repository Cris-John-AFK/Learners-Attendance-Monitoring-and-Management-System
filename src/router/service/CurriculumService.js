import api from '@/config/axios';
import { reactive } from 'vue';

// Curriculum State Management
const state = reactive({
    curriculums: [],
    currentCurriculum: null,
    loading: false,
    error: null
});

// Cache settings
let curriculumCache = null;
let cacheTimestamp = null;
let sectionCache = new Map();
let gradeCache = new Map();
const CACHE_TTL = 300000; // 5 minutes cache lifetime

// Default grades data for fallback
const defaultGrades = [
    { id: 'K1', name: 'Kinder 1', level: 0, status: 'Active' },
    { id: 'K2', name: 'Kinder 2', level: 0, status: 'Active' },
    { id: '1', name: 'Grade 1', level: 1, status: 'Active' },
    { id: '2', name: 'Grade 2', level: 2, status: 'Active' },
    { id: '3', name: 'Grade 3', level: 3, status: 'Active' },
    { id: '4', name: 'Grade 4', level: 4, status: 'Active' },
    { id: '5', name: 'Grade 5', level: 5, status: 'Active' },
    { id: '6', name: 'Grade 6', level: 6, status: 'Active' }
];

// Default sections data for fallback
const defaultSections = {
    K1: [
        { id: 'K1_A', name: 'A', capacity: 25, is_active: true },
        { id: 'K1_B', name: 'B', capacity: 25, is_active: true },
        { id: 'K1_C', name: 'C', capacity: 25, is_active: true }
    ],
    K2: [
        { id: 'K2_A', name: 'A', capacity: 25, is_active: true },
        { id: 'K2_B', name: 'B', capacity: 25, is_active: true }
    ],
    G1: [
        { id: 'G1_A', name: 'A', capacity: 25, is_active: true },
        { id: 'G1_B', name: 'B', capacity: 25, is_active: true }
    ],
    G2: [
        { id: 'G2_A', name: 'A', capacity: 25, is_active: true },
        { id: 'G2_B', name: 'B', capacity: 25, is_active: true }
    ],
    G3: [
        { id: 'G3_A', name: 'A', capacity: 25, is_active: true },
        { id: 'G3_B', name: 'B', capacity: 25, is_active: true }
    ],
    G4: [
        { id: 'G4_A', name: 'A', capacity: 25, is_active: true },
        { id: 'G4_B', name: 'B', capacity: 25, is_active: true }
    ],
    G5: [
        { id: 'G5_A', name: 'A', capacity: 25, is_active: true },
        { id: 'G5_B', name: 'B', capacity: 25, is_active: true }
    ],
    G6: [
        { id: 'G6_A', name: 'A', capacity: 25, is_active: true },
        { id: 'G6_B', name: 'B', capacity: 25, is_active: true }
    ]
};

export const CurriculumService = {
    // Get all curriculums
    async getCurriculums() {
        const now = Date.now(); // Define now at the start of the function

        // First try to get from memory cache
        if (curriculumCache && cacheTimestamp && now - cacheTimestamp < CACHE_TTL) {
            console.log('Using memory cached curriculum data');
            return curriculumCache;
        }

        // Then try localStorage
        try {
            const cachedData = localStorage.getItem('curriculumData');
            const cacheTime = parseInt(localStorage.getItem('curriculumCacheTimestamp'));

            if (cachedData && cacheTime && now - cacheTime < CACHE_TTL) {
                console.log('Using localStorage cached data');
                const data = JSON.parse(cachedData);
                curriculumCache = data;
                cacheTimestamp = cacheTime;
                return data;
            }
        } catch (storageError) {
            console.warn('Could not retrieve curriculum data from localStorage:', storageError);
        }

        // If no cache, fetch from API
        try {
            console.log('Fetching curriculum data from API...');
            const response = await api.get('/api/curriculums', {
                timeout: 30000, // Increase timeout to 30 seconds
                headers: {
                    'Cache-Control': 'max-age=300', // Allow browser caching for 5 minutes
                    Pragma: 'cache'
                },
                params: {
                    fields: 'id,name,status,is_active,start_year,end_year' // Only fetch needed fields
                }
            });

            if (!response.data) {
                console.error('No data received from API');
                return [];
            }

            // Ensure we have an array and normalize it immediately
            const data = Array.isArray(response.data) ? response.data : [response.data];

            // Update both memory and localStorage cache
            curriculumCache = data;
            cacheTimestamp = now;

            try {
                localStorage.setItem('curriculumData', JSON.stringify(data));
                localStorage.setItem('curriculumCacheTimestamp', now.toString());
            } catch (storageError) {
                console.warn('Could not store curriculum data in localStorage:', storageError);
            }

            return data;
        } catch (error) {
            console.error('Error fetching curriculums:', error);
            return [];
        }
    },

    // Get curriculum by ID
    async getCurriculumById(id) {
        try {
            const response = await api.get(`/api/curriculums/${id}`);

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
            const response = await api.post('/api/curriculums', curriculum);

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
            const response = await api.put(`/api/curriculums/${id}`, curriculum);

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
            const response = await api.put(`/api/curriculums/${id}/archive`);

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
            const response = await api.put(`/api/curriculums/${id}/restore`);

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
            const response = await api.put(`/api/curriculums/${id}/activate`);

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
        if (!curriculumId) {
            console.error('Missing required curriculum ID');
            return [];
        }

        try {
            console.log('Fetching grades for curriculum ID:', curriculumId);

            // Direct API call with no fallbacks
            const response = await api.get(`/api/curriculums/${curriculumId}/grades`);

            if (!response.data) {
                console.log('No grades found for this curriculum');
                return [];
            }

            // Process and return the grades
            const grades = Array.isArray(response.data) ? response.data : [response.data];
            console.log(`Retrieved ${grades.length} grades for curriculum ${curriculumId}`);

            return grades;
        } catch (error) {
            console.error('Error fetching grades for curriculum:', error);
            return []; // Return empty array on error, no fallbacks
        }
    },

    // Add grade to curriculum
    async addGradeToCurriculum(curriculumId, grade) {
        try {
            console.log('Adding grade to curriculum:', curriculumId, grade);
            const response = await api.post(`/api/curriculums/${curriculumId}/grades`, grade);
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
            const response = await api.delete(`/api/curriculums/${curriculumId}/grades/${gradeId}`);
            return response.data;
        } catch (error) {
            console.error('Error removing grade from curriculum:', error);
            throw error;
        }
    },

    // Get sections by grade with improved caching and fallback mechanisms
    async getSectionsByGrade(curriculumId, gradeId) {
        if (!curriculumId || !gradeId) {
            console.warn('Missing required parameters for getSectionsByGrade');
            return [];
        }

        const now = Date.now();
        const cacheKey = `sections_${curriculumId}_${gradeId}`;

        try {
            // Clear any existing cached sections to force fresh data
            sectionCache.delete(cacheKey);
            try {
                localStorage.removeItem(cacheKey);
                localStorage.removeItem(`${cacheKey}_timestamp`);
            } catch (e) {
                console.warn('Could not clear localStorage cache:', e);
            }

            console.log('Fetching sections from API...');

            // Make API call with increased timeout
            const response = await api.get(`/api/sections/grade/${gradeId}`, {
                timeout: 15000, // Increased to 15 seconds
                params: {
                    curriculum_id: curriculumId,
                    force_fresh: true,
                    timestamp: Date.now() // Cache busting
                },
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    Pragma: 'no-cache',
                    Expires: '0'
                }
            });

            if (response.data) {
                const sections = Array.isArray(response.data) ? response.data : response.data.data && Array.isArray(response.data.data) ? response.data.data : [response.data];

                console.log(`Retrieved ${sections.length} sections from database`);

                // Ensure each section has a valid numeric ID
                const validSections = sections.filter((section) => section && section.id);

                if (validSections.length === 0) {
                    console.warn('No valid sections returned from API');
                    return [];
                }

                // Update both memory and localStorage cache
                sectionCache.set(cacheKey, { data: validSections, timestamp: now });
                try {
                    localStorage.setItem(cacheKey, JSON.stringify(validSections));
                    localStorage.setItem(`${cacheKey}_timestamp`, now.toString());
                } catch (storageError) {
                    console.warn('Could not store section data in localStorage:', storageError);
                }

                return validSections;
            } else {
                console.warn('API returned empty data for sections');
                return [];
            }
        } catch (error) {
            console.error('Error in getSectionsByGrade:', error);
            return [];
        }
    },

    // Helper method to get fallback sections - DISABLED
    getFallbackSections(gradeId) {
        console.warn('Fallback sections have been disabled');
        return [];
    },

    // Helper method to cache sections
    cacheSections(curriculumId, gradeId, sections) {
        const memoryCacheKey = `sections_${curriculumId}_${gradeId}`;
        const localStorageKey = `sections_${curriculumId}_${gradeId}`;
        const timestamp = Date.now();

        // Update memory cache
        sectionCache.set(memoryCacheKey, {
            data: sections,
            timestamp
        });

        // Update localStorage cache
        try {
            localStorage.setItem(
                localStorageKey,
                JSON.stringify({
                    data: sections,
                    timestamp
                })
            );
        } catch (cacheError) {
            console.warn('Cache write error:', cacheError);
        }
    },

    // Get curriculum grade relationship
    async getCurriculumGrade(curriculumId, gradeId) {
        const cacheKey = `curriculum_grade_${curriculumId}_${gradeId}`;

        try {
            // Check memory cache first (fastest)
            const memoryCached = curriculumCache?.grades?.[curriculumId]?.find((g) => g.id === gradeId);
            if (memoryCached) {
                console.log('Using memory cached curriculum-grade relationship');
                return memoryCached;
            }

            // Then check localStorage
            try {
                const cachedData = localStorage.getItem(cacheKey);
                if (cachedData) {
                    const { data, timestamp } = JSON.parse(cachedData);
                    if (Date.now() - timestamp < CACHE_TTL) {
                        console.log('Using localStorage cached curriculum-grade relationship');
                        return data;
                    }
                }
            } catch (cacheError) {
                console.warn('Cache read error:', cacheError);
            }

            // Try direct query to curriculum_grade table with short timeout
            try {
                const response = await api.get('/api/curriculum_grade', {
                    timeout: 5000, // Short timeout to quickly fall back
                    params: {
                        curriculum_id: curriculumId,
                        grade_id: gradeId
                    }
                });

                if (response.data) {
                    // Cache the successful response
                    this.cacheCurriculumGrade(curriculumId, gradeId, response.data);
                    return response.data;
                }
            } catch (error) {
                console.warn('Direct curriculum_grade query failed:', error.message);
            }

            // Try expired cache before creating fallback
            try {
                const cachedData = localStorage.getItem(cacheKey);
                if (cachedData) {
                    const { data } = JSON.parse(cachedData);
                    console.log('Using expired cache data for curriculum-grade relationship');
                    return data;
                }
            } catch (cacheError) {
                console.warn('Expired cache read error:', cacheError);
            }

            // Create and return fallback object
            const fallback = {
                id: `${curriculumId}_${gradeId}`,
                curriculum_id: parseInt(curriculumId),
                grade_id: parseInt(gradeId),
                _fallback: true
            };

            // Cache the fallback
            this.cacheCurriculumGrade(curriculumId, gradeId, fallback);
            return fallback;
        } catch (error) {
            console.error('Error in getCurriculumGrade:', error);
            return {
                id: `${curriculumId}_${gradeId}`,
                curriculum_id: parseInt(curriculumId),
                grade_id: parseInt(gradeId),
                _fallback: true
            };
        }
    },

    // Helper method to cache curriculum grade relationships
    cacheCurriculumGrade(curriculumId, gradeId, data) {
        const cacheKey = `curriculum_grade_${curriculumId}_${gradeId}`;
        try {
            localStorage.setItem(
                cacheKey,
                JSON.stringify({
                    data,
                    timestamp: Date.now()
                })
            );
        } catch (cacheError) {
            console.warn('Cache write error:', cacheError);
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
                const response = await api.post('/api/sections', sectionData);
                return response.data;
            } catch (directError) {
                console.log('Direct section creation failed, trying nested endpoint');

                // If direct creation fails, try the nested endpoint
                if (sectionData.curriculum_id && sectionData.grade_id) {
                    const response = await api.post(`/api/curriculums/${sectionData.curriculum_id}/grades/${sectionData.grade_id}/sections`, sectionData);
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

            // Try the nested endpoint first
            try {
                console.log('Trying nested endpoint for adding section');
                const response = await api.post(`/api/curriculums/${curriculumId}/grades/${gradeId}/sections`, section);
                console.log('Successfully added section using nested endpoint');
                return response.data;
            } catch (nestedError) {
                console.warn('Nested endpoint failed, trying direct endpoint:', nestedError);

                // Log more details about the error
                if (nestedError.response) {
                    console.warn('Status code:', nestedError.response.status);
                    console.warn('Error data:', nestedError.response.data);

                    // If it's a 500 error, the section might still have been created
                    if (nestedError.response.status === 500) {
                        console.warn('Got 500 error from nested endpoint - this might mean the operation partially succeeded');

                        // Wait a moment before trying the direct endpoint
                        await new Promise((resolve) => setTimeout(resolve, 500));

                        // Check if the section already exists
                        try {
                            const sections = await this.getSectionsByGrade(curriculumId, gradeId);
                            const existingSection = sections.find((s) => s.name === section.name);
                            if (existingSection) {
                                console.warn('Section appears to exist despite the 500 error, returning it');
                                return existingSection;
                            }
                        } catch (checkError) {
                            console.warn('Failed to check if section exists:', checkError);
                        }
                    }
                }

                // If nested endpoint fails, try direct endpoint instead
                // Make sure we have the required fields
                const directSectionData = {
                    ...section,
                    grade_id: gradeId,
                    curriculum_id: curriculumId
                };

                console.log('Trying direct endpoint with data:', directSectionData);
                const directResponse = await api.post('/api/sections', directSectionData);
                console.log('Successfully added section using direct endpoint');
                return directResponse.data;
            }
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
                const response = await api.delete(`/api/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}`);
                return response.data;
            } catch (nestedError) {
                console.log('Nested section delete failed, trying direct endpoint');

                // If nested endpoint fails, try direct endpoint
                const response = await api.delete(`/api/sections/${sectionId}`);
                return response.data;
            }
        } catch (error) {
            console.error('Error removing section:', error);
            throw error;
        }
    },

    // Get subjects for a section with improved caching and fallback
    async getSubjectsBySection(curriculumId, gradeId, sectionId) {
        try {
            // Validate parameters
            if (!sectionId) {
                console.warn('Missing section ID in getSubjectsBySection');
                return [];
            }

            // Check if sectionId is in the fallback format (e.g., "grade_1")
            if (sectionId.toString().includes('grade_')) {
                console.error(`Invalid section ID format detected: ${sectionId}`);
                console.error('This appears to be a fallback ID. Please use real database IDs.');
                return [];
            }

            console.log('Fetching ONLY user-added subjects for section:', { curriculumId, gradeId, sectionId });

            // Clear any existing cached data for this section
            const cacheKey = `section_subjects_${sectionId}`;
            sectionCache.delete(cacheKey);
            try {
                localStorage.removeItem(cacheKey);
                localStorage.removeItem(`${cacheKey}_timestamp`);
            } catch (e) {
                console.warn('Could not clear localStorage cache:', e);
            }

            // Make direct API call with maximum reliability settings
            console.log(`Making direct API call to fetch ONLY user-added subjects for section ${sectionId}`);

            // API request parameters to ensure we only get user-added subjects
            const params = {
                curriculum_id: curriculumId,
                grade_id: gradeId,
                force: true,
                user_added_only: true, // Only return subjects explicitly added by the user
                no_fallback: true,
                force_fresh: true,
                timestamp: Date.now() // Cache busting
            };

            console.log('API request params:', params);

            // Use the special direct-subjects endpoint which ONLY returns manually added subjects
            const response = await api.get(`/api/sections/${sectionId}/direct-subjects`, {
                timeout: 30000, // 30 second timeout for maximum reliability
                params: params,
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    Pragma: 'no-cache',
                    Expires: '0'
                }
            });

            console.log('API response received:', response.status, 'Data length:', Array.isArray(response.data) ? response.data.length : 'not an array');

            if (response.data && Array.isArray(response.data)) {
                console.log(`Found ${response.data.length} user-added subjects for section ${sectionId}`);
                console.log('Subject names:', response.data.map((s) => s.name).join(', '));

                // Ensure all subjects have valid IDs
                const validSubjects = response.data.filter((subject) => subject && subject.id);

                if (validSubjects.length === 0) {
                    console.log('No user-added subjects found for this section');
                    return [];
                }

                // Process the data to ensure it has all required fields
                const processedSubjects = validSubjects.map((subject) => {
                    // Make sure subject has required fields and pivot data
                    return {
                        id: subject.id,
                        name: subject.name || 'Unknown Subject',
                        code: subject.code || 'SUBJ',
                        description: subject.description || '',
                        grade_id: subject.grade_id || gradeId,
                        status: subject.status || 'Active',
                        is_active: subject.is_active !== undefined ? subject.is_active : true,
                        // Make sure the pivot data is included
                        pivot: subject.pivot || {
                            section_id: sectionId,
                            subject_id: subject.id,
                            created_at: subject.created_at || new Date().toISOString(),
                            updated_at: subject.updated_at || new Date().toISOString()
                        }
                    };
                });

                // Cache the successful response
                const now = Date.now();
                sectionCache.set(cacheKey, { data: processedSubjects, timestamp: now });
                try {
                    localStorage.setItem(cacheKey, JSON.stringify(processedSubjects));
                    localStorage.setItem(`${cacheKey}_timestamp`, now.toString());
                } catch (storageError) {
                    console.warn('Could not store section subjects in localStorage:', storageError);
                }

                return processedSubjects;
            } else {
                console.log(`No subjects found for section ${sectionId}`);
                return [];
            }
        } catch (error) {
            console.error('Error in getSubjectsBySection:', error.message);
            return []; // Return empty array on error, no fallbacks
        }
    },

    // Add subject to section
    async addSubjectToSection(curriculumId, gradeId, sectionId, subject) {
        try {
            console.log('Adding subject to section:', curriculumId, gradeId, sectionId, subject);
            const response = await api.post(`/api/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/subjects`, subject);
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
            const response = await api.delete(`/api/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/subjects/${subjectId}`);
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
            const response = await api.post(`/api/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/teacher`, teacherData);
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
            const response = await api.post(`/api/sections/${sectionId}/subjects/${subjectId}/teacher`, teacherData);
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
            const response = await api.post(`/api/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/subjects/${subjectId}/schedule`, schedule);
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
            const response = await api.post(`/api/teachers/${teacherId}/schedule/check-conflicts`, schedule);
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
                    const response = await api.get('/api/sections', {
                        timeout: 30000,
                        headers: {
                            'Cache-Control': 'max-age=300',
                            Pragma: 'cache'
                        },
                        params: {
                            curriculum_grade_id: curriculumGrade.id,
                            curriculum_id: curriculumId,
                            grade_id: gradeId
                        }
                    });

                    if (response.data) {
                        return Array.isArray(response.data) ? response.data : [response.data];
                    }
                } catch (error) {
                    console.log('Failed to get sections by curriculum_grade_id, trying getSectionsByGrade');
                }
            }

            // Fallback to getSectionsByGrade
            return await this.getSectionsByGrade(curriculumId, gradeId);
        } catch (error) {
            console.error('Error fetching sections by curriculum grade:', error);
            return [];
        }
    },

    // Get subject schedules
    async getSubjectSchedules(curriculumId, gradeId, sectionId, subjectId) {
        try {
            console.log('Fetching schedules for subject:', { curriculumId, gradeId, sectionId, subjectId });

            // Try direct endpoint first (this is the correct one from the API routes)
            try {
                const response = await api.get(`/api/sections/${sectionId}/subjects/${subjectId}/schedule`);
                if (response.data) {
                    console.log('Found schedules using direct endpoint');
                    return Array.isArray(response.data) ? response.data : [response.data];
                }
            } catch (directError) {
                console.log('Direct schedule endpoint failed:', directError.message);
            }

            // If direct endpoint fails, try nested endpoint as fallback
            try {
                const response = await api.get(`/api/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/subjects/${subjectId}/schedule`);
                if (response.data) {
                    console.log('Found schedules using nested endpoint');
                    return Array.isArray(response.data) ? response.data : [response.data];
                }
            } catch (nestedError) {
                console.log('Nested schedule endpoint failed:', nestedError.message);
            }

            // If both attempts fail, return empty array
            console.log('No schedules found, returning empty array');
            return [];
        } catch (error) {
            console.error('Error fetching subject schedules:', error);
            return [];
        }
    },

    // Clear cache
    clearCache() {
        console.log('Clearing all caches');
        curriculumCache = null;
        cacheTimestamp = null;
        sectionCache.clear();
        gradeCache.clear();

        // Clear localStorage cache
        try {
            const keys = Object.keys(localStorage);
            for (const key of keys) {
                if (key.startsWith('grades_') || key.startsWith('sections_') || key === 'curriculumData') {
                    localStorage.removeItem(key);
                    localStorage.removeItem(key + '_timestamp');
                }
            }
        } catch (error) {
            console.warn('Error clearing localStorage cache:', error);
        }
    },

    // Repair section-grade relationships
    async repairSectionGradeRelationships() {
        try {
            console.log('Triggering section-grade relationship repair');
            const response = await api.post('/api/system/repair-sections');
            return response.data;
        } catch (error) {
            console.error('Error repairing section-grade relationships:', error);
            throw error;
        }
    }
};
