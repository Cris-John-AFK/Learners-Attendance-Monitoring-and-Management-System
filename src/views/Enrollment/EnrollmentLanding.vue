<template>
    <div class="landing-container">
        <h1>Select Enrollment Type</h1>
        <div class="card-group">
            <div class="enrollment-card old" @click="$router.push('/enrollment/old-student')" :style="{ background: getRandomGradient('green') }">
                <div class="card-content">
                    <i class="pi pi-user-edit card-icon"></i>
                    <h2>Old Student</h2>
                    <p class="card-description">Returning students who were previously enrolled</p>
                </div>
            </div>
            <div class="enrollment-card new" @click="$router.push('/enrollment/registration')" :style="{ background: getRandomGradient('orange') }">
                <div class="card-content">
                    <i class="pi pi-user-plus card-icon"></i>
                    <h2>New Student</h2>
                    <p class="card-description">First-time enrollees in our institution</p>
                </div>
            </div>
            <div class="enrollment-card transfer" @click="$router.push('/enrollment/transfer-student')" :style="{ background: getRandomGradient('purple') }">
                <div class="card-content">
                    <i class="pi pi-directions card-icon"></i>
                    <h2>Transfer Student</h2>
                    <p class="card-description">Students coming from other schools</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
const getRandomGradient = (type) => {
    // Color palettes for different card types
    const palettes = {
        green: [
            ['#84fab0', '#8fd3f4'],
            ['#a8ff78', '#78ffd6'],
            ['#56ab2f', '#a8e063'],
            ['#00b09b', '#96c93d'],
            ['#43a047', '#7cb342']
        ],
        orange: [
            ['#ff9a9e', '#fad0c4'],
            ['#ff758c', '#ff7eb3'],
            ['#f6d365', '#fda085'],
            ['#fb8c00', '#ffb74d'],
            ['#ff7043', '#ffb74d']
        ],
        purple: [
            ['#a18cd1', '#fbc2eb'],
            ['#8e2de2', '#4a00e0'],
            ['#d81b60', '#f06292'],
            ['#c2185b', '#e91e63'],
            ['#6a1b9a', '#9c27b0']
        ]
    };

    const selectedPalette = palettes[type];
    const randomIndex = Math.floor(Math.random() * selectedPalette.length);
    const [color1, color2] = selectedPalette[randomIndex];

    return `linear-gradient(135deg, ${color1}, ${color2})`;
};
</script>

<style scoped>
/* Page Styling */
.landing-container {
    text-align: center;
    padding: 50px;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
    min-height: calc(100vh - 4rem);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

h1 {
    font-size: 32px;
    font-weight: bold;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 40px;
    position: relative;
}

h1::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    border-radius: 2px;
}

/* Cards Container */
.card-group {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-top: 30px;
    flex-wrap: wrap;
}

/* Enrollment Cards */
.enrollment-card {
    width: 300px;
    height: 220px;
    color: white;
    border-radius: 16px;
    cursor: pointer;
    position: relative;
    transition: all 0.3s ease;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.card-content {
    padding: 20px;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    width: 100%;
}

.card-icon {
    font-size: 3rem;
    margin-bottom: 15px;
    background: rgba(255, 255, 255, 0.2);
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.enrollment-card h2 {
    margin: 0 0 10px 0;
    font-size: 24px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.card-description {
    font-size: 14px;
    opacity: 0.9;
    margin: 0;
    max-width: 90%;
}

/* Hover Effect - Shine Glare */
.enrollment-card::before {
    content: '';
    position: absolute;
    top: -100%;
    left: -100%;
    width: 150%;
    height: 150%;
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(45deg);
    transition: all 0.7s ease-in-out;
}

.enrollment-card:hover::before {
    top: 100%;
    left: 100%;
}

/* Hover Effect - Scale & Shadow */
.enrollment-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
}

/* Click Animation */
.enrollment-card:active {
    transform: scale(0.98);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-group {
        flex-direction: column;
        align-items: center;
    }

    .enrollment-card {
        width: 100%;
        max-width: 320px;
    }

    h1 {
        font-size: 28px;
    }
}
</style>
