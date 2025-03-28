<template>
    <div class="landing-container">
        <div class="landing-card">
            <h1>Select Enrollment Type</h1>
            <div class="card-group">
                <div class="enrollment-card old">
                    <!-- Floating symbols -->
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>

                    <div class="card-content" @click="$router.push('/enrollment/old-student')">
                        <i class="pi pi-user-edit card-icon"></i>
                        <h2>Old Student</h2>
                    </div>
                </div>
                <div class="enrollment-card new">
                    <!-- Floating symbols -->
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>

                    <div class="card-content" @click="$router.push('/enrollment/registration')">
                        <i class="pi pi-user-plus card-icon"></i>
                        <h2>New Student</h2>
                    </div>
                </div>
                <div class="enrollment-card transfer">
                    <!-- Floating symbols -->
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>

                    <div class="card-content" @click="$router.push('/enrollment/transfer-student')">
                        <i class="pi pi-directions card-icon"></i>
                        <h2>Transfer Student</h2>
                    </div>
                </div>
            </div>
            <div class="accessibility-note">
                <p>Need help? Call us at <a href="tel:+1234567890">(123) 456-7890</a></p>
            </div>
        </div>
    </div>
</template>

<script setup>
// No script needed
</script>

<style scoped>
/* Page Styling */
.landing-container {
    text-align: center;
    padding: 50px;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
}

.landing-card {
    max-width: 1200px;
    width: 100%;
    padding: 40px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    animation: fadeIn 0.8s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

h1 {
    font-size: 36px;
    font-weight: bold;
    color: white;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 40px;
    position: relative;
    text-shadow:
        0 2px 10px rgba(0, 0, 0, 0.4),
        0 0 5px rgba(0, 0, 0, 0.3);
}

h1::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 5px;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    border-radius: 2px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

/* Cards Container */
.card-group {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-top: 30px;
    flex-wrap: wrap;
}

/* Enrollment Cards - Admin Subject Style */
.enrollment-card {
    width: 280px;
    height: 220px;
    border-radius: 16px;
    cursor: pointer;
    position: relative;
    transition: all 0.3s ease;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(211, 233, 255, 0.9), rgba(233, 244, 255, 0.9));
    border: 1px solid rgba(74, 135, 213, 0.3);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.card-content {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 20px;
}

.card-icon {
    font-size: 4rem;
    margin-bottom: 25px;
    background: white;
    width: 85px;
    height: 85px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    color: #4a87d5;
}

.enrollment-card h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #1a365d;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.4);
}

/* Make symbols more visible */
.enrollment-card .symbol {
    position: absolute;
    color: rgba(26, 54, 93, 0.5);
    font-family: 'Courier New', monospace;
    pointer-events: none;
    z-index: 1;
    animation: float-symbol 8s linear infinite;
    font-weight: bold;
}

.enrollment-card:nth-child(3n) .symbol {
    animation-duration: 10s;
}

.enrollment-card:nth-child(3n + 1) .symbol {
    animation-duration: 7s;
}

.enrollment-card .symbol:nth-child(1) {
    top: 10%;
    left: 10%;
    animation-delay: 0s;
}
.enrollment-card .symbol:nth-child(2) {
    top: 30%;
    left: 80%;
    animation-delay: 1s;
}
.enrollment-card .symbol:nth-child(3) {
    top: 70%;
    left: 30%;
    animation-delay: 2s;
}
.enrollment-card .symbol:nth-child(4) {
    top: 60%;
    left: 70%;
    animation-delay: 3s;
}
.enrollment-card .symbol:nth-child(5) {
    top: 20%;
    left: 50%;
    animation-delay: 4s;
}

/* Math symbol content variations */
.enrollment-card.old .symbol:nth-child(1)::after {
    content: 'π';
    font-size: 28px;
}
.enrollment-card.old .symbol:nth-child(2)::after {
    content: '∑';
    font-size: 32px;
}
.enrollment-card.old .symbol:nth-child(3)::after {
    content: '∞';
    font-size: 36px;
}
.enrollment-card.old .symbol:nth-child(4)::after {
    content: '√';
    font-size: 32px;
}
.enrollment-card.old .symbol:nth-child(5)::after {
    content: 'θ';
    font-size: 28px;
}

.enrollment-card.new .symbol:nth-child(1)::after {
    content: 'H₂O';
    font-size: 24px;
}
.enrollment-card.new .symbol:nth-child(2)::after {
    content: 'CO₂';
    font-size: 24px;
}
.enrollment-card.new .symbol:nth-child(3)::after {
    content: 'E=mc²';
    font-size: 22px;
}
.enrollment-card.new .symbol:nth-child(4)::after {
    content: 'H⁺';
    font-size: 26px;
}
.enrollment-card.new .symbol:nth-child(5)::after {
    content: 'NaCl';
    font-size: 24px;
}

.enrollment-card.transfer .symbol:nth-child(1)::after {
    content: 'A';
    font-size: 34px;
}
.enrollment-card.transfer .symbol:nth-child(2)::after {
    content: 'B';
    font-size: 36px;
}
.enrollment-card.transfer .symbol:nth-child(3)::after {
    content: 'C';
    font-size: 32px;
}
.enrollment-card.transfer .symbol:nth-child(4)::after {
    content: 'X';
    font-size: 30px;
}
.enrollment-card.transfer .symbol:nth-child(5)::after {
    content: 'Y';
    font-size: 34px;
}

@keyframes float-symbol {
    0% {
        transform: translateY(0) translateX(0) rotate(0deg);
        opacity: 0;
    }
    20% {
        opacity: 1;
    }
    80% {
        opacity: 1;
    }
    100% {
        transform: translateY(-100px) translateX(20px) rotate(360deg);
        opacity: 0;
    }
}

/* Accessibility note */
.accessibility-note {
    margin-top: 40px;
    font-size: 18px;
    color: white;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}

.accessibility-note a {
    color: white;
    font-weight: bold;
    text-decoration: underline;
    padding: 5px;
}

.accessibility-note a:hover {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

/* Hover Effect - Scale & Shadow */
.enrollment-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    border-color: rgba(74, 135, 213, 0.5);
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
        font-size: 32px;
    }

    .landing-card {
        padding: 30px 20px;
    }

    .accessibility-note {
        font-size: 16px;
    }
}
</style>
