<script setup>
import { reactive } from 'vue';

// Create array of floating letters and numbers
const floatingItems = reactive([
    // Foreground (larger, more opaque)
    { content: 'A', top: 15, left: 10, size: 90, color: 'rgba(255,255,255,0.7)', duration: 35, delay: 0, zIndex: 0 },
    { content: '7', top: 25, left: 85, size: 100, color: 'rgba(255,255,255,0.65)', duration: 40, delay: 1, zIndex: 0 },
    { content: 'B', top: 60, left: 5, size: 95, color: 'rgba(255,255,255,0.68)', duration: 38, delay: 3, zIndex: 0 },

    // Middleground (medium size and opacity)
    { content: '3', top: 75, left: 80, size: 75, color: 'rgba(255,255,255,0.6)', duration: 42, delay: 2, zIndex: 0 },
    { content: 'C', top: 10, left: 60, size: 70, color: 'rgba(255,255,255,0.62)', duration: 36, delay: 0.5, zIndex: 0 },
    { content: '9', top: 40, left: 90, size: 80, color: 'rgba(255,255,255,0.58)', duration: 44, delay: 2.5, zIndex: 0 },

    // Background (smaller, more visible)
    { content: 'D', top: 85, left: 30, size: 55, color: 'rgba(255,255,255,0.55)', duration: 39, delay: 1.5, zIndex: 0 },
    { content: '1', top: 30, left: 15, size: 50, color: 'rgba(255,255,255,0.5)', duration: 34, delay: 4, zIndex: 0 },
    { content: 'E', top: 50, left: 75, size: 48, color: 'rgba(255,255,255,0.52)', duration: 41, delay: 2, zIndex: 0 },
    { content: '5', top: 70, left: 45, size: 52, color: 'rgba(255,255,255,0.48)', duration: 37, delay: 0.7, zIndex: 0 }
]);
</script>

<template>
    <div class="enrollment-layout">
        <!-- Gradient background -->
        <div class="gradient-background">
            <div class="animated-gradient"></div>
        </div>

        <!-- Floating letters and numbers -->
        <div
            v-for="(item, index) in floatingItems"
            :key="index"
            class="floating-element"
            :style="{
                top: item.top + '%',
                left: item.left + '%',
                fontSize: item.size + 'px',
                color: item.color,
                zIndex: item.zIndex,
                animationDuration: item.duration + 's',
                animationDelay: item.delay + 's'
            }"
        >
            {{ item.content }}
        </div>

        <!-- Main content -->
        <div class="layout-main">
            <div class="layout-content">
                <router-view />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Layout container */
.enrollment-layout {
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
}

/* Gradient background */
.gradient-background {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: -1;
    overflow: hidden;
}

.animated-gradient {
    position: absolute;
    top: -100px;
    left: -100px;
    right: -100px;
    bottom: -100px;
    background: linear-gradient(45deg, rgba(238, 119, 82, 0.7), /* #ee7752 */ rgba(231, 60, 126, 0.7), /* #e73c7e */ rgba(35, 166, 213, 0.7), /* #23a6d5 */ rgba(35, 213, 171, 0.7) /* #23d5ab */);
    background-size: 400% 400%;
    animation: gradientAnimation 15s ease infinite;
    filter: blur(20px);
}

@keyframes gradientAnimation {
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

/* Floating elements */
.floating-element {
    position: fixed;
    font-weight: bold;
    opacity: 1;
    font-family: 'Arial Rounded MT Bold', 'Helvetica Rounded', Arial, sans-serif;
    pointer-events: none;
    animation: float 40s ease-in-out infinite;
    text-shadow:
        0 0 20px rgba(255, 255, 255, 0.7),
        0 0 40px rgba(255, 255, 255, 0.5),
        0 0 60px rgba(255, 255, 255, 0.3);
}

/* Simplified floating animation */
@keyframes float {
    0%,
    100% {
        transform: translate(0, 0) rotate(0deg) scale(1);
    }
    25% {
        transform: translate(40px, 20px) rotate(3deg) scale(1.05);
    }
    50% {
        transform: translate(0, 40px) rotate(0deg) scale(1);
    }
    75% {
        transform: translate(-40px, 20px) rotate(-3deg) scale(0.95);
    }
}

/* Main content area */
.layout-main {
    position: relative;
    z-index: 1;
    min-height: 100vh;
}

.layout-content {
    padding: 0;
    margin: 0;
    width: 100%;
}
</style>
