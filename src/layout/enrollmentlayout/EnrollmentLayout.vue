<script setup>
import { computed, onMounted } from 'vue';
import EnrollmentTopbar from './EnrollmentTopbar.vue';

const containerClass = computed(() => ({
    'layout-overlay': false,
    'layout-static': true,
    'layout-static-inactive': false,
    'layout-overlay-active': false,
    'layout-mobile-active': false
}));

// Set animated gradient background on the body element
onMounted(() => {
    // Remove any existing background image
    document.body.style.backgroundImage = '';

    // Add the animated gradient class to the body
    document.body.classList.add('animated-gradient-background');

    // Clean up when component is unmounted
    return () => {
        document.body.classList.remove('animated-gradient-background');
    };
});
</script>

<template>
    <div class="layout-wrapper" :class="containerClass">
        <EnrollmentTopbar />
        <div class="layout-content">
            <div class="layout-main">
                <router-view />
                <!-- ✅ Ensures pages load inside layout -->
            </div>
        </div>
        <div class="layout-mask animate-fadein"></div>
    </div>
</template>

<style>
/* Animated gradient background */
.animated-gradient-background {
    background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
    background-size: 400% 400%;
    animation: gradient-animation 15s ease infinite;
}

@keyframes gradient-animation {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Floating particles */
.layout-wrapper::before,
.layout-wrapper::after {
    content: '';
    position: fixed;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    z-index: -1;
}

.layout-wrapper::before {
    top: -150px;
    left: -150px;
    animation: float-1 20s infinite linear;
}

.layout-wrapper::after {
    bottom: -150px;
    right: -150px;
    animation: float-2 15s infinite linear;
}

@keyframes float-1 {
    0% {
        transform: translate(0, 0) scale(1);
    }
    25% {
        transform: translate(100px, 100px) scale(1.2);
    }
    50% {
        transform: translate(200px, 50px) scale(0.8);
    }
    75% {
        transform: translate(100px, 200px) scale(1.1);
    }
    100% {
        transform: translate(0, 0) scale(1);
    }
}

@keyframes float-2 {
    0% {
        transform: translate(0, 0) scale(1);
    }
    33% {
        transform: translate(-100px, -50px) scale(1.3);
    }
    66% {
        transform: translate(-50px, -150px) scale(0.9);
    }
    100% {
        transform: translate(0, 0) scale(1);
    }
}

.layout-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
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
    background-color: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* ✅ Removes extra margin/padding */
body,
html {
    margin: 0;
    padding: 0;
    height: 100%;
}
</style>
