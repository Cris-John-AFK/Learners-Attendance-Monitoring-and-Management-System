// This file contains centralized grade level data and related functionality
import { AttendanceService } from './Students';
import { SubjectService } from './Subjects';

// Create an in-memory store for sections
let sectionStore = null;

export const GradeService = {
    // Get all grade levels
    async getGrades() {
        return [
            { id: 'K', name: 'Kinder', sections: ['Mabini', 'Rizal', 'Sampaguita', 'Masipag'] },
            { id: '1', name: 'Grade 1', sections: ['Sampaguita', 'Rosal', 'Makabayan', 'Matulungin'] },
            { id: '2', name: 'Grade 2', sections: ['Bonifacio', 'Mabini', 'Masigasig', 'Malaya'] },
            { id: '3', name: 'Grade 3', sections: ['Aguinaldo', 'Quezon', 'Mahinahon', 'Magalang'] },
            { id: '4', name: 'Grade 4', sections: ['Del Pilar', 'Luna', 'Silangan', 'Mapagmahal'] },
            { id: '5', name: 'Grade 5', sections: ['Orchid', 'Jasmine', 'Magiting', 'Matapat'] },
            { id: '6', name: 'Grade 6', sections: ['Emerald', 'Ruby', 'Cattleya', 'Mayumi'] }
        ];
    },

    // Get all sections with metadata
    async getAllSections() {
        // If we already have section data, return it
        if (sectionStore) {
            return [...sectionStore]; // Return a copy
        }

        // Otherwise build the section store from grades
        const grades = await this.getGrades();
        sectionStore = [];

        // For each grade and section, create a section object with metadata
        for (const grade of grades) {
            for (const sectionName of grade.sections) {
                sectionStore.push({
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

        return [...sectionStore]; // Return a copy
    },

    // Get sections for a specific grade
    async getSectionsByGrade(gradeId) {
        const allSections = await this.getAllSections();
        return allSections.filter((section) => section.gradeId === gradeId);
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

    // Create a new section
    async createSection(gradeId, sectionData) {
        // Ensure sectionStore is loaded
        if (!sectionStore) {
            await this.getAllSections();
        }

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

        // Add to the store
        sectionStore.push(newSection);

        // Now update the grade's sections list
        const grades = await this.getGrades();
        const gradeIndex = grades.findIndex((g) => g.id === gradeId);
        if (gradeIndex >= 0 && !grades[gradeIndex].sections.includes(sectionData.name)) {
            grades[gradeIndex].sections.push(sectionData.name);
        }

        return { ...newSection };
    },

    // Update a section
    async updateSection(gradeId, sectionName, updatedData) {
        // Ensure sectionStore is loaded
        if (!sectionStore) {
            await this.getAllSections();
        }

        // Find and update the section
        const sectionId = `${gradeId}-${sectionName}`;
        const index = sectionStore.findIndex((s) => s.id === sectionId);

        if (index >= 0) {
            sectionStore[index] = { ...sectionStore[index], ...updatedData };
            return { ...sectionStore[index] };
        }

        throw new Error(`Section ${sectionName} in grade ${gradeId} not found`);
    },

    // Delete a section
    async deleteSection(gradeId, sectionName) {
        // Ensure sectionStore is loaded
        if (!sectionStore) {
            await this.getAllSections();
        }

        // Find and remove the section
        const sectionId = `${gradeId}-${sectionName}`;
        const index = sectionStore.findIndex((s) => s.id === sectionId);

        if (index >= 0) {
            const removed = sectionStore.splice(index, 1)[0];

            // Now update the grade's sections list
            const grades = await this.getGrades();
            const gradeIndex = grades.findIndex((g) => g.id === gradeId);
            if (gradeIndex >= 0) {
                const sectionIndex = grades[gradeIndex].sections.indexOf(sectionName);
                if (sectionIndex >= 0) {
                    grades[gradeIndex].sections.splice(sectionIndex, 1);
                }
            }

            return { ...removed };
        }

        throw new Error(`Section ${sectionName} in grade ${gradeId} not found`);
    },

    // Get students by both grade and section
    async getStudentsInSection(gradeId, sectionName) {
        try {
            console.log('getStudentsInSection called with:', gradeId, sectionName);

            // Convert grade ID to grade level number for filtering
            let gradeLevel;
            if (gradeId === 'K') {
                gradeLevel = 0; // Kinder is typically 0
            } else {
                gradeLevel = parseInt(gradeId);
            }

            // Get all students from AttendanceService
            const allStudents = await AttendanceService.getData();
            console.log('All students from AttendanceService:', allStudents);

            // Filter students by grade level and section
            const sectionStudents = allStudents.filter((student) => student.gradeLevel === gradeLevel && student.section === sectionName);

            console.log('Filtered students for section:', sectionStudents);
            return sectionStudents;
        } catch (error) {
            console.error('Error in getStudentsInSection:', error);
            return [];
        }
    },

    // Clear the store (useful for testing or resetting)
    async clearStore() {
        sectionStore = null;
    }
};
