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
        <div class="school-scene-background">
            <div class="sky"></div>
            <div class="clouds">
                <div class="cloud cloud-1"></div>
                <div class="cloud cloud-2"></div>
                <div class="cloud cloud-3"></div>
            </div>
            <div class="butterflies">
                <div class="butterfly butterfly-1"></div>
                <div class="butterfly butterfly-2"></div>
                <div class="butterfly butterfly-3"></div>
            </div>
            <div class="hills"></div>
            <div class="school-building">
                <div class="school-roof"></div>
                <div class="school-flag"></div>
                <div class="school-clock"></div>
                <div class="school-door"></div>
                <div class="school-windows"></div>
            </div>
            <div class="trees">
                <div class="tree tree-1"></div>
                <div class="tree tree-2"></div>
                <div class="tree tree-3"></div>
            </div>
            <div class="teacher">
                <div class="teacher-head"></div>
                <div class="teacher-body"></div>
                <div class="teacher-pointer"></div>
            </div>
            <div class="students">
                <div class="student student-1"></div>
                <div class="student student-2"></div>
            </div>
            <div class="flowers">
                <div class="flower flower-1"></div>
                <div class="flower flower-2"></div>
            </div>
        </div>
        <div class="layout-content">
            <div class="layout-main">
                <router-view />
                <!-- Ensures pages load inside layout -->
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
    position: relative;
    overflow: hidden;
}

/* School scene background */
.school-scene-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    overflow: hidden;
}

/* Sky */
.sky {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 60%;
    background: linear-gradient(to bottom, #87ceeb, #a7e9ff);
}

/* Clouds */
.cloud {
    position: absolute;
    background-color: white;
    border-radius: 50px;
}

.cloud-1 {
    top: 50px;
    left: 100px;
    width: 120px;
    height: 40px;
    animation: float-right 30s linear infinite;
}

.cloud-1::before,
.cloud-1::after {
    content: '';
    position: absolute;
    background-color: white;
    border-radius: 50%;
}

.cloud-1::before {
    width: 50px;
    height: 50px;
    top: -20px;
    left: 15px;
}

.cloud-1::after {
    width: 60px;
    height: 60px;
    top: -30px;
    right: 15px;
}

.cloud-2 {
    top: 80px;
    right: 150px;
    width: 100px;
    height: 30px;
    animation: float-left 25s linear infinite;
}

.cloud-2::before,
.cloud-2::after {
    content: '';
    position: absolute;
    background-color: white;
    border-radius: 50%;
}

.cloud-2::before {
    width: 40px;
    height: 40px;
    top: -15px;
    left: 10px;
}

.cloud-2::after {
    width: 50px;
    height: 50px;
    top: -25px;
    right: 10px;
}

.cloud-3 {
    top: 150px;
    left: 300px;
    width: 80px;
    height: 25px;
    animation: float-right 35s linear infinite;
}

.cloud-3::before,
.cloud-3::after {
    content: '';
    position: absolute;
    background-color: white;
    border-radius: 50%;
}

.cloud-3::before {
    width: 30px;
    height: 30px;
    top: -12px;
    left: 8px;
}

.cloud-3::after {
    width: 40px;
    height: 40px;
    top: -20px;
    right: 8px;
}

@keyframes float-right {
    0% {
        transform: translateX(-200px);
    }
    100% {
        transform: translateX(calc(100vw + 200px));
    }
}

@keyframes float-left {
    0% {
        transform: translateX(calc(100vw + 200px));
    }
    100% {
        transform: translateX(-200px);
    }
}

/* Butterflies */
.butterfly {
    position: absolute;
    width: 20px;
    height: 20px;
    background-color: transparent;
}

.butterfly::before,
.butterfly::after {
    content: '';
    position: absolute;
    width: 10px;
    height: 15px;
    border-radius: 10px 10px 0 0;
}

.butterfly-1 {
    top: 100px;
    left: 200px;
    animation: flutter 10s linear infinite;
}

.butterfly-1::before,
.butterfly-1::after {
    background-color: #ffeb3b;
}

.butterfly-1::before {
    left: -5px;
    transform-origin: right center;
    animation: wing-left 0.5s ease-in-out infinite alternate;
}

.butterfly-1::after {
    right: -5px;
    transform-origin: left center;
    animation: wing-right 0.5s ease-in-out infinite alternate;
}

.butterfly-2 {
    top: 150px;
    left: 250px;
    animation: flutter 15s linear infinite;
}

.butterfly-2::before,
.butterfly-2::after {
    background-color: #ff4081;
}

.butterfly-2::before {
    left: -5px;
    transform-origin: right center;
    animation: wing-left 0.6s ease-in-out infinite alternate;
}

.butterfly-2::after {
    right: -5px;
    transform-origin: left center;
    animation: wing-right 0.6s ease-in-out infinite alternate;
}

.butterfly-3 {
    top: 120px;
    left: 180px;
    animation: flutter 12s linear infinite;
}

.butterfly-3::before,
.butterfly-3::after {
    background-color: #ffeb3b;
}

.butterfly-3::before {
    left: -5px;
    transform-origin: right center;
    animation: wing-left 0.4s ease-in-out infinite alternate;
}

.butterfly-3::after {
    right: -5px;
    transform-origin: left center;
    animation: wing-right 0.4s ease-in-out infinite alternate;
}

@keyframes flutter {
    0% {
        transform: translate(0, 0);
    }
    25% {
        transform: translate(50px, -30px);
    }
    50% {
        transform: translate(100px, 0);
    }
    75% {
        transform: translate(50px, 30px);
    }
    100% {
        transform: translate(0, 0);
    }
}

@keyframes wing-left {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(-30deg);
    }
}

