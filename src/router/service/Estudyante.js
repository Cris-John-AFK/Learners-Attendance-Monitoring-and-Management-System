import axios from 'axios';
import { reactive } from 'vue';

// Define the API base URL
const API_URL = 'http://localhost:8000/api';

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
                return [...state.students];
            }

            // If we're in development, return mock data until API is ready
            // Remove this when your API is working
            if (process.env.NODE_ENV !== 'production') {
                console.log('Using mock student data');
                const mockData = generateMockStudents();
                state.students = mockData;
                state.loaded = true;
                return [...mockData];
            }

            // API call when backend is ready
            const response = await axios.get(`${API_URL}/student-details`);
            state.students = response.data;
            state.loaded = true;
            return [...state.students];
        } catch (error) {
            console.error('Error loading students:', error);
            // Fallback to mock data on error
            const mockData = generateMockStudents();
            state.students = mockData;
            state.loaded = true;
            return [...mockData];
        }
    },

    // Get students by subject code
    async getStudentsBySubject(subjectCode) {
        try {
            // Try to get from API first
            try {
                const response = await axios.get(`${API_URL}/subjects/${subjectCode}/student-details`);
                return response.data;
            } catch (apiError) {
                console.warn(`API not available for subject ${subjectCode} students:`, apiError);
                // Fall back to mock data
            }

            // If API fails or we're in development, use mock data
            console.log('Using mock student data for subject:', subjectCode);
            const allStudents = await this.getData();

            // Filter students randomly for this subject (mock implementation)
            // In a real implementation, this would filter based on enrollment data
            return allStudents.filter(() => Math.random() > 0.3); // Randomly select ~70% of students
        } catch (error) {
            console.error(`Error loading students for subject ${subjectCode}:`, error);
            return [];
        }
    },

    // Get students by grade level
    async getStudentsByGrade(gradeLevel) {
        try {
            const response = await axios.get(`${API_URL}/student-details/grade/${gradeLevel}`);
            return response.data;
        } catch (error) {
            console.error(`Error loading students for grade ${gradeLevel}:`, error);
            return [];
        }
    },

    // Get students by grade and section
    async getStudentsBySection(gradeLevel, section) {
        try {
            const response = await axios.get(`${API_URL}/student-details/grade/${gradeLevel}/section/${section}`);
            return response.data;
        } catch (error) {
            console.error(`Error loading students for section ${section}:`, error);
            return [];
        }
    },

    // Add a new student
    async addStudent(student) {
        try {
            const response = await axios.post(`${API_URL}/student-details`, student);
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
            const response = await axios.put(`${API_URL}/student-details/${id}`, data);

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
            await axios.delete(`${API_URL}/student-details/${id}`);

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

    // Create a new studenn
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
    }
};

// Mock data generator function
function generateMockStudents() {
    const sections = ['Mabini', 'Rizal', 'Masipag', 'Makabayan', 'Magalang', 'Matulungin'];
    const students = [];

    // Generate 200 mock students
    for (let i = 1; i <= 30; i++) {
        const gradeLevel = Math.floor(Math.random() * 7); // 0-6 (Kinder to Grade 6)
        const section = sections[Math.floor(Math.random() * sections.length)];

        students.push({
            id: i,
            name: `Student ${i}`,
            gradeLevel: gradeLevel,
            section: section,
            studentId: `S${i.toString().padStart(4, '0')}`,
            gender: Math.random() > 0.5 ? 'Male' : 'Female',
            contactInfo: `09${Math.floor(Math.random() * 1000000000)
                .toString()
                .padStart(9, '0')}`,
            parentName: `Parent of Student ${i}`,
            parentContact: `09${Math.floor(Math.random() * 1000000000)
                .toString()
                .padStart(9, '0')}`,
            profilePhoto: null
        });
    }

    return students;
}
