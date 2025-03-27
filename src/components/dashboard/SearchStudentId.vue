<script setup>
import { StudentAttendanceService } from '@/router/service/StudentAttendanceService';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const searchId = ref('');
const dialogVisible = ref(false);

async function searchStudentById() {
    const data = await StudentAttendanceService.getStudentsLarge();
    const foundStudent = data.find((student) => student.id == searchId.value);

    if (foundStudent) {
        router.push(`/guest/student/${searchId.value}`);
    } else {
        dialogVisible.value = true; // Show overlay if not found
    }
}
</script>

<template>
    <div class="search-wrapper">
        <div class="profile-container">
            <img src="https://upload.wikimedia.org/wikipedia/commons/8/89/Portrait_Placeholder.png" alt="Student Profile" class="profile-picture" />
        </div>
        <div class="search-content">
            <h1 class="search-title">Search Student Attendance</h1>
            <div class="search-form">
                <InputText v-model="searchId" placeholder="Enter Student ID" class="p-inputtext-lg" />
                <Button label="Search" @click="searchStudentById" class="search-button" />
            </div>
        </div>

        <!-- Not Found Overlay -->
        <Dialog v-model:visible="dialogVisible" header="Error" :modal="true" :closable="true">
            <p class="text-red-500">Student ID not found.</p>
        </Dialog>
    </div>
</template>

<style scoped>
.search-wrapper {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100%;
    max-width: 600px;
    padding: 0 2rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.profile-container {
    margin-bottom: 1.5rem;
}

.profile-picture {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 5px solid white;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    animation: pulse 2s infinite;
    object-fit: cover;
    background-color: #f5f5f5;
}

.search-title {
    font-size: 1.75rem;
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 2rem;
}

.search-form {
    display: flex;
    gap: 1rem;
    justify-content: center;
    align-items: center;
}

:deep(.p-inputtext) {
    width: 350px;
    height: 3rem;
    font-size: 1.1rem;
    border-radius: 6px;
}

:deep(.search-button) {
    height: 3rem;
    min-width: 120px;
    font-size: 1.1rem;
    background: var(--primary-color);
    border: none;
    border-radius: 6px;
    transition: transform 0.2s ease, background-color 0.2s ease;
}

:deep(.search-button:hover) {
    background: var(--primary-600);
    transform: translateY(-2px);
}

:deep(.search-button:active) {
    transform: translateY(0);
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }
    50% {
        transform: scale(1.03);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
    }
}

@media (max-width: 640px) {
    .search-wrapper {
        padding: 0 1rem;
    }

    .profile-picture {
        width: 120px;
        height: 120px;
    }

    .search-title {
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .search-form {
        flex-direction: column;
        gap: 0.75rem;
    }

    :deep(.p-inputtext) {
        width: 100%;
    }

    :deep(.search-button) {
        width: 100%;
    }
}
</style>
