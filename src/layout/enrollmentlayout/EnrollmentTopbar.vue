<script setup>
import { useLayout } from '@/layout/composables/layout';
const { toggleMenu } = useLayout();
</script>

<template>
    <div>
        <!-- Top Bar -->
        <div class="layout-topbar">
            <div class="layout-topbar-logo-container">
                <router-link to="/teacher" class="layout-topbar-logo">
                    <img src="/demo/images/logo.png" alt="Logo" />
                    <span>NCS- for Enrollment</span>
                </router-link>
            </div>

            <!-- Centered Back Button -->
            <div class="layout-topbar-center">
                <router-link to="/" class="back-button">
                    <i class="pi pi-arrow-left"></i>
                    Back to Login
                </router-link>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Topbar styles */
.layout-topbar {
    position: fixed;
    height: 4rem;
    z-index: 997;
    left: 0;
    top: 0;
    width: 100%;
    padding: 0 2rem;
    background-color: var(--surface-card);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.layout-topbar-logo-container {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 200px;
}

.layout-topbar-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    color: var(--text-color);
}

.layout-topbar-logo img {
    height: 2.5rem;
}

.layout-topbar-center {
    flex: 1;
    display: flex;
    justify-content: flex-start;
    align-items: center;
    padding-left: 44rem;
}

/* Back button styles */
.back-button {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    border: none;
    padding: 0.75rem 1.5rem;
    color: white;
    border-radius: 8px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.back-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.back-button:active {
    transform: scale(0.98);
}
/* Mobile responsiveness */
@media (max-width: 991px) {
    .layout-topbar {
        padding: 0 1rem;
    }

    .layout-topbar-logo-container {
        min-width: auto;
    }
}
</style>
