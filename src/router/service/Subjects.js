// This file will contain centralized subject data
import { AttendanceService } from './Students';

export const SubjectService = {
    async getSubjects() {
        // Sample subject data
        return [
            { id: 'MATH101', name: 'Mathematics', grade: 'Grade 1' },
            { id: 'ENG101', name: 'English', grade: 'Grade 1' },
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
    }
};
