import axios from '@/config/axios';

class TeacherAuthService {
    constructor() {
        this.baseURL = '/api/teacher';
        this.tokenKey = 'teacher_token';
        this.teacherKey = 'teacher_data';
    }

    /**
     * Teacher login
     */
    async login(username, password) {
        try {
            const response = await axios.post(`${this.baseURL}/login`, {
                username,
                password
            });

            if (response.data.success) {
                const { teacher, user, assignments, token } = response.data.data;
                
                // Store authentication data with timestamp
                const authData = {
                    teacher,
                    user,
                    assignments,
                    loginTime: new Date().toISOString()
                };
                
                localStorage.setItem(this.tokenKey, token);
                localStorage.setItem(this.teacherKey, JSON.stringify(authData));

                // Set default authorization header
                axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

                console.log('Teacher login successful, data stored:', {
                    teacherId: teacher.id,
                    username: user.username,
                    assignmentsCount: assignments.length
                });

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
            console.error('Login error:', error);
            return {
                success: false,
                message: error.response?.data?.message || 'Login failed'
            };
        }
    }

    /**
     * Teacher logout
     */
    async logout() {
        try {
            const token = this.getToken();
            if (token) {
                await axios.post(`${this.baseURL}/logout`);
            }
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            // Clear local storage and headers
            localStorage.removeItem(this.tokenKey);
            localStorage.removeItem(this.teacherKey);
            delete axios.defaults.headers.common['Authorization'];
        }
    }

    /**
     * Get current teacher profile
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
     * Get stored token
     */
    getToken() {
        // Try multiple storage locations
        let token = localStorage.getItem(this.tokenKey);
        if (!token) {
            token = sessionStorage.getItem(this.tokenKey);
        }
        if (!token && window.teacherAuth) {
            token = window.teacherAuth.token;
        }
        return token;
    }

    /**
     * Get stored teacher data
     */
    getTeacherData() {
        // Try multiple storage locations
        let data = localStorage.getItem(this.teacherKey);
        if (!data) {
            data = sessionStorage.getItem(this.teacherKey);
        }
        if (!data && window.teacherAuth) {
            return window.teacherAuth.data;
        }
        return data ? JSON.parse(data) : null;
    }

    /**
     * Get all teacher assignments
     */
    getAssignments() {
        const teacherData = this.getTeacherData();
        if (!teacherData || !teacherData.assignments) {
            return [];
        }
        return teacherData.assignments;
    }

    /**
     * Get teacher assignments grouped by type
     */
    getGroupedAssignments() {
        const teacherData = this.getTeacherData();
        if (!teacherData || !teacherData.assignments) {
            return {
                homeroom: [],
                subjects: []
            };
        }

        const assignments = teacherData.assignments;
        const grouped = {
            homeroom: [],
            subjects: []
        };

        assignments.forEach(assignment => {
            if (assignment.role === 'homeroom_teacher' || assignment.subject_name === 'Homeroom') {
                grouped.homeroom.push(assignment);
            } else if (assignment.subject_id && assignment.subject_name !== 'Homeroom') {
                grouped.subjects.push(assignment);
            }
        });

        return grouped;
    }

    /**
     * Get unique subjects taught by teacher
     */
    getUniqueSubjects() {
        const teacherData = this.getTeacherData();
        if (!teacherData || !teacherData.assignments) {
            return [];
        }

        const subjectMap = new Map();
        teacherData.assignments.forEach(assignment => {
            if (assignment.subject_id && assignment.subject_name) {
                subjectMap.set(assignment.subject_id, {
                    id: assignment.subject_id,
                    name: assignment.subject_name,
                    sections: []
                });
            }
        });

        // Add sections to each subject
        teacherData.assignments.forEach(assignment => {
            if (assignment.subject_id && subjectMap.has(assignment.subject_id)) {
                const subject = subjectMap.get(assignment.subject_id);
                subject.sections.push({
                    id: assignment.section_id,
                    name: assignment.section_name,
                    grade: assignment.grade_name,
                    assignment_id: assignment.assignment_id
                });
            }
        });

        return Array.from(subjectMap.values());
    }

    /**
     * Initialize authentication on app start
     */
    initializeAuth() {
        // Add delay to ensure localStorage is ready
        setTimeout(() => {
            const token = this.getToken();
            const teacherData = this.getTeacherData();
            
            console.log('TeacherAuthService.initializeAuth() called (delayed):');
            console.log('- Token found:', !!token);
            console.log('- Teacher data found:', !!teacherData);
            console.log('- All localStorage keys:', Object.keys(localStorage));
            
            if (token) {
                axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
                console.log('- Authorization header set');
            } else {
                console.log('- No token found, skipping header setup');
            }
        }, 100);
    }
}

export default new TeacherAuthService();
