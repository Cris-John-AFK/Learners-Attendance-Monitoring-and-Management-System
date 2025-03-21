// src/router/service/TeacherService.js

export class TeacherService {
    static initialTeachers = [
        {
            id: 1,
            name: 'John Smith',
            image: 'teacher1.jpg',
            department: 'Mathematics',
            roomNumber: '101',
            status: 'ACTIVE',
            subjectsCount: 3,
            subjects: [
                {
                    id: 101,
                    name: 'Algebra II',
                    startDate: '2025-01-15',
                    sectionsCount: 2,
                    status: 'ACTIVE',
                    sections: [
                        { id: 1001, name: 'Section A', studentsCount: 25 },
                        { id: 1002, name: 'Section B', studentsCount: 23 }
                    ]
                },
                {
                    id: 102,
                    name: 'Calculus',
                    startDate: '2025-01-15',
                    sectionsCount: 1,
                    status: 'ACTIVE',
                    sections: [
                        { id: 1003, name: 'Section A', studentsCount: 18 }
                    ]
                },
                {
                    id: 103,
                    name: 'Statistics',
                    startDate: '2025-01-15',
                    sectionsCount: 1,
                    status: 'SCHEDULED',
                    sections: [
                        { id: 1004, name: 'Section A', studentsCount: 22 }
                    ]
                }
            ]
        },
        {
            id: 2,
            name: 'Mary Johnson',
            image: 'teacher2.jpg',
            department: 'Science',
            roomNumber: '205',
            status: 'ACTIVE',
            subjectsCount: 2,
            subjects: [
                {
                    id: 104,
                    name: 'Biology',
                    startDate: '2025-01-15',
                    sectionsCount: 2,
                    status: 'ACTIVE',
                    sections: [
                        { id: 1005, name: 'Section A', studentsCount: 24 },
                        { id: 1006, name: 'Section B', studentsCount: 25 }
                    ]
                },
                {
                    id: 105,
                    name: 'Chemistry',
                    startDate: '2025-01-15',
                    sectionsCount: 1,
                    status: 'ACTIVE',
                    sections: [
                        { id: 1007, name: 'Section A', studentsCount: 20 }
                    ]
                }
            ]
        }
    ];

    // Store teachers in localStorage to persist between page reloads
    static getLocalTeachers() {
        const storedTeachers = localStorage.getItem('teachers');
        if (storedTeachers) {
            return JSON.parse(storedTeachers);
        }
        // Initialize with default data if nothing in storage
        localStorage.setItem('teachers', JSON.stringify(this.initialTeachers));
        return this.initialTeachers;
    }

    static saveLocalTeachers(teachers) {
        localStorage.setItem('teachers', JSON.stringify(teachers));
    }

    // Get all teachers
    static getTeachers() {
        return this.getLocalTeachers();
    }

    // Get teacher by ID
    static getTeacherById(id) {
        const teachers = this.getLocalTeachers();
        return teachers.find(teacher => teacher.id === id);
    }

    // Create a new teacher
    static createTeacher(teacher) {
        const teachers = this.getLocalTeachers();

        // Generate new ID (would normally be done by backend)
        const newId = Math.max(...teachers.map(t => t.id), 0) + 1;

        // Create new teacher object with ID
        const newTeacher = {
            ...teacher,
            id: newId,
            subjects: [],
            subjectsCount: 0
        };

        // Add to collection
        teachers.push(newTeacher);
        this.saveLocalTeachers(teachers);

        return newTeacher;
    }

    // Update an existing teacher
    static updateTeacher(id, updatedTeacher) {
        const teachers = this.getLocalTeachers();
        const index = teachers.findIndex(t => t.id === id);

        if (index !== -1) {
            // Preserve subjects when updating teacher
            const existingSubjects = teachers[index].subjects || [];
            teachers[index] = {
                ...updatedTeacher,
                subjects: existingSubjects,
                subjectsCount: existingSubjects.length
            };

            this.saveLocalTeachers(teachers);
            return teachers[index];
        }

        throw new Error(`Teacher with ID ${id} not found`);
    }

    // Delete a teacher
    static deleteTeacher(id) {
        const teachers = this.getLocalTeachers();
        const filteredTeachers = teachers.filter(t => t.id !== id);

        if (filteredTeachers.length < teachers.length) {
            this.saveLocalTeachers(filteredTeachers);
            return { success: true };
        }

        throw new Error(`Teacher with ID ${id} not found`);
    }

