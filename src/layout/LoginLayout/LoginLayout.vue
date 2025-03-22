<script setup>
import { computed } from 'vue';
import LoginTopbar from './LoginTopBar.vue';

const containerClass = computed(() => ({
    'layout-overlay': false,
    'layout-static': true,
    'layout-static-inactive': false,
    'layout-overlay-active': false,
    'layout-mobile-active': false
}));
</script>

<template>
    <div class="layout-wrapper" :class="containerClass">
        <LoginTopbar />
        <div class="layout-content">
            <div class="layout-main">
                <router-view /> <!-- ✅ Ensures pages load inside layout -->
            </div>
        </div>
        <div class="layout-mask animate-fadein"></div>
    </div>
</template>

<style scoped>
.layout-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ✅ Centers the container perfectly */
.layout-content {
    flex: 1;
    display: flex;
    justify-content: center; /* Centers horizontally */
    align-items: center; /* Centers vertically */
    padding: 20px;
}

/* ✅ Ensures the box is centered with proper styling */
.layout-main {
    width: 100%;
    max-width: 600px; /* Adjust width for better centering */
    text-align: center;
    background-color: #ffffff;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

/* ✅ Removes extra margin/padding */
body, html {
    margin: 0;
    padding: 0;
    height: 100%;
}
</style>
