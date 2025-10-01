import axios from 'axios';

const API_URL = 'http://127.0.0.1:8000/api/auth';

class AuthService {
    /**
     * Unified login for all user types (admin, teacher, guardhouse)
     */
    async login(email, password) {
        try {
            const response = await axios.post(`${API_URL}/login`, {
                email,
                password
            });

            if (response.data.success) {
                const {token, user, profile, session} = response.data.data;
                
                // Store authentication data
                this.setAuthData(token, user, profile, session);
                
                return {
                    success: true,
                    user,
                    profile,
                    role: user.role
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
                message: error.response?.data?.message || 'An error occurred during login'
            };
        }
    }

    /**
     * Logout and clear all session data
     */
    async logout() {
        try {
            const token = this.getToken();
            
            if (token) {
                await axios.post(`${API_URL}/logout`, {}, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
            }
        } catch (error) {
            console.error('Logout API error:', error);
        } finally {
            // Always clear local data even if API call fails
            this.clearAuthData();
        }
    }

    /**
     * Check if session is still valid
     */
    async checkSession() {
        try {
            const token = this.getToken();
            
            if (!token) {
                return {valid: false, message: 'No token found'};
            }

            const response = await axios.get(`${API_URL}/check-session`, {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });

            return response.data;
        } catch (error) {
            console.error('Check session error:', error);
            
            if (error.response?.data?.session_expired) {
                // Session expired, clear data
                this.clearAuthData();
            }
            
            return {
                valid: false,
                message: error.response?.data?.message || 'Session check failed'
            };
        }
    }

    /**
     * Get current user info from server
     */
    async me() {
        try {
            const token = this.getToken();
            
            if (!token) {
                return null;
            }

            const response = await axios.get(`${API_URL}/me`, {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });

            if (response.data.success) {
                return response.data.data;
            }

            return null;
        } catch (error) {
            console.error('Get user error:', error);
            return null;
        }
    }

    /**
     * Store authentication data in localStorage
     */
    setAuthData(token, user, profile, session) {
        localStorage.setItem('auth_token', token);
        localStorage.setItem('auth_user', JSON.stringify(user));
        localStorage.setItem('auth_profile', JSON.stringify(profile));
        localStorage.setItem('auth_session', JSON.stringify(session));
        
        // Store role-specific data for backward compatibility
        if (user.role === 'teacher') {
            localStorage.setItem('teacher_data', JSON.stringify({
                token,
                teacher: profile,
                user
            }));
        } else if (user.role === 'admin') {
            localStorage.setItem('admin_data', JSON.stringify({
                token,
                admin: profile,
                user
            }));
        } else if (user.role === 'guardhouse') {
            localStorage.setItem('guardhouse_data', JSON.stringify({
                token,
                guardhouse: profile,
                user
            }));
        }
    }

    /**
     * Clear all authentication data
     */
    clearAuthData() {
        // Clear unified auth data
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        localStorage.removeItem('auth_profile');
        localStorage.removeItem('auth_session');
        
        // Clear role-specific data
        localStorage.removeItem('teacher_data');
        localStorage.removeItem('admin_data');
        localStorage.removeItem('guardhouse_data');
        
        // Clear any other cached data
        const keys = Object.keys(localStorage);
        keys.forEach(key => {
            if (key.startsWith('attendance_') || key.startsWith('cache_')) {
                localStorage.removeItem(key);
            }
        });
    }

    /**
     * Get token from localStorage
     */
    getToken() {
        return localStorage.getItem('auth_token');
    }

    /**
     * Get current user from localStorage
     */
    getUser() {
        const userStr = localStorage.getItem('auth_user');
        return userStr ? JSON.parse(userStr) : null;
    }

    /**
     * Get current profile from localStorage
     */
    getProfile() {
        const profileStr = localStorage.getItem('auth_profile');
        return profileStr ? JSON.parse(profileStr) : null;
    }

    /**
     * Get current session info from localStorage
     */
    getSession() {
        const sessionStr = localStorage.getItem('auth_session');
        return sessionStr ? JSON.parse(sessionStr) : null;
    }

    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!this.getToken();
    }

    /**
     * Get user role
     */
    getUserRole() {
        const user = this.getUser();
        return user?.role || null;
    }

    /**
     * Check if user has specific role
     */
    hasRole(role) {
        return this.getUserRole() === role;
    }

    /**
     * Check if user is admin
     */
    isAdmin() {
        return this.hasRole('admin');
    }

    /**
     * Check if user is teacher
     */
    isTeacher() {
        return this.hasRole('teacher');
    }

    /**
     * Check if user is guardhouse
     */
    isGuardhouse() {
        return this.hasRole('guardhouse');
    }
}

export default new AuthService();
