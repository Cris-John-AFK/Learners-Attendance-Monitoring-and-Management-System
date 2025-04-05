// src/router/service/TeacherService.js

import api from '@/config/axios';
import { reactive } from 'vue';
import { GradesService } from './GradesService';

// Create a reactive state to store all teachers
const state = reactive({
    teachers: [],
    loading: false,
    error: null
});

// Cache settings
let teacherCache = null;
let cacheTimestamp = null;
let scheduleCache = new Map();
const CACHE_TTL = 300000; // 5 minutes cache lifetime
const API_TIMEOUT = 5000; // 5 seconds timeout (reduced from 30 seconds)

// Default teachers data for fallback
const defaultTeachers = [
    { id: 1, name: 'Default Teacher 1', email: 'teacher1@school.edu', status: 'Active', is_active: true },
    { id: 2, name: 'Default Teacher 2', email: 'teacher2@school.edu', status: 'Active', is_active: true }
];

export const TeacherService = {
    // Get all teachers
    async getTeachers() {
        const now = Date.now();

        try {
            // First priority: Check if we already have default teachers
            if (state.teachers.length > 0) {
                console.log('Using memory cached teacher data from state');
                return state.teachers;
            }

            // Second priority: Check memory cache
            if (teacherCache && cacheTimestamp && now - cacheTimestamp < CACHE_TTL) {
                console.log('Using memory cached teacher data');
                return teacherCache;
            }

            // Third priority: Check localStorage
            try {
                const cachedData = localStorage.getItem('teacherData');
                const cacheTime = parseInt(localStorage.getItem('teacherCacheTimestamp'));

                if (cachedData && cacheTime && now - cacheTime < CACHE_TTL) {
                    console.log('Using localStorage cached teacher data');
                    const data = JSON.parse(cachedData);
                    teacherCache = data;
                    cacheTimestamp = cacheTime;
                    state.teachers = data; // Also update the state
                    return data;
                }
            } catch (storageError) {
                console.warn('Could not retrieve teacher data from localStorage:', storageError);
            }

            // Fourth priority: Get from API with very short timeout (1.5 seconds)
            console.log('Fetching teachers from API with short timeout...');

            // Use AbortController for better timeout handling
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 1500); // 1.5 second timeout

            try {
                const response = await api.get('/api/teachers', {
                    timeout: 1500, // 1.5 seconds only
                    signal: controller.signal,
                    headers: {
                        'Cache-Control': 'no-cache',
                        Pragma: 'no-cache'
                    }
                });

                clearTimeout(timeoutId);

                // Validate and process the response
                if (response.data && (Array.isArray(response.data) || typeof response.data === 'object')) {
                    const data = Array.isArray(response.data) ? response.data : [response.data];

                    // Update state and cache with valid data
                    teacherCache = data;
                    cacheTimestamp = now;
                    state.teachers = data;

                    // Update localStorage
                    try {
                        localStorage.setItem('teacherData', JSON.stringify(data));
                        localStorage.setItem('teacherCacheTimestamp', now.toString());
                    } catch (storageError) {
                        console.warn('Could not store teacher data in localStorage:', storageError);
                    }

                    return data;
                }

                throw new Error('Invalid data format from API');
            } catch (apiError) {
                clearTimeout(timeoutId);
                console.warn('Fast API call failed, using defaults:', apiError.message);

                // Fall back to default teachers immediately
                const defaultData = this.getDefaultTeachers();

                // Store in cache and state
                teacherCache = defaultData;
                cacheTimestamp = now;
                state.teachers = defaultData;

                // Try to store in localStorage
                try {
                    localStorage.setItem('teacherData', JSON.stringify(defaultData));
                    localStorage.setItem('teacherCacheTimestamp', now.toString());
                } catch (storageError) {
                    console.warn('Could not store default teacher data in localStorage:', storageError);
                }

                return defaultData;
            }
        } catch (error) {
            console.error('Error in getTeachers:', error);
            const defaultData = this.getDefaultTeachers();
            state.teachers = defaultData;
            return defaultData;
        }
    },

    // Get default teachers data
    getDefaultTeachers() {
        return [
            { id: 1, name: 'John Smith', email: 'john.smith@school.edu', status: 'Active', is_active: true },
            { id: 2, name: 'Maria Garcia', email: 'maria.garcia@school.edu', status: 'Active', is_active: true },
            { id: 3, name: 'James Johnson', email: 'james.johnson@school.edu', status: 'Active', is_active: true },
            { id: 4, name: 'Sarah Williams', email: 'sarah.williams@school.edu', status: 'Active', is_active: true },
            { id: 5, name: 'Robert Brown', email: 'robert.brown@school.edu', status: 'Active', is_active: true }
        ];
    },

    // Get active teachers with caching
    async getActiveTeachers() {
        try {
            const allTeachers = await this.getTeachers();
            return allTeachers.filter((teacher) => teacher.is_active);
        } catch (error) {
            console.error('Error fetching active teachers:', error);
            throw error;
        }
    },

    // Get teachers by section with caching
    async getTeachersBySection(sectionId) {
        try {
            const allTeachers = await this.getTeachers();
            return allTeachers.filter((teacher) => teacher.sections?.includes(sectionId));
        } catch (error) {
            console.error('Error fetching teachers by section:', error);
            throw error;
        }
    },

    // Get teacher by ID with caching
    async getTeacherById(id) {
        try {
            const allTeachers = await this.getTeachers();
            return allTeachers.find((teacher) => teacher.id === id);
        } catch (error) {
            console.error('Error fetching teacher by ID:', error);
            throw error;
        }
    },

    // Create a new teacher
    async createTeacher(teacher) {
        try {
            console.log('Creating new teacher:', teacher);
            const response = await api.post('/api/teachers', teacher);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error creating teacher:', error);
            throw error;
        }
    },

    // Update a teacher
    async updateTeacher(id, teacher) {
        try {
            console.log('Updating teacher:', id, teacher);
            const response = await api.put(`/api/teachers/${id}`, teacher);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error updating teacher:', error);
            throw error;
        }
    },

    // Archive a teacher
    async archiveTeacher(id) {
        try {
            console.log('Archiving teacher:', id);
            const response = await api.put(`/api/teachers/${id}/archive`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error archiving teacher:', error);
            throw error;
        }
    },

    // Restore an archived teacher
    async restoreTeacher(id) {
        try {
            console.log('Restoring teacher:', id);
            const response = await api.put(`/api/teachers/${id}/restore`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error restoring teacher:', error);
            throw error;
        }
    },

    // Assign homeroom to teacher
    async assignHomeroom(teacherId, sectionId) {
        try {
            console.log('Assigning homeroom to teacher:', teacherId, sectionId);
            const response = await api.post(`/api/teachers/${teacherId}/homeroom`, { section_id: sectionId });

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error assigning homeroom to teacher:', error);
            throw error;
        }
    },

    // Remove homeroom from teacher
    async removeHomeroom(teacherId) {
        try {
            console.log('Removing homeroom from teacher:', teacherId);
            const response = await api.delete(`/api/teachers/${teacherId}/homeroom`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error removing homeroom from teacher:', error);
            throw error;
        }
    },

    // Assign subject to teacher
    async assignSubject(teacherId, subjectId) {
        try {
            console.log('Assigning subject to teacher:', teacherId, subjectId);
            const response = await api.post(`/api/teachers/${teacherId}/subjects`, { subject_id: subjectId });

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error assigning subject to teacher:', error);
            throw error;
        }
    },

    // Remove subject from teacher
    async removeSubject(teacherId, subjectId) {
        try {
            console.log('Removing subject from teacher:', teacherId, subjectId);
            const response = await api.delete(`/api/teachers/${teacherId}/subjects/${subjectId}`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error removing subject from teacher:', error);
            throw error;
        }
    },

    // Get teacher schedule with caching
    async getTeacherSchedule(teacherId) {
        try {
            const now = Date.now();
            const cacheKey = `schedule_${teacherId}`;
            const cached = scheduleCache.get(cacheKey);

            if (cached && now - cached.timestamp < CACHE_TTL) {
                console.log('Using cached schedule data for teacher:', teacherId);
                return cached.data;
            }

            console.log('Fetching teacher schedule:', teacherId);
            const response = await api.get(`/api/teachers/${teacherId}/schedule`, {
                timeout: 10000,
                headers: {
                    'Cache-Control': 'max-age=300',
                    Pragma: 'cache'
                }
            });

            const data = response.data;
            scheduleCache.set(cacheKey, { data, timestamp: now });
            return data;
        } catch (error) {
            console.error('Error fetching teacher schedule:', error);
            throw error;
        }
    },

    // Check teacher schedule conflicts
    async checkScheduleConflicts(teacherId, schedule) {
        try {
            console.log('Checking teacher schedule conflicts:', teacherId, schedule);
            const response = await api.post(`/api/teachers/${teacherId}/schedule/check-conflicts`, schedule, {
                timeout: 10000
            });
            return response.data;
        } catch (error) {
            console.error('Error checking teacher schedule conflicts:', error);
            throw error;
        }
    },

    // Clear cache
    clearCache() {
        console.log('Clearing teacher cache');
        teacherCache = null;
        cacheTimestamp = null;
        scheduleCache.clear();
        localStorage.removeItem('teacherData');
        localStorage.removeItem('teacherCacheTimestamp');
    },

    // Get all sections assigned to a teacher with caching
    async getTeacherAssignments(teacherId) {
        try {
            const teacher = await this.getTeacherById(teacherId);
            if (!teacher) return [];

            const assignments = [];
            const grades = await GradesService.getGrades();

            for (const gradeAssignment of teacher.assignedGrades || []) {
                const grade = grades.find((g) => g.id === gradeAssignment.gradeId);
                if (grade) {
                    for (const sectionName of gradeAssignment.sections) {
                        assignments.push({
                            gradeId: grade.id,
                            gradeName: grade.name,
                            sectionName
                        });
                    }
                }
            }

            return assignments;
        } catch (error) {
            console.error('Error getting teacher assignments:', error);
            return [];
        }
    },

    // Get available sections that can be assigned to a teacher with caching
    async getAvailableSections(teacherId) {
        try {
            const [grades, teacher] = await Promise.all([GradesService.getGrades(), this.getTeacherById(teacherId)]);

            if (!teacher) return [];

            const availableSections = [];

            for (const grade of grades) {
                const assignedGrade = (teacher.assignedGrades || []).find((g) => g.gradeId === grade.id);
                const assignedSections = assignedGrade ? assignedGrade.sections : [];

                // Filter sections that are not already assigned to this teacher
                const availableGradeSections = grade.sections.filter((section) => !assignedSections.includes(section));

                if (availableGradeSections.length > 0) {
                    availableSections.push({
                        gradeId: grade.id,
                        gradeName: grade.name,
                        sections: availableGradeSections
                    });
                }
            }

            return availableSections;
        } catch (error) {
            console.error('Error getting available sections:', error);
            return [];
        }
    },

    // Add this method to your TeacherService object
    addSubject(teacherId, subject) {
        const teacher = this.getTeacherById(teacherId);
        if (!teacher) return null;

        // Initialize subjects array if it doesn't exist
        if (!teacher.subjects) {
            teacher.subjects = [];
        }

        // Check if subject already exists
        const existingSubject = teacher.subjects.find((s) => s.id === subject.id);
        if (existingSubject) {
            return existingSubject; // Subject already assigned
        }

        // Generate an ID if none is provided
        if (!subject.id) {
            subject.id = Math.floor(Math.random() * 1000) + 1;
        }

        // Add the subject to the teacher
        teacher.subjects.push(subject);

        // Update subjectsCount
        teacher.subjectsCount = teacher.subjects.length;

        return subject;
    },

    // Add this method to update a subject
    updateSubject(teacherId, subjectId, updatedSubject) {
        const teacher = this.getTeacherById(teacherId);
        if (!teacher || !teacher.subjects) return null;

        const index = teacher.subjects.findIndex((s) => s.id === subjectId);
        if (index === -1) return null;

        teacher.subjects[index] = { ...teacher.subjects[index], ...updatedSubject };
        return teacher.subjects[index];
    },

    // Add this method for adding sections to a subject
    addSection(teacherId, subjectId, section) {
        const teacher = this.getTeacherById(teacherId);
        if (!teacher || !teacher.subjects) return null;

        const subject = teacher.subjects.find((s) => s.id === subjectId);
        if (!subject) return null;

        // Initialize sections array if it doesn't exist
        if (!subject.sections) {
            subject.sections = [];
        }

        // Generate an ID if none is provided
        if (!section.id) {
            section.id = Math.floor(Math.random() * 1000) + 1;
        }

        // Add the section
        subject.sections.push(section);

        // Update sectionsCount
        subject.sectionsCount = subject.sections.length;

        return section;
    }
};
