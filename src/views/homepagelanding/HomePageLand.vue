<template>
    <div class="login-page">
        <!-- Animated Background Particles -->
        <div class="particles-container">
            <div class="particle" v-for="n in 50" :key="n" :style="getParticleStyle()"></div>
        </div>

        <!-- Left side with background -->
        <div class="left-side">
            <div class="left-content">
                <div class="logos-container">
                    <div class="school-logos">
                        <img src="/demo/images/deped-logo.png" alt="DepEd Logo" class="deped-logo" />
                        <img src="/demo/images/logo.png" alt="Naawan Central School Logo" class="school-logo" />
                    </div>
                </div>
                <h1 class="brand-title">LAMMS</h1>
                <p class="brand-subtitle">Learners Attendance Monitoring and Management System</p>
                <p class="brand-description">Naawan Central School - Learner's Academy. A strong start for a lifetime of learning</p>
                <div class="floating-shapes">
                    <div class="shape shape-1"></div>
                    <div class="shape shape-2"></div>
                    <div class="shape shape-3"></div>
                </div>
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
                        <div class="input-icon password-input">
                            <i class="pi pi-lock"></i>
                            <input :type="showPassword ? 'text' : 'password'" id="password" v-model="password" placeholder="Password" required />
                            <button type="button" class="password-toggle" @click="togglePasswordVisibility" :class="{ active: showPassword }">
                                <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
                            </button>
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
import api from '@/config/axios';
import AuthService from '@/services/AuthService';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const username = ref('');
const password = ref('');
const rememberMe = ref(false);
const isLoading = ref(false);
const errorMessage = ref('');
const showPassword = ref(false);

// Particle animation function
const getParticleStyle = () => {
    const colors = ['rgba(255, 255, 255, 0.6)', 'rgba(102, 126, 234, 0.5)', 'rgba(118, 75, 162, 0.4)'];
    const sizes = ['2px', '3px', '4px', '5px'];

    return {
        left: Math.random() * 100 + '%',
        animationDelay: Math.random() * 20 + 's',
        animationDuration: Math.random() * 10 + 15 + 's',
        backgroundColor: colors[Math.floor(Math.random() * colors.length)],
        width: sizes[Math.floor(Math.random() * sizes.length)],
        height: sizes[Math.floor(Math.random() * sizes.length)]
    };
};

// Toggle password visibility
const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const handleLogin = async () => {
    try {
        isLoading.value = true;
        errorMessage.value = '';

        if (!username.value || !password.value) {
            errorMessage.value = 'Please enter both username and password';
            return;
        }

        console.log('🔐 Attempting unified login for:', username.value);

        // Use unified AuthService for all user types
        const result = await AuthService.login(username.value, password.value);

        console.log('📡 Login result:', result);

        if (result.success) {
            console.log('✅ Login successful! Role:', result.role);
            
            // Redirect based on user role
            switch (result.role) {
                case 'teacher':
                    router.push('/teacher');
                    break;
                case 'admin':
                    router.push('/admin');
                    break;
                case 'guardhouse':
                    router.push('/guardhouse');
                    break;
                default:
                    errorMessage.value = 'Unknown user role. Please contact administrator.';
                    return;
            }
        } else {
            console.log('❌ Login failed:', result.message);
            errorMessage.value = result.message || 'Login failed. Please check your credentials.';
        }
    } catch (error) {
        console.error('🚨 Login error:', error);
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

/* Particles Animation */
.particles-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 1;
}

.particle {
    position: absolute;
    border-radius: 50%;
    animation: float 20s infinite linear;
    box-shadow: 0 0 10px currentColor;
}

@keyframes float {
    0% {
        transform: translateY(100vh) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translateY(-100vh) rotate(360deg);
        opacity: 0;
    }
}

.left-side {
    flex: 0.45;
    padding: 4rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%);
    color: white;
    z-index: 2;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.left-side::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    animation: gridMove 30s linear infinite;
    z-index: 1;
}

@keyframes gridMove {
    0% {
        transform: translate(0, 0);
    }
    100% {
        transform: translate(10px, 10px);
    }
}

.overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    z-index: 2;
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
    z-index: 3;
    padding: 2rem;
    animation: fadeInLeft 1s ease-out 0.5s both;
}

@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-50px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.logos-container {
    margin-bottom: 2rem;
    display: flex;
    justify-content: center;
}

