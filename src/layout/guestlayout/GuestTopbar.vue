<script setup>
import Button from 'primevue/button';
</script>

<template>
    <div>
        <div class="layout-topbar">
            <div class="layout-topbar-logo-container">
                <router-link to="/guest" class="layout-topbar-logo">
                    <img src="/demo/images/logo.svg" alt="Logo" />
                    <span>NCS- for Guardians</span>
                </router-link>
            </div>

            <div class="layout-topbar-center">
                <Button class="back-button" @click="$router.push('/')">
                    <i class="pi pi-arrow-left"></i>
                    Back to Login
                </Button>
            </div>

            <div class="layout-topbar-right">
                <!-- Empty placeholder to balance the layout -->
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

.layout-topbar-center {
    position: absolute;
    left: 50%;
    top: 0;
    transform: translateX(-50%);
    height: 4rem;
    display: flex;
    justify-content: center;
    align-items: center;
}

.layout-topbar-right {
    margin-left: auto;
    min-width: 200px;
}

.back-button {
    background: linear-gradient(135deg, #3B82F6, #8B5CF6);
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

/* Search Section Styles */
.search-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 4rem); /* Full height minus topbar */
    padding-top: 4rem; /* Account for fixed topbar */
    background-color: var(--surface-ground);
}

.search-title {
    font-size: 1.5rem;
    color: var(--text-color);
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.search-container {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.search-input {
    padding: 0.75rem 1rem;
    border: 1px solid var(--surface-border);
    border-radius: 6px;
    font-size: 1rem;
    width: 300px;
    transition: border-color 0.2s;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

.search-button {
    background: #10B981;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.search-button:hover {
    background: #059669;
    transform: translateY(-2px);
}

.search-button:active {
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
    .layout-topbar-center {
        position: static;
        transform: none;
        margin: 0 auto;
    }

    .layout-topbar-right {
        display: none;
    }
}
</style>

        display: none;
