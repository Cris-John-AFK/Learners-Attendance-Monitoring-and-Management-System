<template>
    <div class="landing-page fixed right-0 top-0 w-[50%] h-full bg-black">
        <TopBarPageGlobal />

        <!-- Main Content -->
        <main class="flex-grow flex items-center relative overflow-hidden">
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-20 left-10 w-20 h-20 rounded-full bg-indigo-500 opacity-20 floating"></div>
                <div class="absolute top-40 right-20 w-32 h-32 rounded-full bg-purple-500 opacity-20 floating-slow"></div>
                <div class="absolute bottom-20 left-1/4 w-24 h-24 rounded-full bg-pink-500 opacity-20 floating-slower"></div>
                <div class="absolute top-1/3 right-1/3 w-16 h-16 rounded-full bg-indigo-300 opacity-20 floating"></div>
            </div>

            <div class="container mx-auto px-4 py-12 relative z-10">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div class="text-center md:text-left">
                        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 leading-tight">Something <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">LAMMS</span> is Coming Soon</h1>
                        <p class="text-lg md:text-xl text-indigo-200 mb-8 max-w-lg mx-auto md:mx-0">We're working hard to bring you our new platform. Sign up to be the first to know when we launch and get exclusive early access.</p>

                        <!-- Countdown Timer -->
                        <div class="grid grid-cols-4 gap-4 max-w-md mx-auto md:mx-0 mb-10">
                            <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                                <div class="text-3xl md:text-4xl font-bold">{{ days }}</div>
                                <div class="text-indigo-300 text-sm">Days</div>
                            </div>
                            <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                                <div class="text-3xl md:text-4xl font-bold">{{ hours }}</div>
                                <div class="text-indigo-300 text-sm">Hours</div>
                            </div>
                            <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                                <div class="text-3xl md:text-4xl font-bold">{{ minutes }}</div>
                                <div class="text-indigo-300 text-sm">Minutes</div>
                            </div>
                            <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                                <div class="text-3xl md:text-4xl font-bold">{{ seconds }}</div>
                                <div class="text-indigo-300 text-sm">Seconds</div>
                            </div>
                        </div>

                        <!-- Newsletter Signup -->
                        <div class="max-w-md mx-auto md:mx-0">
                            <form class="flex flex-col sm:flex-row gap-3">
                                <input type="email" placeholder="Enter your email" class="flex-grow px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white/10 backdrop-blur-sm border border-white/20" />
                                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 rounded-lg font-medium transition-all duration-200 whitespace-nowrap">Notify Me</button>
                            </form>
                            <p class="text-xs text-indigo-300 mt-3">We respect your privacy. No spam, ever. Unsubscribe anytime.</p>
                        </div>

                        <!-- Social Media -->
                        <div class="mt-10 flex justify-center md:justify-start space-x-6">
                            <a href="#" class="text-indigo-300 hover:text-white transition-colors duration-200">
                                <i class="fab fa-twitter text-xl"></i>
                            </a>
                            <a href="#" class="text-indigo-300 hover:text-white transition-colors duration-200">
                                <i class="fab fa-instagram text-xl"></i>
                            </a>
                            <a href="#" class="text-indigo-300 hover:text-white transition-colors duration-200">
                                <i class="fab fa-facebook text-xl"></i>
                            </a>
                            <a href="#" class="text-indigo-300 hover:text-white transition-colors duration-200">
                                <i class="fab fa-linkedin-in text-xl"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Illustration -->
                    <div class="hidden md:block">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full opacity-20 blur-3xl"></div>
                            <img src="https://cdn.pixabay.com/photo/2018/11/29/21/51/social-media-3846597_1280.png" alt="Illustration" class="relative z-10 mx-auto floating-slow" />
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="container mx-auto px-4 py-6 border-t border-white/10">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-sm text-indigo-300">&copy; 2025 Nova. All rights reserved.</div>
                <div class="mt-4 md:mt-0">
                    <nav class="flex space-x-6 text-sm text-indigo-300">
                        <a href="#" class="hover:text-white transition-colors duration-200">Privacy Policy</a>
                        <a href="#" class="hover:text-white transition-colors duration-200">Terms of Service</a>
                        <a href="#" class="hover:text-white transition-colors duration-200">Contact Us</a>
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import TopBarPageGlobal from '../../layout/landingpagegloballayout/TopBarPageGlobal.vue';

// Countdown timer logic
const targetDate = new Date('2025-04-30T00:00:00').getTime(); // Set your target date here
const days = ref('15');
const hours = ref('08');
const minutes = ref('23');
const seconds = ref('42');

const updateCountdown = () => {
    const now = new Date().getTime();
    const distance = targetDate - now;

    if (distance > 0) {
        const d = Math.floor(distance / (1000 * 60 * 60 * 24));
        const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((distance % (1000 * 60)) / 1000);

        days.value = d.toString().padStart(2, '0');
        hours.value = h.toString().padStart(2, '0');
        minutes.value = m.toString().padStart(2, '0');
        seconds.value = s.toString().padStart(2, '0');
    } else {
        days.value = '00';
        hours.value = '00';
        minutes.value = '00';
        seconds.value = '00';
    }
};

// Update the countdown every second
onMounted(() => {
    setInterval(updateCountdown, 1000);
});
</script>

<style scoped>
.landing-page {
    width: 100%;
    min-height: 100vh;
    background-color: #000;
    color: white;
    padding: 2rem;
    display: flex;
    flex-direction: column;
}

@keyframes float {
    0% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-20px);
    }
    100% {
        transform: translateY(0px);
    }
}

.floating {
    animation: float 6s ease-in-out infinite;
}

.floating-slow {
    animation: float 8s ease-in-out infinite;
}

.floating-slower {
    animation: float 10s ease-in-out infinite;
}
</style>
