import axios from 'axios';

class TeacherAuthService {
    constructor() {
        const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '';
        this.baseURL = apiBaseUrl + '/api/teachers';
        this.teacherKey = 'teacher_data';
        this.tokenKey = 'teacher_token';

        // Clear old authentication data on initialization (only once)
        if (!sessionStorage.getItem('auth_cleaned')) {
            TeacherAuthService.clearOldAuthData();
            sessionStorage.setItem('auth_cleaned', 'true');
        }
    }

    /**
     * Teacher login
     */
    async login(username, password) {
        try {
            console.log('🔐 Starting login process for:', username);
            const response = await axios.post(`${this.baseURL}/login`, {
                username,
                password
            });

            console.log('📡 Login API response:', response.data);

            if (response.data.success) {
                const { teacher, user, assignments, token } = response.data.data;

                // Store authentication data with timestamp
                const authData = {
                    teacher,
                    user,
                    assignments,
                    loginTime: new Date().toISOString()
                };

                // Store teacher data and token with tab-specific keys
                TeacherAuthService.setAuthData(authData, token);

                console.log('Auth data stored successfully for tab:', TeacherAuthService.getTabId());
                return {
                    success: true,
                    data: { teacher, user, assignments }
                };
            }

            return {
                success: false,
                message: response.data.message || 'Login failed'
            };
        } catch (error) {
            console.error('🚨 Login error:', error);
            console.error('🚨 Error response:', error.response?.data);
            console.error('🚨 Error status:', error.response?.status);
            return {
                success: false,
                message: error.response?.data?.message || error.message || 'Login failed'
            };
        }
    }

    /**
     * Teacher logout
     */
    async logout() {
        try {
            const tabId = TeacherAuthService.getTabId();
            const teacherKey = `teacher_data_${tabId}`;
            const tokenKey = `teacher_token_${tabId}`;

            // Remove tab-specific data
            localStorage.removeItem(teacherKey);
            localStorage.removeItem(tokenKey);
            sessionStorage.removeItem('tab_id');

            delete axios.defaults.headers.common['Authorization'];

            console.log('Logged out from tab:', tabId);
        } catch (error) {
            console.error('Logout error:', error);
        }
    }

    /**
     * Get profile
     */
    async getProfile() {
        try {
            const response = await axios.get(`${this.baseURL}/profile`);

            if (response.data.success) {
                return {
                    success: true,
                    data: response.data.data
                };
            }

            return {
                success: false,
                message: response.data.message || 'Failed to get profile'
            };
        } catch (error) {
            console.error('Profile error:', error);

            // If API call fails due to authentication, clear stored data
            if (error.response?.status === 401 || error.response?.status === 403) {
                console.log('API authentication failed, clearing stored data');
                this.logout();
            }

            return {
                success: false,
                message: error.response?.data?.message || 'Failed to get profile'
            };
        }
    }

    /**
     * Check if teacher is authenticated
     */
    isAuthenticated() {
        const token = this.getToken();
        const teacherData = this.getTeacherData();

        const isAuth = !!(token && teacherData);

        return isAuth;
    }

    /**
     * Get stored token (check both unified auth and tab-specific)
     */
    getToken() {
        // First check unified auth token
        const unifiedToken = localStorage.getItem('auth_token');
        if (unifiedToken) {
            return unifiedToken;
        }

        // Fallback to tab-specific token
        const tabId = TeacherAuthService.getTabId();
        const tokenKey = `teacher_token_${tabId}`;
        return localStorage.getItem(tokenKey);
    }

    /**
     * Get stored teacher data (check both unified auth and tab-specific)
     */
    getTeacherData() {
        try {
            // First check unified auth data
            const unifiedData = localStorage.getItem('teacher_data');
            if (unifiedData) {
                return JSON.parse(unifiedData);
            }

            // Fallback to tab-specific data
            const tabId = TeacherAuthService.getTabId();
            const teacherKey = `teacher_data_${tabId}`;
            const data = localStorage.getItem(teacherKey);
            return data ? JSON.parse(data) : null;
        } catch (error) {
            console.error('Error parsing teacher data:', error);
            return null;
        }
    }

    /**
     * Get all teacher assignments
     */
    getAssignments() {
        const teacherData = this.getTeacherData();
        if (!teacherData || !teacherData.assignments) {
            return [];
        }

        // Found assignments
        return teacherData.assignments;
    }

