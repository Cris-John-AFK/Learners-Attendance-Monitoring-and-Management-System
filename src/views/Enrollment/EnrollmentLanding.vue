<template>
    <div class="landing-container">
      <h1>Select Enrollment Type</h1>
      <div class="card-group">
        <div class="enrollment-card old" @click="$router.push('/enrollment/old-student')">
          <h2>Old Student</h2>
        </div>
        <div class="enrollment-card new" @click="$router.push('/enrollment/new-student')">
          <h2>New Student</h2>
        </div>
        <div class="enrollment-card transfer" @click="$router.push('/enrollment/transfer-student')">
          <h2>Transfer Student</h2>
        </div>
      </div>
    </div>
</template>

<script setup>
</script>

<style scoped>
/* Page Styling */
.landing-container {
    text-align: center;
    padding: 50px;
}

h1 {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

/* Cards Container */
.card-group {
    display: flex;
    justify-content: center;
    gap: 40px; /* Increased gap for better spacing */
    margin-top: 30px;
}

/* Enrollment Cards */
.enrollment-card {
    width: 280px; /* Increased width */
    height: 180px; /* Increased height */
    background: linear-gradient(135deg, #1976D2, #64B5F6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    font-size: 22px;
    font-weight: bold;
    text-transform: uppercase;
    cursor: pointer;
    position: relative;
    transition: transform 0.3s ease-out, box-shadow 0.3s ease-out;
    box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    text-align: center;
    white-space: nowrap; /* Prevents text from breaking */
    padding: 10px;
}

/* Prevents text from cutting off */
.enrollment-card h2 {
    margin: 0;
    padding: 10px;
    font-size: 22px; /* Adjusted font size */
    text-align: center;
    max-width: 90%; /* Ensures text doesn't overflow */
    white-space: normal; /* Allow wrapping */
    word-wrap: break-word; /* Forces words to break properly */
    overflow: hidden; /* Prevents overflow */
}


/* Hover Effect - Shine Glare */
.enrollment-card::before {
    content: "";
    position: absolute;
    top: -100%;
    left: -100%;
    width: 150%;
    height: 150%;
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(45deg);
    transition: all 0.5s ease-in-out;
}

.enrollment-card:hover::before {
    top: 100%;
    left: 100%;
}

/* Hover Effect - Scale & Shadow */
.enrollment-card:hover {
    transform: scale(1.05);
    box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.3);
}

/* Click Animation */
.enrollment-card:active {
    transform: scale(0.95);
}

/* Unique Colors for Each Card */
.old {
    background: linear-gradient(135deg, #43a047, #81c784);
}

.new {
    background: linear-gradient(135deg, #fb8c00, #ffb74d);
}

.transfer {
    background: linear-gradient(135deg, #d81b60, #f06292);
}
</style>
