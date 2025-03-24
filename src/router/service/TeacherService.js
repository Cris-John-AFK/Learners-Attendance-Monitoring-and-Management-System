// src/router/service/TeacherService.js

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

export const TeacherService = {
    // Get all teachers
    getTeachers() {
        return state.teachers;
    },

    // Get a teacher by ID
    getTeacherById(id) {
        return state.teachers.find((teacher) => teacher.id === id);
    },

    // Create a new teacher
    createTeacher(teacher) {
        const newId = Math.max(0, ...state.teachers.map((t) => t.id)) + 1;
        const newTeacher = {
            id: newId,
            ...teacher,
            assignedGrades: teacher.assignedGrades || []
        };
        state.teachers.push(newTeacher);
        return newTeacher;
    },

    // Update a teacher
    updateTeacher(id, updatedTeacher) {
        const index = state.teachers.findIndex((teacher) => teacher.id === id);
        if (index !== -1) {
            state.teachers[index] = { ...state.teachers[index], ...updatedTeacher };
            return state.teachers[index];
        }
        return null;
    },

    // Delete a teacher
    deleteTeacher(id) {
        const index = state.teachers.findIndex((teacher) => teacher.id === id);
        if (index !== -1) {
            state.teachers.splice(index, 1);
            return true;
        }
        return false;
    },

    // Assign a teacher to a grade and section
    assignTeacherToSection(teacherId, gradeId, sectionName) {
        const teacher = this.getTeacherById(teacherId);
        if (!teacher) return false;

        // Check if the grade exists in the teacher's assigned grades
        const gradeIndex = teacher.assignedGrades.findIndex((g) => g.gradeId === gradeId);

        if (gradeIndex === -1) {
            // Grade not assigned yet, add it with the section
            teacher.assignedGrades.push({
                gradeId,
                sections: [sectionName]
            });
        } else {
            // Grade already assigned, check if section is already in the list
            const assignedGrade = teacher.assignedGrades[gradeIndex];
            if (!assignedGrade.sections.includes(sectionName)) {
                assignedGrade.sections.push(sectionName);
            }
        }
        return true;
    },

    // Remove a teacher from a grade and section
    removeTeacherFromSection(teacherId, gradeId, sectionName) {
        const teacher = this.getTeacherById(teacherId);
        if (!teacher) return false;

        const gradeIndex = teacher.assignedGrades.findIndex((g) => g.gradeId === gradeId);
        if (gradeIndex === -1) return false;

        const assignedGrade = teacher.assignedGrades[gradeIndex];
        const sectionIndex = assignedGrade.sections.indexOf(sectionName);

        if (sectionIndex !== -1) {
            assignedGrade.sections.splice(sectionIndex, 1);

            // If no sections left in this grade, remove the grade assignment
            if (assignedGrade.sections.length === 0) {
                teacher.assignedGrades.splice(gradeIndex, 1);
            }

            return true;
        }

        return false;
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
