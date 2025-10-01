<template>
    <div class="landing-container">
        <h1>Welcome</h1>
        <p class="subtitle">Login to access your account</p>
        
        <div class="login-card">
            <form @submit.prevent="handleLogin">
                <div v-if="errorMessage" class="error-message">
                    <i class="pi pi-exclamation-triangle"></i>
                    {{ errorMessage }}
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="pi pi-envelope"></i>
                        <input 
                            type="email" 
                            id="email" 
                            v-model="email" 
                            placeholder="Enter your email"
                            required 
                        />
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="pi pi-lock"></i>
                        <input 
                            :type="showPassword ? 'text' : 'password'" 
                            id="password" 
                            v-model="password" 
                            placeholder="Enter your password"
                            required 
                        />
                        <button 
                            type="button" 
                            class="toggle-password" 
                            @click="showPassword = !showPassword"
                        >
                            <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
                        </button>
                    </div>
                </div>

                <div class="form-footer">
                    <label class="remember-checkbox">
                        <input type="checkbox" v-model="rememberMe" />
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="login-button" :disabled="isLoading">
                    <span v-if="isLoading">
                        <i class="pi pi-spin pi-spinner"></i> Logging in...
                    </span>
                    <span v-else>
                        <i class="pi pi-sign-in"></i> LOGIN
                    </span>
                </button>
            </form>

            <div class="info-text">
                <p><strong>For Teachers:</strong> Use your school email (e.g., maria.santos@school.edu)</p>
                <p><strong>For Admin:</strong> Use your admin credentials</p>
                <p><strong>For Guardhouse:</strong> Use your assigned credentials</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import AuthService from '@/services/AuthService';

const router = useRouter();
const email = ref('');
const password = ref('');
const rememberMe = ref(false);
const isLoading = ref(false);
const errorMessage = ref('');
const showPassword = ref(false);

const handleLogin = async () => {
    try {
        isLoading.value = true;
        errorMessage.value = '';

        if (!email.value || !password.value) {
            errorMessage.value = 'Please enter both email and password';
            return;
        }

        console.log('🔐 Attempting unified login for:', email.value);

        // Use unified AuthService for all user types
        const result = await AuthService.login(email.value, password.value);

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
/* Page Styling */
.landing-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

h1 {
    font-size: 36px;
    font-weight: bold;
    color: white;
    margin-bottom: 10px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
}

.subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 16px;
    margin-bottom: 30px;
}

/* Login Card */
.login-card {
    background: white;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 450px;
    width: 100%;
}

.error-message {
    background: #fee;
    border: 1px solid #fcc;
    color: #c33;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
}

.form-group {
    margin-bottom: 20px;
    text-align: left;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 600;
    font-size: 14px;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-wrapper i {
    position: absolute;
    left: 15px;
    color: #999;
}

.input-wrapper input {
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 15px;
    transition: all 0.3s;
}

.input-wrapper input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.toggle-password {
    position: absolute;
    right: 15px;
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 5px;
    display: flex;
    align-items: center;
}

.toggle-password:hover {
    color: #667eea;
}

.form-footer {
    margin-bottom: 20px;
    text-align: left;
}

.remember-checkbox {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 14px;
    color: #666;
}

.remember-checkbox input {
    margin-right: 8px;
    cursor: pointer;
}

.login-button {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.login-button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.login-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.info-text {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #e0e0e0;
    text-align: left;
}

.info-text p {
    font-size: 13px;
    color: #666;
    margin-bottom: 8px;
}

.info-text strong {
    color: #333;
    font-weight: 600;
}

/* Hover Effects */
.new-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-group {
        grid-template-columns: 1fr;
    }
}
</style>