@keyframes wing-right {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(30deg);
    }
}

/* Hills */
.hills {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 40%;
    background: linear-gradient(to bottom, #8bc34a, #7cb342);
    border-radius: 50% 50% 0 0 / 100% 100% 0 0;
}

/* School Building */
.school-building {
    position: absolute;
    bottom: 20%;
    right: 10%;
    width: 300px;
    height: 150px;
    background-color: #fdd835;
    border: 3px solid #5d4037;
}

.school-roof {
    position: absolute;
    top: -30px;
    left: 0;
    width: 100%;
    height: 30px;
    background-color: #f57f17;
}

.school-flag {
    position: absolute;
    top: -50px;
    left: 50%;
    width: 5px;
    height: 30px;
    background-color: #5d4037;
}

.school-flag::after {
    content: '';
    position: absolute;
    top: 0;
    left: 5px;
    width: 20px;
    height: 15px;
    background-color: #f44336;
}

.school-clock {
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 30px;
    background-color: white;
    border-radius: 50%;
    border: 2px solid #5d4037;
}

.school-clock::before,
.school-clock::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    background-color: #5d4037;
}

.school-clock::before {
    width: 10px;
    height: 2px;
    transform: translate(-50%, -50%) rotate(45deg);
}

.school-clock::after {
    width: 8px;
    height: 2px;
    transform: translate(-50%, -50%) rotate(-45deg);
}

.school-door {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 60px;
    background-color: #f44336;
}

.school-windows {
    position: absolute;
    top: 30px;
    left: 0;
    width: 100%;
    height: 60px;
}

.school-windows::before,
.school-windows::after {
    content: '';
    position: absolute;
    width: 30px;
    height: 30px;
    background-color: #bbdefb;
    border: 2px solid #5d4037;
}

.school-windows::before {
    left: 60px;
}

.school-windows::after {
    right: 60px;
}

/* Trees */
.tree {
    position: absolute;
    bottom: 20%;
}

.tree::before {
    content: '';
    position: absolute;
    width: 15px;
    height: 40px;
    background-color: #795548;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
}

.tree::after {
    content: '';
    position: absolute;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #388e3c;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
}

.tree-1 {
    right: 30%;
}

.tree-2 {
    right: 5%;
}

.tree-3 {
    left: 75%;
}

/* Teacher */
.teacher {
    position: absolute;
    bottom: 20%;
    left: 20%;
    width: 30px;
    height: 80px;
}

.teacher-head {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 30px;
    background-color: #ffccbc;
    border-radius: 50%;
}

.teacher-head::before {
    content: '';
    position: absolute;
    top: -10px;
    left: -5px;
    width: 40px;
    height: 20px;
    background-color: #795548;
    border-radius: 50% 50% 0 0;
}

.teacher-body {
    position: absolute;
    top: 30px;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 50px;
    background-color: #f48fb1;
    border-radius: 10px 10px 0 0;
}

.teacher-pointer {
    position: absolute;
    top: 40px;
    right: -20px;
    width: 30px;
    height: 3px;
    background-color: #795548;
    transform: rotate(-20deg);
}

/* Students */
.student {
    position: absolute;
    bottom: 20%;
    width: 20px;
    height: 50px;
}

.student::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 20px;
    background-color: #ffccbc;
    border-radius: 50%;
}

.student-1 {
    left: 25%;
}

.student-1::before {
    background-color: #ffccbc;
}

.student-1::after {
    content: '';
    position: absolute;
    top: -5px;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 10px;
    background-color: #ff5722;
    border-radius: 50% 50% 0 0;
}

.student-2 {
    left: 30%;
}

.student-2::before {
    background-color: #ffccbc;
}

.student-2::after {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 10px;
    background-color: #3f51b5;
    border-radius: 5px 5px 0 0;
}

/* Flowers */
.flower {
    position: absolute;
    bottom: 20%;
}

.flower::before {
    content: '';
    position: absolute;
    width: 10px;
    height: 30px;
    background-color: #4caf50;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
}

.flower::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    bottom: 25px;
    left: 50%;
    transform: translateX(-50%);
}

.flower-1 {
    left: 10%;
}

.flower-1::after {
    background-color: #f44336;
}

.flower-2 {
    left: 15%;
}

.flower-2::after {
    background-color: #ffeb3b;
}

/* Centers the container perfectly */
.layout-content {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    position: relative;
    z-index: 1;
}

/* Ensures the box is centered with proper styling */
.layout-main {
    width: 100%;
    max-width: 600px;
    text-align: center;
    background-color: rgba(255, 255, 255, 0.85);
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(5px);
    position: relative;
    z-index: 1;
}

/* Removes extra margin/padding */
body,
html {
    margin: 0;
    padding: 0;
    height: 100%;
}
</style>
