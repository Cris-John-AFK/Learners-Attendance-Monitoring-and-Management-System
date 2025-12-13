// API Configuration
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '';

export const API_ENDPOINTS = {
    AUTH: `${API_BASE_URL}/api/auth`,
    TEACHERS: `${API_BASE_URL}/api/teachers`,
    STUDENTS: `${API_BASE_URL}/api/students`,
    ATTENDANCE: `${API_BASE_URL}/api/attendance`,
    REPORTS: `${API_BASE_URL}/api/teacher/reports`
};

console.log('🌐 API Base URL:', API_BASE_URL);
