// src/router/service/TeacherService.js

import axios from 'axios';
import { reactive } from 'vue';
import { GradeService } from './Grades';

// Create a reactive state to store all teachers
const state = reactive({
    teachers: [
        {
            id: 1,
            name: 'Maria Santos Reyes',
            department: 'Mathematics',
            roomNumber: '101',
            status: 'ACTIVE',
            image: 'amyelsner.png',
            assignedGrades: [{ gradeId: '3', sections: ['Aguinaldo', 'Quezon'] }]
        },
        {
            id: 2,
            name: 'Jose Cruz Mendoza',
            department: 'Science',
            roomNumber: '205',
            status: 'ACTIVE',
            image: 'asiyajavayant.png',
            assignedGrades: [{ gradeId: '4', sections: ['Del Pilar', 'Luna'] }]
        },
        {
            id: 3,
            name: 'Carmela Bautista Lim',
            department: 'Filipino',
            roomNumber: '304',
            status: 'ON_LEAVE',
            image: 'xuxuefeng.png',
            assignedGrades: [{ gradeId: '2', sections: ['Bonifacio', 'Mabini'] }]
        },
        {
            id: 4,
            name: 'Antonio dela Cruz',
            department: 'Social Studies',
            roomNumber: '202',
            status: 'ACTIVE',
            image: 'robertoortiz.png',
            assignedGrades: [{ gradeId: '5', sections: ['Orchid', 'Jasmine'] }]
        },
        {
            id: 5,
            name: 'Rosario Fernandez',
            department: 'English',
            roomNumber: '103',
            status: 'ACTIVE',
            image: 'ionibowcher.png',
            assignedGrades: [{ gradeId: '1', sections: ['Sampaguita', 'Rosal'] }]
        }
    ]
});

// Base URL for the API
const API_URL = 'http://localhost:8000/api';

// Cache settings
let teacherCache = null;
let cacheTimestamp = null;
const CACHE_TTL = 60000; // 1 minute cache lifetime

export const TeacherService = {
    // Get all teachers
    async getTeachers() {
        try {
            // Check if we have a valid cache
            const now = Date.now();
            if (teacherCache && cacheTimestamp && now - cacheTimestamp < CACHE_TTL) {
                console.log('Using cached teacher data');
                return teacherCache;
            }

            console.log('Fetching teachers from API...');
            const response = await axios.get(`${API_URL}/teachers`);

            // Update cache
            teacherCache = response.data;
            cacheTimestamp = now;

            return response.data;
        } catch (error) {
            console.error('Error fetching teachers:', error);
            throw error;
        }
    },

    // Get teacher by ID
    async getTeacherById(id) {
        try {
            console.log('Fetching teacher by ID:', id);
            const response = await axios.get(`${API_URL}/teachers/${id}`);
            return response.data;
        } catch (error) {
            console.error('Error fetching teacher by ID:', error);
            throw error;
        }
    },

    // Create a new teacher
    async createTeacher(teacher) {
        try {
            console.log('Creating new teacher:', teacher);
            const response = await axios.post(`${API_URL}/teachers`, teacher);

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
            const response = await axios.put(`${API_URL}/teachers/${id}`, teacher);

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
            const response = await axios.put(`${API_URL}/teachers/${id}/archive`);

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
            const response = await axios.put(`${API_URL}/teachers/${id}/restore`);

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
            const response = await axios.post(`${API_URL}/teachers/${teacherId}/homeroom`, { section_id: sectionId });

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
            const response = await axios.delete(`${API_URL}/teachers/${teacherId}/homeroom`);

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
            const response = await axios.post(`${API_URL}/teachers/${teacherId}/subjects`, { subject_id: subjectId });

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
            const response = await axios.delete(`${API_URL}/teachers/${teacherId}/subjects/${subjectId}`);

            // Invalidate cache
            this.clearCache();

            return response.data;
        } catch (error) {
            console.error('Error removing subject from teacher:', error);
            throw error;
        }
    },

    // Get teacher schedule
    async getTeacherSchedule(teacherId) {
        try {
            console.log('Fetching teacher schedule:', teacherId);
            const response = await axios.get(`${API_URL}/teachers/${teacherId}/schedule`);
            return response.data;
        } catch (error) {
            console.error('Error fetching teacher schedule:', error);
            throw error;
        }
    },

    // Check teacher schedule conflicts
    async checkScheduleConflicts(teacherId, schedule) {
        try {
            console.log('Checking teacher schedule conflicts:', teacherId, schedule);
            const response = await axios.post(`${API_URL}/teachers/${teacherId}/schedule/check-conflicts`, schedule);
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
    },

    // Get all sections assigned to a teacher
    getTeacherAssignments(teacherId) {
        const teacher = this.getTeacherById(teacherId);
        if (!teacher) return [];

        const assignments = [];

        for (const gradeAssignment of teacher.assignedGrades) {
            const grade = GradeService.getGradeById(gradeAssignment.gradeId);
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
    },

    // Get available sections that can be assigned to a teacher
    getAvailableSections(teacherId) {
        const allGrades = GradeService.getGrades();
        const teacher = this.getTeacherById(teacherId);

        if (!teacher) return [];

        const availableSections = [];

        for (const grade of allGrades) {
            const assignedGrade = teacher.assignedGrades.find((g) => g.gradeId === grade.id);
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
