import axios from 'axios';

class TeacherAuthService {
    constructor() {
        this.baseURL = 'http://localhost:8000/api/teachers';
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
        
        console.log('TeacherAuthService.isAuthenticated() check:');
        console.log('- Token exists:', !!token);
        console.log('- Teacher data exists:', !!teacherData);
        console.log('- Token value:', token ? token.substring(0, 20) + '...' : 'null');
        console.log('- Teacher data:', teacherData ? { id: teacherData.teacher?.id, username: teacherData.user?.username } : 'null');
        
        const isAuth = !!(token && teacherData);
        console.log('- Final authentication result:', isAuth);
        
        return isAuth;
    }

    /**
     * Get stored token for current tab
     */
    getToken() {
        const tabId = TeacherAuthService.getTabId();
        const tokenKey = `teacher_token_${tabId}`;
        return localStorage.getItem(tokenKey);
    }

    /**
     * Get stored teacher data for current tab
     */
    getTeacherData() {
        try {
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
        console.log('🔍 Teacher data for assignments:', teacherData);
        
        if (!teacherData || !teacherData.assignments) {
            console.log('❌ No assignments found in teacher data');
            return [];
        }
        
        console.log('✅ Found assignments:', teacherData.assignments);
        return teacherData.assignments;
    }

    /**
     * Get unique subjects for the teacher
     */
    getUniqueSubjects() {
        const assignments = this.getAssignments();
        const subjects = [];
        const seen = new Set();

        console.log('🔍 Processing assignments for unique subjects:', assignments);

        assignments.forEach(assignment => {
            const subjectId = assignment.subject_id;
            const subjectName = assignment.subject_name || 'Homeroom';
            
            if (!seen.has(subjectId)) {
                seen.add(subjectId);
                subjects.push({
                    id: subjectId,
                    name: subjectName,
                    sections: [{
                        id: assignment.section_id,
                        name: assignment.section_name,
                        grade: assignment.grade_name
                    }],
                    originalSubject: {
                        id: subjectId,
                        name: subjectName,
                        sectionId: assignment.section_id
                    }
                });
            }
        });

        console.log('✅ Unique subjects generated:', subjects);
        return subjects;
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
        console.log('TeacherAuthService.initializeAuth() called (delayed):');
        console.log('- Token found:', !!this.getToken());
        console.log('- Teacher data found:', !!this.getTeacherData());
        console.log('- All localStorage keys:', Object.keys(localStorage));
        
        const token = this.getToken();
        if (token) {
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            console.log('- Authorization header set');
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
        
        console.log('Auth data stored successfully for tab:', tabId);
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
        
        console.log('Cleared old authentication data');
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
