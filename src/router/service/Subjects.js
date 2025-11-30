// This file will contain centralized subject data
import axios from 'axios';
import { AttendanceService } from './Students';

// Mock data
const mockSubjects = [
    { id: 1, name: 'Mathematics', description: 'Basic arithmetic and numbers', credits: 3 },
    { id: 2, name: 'Mathematics', description: 'Intermediate arithmetic', credits: 3 },
    { id: 3, name: 'Mathematics', description: 'Advanced arithmetic and basic algebra', credits: 3 },
    { id: 4, name: 'English', description: 'Basic reading and writing', credits: 3 }
    // Add more mock subjects as needed
];

// Base URL for the API
const API_URL = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000') + '/api';

// Create an in-memory cache for performance
let subjectCache = null;
let cacheTimestamp = null;
const CACHE_TTL = 60000; // 1 minute cache lifetime

export const SubjectService = {
    async getSubjects() {
        try {
            // Check if we have a valid cache
            const now = Date.now();
            if (subjectCache && cacheTimestamp && now - cacheTimestamp < CACHE_TTL) {
                console.log('Using cached subjects data, age:', Math.round((now - cacheTimestamp) / 1000), 'seconds');
                return subjectCache;
            }

            console.log('Cache expired or not available, fetching subjects from API...');
            const response = await axios.get(`${API_URL}/subjects`);
            console.log('API response received:', response.status, 'with', response.data.length, 'subjects');

            // Update cache
            subjectCache = response.data;
            cacheTimestamp = now;

            return response.data;
        } catch (error) {
            console.error('Error fetching subjects:', error);
            if (error.response) {
                // The request was made and the server responded with a status code
                // that falls out of the range of 2xx
                console.error('Server responded with error:', error.response.status, error.response.data);
            } else if (error.request) {
                // The request was made but no response was received
                console.error('No response received from server');
            } else {
                // Something happened in setting up the request that triggered an Error
                console.error('Error setting up request:', error.message);
            }
            console.log('Falling back to mock data');
            return mockSubjects;
        }
    },

    async getSectionsByGrade(grade) {
        // This method still uses mock data, can be updated later to use API
        const allSections = [
            {
                id: 'MATH101-A',
                title: 'Math Class - Section A',
                grade: 'Grade 1',
                subject: 'Mathematics',
                date: '2025-03-10'
            },
            {
                id: 'ENG101-A',
                title: 'English Class - Section A',
                grade: 'Grade 1',
                subject: 'English',
                date: '2025-03-10'
            },
            {
                id: 'MATH201-A',
                title: 'Math Class - Section A',
                grade: 'Grade 2',
                subject: 'Mathematics',
                date: '2025-03-10'
            },
            {
                id: 'MATH301-A',
                title: 'Math Class - Section A',
                grade: 'Grade 3',
                subject: 'Mathematics',
                date: '2025-03-10'
            }
        ];

        // Filter sections by grade
        return allSections.filter((section) => section.grade === grade);
    },

    async getStudentsBySection(sectionId) {
        // This method still uses the existing AttendanceService, can be updated later to use API
        // Get all students from AttendanceService
        const allStudents = await AttendanceService.getData();

        // Extract grade number and section letter from sectionId
        // Example: MATH301-A would give grade 3, section A
        const gradeMatch = sectionId.match(/(\d+)/);
        const sectionMatch = sectionId.match(/\w+-(\w+)/);

        const gradeNumber = gradeMatch ? parseInt(gradeMatch[0].charAt(0)) : null;
        const sectionLetter = sectionMatch ? sectionMatch[1] : null;

        // If we can extract both grade and section, use them to filter
        if (gradeNumber && sectionLetter) {
            // Filter students that match both grade level and section
            const sectionStudents = allStudents
                .filter((student) => student.gradeLevel === gradeNumber && student.section === sectionLetter)
                .map((student) => ({
                    id: student.id,
                    name: student.name,
                    status: 'Present', // Default status
                    remarks: '',
                    photo: student.photo, // Include photo for UI
                    gender: student.gender
                }));

            return sectionStudents;
        }

        // Fallback to the previous approach if extraction fails
        const gradeMap = {
            1: 'Grade 1',
            2: 'Grade 2',
            3: 'Grade 3',
            4: 'Grade 4',
            5: 'Grade 5',
            6: 'Grade 6'
        };

        let grade = 1;
        if (sectionId.includes('MATH')) {
            grade = parseInt(sectionId.match(/MATH(\d)/)?.[1] || '1');
        } else if (sectionId.includes('ENG')) {
            grade = parseInt(sectionId.match(/ENG(\d)/)?.[1] || '1');
        }

        // Filter students based on grade level
        return allStudents
            .filter((student) => student.gradeLevel === grade)
            .map((student) => ({
                id: student.id,
                name: student.name,
                status: 'Present',
                remarks: '',
                photo: student.photo,
                gender: student.gender
            }));
    },

    // Other methods that don't need to change yet
    async updateSectionDate(sectionId, newDate) {
        console.log(`Updated section ${sectionId} date to ${newDate}`);
        return { success: true, sectionId, newDate };
    },

    async updateStudentStatus(sectionId, studentId, status, remarks = '') {
        console.log(`Updated student ${studentId} in section ${sectionId} to ${status}`);

        await AttendanceService.recordAttendance(studentId, {
            date: new Date().toISOString().split('T')[0],
            status: status,
            time: new Date().toLocaleTimeString(),
            remarks: remarks
        });

        return { success: true, sectionId, studentId, status, remarks };
    },

    async getSectionAttendance(sectionId) {
        const students = await this.getStudentsBySection(sectionId);

        return students.map((student) => ({
            studentId: student.id,
            studentName: student.name,
            status: 'Not Marked',
            date: new Date().toISOString().split('T')[0],
            time: '',
            remarks: ''
        }));
    },

    // Create a new subject - Updated to use API
    async createSubject(subject) {
        try {
            console.log('Sending subject data to API:', subject);
            const response = await axios.post(`${API_URL}/subjects`, subject);
            console.log('Subject created successfully:', response.status, response.data);

            // Invalidate cache after creating a new subject
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error creating subject:', error);

            if (error.response && error.response.status === 422) {
                console.error('Validation error details:', error.response.data);
                // Throw the error to be handled by the caller
                throw error;
            }

            // For testing purposes or if server is down, return a mock created subject
            console.log('Falling back to mock response');
            return { ...subject, id: Date.now() };
        }
    },

    // Update curriculum for a subject
    async updateCurriculum(subjectId, curriculum) {
        console.log(`Updated curriculum for subject ${subjectId}`);
        return {
            subjectId,
            curriculum,
            success: true
        };
    },

    // Update subject - Updated to use API
    async updateSubject(id, subject) {
        try {
            const response = await axios.put(`${API_URL}/subjects/${id}`, subject);
            return response.data;
        } catch (error) {
            console.error('Error updating subject:', error);
            // For testing purposes, return the updated subject
            return subject;
        }
    },

    // Delete subject - Updated to use API
    async deleteSubject(id) {
        try {
            const response = await axios.delete(`${API_URL}/subjects/${id}`);

            // Invalidate cache after deleting a subject
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error deleting subject:', error);
            // For testing purposes, return success
            return { success: true };
        }
    },

    // Get subject by ID - Updated to use API
    async getSubjectById(id) {
        try {
            const response = await axios.get(`${API_URL}/subjects/${id}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching subject by ID:', error);
            // Return a mock subject
            return mockSubjects.find((s) => s.id === id) || null;
        }
    },

    // Clear the cache (useful for testing or resetting)
    async clearCache() {
        console.log('Clearing subject cache');
        subjectCache = null;
        cacheTimestamp = null;
        return true;
    },

    // Get unique subjects (by name)
    async getUniqueSubjects() {
        try {
            const response = await axios.get(`${API_URL}/subjects/unique`);
            return response.data;
        } catch (error) {
            console.error('Error getting unique subjects:', error);

            // Fallback - Get all subjects and filter unique ones
            const subjects = await this.getSubjects();

            // Create a map to track seen subject names
            const seen = new Map();

            // Filter to unique subject names
            const uniqueSubjects = subjects.filter((subject) => {
                if (seen.has(subject.name)) {
                    return false;
                }
                seen.set(subject.name, true);
                return true;
            });

            return uniqueSubjects;
        }
    },

    // Toggle subject active status
    async toggleSubjectStatus(id) {
        try {
            const response = await axios.patch(`${API_URL}/subjects/${id}/toggle-status`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error toggling subject status:', error);
            return { success: false, error: error.message };
        }
    }
};
