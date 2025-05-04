import axios from 'axios';

const API_URL = 'http://localhost:8000/api'; // Adjust this to your Laravel backend URL

export default {
    // Registration
    registerStudent(studentData) {
        return axios.post(`${API_URL}/students/register`, studentData);
    },

    // Get students by status
    getStudentsByStatus(status) {
        return axios.get(`${API_URL}/students/status/${status}`);
    },

    // Update student status
    updateStudentStatus(studentId, status) {
        return axios.patch(`${API_URL}/students/${studentId}/status`, { status });
    },

    // Assign section to student
    assignSection(studentId, gradeLevel, section) {
        return axios.patch(`${API_URL}/students/${studentId}/assign-section`, {
            gradeLevel,
            section
        });
    },

    // Get all students
    getAllStudents() {
        return axios.get(`${API_URL}/students`);
    },

    // Get student by ID
    getStudentById(studentId) {
        return axios.get(`${API_URL}/students/${studentId}`);
    },

    // Update student
    updateStudent(studentId, studentData) {
        return axios.put(`${API_URL}/students/${studentId}`, studentData);
    },

    // Delete student
    deleteStudent(studentId) {
        return axios.delete(`${API_URL}/students/${studentId}`);
    }
};
