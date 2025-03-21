<script setup>
import { useLayout } from '@/layout/composables/layout';
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

        <div class="layout-topbar-actions">
            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
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
                            <button class="logout-button" @click="logout">
                                <i class="pi pi-sign-out"></i> Log Out
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Modal -->
    <Dialog v-model:visible="isCalendarOpen" header="School Activities Calendar" :modal="true" :style="{ width: '290px' }">
        <VCalendar :attributes="attributes" />
    </Dialog>
</template>

<style scoped>
img {
    width: 50px;
    height: 50px;
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
