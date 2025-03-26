// This file will contain centralized subject data
import { AttendanceService } from './Students';
import axios from 'axios';

// Base URL for the API
const API_URL = 'http://localhost:8000/api';

// Create an in-memory cache for performance
let subjectCache = null;

export const SubjectService = {
    async getSubjects() {
        try {
            // If we already have the data in our cache, return it
            if (subjectCache) {
                return [...subjectCache]; // Return a copy
            }

            // Make API call to get subjects
            const response = await axios.get(`${API_URL}/subjects`);
            subjectCache = response.data;
            return [...subjectCache]; // Return a copy
        } catch (error) {
            console.error('Error fetching subjects:', error);

            // Fallback to mock data if API fails
            console.warn('Falling back to mock data');
            return [
                { id: 'MATH101', name: 'Mathematics', grade: 'Grade 1', description: 'Basic mathematics', credits: 3 },
                { id: 'ENG101', name: 'English', grade: 'Grade 1', description: 'English language fundamentals', credits: 3 },
                { id: 'SCI101', name: 'Science', grade: 'Grade 1' },
                { id: 'MATH201', name: 'Mathematics', grade: 'Grade 2' },
                { id: 'ENG201', name: 'English', grade: 'Grade 2' },
                { id: 'SCI201', name: 'Science', grade: 'Grade 2' },
                { id: 'MATH301', name: 'Mathematics', grade: 'Grade 3' },
                { id: 'ENG301', name: 'English', grade: 'Grade 3' },
                { id: 'SCI301', name: 'Science', grade: 'Grade 3' },
                { id: 'MATH401', name: 'Mathematics', grade: 'Grade 4' },
                { id: 'ENG401', name: 'English', grade: 'Grade 4' },
                { id: 'SCI401', name: 'Science', grade: 'Grade 4' },
                { id: 'MATH501', name: 'Mathematics', grade: 'Grade 5' },
                { id: 'ENG501', name: 'English', grade: 'Grade 5' },
                { id: 'SCI501', name: 'Science', grade: 'Grade 5' },
                { id: 'MATH601', name: 'Mathematics', grade: 'Grade 6' },
                { id: 'ENG601', name: 'English', grade: 'Grade 6' },
                { id: 'SCI601', name: 'Science', grade: 'Grade 6' },
                { id: 'MATHK', name: 'Mathematics', grade: 'Kinder' },
                { id: 'ENGK', name: 'English', grade: 'Kinder' },
                { id: 'SCIK', name: 'Science', grade: 'Kinder' }
            ];
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

    // Get all grades associated with subjects
    async getSubjectGrades() {
        try {
            const subjects = await this.getSubjects();
            // Extract unique grades from subjects
            const grades = [...new Set(subjects.map((subject) => subject.grade))];
            return grades;
        } catch (error) {
            console.error('Error getting subject grades:', error);
            return [];
        }
    },

    // Get sections by both grade and subject
    async getSectionsByGradeAndSubject(gradeName, subjectName) {
        const allSections = await this.getSectionsByGrade(gradeName);
        return allSections.filter((section) => section.subject === subjectName);
    },

    // Create a new subject - Updated to use API
    async createSubject(subject) {
        try {
            // Make API call to create subject
            const response = await axios.post(`${API_URL}/subjects`, subject);

            // Update the cache with the new subject
            if (subjectCache) {
                subjectCache.push(response.data);
            }

            return response.data;
        } catch (error) {
            console.error('Error creating subject:', error);
            throw error;
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
    async updateSubject(id, updatedData) {
        try {
            // Make API call to update subject
            const response = await axios.put(`${API_URL}/subjects/${id}`, updatedData);

            // Update the cache with the updated subject
            if (subjectCache) {
                const index = subjectCache.findIndex((s) => s.id === id);
                if (index >= 0) {
                    subjectCache[index] = response.data;
                }
            }

            return response.data;
        } catch (error) {
            console.error('Error updating subject:', error);
            throw error;
        }
    },

    // Delete subject - Updated to use API
    async deleteSubject(id) {
        try {
            // Make API call to delete subject
            const response = await axios.delete(`${API_URL}/subjects/${id}`);

            // Update the cache by removing the deleted subject
            if (subjectCache) {
                const index = subjectCache.findIndex((s) => s.id === id);
                if (index >= 0) {
                    subjectCache.splice(index, 1);
                }
            }

            return { id, success: true };
        } catch (error) {
            console.error('Error deleting subject:', error);
            throw error;
        }
    },

    // Get subject by ID - Updated to use API
    async getSubjectById(id) {
        try {
            // Try to find in cache first
            if (subjectCache) {
                const cached = subjectCache.find((s) => s.id === id);
                if (cached) return cached;
            }

            // Make API call to get subject
            const response = await axios.get(`${API_URL}/subjects/${id}`);
            return response.data;
        } catch (error) {
            console.error(`Error getting subject ${id}:`, error);
            throw error;
        }
    },

    // Clear the cache (useful for testing or resetting)
    async clearCache() {
        subjectCache = null;
    },

    // Get unique subjects (one per name) - Updated to use API
    async getUniqueSubjects() {
        try {
            // Make API call to get unique subjects
            const response = await axios.get(`${API_URL}/subjects/unique`);
            return response.data;
        } catch (error) {
            console.error('Error getting unique subjects:', error);

            // Fallback to client-side filtering
            const allSubjects = await this.getSubjects();
            const uniqueSubjects = [];
            const subjectNames = new Set();

            for (const subject of allSubjects) {
                if (!subjectNames.has(subject.name)) {
                    subjectNames.add(subject.name);
                    uniqueSubjects.push(subject);
                }
            }

            return uniqueSubjects;
        }
    },

    // Get subjects by grade - Updated to use API
    async getSubjectsByGrade(gradeId) {
        try {
            // Format gradeId if needed
            const grade = gradeId.startsWith('Grade ') ? gradeId : `Grade ${gradeId}`;

            // Make API call to get subjects by grade
            const response = await axios.get(`${API_URL}/subjects/grade/${encodeURIComponent(grade)}`);
            return response.data;
        } catch (error) {
            console.error('Error getting subjects by grade:', error);

            // Fallback to client-side filtering
            const allSubjects = await this.getSubjects();
            return allSubjects.filter((subject) => {
                const subjectGrade = subject.grade || subject.gradeId;
                if (subjectGrade === gradeId) return true;
                if (subjectGrade === `Grade ${gradeId}`) return true;
                return false;
            });
        }
    }
};
