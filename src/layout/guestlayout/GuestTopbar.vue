<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const isProfileOpen = ref(false);

const logout = () => {
    // Clear user session data
    localStorage.removeItem('user');
    sessionStorage.removeItem('user');

    // Redirect to homepage
    router.push('/');
};
</script>

<template>
    <div>
        <div class="layout-topbar">
            <div class="layout-topbar-logo-container">
                <router-link to="/guest" class="layout-topbar-logo">
                    <img src="/demo/images/logo.png" alt="Logo" />
                    <span>NCS- for Guardians</span>
                </router-link>
            </div>

            <div class="layout-topbar-right">
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

.layout-topbar-right {
    margin-left: auto;
    min-width: 200px;
}

.layout-topbar-action {
    background: none;
    border: none;
    padding: 0.75rem 1rem;
    color: var(--text-color);
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.layout-topbar-action:hover {
    background-color: var(--surface-hover);
}

.layout-topbar-action:active {
    transform: scale(0.98);
}

.profile-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background-color: var(--surface-card);
    padding: 0.5rem;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.logout-button {
    background: #10b981;
    color: white;
    border: none;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.logout-button:hover {
    background: #059669;
    transform: translateY(-2px);
}

.logout-button:active {
    transform: scale(0.98);
}

@media (max-width: 991px) {
    .layout-topbar {
        padding: 0 1rem;
    }

    .layout-topbar-logo-container {
        min-width: 150px;
    }

    .layout-topbar-right {
        min-width: 150px;
    }
}

@media (max-width: 640px) {
    .layout-topbar-right {
        display: none;
    }
}
</style>
