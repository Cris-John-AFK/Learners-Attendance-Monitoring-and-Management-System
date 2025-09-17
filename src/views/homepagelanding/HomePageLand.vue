<template>
    <div class="login-page">
        <!-- Left side with background -->
        <div class="left-side">
            <div class="left-content">
                <div class="logo-text">NCS</div>
                <h1 class="brand-title">LAMMS</h1>
                <p class="brand-subtitle">Learners Attendance Monitoring and Management System</p>
                <p class="brand-description">Learner's Academy. A strong start for a lifetime of learning</p>
            </div>
            <div class="overlay"></div>
        </div>

        <!-- Right side with login form -->
        <div class="right-side">
            <div class="login-container">
                <h2 class="welcome-text">Welcome</h2>
                <p class="login-subtitle">Login with Email</p>

                <form @submit.prevent="handleLogin" class="login-form">
                    <div v-if="errorMessage" class="error-message">
                        <i class="pi pi-exclamation-triangle warning-icon"></i>
                        {{ errorMessage }}
                    </div>

                    <div class="form-group">
                        <div class="input-icon">
                            <i class="pi pi-user"></i>
                            <input type="text" id="username" v-model="username" placeholder="Username or Email Address" required />
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-icon">
                            <i class="pi pi-lock"></i>
                            <input type="password" id="password" v-model="password" placeholder="Password" required />
                        </div>
                    </div>

                    <div class="form-footer">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" v-model="rememberMe" />
                            <label for="remember">Remember me</label>
                        </div>
                    </div>

                    <button type="submit" class="login-button" :disabled="isLoading">
                        {{ isLoading ? 'Logging in...' : 'LOGIN' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import TeacherAuthService from '@/services/TeacherAuthService';
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/config/axios';

const router = useRouter();
const username = ref('');
const password = ref('');
const rememberMe = ref(false);
const isLoading = ref(false);
const errorMessage = ref('');


const handleLogin = async () => {
    try {
        isLoading.value = true;
        errorMessage.value = '';

        if (!username.value || !password.value) {
            errorMessage.value = 'Please enter both username and password';
            return;
        }

        // Check if this is a teacher login attempt first
        if (username.value.includes('.')) {
            try {
                // Authenticate with the backend API
                const response = await api.post('/api/teacher/login', {
                    username: username.value,
                    password: password.value
                });

                if (response.data.success) {
                    const teacherData = response.data.data;
                    const token = teacherData.token;

                    // Store authentication data
                    localStorage.setItem('teacher_token', token);
                    localStorage.setItem('teacher_data', JSON.stringify(teacherData));
                    sessionStorage.setItem('teacher_token', token);
                    sessionStorage.setItem('teacher_data', JSON.stringify(teacherData));
                    window.teacherAuth = { token, data: teacherData };

                    console.log('Teacher login successful via API:', teacherData);

                    // Redirect to teacher dashboard
                    window.location.href = '/teacher';
                    return;
                }
            } catch (apiError) {
                console.error('Teacher login failed:', apiError);
                
                if (apiError.response?.status === 422) {
                    errorMessage.value = 'Invalid username or password';
                } else if (apiError.response?.status === 404) {
                    errorMessage.value = 'Teacher profile not found';
                } else {
                    errorMessage.value = 'Login failed. Please check your credentials and try again.';
                }
                return;
            }
        }

        // Hardcoded authentication for different user types
        const validCredentials = {
            admin: { password: 'admin', dashboard: '/admin' },
            teacher: { password: 'teacher', dashboard: '/teacher' },
            guardian: { password: 'guardian', dashboard: '/guest' },
            gate: { password: 'gate', dashboard: '/guardhouse' }
        };

        const userType = username.value.toLowerCase();

        if (!validCredentials[userType]) {
            errorMessage.value = 'Invalid username. Please use: admin, teacher, guardian, gate, or teacher credentials (e.g., maria.santos)';
            return;
        }

        if (password.value !== validCredentials[userType].password) {
            errorMessage.value = 'Invalid password. Password should match the username.';
            return;
        }

        // Store user info
        const storage = rememberMe.value ? localStorage : sessionStorage;
        storage.setItem(
            'user',
            JSON.stringify({
                username: username.value,
                type: userType,
                authenticated: true
            })
        );

        // Redirect to appropriate dashboard
        router.push(validCredentials[userType].dashboard);
    } catch (error) {
        console.error('Login error:', error);
        errorMessage.value = 'An error occurred during login. Please try again.';
    } finally {
        isLoading.value = false;
    }
};
</script>

<style scoped>
.login-page {
    display: flex;
    min-height: 100vh;
    background: #fff;
}

.left-side {
    flex: 0.45;
    padding: 4rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    background-image: url('https://images.pexels.com/photos/998067/pexels-photo-998067.jpeg');
    background-size: cover;
    background-position: center;
    color: white;
}

.overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(177, 240, 247, 0.9) 0%, rgba(129, 191, 218, 0.8) 100%);
    z-index: 1;
}

