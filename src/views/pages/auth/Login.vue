<script setup>
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import { onMounted, ref } from 'vue';

const email = ref('');
const password = ref('');
x;
const checked = ref(false);
const isLoading = ref(false);

const handleLogin = async () => {
    isLoading.value = true;
    // Simulate login process
    setTimeout(() => {
        isLoading.value = false;
        // Add your login logic here
    }, 2000);
};

// Create floating particles animation
onMounted(() => {
    createParticles();
});

const createParticles = () => {
    const container = document.querySelector('.particles-container');
    if (!container) return;

    for (let i = 0; i < 50; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 20 + 's';
        particle.style.animationDuration = Math.random() * 10 + 10 + 's';
        container.appendChild(particle);
    }
};
</script>

<template>
    <FloatingConfigurator />

    <!-- Animated Background -->
    <div class="login-container">
        <!-- Particles Background -->
        <div class="particles-container"></div>

        <!-- Animated Background Shapes -->
        <div class="bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
        </div>

        <!-- Main Content -->
        <div class="login-content">
            <!-- Left Side - Branding -->
            <div class="branding-section">
                <div class="brand-content">
                    <div class="logo-container">
                        <div class="logo-circle">
                            <span class="logo-text">NCS</span>
                        </div>
                    </div>
                    <h1 class="brand-title">LAMMS</h1>
                    <p class="brand-subtitle">Learners Attendance Monitoring and Management System</p>
                    <div class="brand-tagline">
                        <p>Learner's Academy. A strong start for a lifetime of learning.</p>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="form-section">
                <div class="form-container">
                    <div class="form-header">
                        <h2 class="form-title">Welcome</h2>
                        <p class="form-subtitle">Login with Email</p>
                    </div>

                    <form @submit.prevent="handleLogin" class="login-form">
                        <div class="input-group">
                            <div class="input-container">
                                <i class="pi pi-user input-icon"></i>
                                <InputText id="email1" type="text" placeholder="Username or Email Address" class="modern-input" v-model="email" />
                            </div>
                        </div>

                        <div class="input-group">
                            <div class="input-container">
                                <i class="pi pi-lock input-icon"></i>
                                <Password id="password1" v-model="password" placeholder="Password" :toggleMask="true" class="modern-password" :feedback="false" fluid />
                            </div>
                        </div>

                        <div class="form-options">
                            <div class="remember-me">
                                <Checkbox v-model="checked" id="rememberme1" binary class="custom-checkbox" />
                                <label for="rememberme1" class="checkbox-label">Remember me</label>
                            </div>
                            <a href="#" class="forgot-password">Forgot password?</a>
                        </div>

                        <Button type="submit" :label="isLoading ? 'Signing In...' : 'LOGIN'" :loading="isLoading" class="login-button" :disabled="isLoading" />
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.pi-eye {
    transform: scale(1.6);
    margin-right: 1rem;
}

.pi-eye-slash {
    transform: scale(1.6);
    margin-right: 1rem;
}
</style>
