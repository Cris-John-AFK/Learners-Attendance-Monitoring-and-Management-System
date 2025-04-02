<template>
    <div class="login-page">
        <!-- Left side with background -->
        <div class="left-side">
            <div class="left-content">
                <div class="back-button mb-6">
                    <router-link to="/" class="back-link"> <i class="pi pi-arrow-left"></i> Back </router-link>
                </div>
                <div class="logo-text">NCS</div>
                <h1 class="brand-title">LAMMS</h1>
                <p class="brand-subtitle">Learners Attendance Monitoring and Management System</p>
                <p class="brand-description">Learner’s Academy. A strong start for a lifetime of learning</p>
            </div>
            <div class="overlay"></div>
        </div>

        <!-- Right side with login form -->
        <div class="right-side">
            <div class="login-container">
                <div class="back-button mb-6">
                    <router-link to="/global" class="back-link"> <i class="pi pi-arrow-left"></i> Back </router-link>
                </div>
                <h2 class="welcome-text">Welcome</h2>
                <p class="login-subtitle">Login with Email - I am a</p>

                <div class="panel-buttons">
                    <button class="panel-btn" :class="{ active: activePanel === 'Admin Panel' }" @click="handleTabClick('Admin Panel')">Admin Panel</button>
                    <button class="panel-btn" :class="{ active: activePanel === 'Teacher Panel' }" @click="handleTabClick('Teacher Panel')">Teacher Panel</button>
                    <button class="panel-btn" :class="{ active: activePanel === 'Gaurdian Panel' }" @click="handleTabClick('Gaurdian Panel')">Gaurdian Panel</button>
                    <button class="panel-btn" :class="{ active: activePanel === 'Gate Personel Panel' }" @click="handleTabClick('Gate Personel Panel')">Gate Personel Panel</button>
                </div>

                <form @submit.prevent="handleLogin" class="login-form">
                    <div v-if="errorMessage" class="error-message">
                        <i class="pi pi-exclamation-triangle warning-icon"></i>
                        {{ errorMessage }}
                    </div>

                    <div class="form-group">
                        <div class="input-icon">
                            <i class="pi pi-user"></i>
                            <input type="text" id="username" v-model="username" :placeholder="activePanel === 'Admin Panel' ? 'Username' : 'Username or Email Address'" required />
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

                    <div class="social-divider">
                        <span>OR</span>
                    </div>

                    <div class="social-login">
                        <button type="button" class="social-btn google">
                            <i class="pi pi-google"></i>
                        </button>
                        <button type="button" class="social-btn facebook">
                            <i class="pi pi-facebook"></i>
                        </button>
                        <button type="button" class="social-btn apple">
                            <i class="pi pi-apple"></i>
                        </button>
                    </div>

                    <div class="register-link">Join us now! Enroll your child today! <a href="#">Click Here</a></div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const username = ref('');
const password = ref('');
const rememberMe = ref(false);
const isLoading = ref(false);
const errorMessage = ref('');
const activePanel = ref('Admin Panel');

const handleLogin = async () => {
    try {
        isLoading.value = true;
        errorMessage.value = '';

        if (!username.value || !password.value) {
            errorMessage.value = 'Please enter both username and password';
            return;
        }

        const response = await axios.post('/api/auth/login', {
            username: username.value,
            password: password.value,
            remember: rememberMe.value,
            panel: activePanel.value
        });

        const storage = rememberMe.value ? localStorage : sessionStorage;
        storage.setItem('token', response.data.token);
        storage.setItem('user', JSON.stringify(response.data.user));

        router.push('/dashboard');
    } catch (error) {
        console.error('Login error:', error);
        errorMessage.value = error.response?.data?.message || 'Invalid credentials';
    } finally {
        isLoading.value = false;
    }
};

const handleTabClick = (panel) => {
    activePanel.value = panel;
    username.value = '';
    password.value = '';
    errorMessage.value = '';
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

.left-content {
    position: relative;
    z-index: 2;
    padding: 2rem;
}

.logo-text {
    font-size: 1.8rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 1rem;
    letter-spacing: 3px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
}

.brand-title {
    font-size: 4.5rem;
    font-weight: 800;
    margin-bottom: 1.5rem;
    color: #fff;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    letter-spacing: 2px;
}

.brand-subtitle {
    font-size: 1.5rem;
    color: #fff;
    line-height: 1.8;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
    max-width: 500px;
    font-weight: 500;
}

.brand-description {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.6;
    margin-top: 1.5rem;
    max-width: 450px;
}

.right-side {
    flex: 0.55;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
    background: #fff;
}

.login-container {
    width: 100%;
    max-width: 450px;
    padding: 2.5rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.welcome-text {
    font-size: 2.5rem;
    color: #333;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.login-subtitle {
    color: #666;
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.panel-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 2rem;
}

.panel-btn {
    flex: 1 1 calc(50% - 0.5rem);
    padding: 0.8rem;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    background: white;
    color: #666;
    cursor: pointer;
    transition: all 0.3s ease;
}

.panel-btn:hover {
    background: #f5f5f5;
}

.panel-btn.active {
    background: #81bfda;
    color: white;
    border-color: #81bfda;
}

.input-icon {
    position: relative;
    display: flex;
    align-items: center;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 0.75rem;
    transition: all 0.3s ease;
}

.input-icon:hover {
    border-color: #ccc;
}

.input-icon i {
    color: #666;
    margin-right: 0.75rem;
}

.input-icon input {
    border: none;
    outline: none;
    width: 100%;
    font-size: 1rem;
    padding: 0.5rem 0;
    color: #333;
}

.form-group {
    margin-bottom: 1.5rem;
}

.login-button {
    width: 100%;
    padding: 1rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s ease;
}

.login-button:hover {
    background: #0056b3;
}

.login-button:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.social-divider {
    text-align: center;
    margin: 2rem 0;
    position: relative;
}

.social-divider::before,
.social-divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 45%;
    height: 1px;
    background: #e0e0e0;
}

.social-divider::before {
    left: 0;
}

.social-divider::after {
    right: 0;
}

.social-divider span {
    background: white;
    padding: 0 1rem;
    color: #666;
}

.social-login {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.social-btn {
    width: 40px;
    height: 40px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    background: white;
    color: #666;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.social-btn:hover {
    background: #f5f5f5;
}

.register-link {
    text-align: center;
    color: #666;
}

.register-link a {
    color: #81bfda;
    text-decoration: none;
    font-weight: 600;
}

.register-link a:hover {
    text-decoration: underline;
}

.error-message {
    color: #dc3545;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 1rem;
}

.warning-icon {
    color: #dc3545;
}

.back-button {
    margin-bottom: 1rem;
}

.back-link {
    color: #666;
    text-decoration: none;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.back-link:hover {
    text-decoration: underline;
}
</style>
