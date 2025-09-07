<template>
    <Teleport to="body">
        <Transition name="loader-fade">
            <div v-if="isGlobalLoading" class="global-loader-overlay">
                <div class="global-loader-container">
                    <BookFlipLoader 
                        :size="globalLoadingSize" 
                        :text="globalLoadingText"
                        :show-text="true"
                    />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { useGlobalLoader } from '@/composables/useGlobalLoader'
import BookFlipLoader from './BookFlipLoader.vue'

const { isGlobalLoading, globalLoadingText, globalLoadingSize } = useGlobalLoader()
</script>

<style scoped>
.global-loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.global-loader-container {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.05);
    min-width: 200px;
    text-align: center;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .global-loader-overlay {
        background: rgba(15, 23, 42, 0.9);
    }
    
    .global-loader-container {
        background: #1e293b;
        border-color: #334155;
    }
}

/* Transition animations */
.loader-fade-enter-active,
.loader-fade-leave-active {
    transition: all 0.3s ease;
}

.loader-fade-enter-from,
.loader-fade-leave-to {
    opacity: 0;
    transform: scale(0.9);
}

.loader-fade-enter-active .global-loader-container {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.loader-fade-enter-from .global-loader-container {
    transform: scale(0.8) translateY(20px);
}
</style>