.school-logos {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 1.5rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(15px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    animation: logoFloat 3s ease-in-out infinite;
}

@keyframes logoFloat {
    0%,
    100% {
        transform: translateY(0px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    }
    50% {
        transform: translateY(-10px);
        box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
    }
}

.deped-logo,
.school-logo {
    height: 80px;
    width: auto;
    border-radius: 10px;
    transition: all 0.3s ease;
    filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
}

.deped-logo:hover,
.school-logo:hover {
    transform: scale(1.1);
    filter: drop-shadow(0 8px 25px rgba(0, 0, 0, 0.4));
}

.deped-logo {
    background: white;
    padding: 0.5rem;
}

.school-logo {
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.brand-title {
    font-size: 5.5rem;
    font-weight: 800;
    margin-bottom: 2rem;
    color: #fff;
    text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
    letter-spacing: 3px;
    animation: slideInUp 1s ease-out 0.7s both;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.floating-shapes {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.shape {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    animation: float-shapes 15s ease-in-out infinite;
}

.shape-1 {
    width: 60px;
    height: 60px;
    top: 20%;
    left: 10%;
    animation-delay: 0s;
}

.shape-2 {
    width: 40px;
    height: 40px;
    top: 60%;
    right: 15%;
    animation-delay: 5s;
}

.shape-3 {
    width: 80px;
    height: 80px;
    bottom: 20%;
    left: 20%;
    animation-delay: 10s;
}

@keyframes float-shapes {
    0%,
    100% {
        transform: translateY(0px) rotate(0deg);
    }
    33% {
        transform: translateY(-20px) rotate(120deg);
    }
    66% {
        transform: translateY(10px) rotate(240deg);
    }
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
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    z-index: 3;
}

.login-container {
    width: 100%;
    max-width: 520px;
    padding: 3.5rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    animation: fadeInRight 1s ease-out 0.9s both;
}

@keyframes fadeInRight {
    from {
        opacity: 0;
        transform: translateX(50px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.welcome-text {
    font-size: 3.2rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 1rem;
    font-weight: 700;
    text-align: center;
    animation: textGlow 2s ease-in-out infinite alternate;
}

@keyframes textGlow {
    from {
        filter: drop-shadow(0 0 5px rgba(102, 126, 234, 0.3));
    }
    to {
        filter: drop-shadow(0 0 15px rgba(102, 126, 234, 0.6));
    }
}

.login-subtitle {
    color: #666;
    margin-bottom: 2.5rem;
    font-size: 1.4rem;
    text-align: center;
    font-weight: 500;
    animation: slideInUp 1s ease-out 1.1s both;
}

.input-icon {
    position: relative;
    display: flex;
    align-items: center;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 1.2rem 1rem;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.input-icon:hover {
    border-color: #667eea;
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
}

.input-icon:focus-within {
    border-color: #667eea;
    background: rgba(255, 255, 255, 1);
    box-shadow:
        0 0 0 4px rgba(102, 126, 234, 0.15),
        0 8px 25px rgba(102, 126, 234, 0.2);
    transform: translateY(-3px);
}

.password-input {
    padding-right: 3.5rem;
}

.password-toggle {
    position: absolute;
    right: 1rem;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.3s ease;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
}

.password-toggle:hover {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    transform: scale(1.1);
}

.password-toggle.active {
    color: #667eea;
    background: rgba(102, 126, 234, 0.15);
}

.password-toggle i {
    font-size: 1.2rem;
    transition: all 0.3s ease;
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

.password-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    color: #666;
    font-size: 1.2rem;
    transition: color 0.3s ease;
}

.password-toggle:hover {
    color: #81bfda;
}

.form-group {
    margin-bottom: 2rem;
    animation: slideInUp 0.8s ease-out both;
}

.form-group:nth-child(1) {
    animation-delay: 1.3s;
}

.form-group:nth-child(2) {
    animation-delay: 1.5s;
}

.form-group:nth-child(3) {
    animation-delay: 1.7s;
}

.form-footer {
    margin-bottom: 2rem;
    animation: slideInUp 0.8s ease-out 1.9s both;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.3rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    animation: slideInUp 0.8s ease-out 2.1s both;
}

.login-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.login-button:hover::before {
    left: 100%;
}

.login-button:hover {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
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
