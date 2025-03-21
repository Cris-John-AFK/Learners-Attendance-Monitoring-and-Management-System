<template>
    <div class="enrollment-container">
        <div class="school-placeholder">
            <img src="@/assets/school-placeholder.jpg" alt="School Placeholder">
        </div>
        <h1>Want to Enroll?</h1>
        <button class="enroll-now" @click="$router.push('/enrollment/landing')">Enroll Now!</button>
    </div>
</template>

<script setup>
</script>

<style scoped>
/* ✅ Keeps content centered but reduces excess height */
.enrollment-container {
    display: flex;
    flex-direction: column;
    justify-content: center; /* Centers vertically */
    align-items: center; /* Centers horizontally */
    text-align: center;
    height: calc(100vh - 120px); /* Adjusts height to leave space under the top bar */
    padding-top: 50px; /* Adds space below the top bar */
}

/* ✅ Ensures image stays centered & properly sized */
.school-placeholder img {
    max-width: 100%; /* Ensures responsiveness */
    width: 300px;
    height: auto;
    margin-bottom: 20px;
}

/* ✅ Improves heading styling */
h1 {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 15px;
}

/* ✅ Centers button & adds smooth hover effect */
.enroll-now {
    background: #1976D2;
    color: white;
    padding: 15px 30px;
    font-size: 18px;
    font-weight: bold;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.3s ease-in-out;
}

.enroll-now:hover {
    background: #1257A3;
}
</style>
