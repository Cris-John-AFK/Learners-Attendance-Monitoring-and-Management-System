<template>
    <div class="landing-container">
        <h1>Select User Type</h1>
        <div class="card-group">
            <Card class="enrollment-card guardians" @click="$router.push('/guest')">
                <template #content>
                    <i class="pi pi-users icon"></i>
                    <h2>Guardians</h2>
                </template>
            </Card>

            <Card class="enrollment-card teachers" @click="$router.push('/teacher')">
                <template #content>
                    <i class="pi pi-user icon"></i>
                    <h2>Teachers</h2>
                </template>
            </Card>

            <Card class="enrollment-card admin" @click="$router.push('/admin')">
                <template #content>
                    <i class="pi pi-cog icon"></i>
                    <h2>Admin</h2>
                </template>
            </Card>

            <Card class="enrollment-card gate" @click="$router.push('/guardhouse')">
                <template #content>
                    <i class="pi pi-shield icon"></i>
                    <h2>Gate</h2>
                </template>
            </Card>
        </div>
    </div>
</template>

<script setup>
import Card from 'primevue/card';
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
    max-width: 600px;
    margin: 0 auto;
}

.enrollment-card {
    width: 100%;
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    flex-direction: column;
    border-radius: 15px;
    font-size: 22px;
    font-weight: bold;
    text-transform: uppercase;
    cursor: pointer;
    transition: transform 0.3s ease-out, box-shadow 0.3s ease-out;
    box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.2);
    padding-top: 20px;
}

/* Text & Icon Styling */
.enrollment-card h2 {
    margin: 10px 0 0;
    font-size: 20px;
    text-align: center;
    color: white;
    text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.7); /* Stronger contrast */
}

.icon {
    font-size: 50px;
    color: white;
}

/* Hover Effects */
.enrollment-card:hover {
    transform: scale(1.05);
    box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.3);
}

.enrollment-card:active {
    transform: scale(0.95);
}

/* Unique Colors for Each Card */
.guardians {
    background: linear-gradient(135deg, #1E88E5, #1976D2);
}
.teachers {
    background: linear-gradient(135deg, #43A047, #2E7D32);
}
.admin {
    background: linear-gradient(135deg, #FB8C00, #E65100);
}
.gate {
    background: linear-gradient(135deg, #D81B60, #880E4F);
}
</style>
