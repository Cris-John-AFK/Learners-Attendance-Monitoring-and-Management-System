import api from '@/config/axios';
import { reactive } from 'vue';

// Create a reactive state
const state = reactive({
    students: [],
    loaded: false
});

export const AttendanceService = {
    // Load students from API
    async getData() {
        try {
            // If we already have the data and it's loaded, return it
            if (state.loaded && state.students.length > 0) {
                return state.students;
            }

            // Get data from API using the correct endpoint
            const response = await api.get('/api/students');
            state.students = response.data;
            state.loaded = true;
            return state.students;
        } catch (error) {
            console.error('Error loading students:', error);
            throw error; // Re-throw to let caller handle the error
        }
    },

    // Get students by grade level
    async getStudentsByGrade(gradeLevel) {
        try {
            const response = await api.get(`/api/students/grade/${gradeLevel}`);
            return response.data;
        } catch (error) {
            console.error(`Error loading students for grade ${gradeLevel}:`, error);
            return [];
        }
    },

    // Get students by grade and section
    async getStudentsBySection(gradeLevel, section) {
        try {
            const response = await api.get(`/api/students/grade/${gradeLevel}/section/${section}`);
            return response.data;
        } catch (error) {
            console.error(`Error loading students for section ${section}:`, error);
            return [];
        }
    },

    // Add a new student
    async addStudent(student) {
        try {
            const response = await api.post('/api/students', student);
            state.students.push(response.data);
            return response.data;
        } catch (error) {
            console.error('Error adding student:', error);
            throw error;
        }
    },

    // Update a student
    async updateStudent(id, data) {
        try {
            const response = await api.put(`/api/students/${id}`, data);

            // Update the local state
            const index = state.students.findIndex((s) => s.id === id);
            if (index >= 0) {
                state.students[index] = response.data;
            }

            return response.data;
        } catch (error) {
            console.error(`Error updating student ${id}:`, error);
            throw error;
        }
    },

    // Delete a student
    async deleteStudent(id) {
        try {
            await api.delete(`/api/students/${id}`);

            // Remove from local state
            const index = state.students.findIndex((s) => s.id === id);
            if (index >= 0) {
                state.students.splice(index, 1);
            }

            return true;
        } catch (error) {
            console.error(`Error deleting student ${id}:`, error);
            throw error;
        }
    },

    // Get attendance history for a subject
    async getAttendanceForSubject(subjectName) {
        // This would typically fetch from a database
        // For now, return sample data
        return [
            {
                date: '2023-09-15',
                studentName: 'Maria Clara Santos',
                studentId: '1001',
                status: 'Present',
                time: '10:30:45 AM',
                remarks: ''
            },
            {
                date: '2023-09-15',
                studentName: 'Juan Dela Cruz',
                studentId: '1002',
                status: 'Late',
                time: '10:45:12 AM',
                remarks: 'Traffic'
            }
        ];
    },

    // Get a student by ID
    async getStudentById(id) {
        const students = await this.getData();
        return students.find((student) => student.id === parseInt(id));
    },

    // Create a new student
    async createStudent(student) {
        // Ensure studentStore is loaded
        if (!state.students) {
            await this.getData();
        }

        // Generate a unique ID if not provided
        if (!student.id) {
            const maxId = Math.max(...state.students.map((s) => parseInt(s.id)));
            student.id = maxId + 1;
        }

        // Add to the store
        state.students.push({ ...student });

        return { ...student };
    },

    // Import students from CSV/Excel (simulated)
    async importStudents(fileData) {
        // In a real implementation, this would process the file and add students
        console.log('Importing students from file');

        return {
            importedCount: fileData.length || 5, // Simulate importing 5 students
            success: true
        };
    },

    // Clear the store (useful for testing or resetting)
    async clearStore() {
        state.students = [];
        state.loaded = false;
    },

    async getStudentsInGrade(gradeId) {
        try {
            const students = await this.getData();
            return students.filter((student) => student.grade_level === gradeId);
        } catch (error) {
            console.error('Error getting students in grade:', error);
            throw error;
        }
    },

    // Get attendance records for a student
    async getAttendanceRecords(studentId) {
        try {
            const response = await api.get(`/api/students/${studentId}/attendance`);
            return response.data;
        } catch (error) {
            console.error(`Error loading attendance records for student ${studentId}:`, error);
            return [];
        }
    }
};