.teacher-hint {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 20px;
}

.hint-text {
    margin: 4px 0;
    font-size: 14px;
    color: #495057;
}

.left-content {
    position: relative;
    z-index: 2;
    padding: 2rem;
}

.logo-text {
    font-size: 2.2rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 1.5rem;
    letter-spacing: 3px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.brand-title {
    font-size: 5.5rem;
    font-weight: 800;
    margin-bottom: 2rem;
    color: #fff;
    text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
    letter-spacing: 3px;
}

.brand-subtitle {
    font-size: 1.8rem;
    color: #fff;
    line-height: 1.8;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    font-weight: 500;
}

.brand-description {
    font-size: 1.3rem;
    color: rgba(255, 255, 255, 0.95);
    line-height: 1.7;
    margin-top: 2rem;
    max-width: 450px;
    font-weight: 400;
}

.right-side {
    flex: 0.55;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem;
    background: #fff;
}

.login-container {
    width: 100%;
    max-width: 520px;
    padding: 3.5rem;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.welcome-text {
    font-size: 3.2rem;
    color: #333;
    margin-bottom: 1rem;
    font-weight: 600;
    text-align: center;
}

.login-subtitle {
    color: #555;
    margin-bottom: 2.5rem;
    font-size: 1.4rem;
    text-align: center;
    font-weight: 500;
}

.input-icon {
    position: relative;
    display: flex;
    align-items: center;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 1.2rem 1rem;
    transition: all 0.3s ease;
    background: #fafafa;
}

.input-icon:hover {
    border-color: #81bfda;
    background: #fff;
}

.input-icon:focus-within {
    border-color: #81bfda;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(129, 191, 218, 0.1);
}

.input-icon i {
    color: #666;
    margin-right: 1rem;
    font-size: 1.3rem;
}

.input-icon input {
    border: none;
    outline: none;
    width: 100%;
    font-size: 1.2rem;
    padding: 0.5rem 0;
    color: #333;
    background: transparent;
}

.input-icon input::placeholder {
    color: #999;
    font-size: 1.1rem;
}

.form-group {
    margin-bottom: 2rem;
}

.form-footer {
    margin-bottom: 2rem;
}

.remember-me {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.remember-me input[type='checkbox'] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.remember-me label {
    font-size: 1.1rem;
    color: #555;
    cursor: pointer;
    font-weight: 500;
}

.login-button {
    width: 100%;
    padding: 1.4rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1.3rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.login-button:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
}

.login-button:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.error-message {
    color: #dc3545;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
    font-size: 1.1rem;
}

.warning-icon {
    color: #dc3545;
    font-size: 1.3rem;
}

.back-button {
    margin-bottom: 1.5rem;
}

.back-link {
    color: #666;
    text-decoration: none;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.back-link:hover {
    text-decoration: underline;
    color: #81bfda;
}

.back-link i {
    font-size: 2rem;
}

/* Responsive adjustments for smaller screens */
@media (max-width: 768px) {
    .login-page {
        flex-direction: column;
    }

    .left-side {
        flex: none;
        min-height: 40vh;
        padding: 2rem;
    }

    .right-side {
        flex: none;
        padding: 2rem;
    }

    .login-container {
        padding: 2.5rem;
        max-width: 100%;
    }

    .brand-title {
        font-size: 4rem;
    }

    .welcome-text {
        font-size: 2.5rem;
    }
}
</style>
