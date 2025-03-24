// This file will contain centralized subject data
import { AttendanceService } from './Students';

// Create an in-memory store
let subjectStore = null;

export const SubjectService = {
    async getSubjects() {
        // If we already have the data in our store, return it
        if (subjectStore) {
            return [...subjectStore]; // Return a copy
        }

        // Otherwise populate the store
        subjectStore = [
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

        return [...subjectStore]; // Return a copy
    },

    async getSectionsByGrade(grade) {
        // Sample sections data
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
            // Add more sections as needed
        ];

        // Filter sections by grade
        return allSections.filter((section) => section.grade === grade);
    },

    async getStudentsBySection(sectionId) {
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

    async updateSectionDate(sectionId, newDate) {
        // This would update the section date in a real database
        console.log(`Updated section ${sectionId} date to ${newDate}`);
        return { success: true, sectionId, newDate };
    },

    async updateStudentStatus(sectionId, studentId, status, remarks = '') {
        // This would update a student's attendance status in a real database
        console.log(`Updated student ${studentId} in section ${sectionId} to ${status}`);

        // Also update the attendance record using AttendanceService
        await AttendanceService.recordAttendance(studentId, {
            date: new Date().toISOString().split('T')[0],
            status: status,
            time: new Date().toLocaleTimeString(),
            remarks: remarks
        });

        return { success: true, sectionId, studentId, status, remarks };
    },

    // Get attendance records for a specific section
    async getSectionAttendance(sectionId) {
        // In a real app this would fetch from database
        // For now return empty records that will be filled as attendance is taken
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
        const subjects = await this.getSubjects();
        // Extract unique grades from subjects
        const grades = [...new Set(subjects.map((subject) => subject.grade))];
        return grades;
    },

    // Get sections by both grade and subject
    async getSectionsByGradeAndSubject(gradeName, subjectName) {
        const allSections = await this.getSectionsByGrade(gradeName);
        return allSections.filter((section) => section.subject === subjectName);
    },

    // Create a new subject
    async createSubject(subject) {
        // Ensure subjectStore is loaded
        if (!subjectStore) {
            await this.getSubjects();
        }

        // Generate ID if not provided
        if (!subject.id) {
            subject.id = `${subject.name.substring(0, 4).toUpperCase()}${subject.grade.replace('Grade ', '')}`;
        }

        // Add to store
        subjectStore.push({ ...subject });

        return { ...subject };
    },

    // Update curriculum for a subject
    async updateCurriculum(subjectId, curriculum) {
        // This would connect to a backend in a real implementation
        console.log(`Updated curriculum for subject ${subjectId}`);
        return {
            subjectId,
            curriculum,
            success: true
        };
    },

    // Update subject
    async updateSubject(id, updatedData) {
        // Ensure subjectStore is loaded
        if (!subjectStore) {
            await this.getSubjects();
        }

        // Find and update the subject
        const index = subjectStore.findIndex((s) => s.id === id);
        if (index >= 0) {
            subjectStore[index] = { ...subjectStore[index], ...updatedData };
            return { ...subjectStore[index] };
        }

        throw new Error(`Subject with ID ${id} not found`);
    },

    // Delete subject
    async deleteSubject(id) {
        // Ensure subjectStore is loaded
        if (!subjectStore) {
            await this.getSubjects();
        }

        // Find and remove the subject
        const index = subjectStore.findIndex((s) => s.id === id);
        if (index >= 0) {
            const removed = subjectStore.splice(index, 1)[0];
            return { ...removed };
        }

        throw new Error(`Subject with ID ${id} not found`);
    },

    // Get subject by ID
    async getSubjectById(id) {
        const subjects = await this.getSubjects();
        return subjects.find((subject) => subject.id === id);
    },

    // Clear the store (useful for testing or resetting)
    async clearStore() {
        subjectStore = null;
    },

    // Get unique subjects (one per name)
    async getUniqueSubjects() {
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
    },

    // Add this method to your SubjectService if it doesn't exist
    async getSubjectsByGrade(gradeId) {
        try {
            const allSubjects = await this.getSubjects();

            // Filter subjects by grade
            // Assuming your subject objects have a grade or gradeId property
            return allSubjects.filter((subject) => {
                // Handle different formats of grade property
                const subjectGrade = subject.grade || subject.gradeId;

                // It could be "Grade 3" or just "3" or the ID
                if (subjectGrade === gradeId) return true;
                if (subjectGrade === `Grade ${gradeId}`) return true;

                return false;
            });
        } catch (error) {
            console.error('Error getting subjects by grade:', error);
            return [];
        }
    }
};