    // Add a subject to a teacher
    static addSubject(teacherId, subject) {
        const teachers = this.getLocalTeachers();
        const teacherIndex = teachers.findIndex(t => t.id === teacherId);

        if (teacherIndex !== -1) {
            // Generate new ID for subject
            const subjects = teachers[teacherIndex].subjects || [];
            const newSubjectId = subjects.length > 0
                ? Math.max(...subjects.map(s => s.id), 0) + 1
                : 201;

            // Create new subject with ID
            const newSubject = {
                ...subject,
                id: newSubjectId,
                sections: [],
                sectionsCount: 0
            };

            // Add sections if initial count > 0
            if (newSubject.sectionsCount > 0) {
                for (let i = 0; i < newSubject.sectionsCount; i++) {
                    newSubject.sections.push({
                        id: Date.now() + i,
                        name: `Section ${String.fromCharCode(65 + i)}`, // A, B, C, etc.
                        studentsCount: 0
                    });
                }
            }

            // Add to teacher's subjects
            if (!teachers[teacherIndex].subjects) {
                teachers[teacherIndex].subjects = [];
            }

            teachers[teacherIndex].subjects.push(newSubject);
            teachers[teacherIndex].subjectsCount = teachers[teacherIndex].subjects.length;

            this.saveLocalTeachers(teachers);
            return newSubject;
        }

        throw new Error(`Teacher with ID ${teacherId} not found`);
    }

    // Update a teacher's subject
    static updateSubject(teacherId, subjectId, updatedSubject) {
        const teachers = this.getLocalTeachers();
        const teacherIndex = teachers.findIndex(t => t.id === teacherId);

        if (teacherIndex !== -1) {
            const subjects = teachers[teacherIndex].subjects || [];
            const subjectIndex = subjects.findIndex(s => s.id === subjectId);

            if (subjectIndex !== -1) {
                // Preserve sections when updating
                const existingSections = subjects[subjectIndex].sections || [];

                subjects[subjectIndex] = {
                    ...updatedSubject,
                    sections: existingSections,
                    sectionsCount: existingSections.length
                };

                teachers[teacherIndex].subjects = subjects;
                this.saveLocalTeachers(teachers);

                return subjects[subjectIndex];
            }

            throw new Error(`Subject with ID ${subjectId} not found`);
        }

        throw new Error(`Teacher with ID ${teacherId} not found`);
    }

    // Add a section to a subject
    static addSection(teacherId, subjectId, section) {
        const teachers = this.getLocalTeachers();
        const teacherIndex = teachers.findIndex(t => t.id === teacherId);

        if (teacherIndex !== -1) {
            const subjects = teachers[teacherIndex].subjects || [];
            const subjectIndex = subjects.findIndex(s => s.id === subjectId);

            if (subjectIndex !== -1) {
                if (!subjects[subjectIndex].sections) {
                    subjects[subjectIndex].sections = [];
                }

                // Generate new section ID
                const newSectionId = subjects[subjectIndex].sections.length > 0
                    ? Math.max(...subjects[subjectIndex].sections.map(s => s.id), 0) + 1
                    : 3001;

                // Create new section with ID
                const newSection = {
                    ...section,
                    id: newSectionId
                };

                // Add to subject's sections
                subjects[subjectIndex].sections.push(newSection);
                subjects[subjectIndex].sectionsCount = subjects[subjectIndex].sections.length;

                this.saveLocalTeachers(teachers);
                return newSection;
            }

            throw new Error(`Subject with ID ${subjectId} not found`);
        }

        throw new Error(`Teacher with ID ${teacherId} not found`);
    }

    // Get sections for a subject
    static getSections(teacherId, subjectId) {
        const teachers = this.getLocalTeachers();
        const teacher = teachers.find(t => t.id === teacherId);

        if (teacher) {
            const subject = teacher.subjects?.find(s => s.id === subjectId);

            if (subject) {
                return subject.sections || [];
            }

            throw new Error(`Subject with ID ${subjectId} not found`);
        }

        throw new Error(`Teacher with ID ${teacherId} not found`);
    }
}