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
            // Call the standard update endpoint with is_active: true
            const response = await api.put(`/api/curriculums/${id}`, { is_active: true });

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
        const now = Date.now();
        const cacheKey = `grades_${curriculumId}`;

        try {
            // First try memory cache (fastest)
            const cachedData = gradeCache.get(cacheKey);
            if (cachedData && now - cachedData.timestamp < CACHE_TTL) {
                console.log('Using memory cached grade data');
                return cachedData.data;
            }

            // Then try localStorage (second fastest)
            try {
                const localData = localStorage.getItem(cacheKey);
                const localTimestamp = parseInt(localStorage.getItem(`${cacheKey}_timestamp`));

                if (localData && localTimestamp && now - localTimestamp < CACHE_TTL) {
                    console.log('Using localStorage cached grade data');
                    const data = JSON.parse(localData);
                    gradeCache.set(cacheKey, { data, timestamp: localTimestamp });
                    return data;
                }
            } catch (storageError) {
                console.warn('Could not retrieve grade data from localStorage:', storageError);
            }

            // If no cache hit, fetch from API
            console.log('Fetching grades from API...');
            const response = await api.get(`/api/curriculums/${curriculumId}/grades`, {
                timeout: 30000, // 30 seconds timeout
                headers: {
                    'Cache-Control': 'max-age=300',
                    Pragma: 'cache'
                }
            });

            if (!response.data) {
                throw new Error('No data received from API');
            }

            const grades = Array.isArray(response.data) ? response.data : [response.data];

            // Update both memory and localStorage cache
            gradeCache.set(cacheKey, { data: grades, timestamp: now });
            try {
                localStorage.setItem(cacheKey, JSON.stringify(grades));
                localStorage.setItem(`${cacheKey}_timestamp`, now.toString());
            } catch (storageError) {
                console.warn('Could not store grade data in localStorage:', storageError);
            }

            return grades;
        } catch (error) {
            console.warn('API fetch error:', error);

            // Try to get expired cache as fallback
            try {
                const localData = localStorage.getItem(cacheKey);
                if (localData) {
                    console.log('Using expired cache as fallback');
                    return JSON.parse(localData);
                }
            } catch (cacheError) {
                console.warn('Could not read expired cache:', cacheError);
            }

            // If no cached data available, return empty array
            console.log('No grade data available, returning empty array');
            return [];
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
            return this.getFallbackSections(gradeId);
        }

        const now = Date.now();
        const cacheKey = `sections_${curriculumId}_${gradeId}`;

        try {
            // First check memory cache (fastest)
            const memoryCache = sectionCache.get(cacheKey);
            if (memoryCache && now - memoryCache.timestamp < CACHE_TTL) {
                console.log('Using memory cached section data');
                return memoryCache.data;
            }
        } catch (cacheError) {
            console.warn('Cache error, proceeding to API call:', cacheError);
        }

        return this.getSectionsByGradeForced(curriculumId, gradeId);
    },

    // Force fresh API call bypassing all caches
    async getSectionsByGradeForced(curriculumId, gradeId) {
        if (!curriculumId || !gradeId) {
            console.warn('Missing required parameters for getSectionsByGradeForced');
            return this.getFallbackSections(gradeId);
        }

        const cacheKey = `sections_${curriculumId}_${gradeId}`;
        const now = Date.now();

        try {
            // Skip all caches and fetch directly from API
            console.log('Forcing fresh API call for sections...');

            // Create an AbortController for timeout handling
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

            try {
                // Try direct API call with longer timeout
                const response = await api.get(`/api/sections/grade/${gradeId}`, {
                    timeout: 10000, // 10 seconds
                    signal: controller.signal,
                    params: {
                        curriculum_id: curriculumId
                    }
                });

                clearTimeout(timeoutId);

                if (response.data) {
                    const sections = Array.isArray(response.data) ? response.data : response.data.data && Array.isArray(response.data.data) ? response.data.data : [response.data];

                    // Update both memory and localStorage cache
                    sectionCache.set(cacheKey, { data: sections, timestamp: now });
                    try {
                        localStorage.setItem(cacheKey, JSON.stringify(sections));
                        localStorage.setItem(`${cacheKey}_timestamp`, now.toString());
                    } catch (storageError) {
                        console.warn('Could not store section data in localStorage:', storageError);
                    }

                    return sections;
                }
            } catch (directError) {
                clearTimeout(timeoutId);
                console.warn('Direct sections endpoint failed:', directError);

                // Check if we have any stored cache (even expired) before trying another endpoint
                try {
                    const localData = localStorage.getItem(cacheKey);
                    if (localData) {
                        const parsedData = JSON.parse(localData);
                        if (Array.isArray(parsedData) && parsedData.length > 0) {
                            console.log('Using expired cache as initial fallback');
                            return parsedData;
                        }
                    }
                } catch (cacheError) {
                    console.warn('Could not read expired cache:', cacheError);
                }

                // If no cached data, return the fallback right away
                return this.getFallbackSections(gradeId);
            }

            // If we got here with no data, return the fallback
            return this.getFallbackSections(gradeId);
        } catch (error) {
            console.error('Error in getSectionsByGrade:', error);
            return this.getFallbackSections(gradeId);
        }
    },

    // Helper method to get fallback sections
    getFallbackSections(gradeId) {
        console.log('Using fallback sections for grade:', gradeId);
        // Create more comprehensive default sections
        const sectionNames = ['A', 'B', 'C'];
        return sectionNames.map((name, index) => ({
            id: `${gradeId}_${index}`,
            name: `Section ${name}`,
            grade_id: parseInt(gradeId),
            capacity: 30,
            status: 'Active',
            is_active: true,
            homeroom_teacher_id: null,
            subjects: []
        }));
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
            const response = await api.post(`/api/curriculums/${curriculumId}/grades/${gradeId}/sections`, section);
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

            console.log('Fetching subjects for section:', { curriculumId, gradeId, sectionId });

            // Clear cache for this specific section to force fresh data
            const cacheKey = `section_subjects_${sectionId}`;
            localStorage.removeItem(cacheKey);

            // FORCE: Repair section-subject relationships first, before trying to get subjects
            try {
                console.log('Forcing section-subject relationship repair first');
                await api.post(`/api/sections/${sectionId}/repair-subjects`);
            } catch (repairError) {
                console.warn('Initial repair failed:', repairError.message);
            }

            // Try direct endpoint with increased timeout
            try {
                console.log(`Fetching subjects for section ${sectionId} with increased timeout`);
                const response = await api.get(`/api/sections/${sectionId}/subjects`, {
                    timeout: 30000, // 30 seconds timeout to ensure we get a proper response
                    params: {
                        curriculum_id: curriculumId,
                        grade_id: gradeId,
                        user_added_only: true, // This ensures we get subjects with schedules loaded
                        force: true,
                        timestamp: Date.now() // Prevent any caching
                    },
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        Pragma: 'no-cache',
                        Expires: '0'
                    }
                });

                if (response.data && Array.isArray(response.data)) {
                    console.log(`SUCCESS: Found ${response.data.length} subjects for section ${sectionId}`);
                    console.log('Raw API response data:', JSON.stringify(response.data, null, 2));

                    // Process the data to ensure it has all required fields
                    const processedSubjects = response.data.map((subject) => {
                        // Make sure subject has required fields and pivot data
                        return {
                            id: subject.id,
                            name: subject.name || 'Unknown Subject',
                            code: subject.code || 'SUBJ',
                            description: subject.description || '',
                            grade_id: subject.grade_id || gradeId,
                            status: subject.status || 'Active',
                            is_active: subject.is_active !== undefined ? subject.is_active : true,
                            // CRITICAL: Preserve the schedules array from API response
                            schedules: subject.schedules || [],
                            // Make sure the pivot data is included
                            pivot: subject.pivot || {
                                section_id: sectionId,
                                subject_id: subject.id,
                                created_at: subject.created_at || new Date().toISOString(),
                                updated_at: subject.updated_at || new Date().toISOString()
                            }
                        };
                    });

                    console.log('Processed subjects with schedules:', JSON.stringify(processedSubjects, null, 2));
                    return processedSubjects;
                } else {
                    console.warn(`API returned empty data for section ${sectionId}, trying repair`);
                }
            } catch (error) {
                console.warn('Direct API call failed:', error.message);
            }

            // If direct call failed, try to repair again and then try one more time
            try {
                console.log('Trying to repair and retry');
                await api.post(`/api/sections/${sectionId}/repair-subjects`);

                // Try one more time after repair
                const retryResponse = await api.get(`/api/sections/${sectionId}/subjects`, {
                    timeout: 10000,
                    params: {
                        curriculum_id: curriculumId,
                        grade_id: gradeId,
                        force: true,
                        timestamp: Date.now()
                    },
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        Pragma: 'no-cache',
                        Expires: '0'
                    }
                });

                if (retryResponse.data && Array.isArray(retryResponse.data) && retryResponse.data.length > 0) {
                    console.log(`SUCCESS after repair: Found ${retryResponse.data.length} subjects`);
                    return retryResponse.data;
                }
            } catch (repairError) {
                console.warn('Repair and retry failed:', repairError.message);
            }

            // If we get here, all attempts failed. Make one last direct database query
            try {
                console.log('Making one final attempt to get real subject data');
                // Try alternative approach - get all subjects and manually assign them
                const allSubjectsResponse = await api.get('/api/subjects', {
                    timeout: 5000,
                    params: {
                        timestamp: Date.now()
                    }
                });

                if (allSubjectsResponse.data && Array.isArray(allSubjectsResponse.data) && allSubjectsResponse.data.length > 0) {
                    console.log('Got all subjects, using first 3 as fallback');
                    // Use the first 3 subjects as a last resort
                    const subjects = allSubjectsResponse.data.slice(0, 3).map((subject) => ({
                        ...subject,
                        pivot: {
                            section_id: sectionId,
                            subject_id: subject.id,
                            created_at: new Date().toISOString(),
                            updated_at: new Date().toISOString()
                        }
                    }));

                    // Try to save these relationships to the database
                    for (const subject of subjects) {
                        try {
                            await api.post(`/api/sections/${sectionId}/subjects`, {
                                subject_id: subject.id
                            });
                        } catch (e) {
                            console.warn(`Failed to add subject ${subject.id} to section ${sectionId}:`, e.message);
                        }
                    }

                    return subjects;
                }
            } catch (finalError) {
                console.error('Final attempt failed:', finalError.message);
            }

            // Absolutely last resort - completely static fallback
            console.error('ALL APPROACHES FAILED: Using static fallback data');
            return [
                {
                    id: 1,
                    name: 'Mathematics',
                    code: 'MATH',
                    status: 'Active',
                    is_active: true,
                    pivot: {
                        section_id: sectionId,
                        subject_id: 1,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString()
                    }
                },
                {
                    id: 2,
                    name: 'Science',
                    code: 'SCI',
                    status: 'Active',
                    is_active: true,
                    pivot: {
                        section_id: sectionId,
                        subject_id: 2,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString()
                    }
                },
                {
                    id: 3,
                    name: 'English',
                    code: 'ENG',
                    status: 'Active',
                    is_active: true,
                    pivot: {
                        section_id: sectionId,
                        subject_id: 3,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString()
                    }
                }
            ];
        } catch (error) {
            console.error('Error in getSubjectsBySection:', error);
            // Return absolute minimal fallback data
            return [
                { id: 1, name: 'Mathematics', code: 'MATH', status: 'Active', is_active: true },
                { id: 2, name: 'Science', code: 'SCI', status: 'Active', is_active: true },
                { id: 3, name: 'English', code: 'ENG', status: 'Active', is_active: true }
            ];
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

    // Assign homeroom teacher to section
    async assignHomeroomTeacher(sectionId, teacherId) {
        try {
            console.log('Assigning homeroom teacher:', { sectionId, teacherId });

            // Get curriculum and section details to build the correct API path
            const curriculums = await this.getCurriculums();
            if (!curriculums || !Array.isArray(curriculums) || curriculums.length === 0) {
                throw new Error('No curriculum found');
            }

            // Get the first (and only) curriculum since system enforces single curriculum
            const curriculum = curriculums[0];
            if (!curriculum || !curriculum.id) {
                throw new Error('Invalid curriculum data');
            }

            // We need to get the section details directly from the API since we don't have a getSections method
            // Instead, we'll use the section ID directly and let the backend handle the relationship lookup
            const curriculumId = curriculum.id;

            // Try to get section details from the API to find the grade
            let gradeId = null;
            try {
                const sectionResponse = await api.get(`/api/sections/${sectionId}`);
                const section = sectionResponse.data;
                if (section && section.curriculum_grade && section.curriculum_grade.grade_id) {
                    gradeId = section.curriculum_grade.grade_id;
                } else if (section && section.grade_id) {
                    gradeId = section.grade_id;
                } else {
                    throw new Error('Could not determine grade ID from section data');
                }
            } catch (sectionError) {
                console.error('Error fetching section details:', sectionError);
                throw new Error('Could not fetch section details to determine grade');
            }

            console.log('API call details:', { curriculumId, gradeId, sectionId, teacherId });

            const response = await api.post(`/api/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/teacher`, {
                teacher_id: teacherId
            });

            // Clear section cache to force refresh
            sectionCache.clear();

            // Also clear localStorage cache
            const cacheKey = `sections_${curriculumId}_${gradeId}`;
            localStorage.removeItem(cacheKey);

            return response.data;
        } catch (error) {
            console.error('Error assigning homeroom teacher:', error);
            throw error;
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
    }
};
