<template>
    <div class="enrollment-hero">
        <div class="hero-content">
            <div class="school-image-container">
                <img src="@/assets/school-placeholder.jpg" alt="School" class="school-image" />
                <div class="image-overlay"></div>
            </div>

            <div class="hero-text-container">
                <div class="school-logo">
                    <img src="/demo/images/logo.svg" alt="School Logo" />
                </div>

                <h1 class="hero-title">Begin Your Educational Journey</h1>
                <p class="hero-subtitle">Join our community of learners and discover your potential</p>

                <div class="cta-container">
                    <button class="enroll-button" @click="$router.push('/enrollment/landing')">
                        <span>Enroll Now</span>
                        <i class="pi pi-arrow-right"></i>
                    </button>

                    <button class="learn-more-button" @click="scrollToInfo">
                        <span>Learn More</span>
                        <i class="pi pi-info-circle"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="wave-divider">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                <path
                    fill="#ffffff"
                    fill-opacity="1"
                    d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,149.3C960,160,1056,160,1152,138.7C1248,117,1344,75,1392,53.3L1440,32L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"
                ></path>
            </svg>
        </div>
    </div>

    <div id="school-info" class="school-info-section">
        <div class="info-container">
            <h2 class="section-title">Why Choose Our School?</h2>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="pi pi-book"></i>
                    </div>
                    <h3>Quality Education</h3>
                    <p>Our curriculum is designed to foster critical thinking and creativity</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="pi pi-users"></i>
                    </div>
                    <h3>Expert Teachers</h3>
                    <p>Learn from passionate educators dedicated to student success</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="pi pi-desktop"></i>
                    </div>
                    <h3>Modern Facilities</h3>
                    <p>Access to state-of-the-art technology and learning resources</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="pi pi-heart"></i>
                    </div>
                    <h3>Supportive Community</h3>
                    <p>Join a welcoming environment that nurtures personal growth</p>
                </div>
            </div>

            <div class="enrollment-cta">
                <h3>Ready to join our community?</h3>
                <button class="enroll-button-secondary" @click="$router.push('/enrollment/landing')">Start Enrollment Process</button>
            </div>
        </div>
    </div>
</template>

<script setup>
const scrollToInfo = () => {
    document.getElementById('school-info').scrollIntoView({
        behavior: 'smooth'
    });
};
</script>

<style scoped>
/* Hero Section */
.enrollment-hero {
    position: relative;
    height: 100vh;
    overflow: hidden;
}

.hero-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
    position: relative;
    z-index: 1;
    padding: 0 20px;
}

.school-image-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
}

.school-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.8), rgba(139, 92, 246, 0.8));
}

.hero-text-container {
    max-width: 800px;
    text-align: center;
    color: white;
    padding: 40px;
    border-radius: 20px;
    background: rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(10px);
    animation: fadeIn 1s ease-out;
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

.school-logo {
    margin-bottom: 20px;
}

.school-logo img {
    height: 80px;
    filter: drop-shadow(0 0 10px rgba(0, 0, 0, 0.3));
}

.hero-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 20px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.hero-subtitle {
    font-size: 1.5rem;
    margin-bottom: 40px;
    opacity: 0.9;
}

.cta-container {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.enroll-button {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: white;
    border: none;
    padding: 15px 30px;
    font-size: 1.2rem;
    font-weight: 600;
    border-radius: 50px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.enroll-button:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.enroll-button:active {
    transform: translateY(-2px);
}

.learn-more-button {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 2px solid white;
    padding: 15px 30px;
    font-size: 1.2rem;
    font-weight: 600;
    border-radius: 50px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.learn-more-button:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-5px);
}

.learn-more-button:active {
    transform: translateY(-2px);
}

.wave-divider {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
}

/* School Info Section */
.school-info-section {
    background-color: white;
    padding: 80px 20px;
}

.info-container {
    max-width: 1200px;
    margin: 0 auto;
}

.section-title {
    text-align: center;
    font-size: 2.5rem;
    color: #1e293b;
    margin-bottom: 60px;
    position: relative;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    border-radius: 2px;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-bottom: 80px;
}

.feature-card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
}

.feature-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: white;
    font-size: 1.8rem;
}

.feature-card h3 {
    color: #1e293b;
    font-size: 1.3rem;
    margin-bottom: 15px;
}

.feature-card p {
    color: #64748b;
    font-size: 1rem;
    line-height: 1.6;
}

.enrollment-cta {
    text-align: center;
    background: linear-gradient(135deg, #f5f7fa, #e4e8f0);
    padding: 50px;
    border-radius: 16px;
    margin-top: 40px;
}

.enrollment-cta h3 {
    font-size: 1.8rem;
    color: #1e293b;
    margin-bottom: 30px;
}

.enroll-button-secondary {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: white;
    border: none;
    padding: 15px 30px;
    font-size: 1.2rem;
    font-weight: 600;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
}

.enroll-button-secondary:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2.2rem;
    }

    .hero-subtitle {
        font-size: 1.2rem;
    }

    .hero-text-container {
        padding: 30px;
    }

    .section-title {
        font-size: 2rem;
    }

    .enrollment-cta h3 {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 1.8rem;
    }

    .hero-subtitle {
        font-size: 1rem;
    }

    .cta-container {
        flex-direction: column;
    }

    .school-logo img {
        height: 60px;
    }
}
</style>
