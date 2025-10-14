<template>
    <!-- Loading Screen -->
    <div v-if="isPageLoading" class="loading-screen">
        <div class="loading-content">
            <div class="loading-logos">
                <img src="/demo/images/deped-logo.png" alt="DepEd Logo" class="loading-deped-logo" />
                <img src="/demo/images/logo.png" alt="Naawan Central School Logo" class="loading-school-logo" />
            </div>
            <div class="loading-spinner"></div>
            <p class="loading-text">Loading...</p>
        </div>
    </div>

    <!-- Main Login Page -->
    <div v-else class="login-page">
        <!-- Branding Section -->
        <div class="branding-section">
            <div class="logos-container">
                <div class="school-logos">
                    <img src="/demo/images/deped-logo.png" alt="DepEd Logo" class="deped-logo" />
                    <img src="/demo/images/logo.png" alt="Naawan Central School Logo" class="school-logo" />
                </div>
            </div>
            <h1 class="brand-title">Learners Attendance Monitoring and Management System</h1>
        </div>

        <!-- Centered Login Container -->
        <div class="centered-login">
            <div class="login-container">
                <h2 class="welcome-text">Welcome</h2>
                <p class="login-subtitle">Enter your credentials to access your account</p>

                <form @submit.prevent="handleLogin" class="login-form">
                    <div v-if="errorMessage" class="error-message">
                        <i class="pi pi-exclamation-triangle warning-icon"></i>
                        {{ errorMessage }}
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            <i class="pi pi-user"></i>
                            Username
                        </label>
                        <div class="input-icon">
                            <input type="text" id="username" v-model="username" placeholder="Enter your username" required />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="field-label">
                            <i class="pi pi-lock"></i>
                            Password
                        </label>
                        <div class="input-icon password-input">
                            <input :type="showPassword ? 'text' : 'password'" id="password" v-model="password" placeholder="Enter your password" required />
                            <button type="button" class="password-toggle" @click="togglePasswordVisibility" :class="{ active: showPassword }">
                                <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
                            </button>
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
import AuthService from '@/services/AuthService';
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const username = ref('');
const password = ref('');
const rememberMe = ref(false);
const isLoading = ref(false);
const errorMessage = ref('');
const showPassword = ref(false);
const isPageLoading = ref(true);

// Show loading screen for 2 seconds
onMounted(() => {
    setTimeout(() => {
        isPageLoading.value = false;
    }, 2000);
});

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

            // Add a small delay to ensure auth state is properly set
            await new Promise(resolve => setTimeout(resolve, 200));

            // Clear any existing error messages
            errorMessage.value = '';
            
            // Force a complete page reload to ensure clean state
            let targetPath;
            switch (result.role) {
                case 'teacher':
                    targetPath = '/teacher';
                    break;
                case 'admin':
                    targetPath = '/admin';
                    break;
                case 'guardhouse':
                    targetPath = '/guardhouse';
                    break;
                default:
                    errorMessage.value = 'Unknown user role. Please contact administrator.';
                    return;
            }
            
            console.log('🔄 Redirecting to:', targetPath);
            
            // Use window.location.replace for a clean navigation
            window.location.replace(targetPath);
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
/* Loading Screen Styles */
.loading-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100vh;
    background: rgb(243, 239, 239);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-content {
    text-align: center;
    animation: fadeInUp 1s ease-out;
}

.loading-logos {
    display: flex;
    align-items: center;
    gap: 2rem;
    background: rgba(255, 255, 255, 0.15);
    padding: 2rem 3rem;
    border-radius: 25px;
    backdrop-filter: blur(20px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    margin-bottom: 2rem;
    animation: logoGlow 2s ease-in-out infinite alternate;
}

@keyframes logoGlow {
    from {
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        transform: translateY(0px);
    }
    to {
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
        transform: translateY(-5px);
    }
}

.loading-deped-logo,
.loading-school-logo {
    height: 100px;
    width: auto;
    border-radius: 15px;
    filter: drop-shadow(0 8px 20px rgba(0, 0, 0, 0.4));
    transition: all 0.3s ease;
}

.loading-deped-logo {
    background: white;
    padding: 0.8rem;
}

.loading-school-logo {
    border: 3px solid rgba(255, 255, 255, 0.4);
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid #00ff00;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem auto;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.loading-text {
    color: #333;
    font-size: 1.5rem;
    font-weight: 600;
    text-shadow: none;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Main Login Page Styles */
.login-page {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: rgb(225, 224, 224);
    position: relative;
    overflow: hidden;
    padding: 0.5rem;
}

.branding-section {
    text-align: center;
    margin-bottom: 2rem;
    color: #333;
}

.logos-container {
    margin-bottom: 1rem;
    display: flex;
    justify-content: center;
}

.school-logos {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem 1.5rem;
    border-radius: 20px;
    backdrop-filter: blur(15px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
}

.deped-logo,
.school-logo {
    height: 45px;
    width: auto;
    border-radius: 8px;
    transition: all 0.3s ease;
    filter: drop-shadow(0 3px 10px rgba(0, 0, 0, 0.3));
}

.deped-logo {
    background: white;
    padding: 0.4rem;
}

.school-logo {
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.brand-title {
    font-size: 1.6rem;
    font-weight: 900;
    margin-bottom: 1rem;
    color: #0d0ddeb5;
    text-shadow: none;
    letter-spacing: 0.5px;
    line-height: 1.1;
}

.brand-subtitle {
    font-size: 1.2rem;
    color: #666;
    line-height: 1.6;
    text-shadow: none;
    font-weight: 500;
}

.centered-login {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    max-width: 600px;
}

.login-container {
    width: 100%;
    max-width: 380px;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 15px;
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-top: 1rem;
}


.welcome-text {
    font-size: 2.2rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 1rem;
    font-weight: 700;
    text-align: center;
}


.login-subtitle {
    color: #666;
    margin-bottom: 1rem;
    font-size: 1rem;
    text-align: center;
    font-weight: 500;
}

.field-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
}

.field-label i {
    color: #666;
    font-size: 1.2rem;
}

.input-icon {
    position: relative;
    display: flex;
    align-items: center;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 0.6rem 0.8rem;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.input-icon:hover {
    border-color: #667eea;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
}

.input-icon:focus-within {
    border-color: #667eea;
    background: rgba(255, 255, 255, 1);
    box-shadow:
        0 0 0 4px rgba(102, 126, 234, 0.15),
        0 8px 25px rgba(102, 126, 234, 0.2);
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
}

.password-toggle.active {
    color: #667eea;
    background: rgba(102, 126, 234, 0.15);
}

.password-toggle i {
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.input-icon input {
    border: none;
    outline: none;
    width: 100%;
    font-size: 0.9rem;
    padding: 0.3rem 0.5rem;
    color: #333;
    background: transparent;
}

.input-icon input::placeholder {
    color: #999;
    font-size: 0.85rem;
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
    margin-bottom: 0.8rem;
}

.form-footer {
    margin-bottom: 0.5rem;
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
    padding: 0.8rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
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
        padding: 1rem;
    }

    .branding-section {
        margin-bottom: 1.5rem;
    }

    .login-container {
        padding: 1.5rem;
        max-width: 100%;
        margin-top: 0.5rem;
    }

    .brand-title {
        font-size: 1.4rem;
    }

    .welcome-text {
        font-size: 1.8rem;
    }

    .school-logos {
        padding: 0.8rem 1rem;
        gap: 1rem;
    }

    .deped-logo,
    .school-logo {
        height: 35px;
    }
}
</style>
