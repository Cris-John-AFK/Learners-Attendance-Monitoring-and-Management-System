<template>
    <div class="landing-container">
        <h1>Select User Type</h1>
        <div class="card-group">
            <div class="new-card guardians" @click="$router.push('/guest')">
                <div class="card-content">
                    <h2>I am a Guardian</h2>
                    <p>View student information</p>
                </div>
                <div class="card-arrow">
                    <i class="pi pi-angle-right"></i>
                </div>
            </div>

            <div class="new-card teachers" @click="$router.push('/teacher')">
                <div class="card-content">
                    <h2>I am a Teacher</h2>
                    <p>Manage classes and grades</p>
                </div>
                <div class="card-arrow">
                    <i class="pi pi-angle-right"></i>
                </div>
            </div>

            <div class="new-card admin" @click="$router.push('/admin')">
                <div class="card-content">
                    <h2>I am an Admin</h2>
                    <p>School administration</p>
                </div>
                <div class="card-arrow">
                    <i class="pi pi-angle-right"></i>
                </div>
            </div>

            <div class="new-card gate" @click="$router.push('/guardhouse')">
                <div class="card-content">
                    <h2>I am Gate Personnel</h2>
                    <p>Manage entry and exit</p>
                </div>
                <div class="card-arrow">
                    <i class="pi pi-angle-right"></i>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
const router = useRouter();
</script>

<style scoped>
/* Page Styling */
.landing-container {
    text-align: center;
    padding: 5px;
}

h1 {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 20px;
}

/* Cards Container */
.card-group {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    justify-content: center;
    align-items: center;
    max-width: 800px;
    margin: 0 auto;
}

/* New Card Design */
.new-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    height: 100px;
    position: relative;
    overflow: hidden;
}

.new-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 8px;
    height: 100%;
    border-radius: 4px 0 0 4px;
}

.guardians::before {
    background-color: #1e88e5;
}

.teachers::before {
    background-color: #43a047;
}

.admin::before {
    background-color: #fb8c00;
}

.gate::before {
    background-color: #d81b60;
}

.card-content {
    text-align: left;
    padding-left: 10px;
}

.card-content h2 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0 0 5px 0;
}

.card-content p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

.card-arrow {
    background-color: #ff7518;
    color: white;
    width: 40px;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: absolute;
    right: 0;
    top: 0;
}

.card-arrow i {
    font-size: 24px;
}

/* Hover Effects */
.new-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-group {
        grid-template-columns: 1fr;
    }
}
</style>