    /**
     * Get unique subjects for the teacher
     * For departmentalized teachers (Grade 4-6), returns subjects with section names
     * For homeroom teachers (Kinder-Grade 3), returns just the subject name
     */
    getUniqueSubjects() {
        const teacherData = this.getTeacherData();
        const assignments = this.getAssignments();
        const subjectSectionMap = new Map(); // Map: subjectId -> array of sections

        // CRITICAL: Don't add "Homeroom" as a subject - it's just a section assignment
        const homeroomSeen = new Set();
        if (teacherData?.teacher?.homeroom_section) {
            homeroomSeen.add('homeroom');
            homeroomSeen.add(null);
        }

        // Group assignments by subject, collecting all sections for each subject
        assignments.forEach((assignment) => {
            const subjectId = assignment.subject_id;

            // Skip homeroom assignments (subject_id = null)
            if (!subjectId || homeroomSeen.has(subjectId)) {
                return;
            }

            // Extract subject name
            let subjectName = 'Unknown Subject';
            if (assignment.subject_name) {
                subjectName = assignment.subject_name;
            } else if (assignment.subject && assignment.subject.name) {
                subjectName = assignment.subject.name;
            }

            // Extract grade name
            let gradeName = 'Unknown';
            if (assignment.grade_name) {
                gradeName = assignment.grade_name;
            } else if (assignment.section && assignment.section.curriculum_grade && assignment.section.curriculum_grade.grade) {
                gradeName = assignment.section.curriculum_grade.grade.name;
            } else if (assignment.section && assignment.section.grade) {
                gradeName = assignment.section.grade.name;
            }

            // Clean grade name
            if (gradeName.startsWith('Grade ')) {
                gradeName = gradeName.replace('Grade ', '');
            }

            // Extract section info
            const sectionName = assignment.section_name || (assignment.section && assignment.section.name) || 'Unknown Section';
            const sectionId = assignment.section_id || (assignment.section && assignment.section.id);

            // Create unique key for subject+section combination
            const key = `${subjectId}_${sectionId}`;

            if (!subjectSectionMap.has(key)) {
                subjectSectionMap.set(key, {
                    id: subjectId,
                    name: subjectName,
                    sectionId: sectionId,
                    sectionName: sectionName,
                    grade: gradeName,
                    originalSubject: {
                        id: subjectId,
                        name: subjectName,
                        sectionId: sectionId
                    }
                });
            }
        });

        return Array.from(subjectSectionMap.values());
    }

    /**
     * Get current teacher info
     */
    getCurrentTeacher() {
        const teacherData = this.getTeacherData();
        return teacherData ? teacherData.teacher : null;
    }

    /**
     * Get current user info
     */
    getCurrentUser() {
        const teacherData = this.getTeacherData();
        return teacherData ? teacherData.user : null;
    }

    /**
     * Initialize authentication state
     */
    initializeAuth() {
        const token = this.getToken();
        if (token) {
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }
    }

    /**
     * Store teacher data and token with tab-specific keys
     */
    static setAuthData(teacherData, token) {
        const tabId = this.getTabId();
        const teacherKey = `teacher_data_${tabId}`;
        const tokenKey = `teacher_token_${tabId}`;

        localStorage.setItem(teacherKey, JSON.stringify(teacherData));
        localStorage.setItem(tokenKey, token);
        localStorage.setItem('current_teacher_tab', tabId);

        // Set axios default header for future requests
        if (axios.defaults.headers) {
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }

        // Auth data stored
    }

    /**
     * Generate unique tab ID
     */
    static getTabId() {
        let tabId = sessionStorage.getItem('tab_id');
        if (!tabId) {
            tabId = 'tab_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            sessionStorage.setItem('tab_id', tabId);
        }
        return tabId;
    }

    /**
     * Clear old authentication data (for migration)
     */
    static clearOldAuthData() {
        // Remove old non-tab-specific keys
        localStorage.removeItem('teacher_data');
        localStorage.removeItem('teacher_token');
        localStorage.removeItem('current_teacher_tab');

        // Cleared old data
    }

    /**
     * Get stored teacher data for current tab (static version)
     */
    static getTeacherData() {
        try {
            const tabId = this.getTabId();
            const teacherKey = `teacher_data_${tabId}`;
            const data = localStorage.getItem(teacherKey);
            return data ? JSON.parse(data) : null;
        } catch (error) {
            console.error('Error parsing teacher data:', error);
            return null;
        }
    }

    /**
     * Get stored token for current tab (static version)
     */
    static getToken() {
        const tabId = this.getTabId();
        const tokenKey = `teacher_token_${tabId}`;
        return localStorage.getItem(tokenKey);
    }
}

export default new TeacherAuthService();
