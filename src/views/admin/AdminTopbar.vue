<script setup>
import { useLayout } from '@/layout/composables/layout';
import Button from 'primevue/button';
import { ref } from 'vue';

const { toggleMenu } = useLayout();
const isCalendarOpen = ref(false);
const isProfileOpen = ref(false); // Controls dropdown visibility
const isLogoutSuccess = ref(false); // Controls the log out confirmation modal

const logout = () => {
    isLogoutSuccess.value = true; // Show the success message
};
</script>

<template>
    <div>
        <div class="layout-topbar">
            <div class="layout-topbar-logo-container">
                <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                    <i class="pi pi-bars"></i>
                </button>
                <router-link to="/admin" class="layout-topbar-logo">
                    <img src="/demo/images/logo.svg" alt="Logo" />
                    <span>NCS - for Admin</span>
                </router-link>
            </div>

            <div class="layout-topbar-center">
                <Button class="back-button" @click="$router.push('/')">
                    <i class="pi pi-arrow-left"></i>
                    Back to Login
                </Button>
            </div>

            <div class="layout-topbar-actions">
                <button type="button" class="layout-topbar-action" @click="isCalendarOpen = true">
                    <i class="pi pi-calendar"></i>
                    <span>Calendar</span>
                </button>
                <!-- Profile Button with Dropdown -->
                <div class="relative">
                    <button type="button" class="layout-topbar-action" @click="isProfileOpen = !isProfileOpen">
                        <i class="pi pi-user"></i>
                        <span>Profile</span>
                    </button>
                    <!-- Styled Dropdown Menu -->
                    <div v-if="isProfileOpen" class="profile-dropdown">
                        <button class="logout-button" @click="logout"><i class="pi pi-sign-out"></i> Log Out</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar Modal -->
        <Dialog v-model="isCalendarOpen" header="School Activities Calendar" :modal="true" :style="{ width: '290px' }">
            <VCalendar :attributes="attributes" />
        </Dialog>
    </div>
</template>

<style scoped>
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
    justify-content: center;
    align-items: center;
}

.layout-topbar-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 200px;
    justify-content: flex-end;
}

.layout-topbar-action {
    width: auto;
    height: 2.5rem;
    padding: 0 0.5rem;
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-color);
    transition: background-color 0.2s;
}

.layout-topbar-action:hover {
    background-color: var(--surface-hover);
}

.layout-topbar-action i {
    font-size: 1.25rem;
}

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

.logout-button {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 8px 12px;
    border: none;
    background: #f56565; /* Red button */
    color: white;
    font-size: 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
}

.logout-button:hover {
    background: #c53030; /* Darker red on hover */
}

.logout-button i {
    margin-right: 6px;
}

.profile-dropdown {
    position: absolute;
    right: 0;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    min-width: 120px;
    z-index: 100;
}
</style>
