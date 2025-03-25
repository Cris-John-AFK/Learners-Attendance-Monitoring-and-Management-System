// This file contains centralized grade level data and related functionality
import { reactive } from 'vue';
import { AttendanceService } from './Students';
import { SubjectService } from './Subjects';

// Create a reactive state to store grades data
const state = reactive({
    grades: [
        {
            id: 'K',
            name: 'Kinder',
            sections: ['Mabini', 'Rizal', 'Sampaguita', 'Masipag']
        },
        {
            id: '1',
            name: 'Grade 1',
            sections: ['Sampaguita', 'Rosal', 'Makabayan', 'Matulungin']
        },
        {
            id: '2',
            name: 'Grade 2',
            sections: ['Bonifacio', 'Mabini', 'Masigasig', 'Malaya']
        },
        {
            id: '3',
            name: 'Grade 3',
            sections: ['Aguinaldo', 'Quezon', 'Mahinahon', 'Magalang']
        },
        {
            id: '4',
            name: 'Grade 4',
            sections: ['Del Pilar', 'Luna', 'Silangan', 'Mapagmahal']
        },
        {
            id: '5',
            name: 'Grade 5',
            sections: ['Orchid', 'Jasmine', 'Magiting', 'Matapat']
        },
        {
            id: '6',
            name: 'Grade 6',
            sections: ['Emerald', 'Ruby', 'Cattleya', 'Mayumi']
        }
    ],
    students: {}, // Store students by grade and section
    sectionDetails: [] // Store section metadata
});

