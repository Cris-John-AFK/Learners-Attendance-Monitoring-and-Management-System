import axios from 'axios';

const API_URL = (import.meta.env.VITE_API_BASE_URL || '') + '/api';

export default {
    // Registration
    registerStudent(studentData) {
        return axios.post(`${API_URL}/student-details/register`, studentData);
    },

    // Get students by status
    getStudentsByStatus(status) {
        return axios.get(`${API_URL}/student-details/status/${status}`);
    },

    // Update student status
    updateStudentStatus(studentId, status) {
        return axios.patch(`${API_URL}/student-details/${studentId}/status`, { status });
    },

    // Assign section to student
    assignSection(studentId, gradeLevel, section) {
        return axios.patch(`${API_URL}/student-details/${studentId}/assign-section`, {
            gradeLevel,
            section
        });
    },

    // Get all students
    getAllStudents() {
        return axios.get(`${API_URL}/student-details`);
    },

    // Get student by ID
    getStudentById(studentId) {
        return axios.get(`${API_URL}/student-details/${studentId}`);
    },

    // Update student
    updateStudent(studentId, studentData) {
        return axios.put(`${API_URL}/student-details/${studentId}`, studentData);
    },

    // Delete student
    deleteStudent(studentId) {
        return axios.delete(`${API_URL}/student-details/${studentId}`);
    }
};
