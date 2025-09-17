<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
        <div class="max-w-md w-full">
            <!-- Logo and Title -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <img src="/favicon.ico" alt="NCS Logo" class="h-16 w-16">
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">NCS - for Teachers</h1>
                <p class="text-gray-600">Learning and Academic Management System</p>
            </div>

            <!-- Login Form -->
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-6 text-center">Teacher Login</h2>
                
                <form @submit.prevent="handleLogin" class="space-y-6">
                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username
                        </label>
                        <input
                            id="username"
                            v-model="form.username"
                            type="text"
                            required
                            :disabled="loading"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="Enter your username"
                        />
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                :disabled="loading"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors pr-12"
                                placeholder="Enter your password"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                            >
                                <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="pi pi-exclamation-triangle text-red-500 mr-2"></i>
                            <p class="text-red-700 text-sm">{{ error }}</p>
                        </div>
                    </div>

                    <!-- Login Button -->
                    <button
                        type="submit"
                        :disabled="loading || !form.username || !form.password"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-semibold py-3 px-4 rounded-lg transition-colors flex items-center justify-center"
                    >
                        <ProgressSpinner v-if="loading" strokeWidth="4" style="width: 20px; height: 20px" class="mr-2" />
                        <span>{{ loading ? 'Signing in...' : 'Sign In' }}</span>
                    </button>
                </form>

                <!-- Footer -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-500">
                        Need help? Contact your administrator
                    </p>
                </div>
            </div>

            <!-- Demo Credentials -->
            <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="text-sm font-medium text-yellow-800 mb-2">Demo Credentials:</h3>
                <div class="text-sm text-yellow-700">
                    <p><strong>Username:</strong> maria.santos</p>
                    <p><strong>Password:</strong> teacher123</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import TeacherAuthService from '@/services/TeacherAuthService';
import ProgressSpinner from 'primevue/progressspinner';

const router = useRouter();

// Form data
const form = ref({
    username: 'maria.santos',
    password: 'teacher123'
});

// UI state
const loading = ref(false);
const error = ref('');
const showPassword = ref(false);

// Handle login
const handleLogin = async () => {
    if (loading.value) return;
    
    loading.value = true;
    error.value = '';

    try {
        const result = await TeacherAuthService.login(form.value.username, form.value.password);
        
        if (result.success) {
            // Redirect to teacher dashboard
            router.push('/teacher');
        } else {
            error.value = result.message || 'Login failed. Please check your credentials.';
        }
    } catch (err) {
        console.error('Login error:', err);
        error.value = 'An unexpected error occurred. Please try again.';
    } finally {
        loading.value = false;
    }
};

// Check if already authenticated
onMounted(() => {
    if (TeacherAuthService.isAuthenticated()) {
        router.push('/teacher');
    }
});
</script>

<style scoped>
/* Custom styles for the login page */
.min-h-screen {
    min-height: 100vh;
}
</style>
