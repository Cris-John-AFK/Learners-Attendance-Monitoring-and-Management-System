<template>
    <div class="book-flip-loader" :class="{ 'small': size === 'small', 'large': size === 'large' }">
        <div class="book">
            <div class="book-spine"></div>
            <div class="book-cover book-cover-front">
                <div class="book-pages">
                    <div class="page page-1"></div>
                    <div class="page page-2"></div>
                    <div class="page page-3"></div>
                </div>
            </div>
            <div class="book-cover book-cover-back"></div>
        </div>
        <div class="loading-text" v-if="showText">
            {{ text || 'Loading...' }}
        </div>
    </div>
</template>

<script setup>
defineProps({
    size: {
        type: String,
        default: 'medium', // small, medium, large
        validator: (value) => ['small', 'medium', 'large'].includes(value)
    },
    text: {
        type: String,
        default: 'Loading...'
    },
    showText: {
        type: Boolean,
        default: true
    }
});
</script>

<style scoped>
.book-flip-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 2rem;
}

.book {
    position: relative;
    width: 60px;
    height: 80px;
    perspective: 1000px;
    animation: bookFloat 3s ease-in-out infinite;
}

.book-spine {
    position: absolute;
    left: -3px;
    top: 0;
    width: 6px;
    height: 100%;
    background: linear-gradient(to bottom, #8B4513, #654321);
    border-radius: 2px 0 0 2px;
    z-index: 1;
}

.book-cover {
    position: absolute;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #4A90E2, #357ABD);
    border-radius: 0 4px 4px 0;
    border: 2px solid #2C5282;
    transform-origin: left center;
    transition: transform 0.6s ease;
}

.book-cover-front {
    z-index: 2;
    animation: flipPages 2s ease-in-out infinite;
    box-shadow: 
        2px 2px 8px rgba(0,0,0,0.3),
        inset 1px 1px 2px rgba(255,255,255,0.2);
}

.book-cover-front::before {
    content: '';
    position: absolute;
    top: 8px;
    left: 8px;
    right: 8px;
    bottom: 20px;
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
    border: 1px solid rgba(255,255,255,0.2);
}

.book-cover-front::after {
    content: '📚';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 20px;
    opacity: 0.8;
}

.book-cover-back {
    background: linear-gradient(135deg, #2C5282, #1A365D);
    z-index: 0;
}

.book-pages {
    position: absolute;
    top: 4px;
    left: 4px;
    right: 4px;
    bottom: 4px;
    overflow: hidden;
}

.page {
    position: absolute;
    width: 100%;
    height: 100%;
    background: #f8f9fa;
    border-radius: 1px;
    opacity: 0;
    animation: pageFlip 2s ease-in-out infinite;
}

.page::before {
    content: '';
    position: absolute;
    top: 8px;
    left: 6px;
    right: 6px;
    height: 2px;
    background: linear-gradient(90deg, #e2e8f0 0%, transparent 100%);
    border-radius: 1px;
}

.page::after {
    content: '';
    position: absolute;
    top: 14px;
    left: 6px;
    right: 12px;
    height: 1px;
    background: linear-gradient(90deg, #cbd5e0 0%, transparent 100%);
    border-radius: 1px;
}

.page-1 {
    animation-delay: 0s;
}

.page-2 {
    animation-delay: 0.3s;
}

.page-3 {
    animation-delay: 0.6s;
}

.loading-text {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    text-align: center;
    animation: textPulse 2s ease-in-out infinite;
}

/* Size variants */
.book-flip-loader.small .book {
    width: 40px;
    height: 55px;
}

.book-flip-loader.small .book-cover-front::after {
    font-size: 14px;
}

.book-flip-loader.small .loading-text {
    font-size: 12px;
}

.book-flip-loader.large .book {
    width: 80px;
    height: 105px;
}

.book-flip-loader.large .book-cover-front::after {
    font-size: 28px;
}

.book-flip-loader.large .loading-text {
    font-size: 16px;
}

/* Animations */
@keyframes bookFloat {
    0%, 100% {
        transform: translateY(0px) rotateY(-5deg);
    }
    50% {
        transform: translateY(-8px) rotateY(5deg);
    }
}

@keyframes flipPages {
    0%, 20% {
        transform: rotateY(0deg);
    }
    50% {
        transform: rotateY(-25deg);
    }
    80%, 100% {
        transform: rotateY(0deg);
    }
}

@keyframes pageFlip {
    0%, 30% {
        opacity: 0;
        transform: translateX(0px);
    }
    50% {
        opacity: 1;
        transform: translateX(2px);
    }
    70%, 100% {
        opacity: 0;
        transform: translateX(4px);
    }
}

@keyframes textPulse {
    0%, 100% {
        opacity: 0.7;
    }
    50% {
        opacity: 1;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .book-cover-front {
        background: linear-gradient(135deg, #3B82F6, #1D4ED8);
        border-color: #1E40AF;
    }
    
    .book-cover-back {
        background: linear-gradient(135deg, #1E40AF, #1E3A8A);
    }
    
    .loading-text {
        color: #94a3b8;
    }
    
    .page {
        background: #f1f5f9;
    }
}
</style>