export const GradeService = {
    // Get all grades
    getGrades() {
        return [...state.grades];
    },

    // Get a specific grade by ID
    getGradeById(id) {
        return state.grades.find((grade) => grade.id === id);
    },

    // Get a specific grade by name
    getGradeByName(name) {
        return state.grades.find((grade) => grade.name === name);
    },

    // Get sections for a specific grade
    getSectionsByGrade(gradeId) {
        const grade = this.getGradeById(gradeId);
        return grade ? [...grade.sections] : [];
    },

    // Get students in a specific section
    getStudentsInSection(gradeId, sectionName) {
        const key = `${gradeId}-${sectionName}`;
        return state.students[key] || [];
    },

    // Add students to a section
    addStudentsToSection(gradeId, sectionName, students) {
        const key = `${gradeId}-${sectionName}`;

        if (!Array.isArray(state.students[key])) {
            state.students[key] = [];
        }

        // Add students while avoiding duplicates
        students.forEach((student) => {
            const exists = state.students[key].some((s) => s.id === student.id);
            if (!exists) {
                state.students[key].push(student);
            }
        });

        return state.students[key];
    },

    // Create a new grade
    createGrade(grade) {
        if (!grade.id) {
            grade.id = (state.grades.length + 1).toString();
        }

        if (!grade.sections) {
            grade.sections = [];
        }

        state.grades.push(grade);
        return grade;
    },

    // Add a section to a grade
    createSection(gradeId, sectionName) {
        const grade = this.getGradeById(gradeId);
        if (!grade) {
            throw new Error(`Grade with ID ${gradeId} not found`);
        }

        if (!grade.sections.includes(sectionName)) {
            grade.sections.push(sectionName);
        }

        return grade;
    },

    // Get all sections with metadata
    async getAllSections() {
        // If we already have section data, return it
        if (state.sectionDetails.length > 0) {
            return [...state.sectionDetails]; // Return a copy
        }

        // Otherwise build the section store from grades
        const grades = await this.getGrades();
        state.sectionDetails = [];

        // For each grade and section, create a section object with metadata
        for (const grade of grades) {
            for (const sectionName of grade.sections) {
                state.sectionDetails.push({
                    id: `${grade.id}-${sectionName}`,
                    gradeId: grade.id,
                    gradeName: grade.name,
                    name: sectionName,
                    capacity: 40, // Default values
                    adviser: 'TBA',
                    room: `Room ${sectionName}`,
                    schedule: {
                        startTime: '08:00 AM',
                        endTime: '04:00 PM'
                    }
                });
            }
        }

        return [...state.sectionDetails]; // Return a copy
    },

    // Get students by grade
    async getStudentsByGrade(gradeId) {
        // Convert grade ID to grade level number for filtering
        let gradeLevel;

        if (gradeId === 'K') {
            gradeLevel = 0; // Assuming Kinder is represented as 0 in your student data
        } else {
            gradeLevel = parseInt(gradeId);
        }

        // Get all students and filter by grade level
        const allStudents = await AttendanceService.getData();
        return allStudents.filter((student) => student.gradeLevel === gradeLevel);
    },

    // Get subjects by grade
    async getSubjectsByGrade(gradeId) {
        // Get all subjects and filter by grade
        const allSubjects = await SubjectService.getSubjects();

        // Format grade ID to match the format in subjects
        let gradeName;
        if (gradeId === 'K') {
            gradeName = 'Kinder';
        } else {
            gradeName = `Grade ${gradeId}`;
        }

        return allSubjects.filter((subject) => subject.grade === gradeName);
    },

    // Get both students and subjects for a grade
    async getGradeDetails(gradeId) {
        const [students, subjects, sections] = await Promise.all([this.getStudentsByGrade(gradeId), this.getSubjectsByGrade(gradeId), this.getSectionsByGrade(gradeId)]);

        return {
            gradeId,
            students,
            subjects,
            sections
        };
    },

    // Create a new section with details
    async createSectionWithDetails(gradeId, sectionData) {
        // First, add the section name to the grade's sections list
        const grade = this.getGradeById(gradeId);
        if (!grade) {
            throw new Error(`Grade with ID ${gradeId} not found`);
        }

        if (!grade.sections.includes(sectionData.name)) {
            grade.sections.push(sectionData.name);
        }

        // Ensure section details are loaded
        await this.getAllSections();

        // Create the section object
        const newSection = {
            id: `${gradeId}-${sectionData.name}`,
            gradeId: gradeId,
            gradeName: gradeId === 'K' ? 'Kinder' : `Grade ${gradeId}`,
            name: sectionData.name,
            capacity: sectionData.capacity || 40,
            adviser: sectionData.adviser || 'TBA',
            room: sectionData.room || `Room ${sectionData.name}`,
            schedule: sectionData.schedule || {
                startTime: '08:00 AM',
                endTime: '04:00 PM'
            }
        };

        // Add to the sectionDetails
        state.sectionDetails.push(newSection);

        return { ...newSection };
    },

    // Update a section
    async updateSection(gradeId, sectionName, updatedData) {
        // Ensure section details are loaded
        await this.getAllSections();

        // Find and update the section
        const sectionId = `${gradeId}-${sectionName}`;
        const index = state.sectionDetails.findIndex((s) => s.id === sectionId);

        if (index >= 0) {
            state.sectionDetails[index] = { ...state.sectionDetails[index], ...updatedData };
            return { ...state.sectionDetails[index] };
        }

        throw new Error(`Section ${sectionName} in grade ${gradeId} not found`);
    },

    // Delete a section
    async deleteSection(gradeId, sectionName) {
        // Ensure section details are loaded
        await this.getAllSections();

        // Find and remove the section
        const sectionId = `${gradeId}-${sectionName}`;
        const index = state.sectionDetails.findIndex((s) => s.id === sectionId);

        if (index >= 0) {
            const removed = state.sectionDetails.splice(index, 1)[0];

            // Now update the grade's sections list
            const grade = this.getGradeById(gradeId);
            if (grade) {
                const sectionIndex = grade.sections.indexOf(sectionName);
                if (sectionIndex >= 0) {
                    grade.sections.splice(sectionIndex, 1);
                }
            }

            return { ...removed };
        }

        throw new Error(`Section ${sectionName} in grade ${gradeId} not found`);
    },

    // Clear the store (useful for testing or resetting)
    async clearStore() {
        state.sectionDetails = [];
    }
};
