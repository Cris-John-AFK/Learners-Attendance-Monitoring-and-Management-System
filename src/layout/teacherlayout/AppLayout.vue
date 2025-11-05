<script setup>
import { useLayout } from '@/layout/composables/layout';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppFooter from './AppFooter.vue';
import AppSidebar from './AppSidebar.vue';
import AppTopbar from './AppTopbar.vue';

const { layoutConfig, layoutState, isSidebarActive } = useLayout();
const route = useRoute();

const outsideClickListener = ref(null);

// Scroll progress indicator
const scrollProgress = ref(0);
const showScrollIndicator = ref(true);

function updateScrollProgress() {
    const windowHeight = window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const scrollPercent = (scrollTop / (documentHeight - windowHeight)) * 100;
    scrollProgress.value = Math.min(100, Math.max(0, scrollPercent));

    // Hide scroll indicator only when scrolled past 50% of the page
    showScrollIndicator.value = scrollPercent < 80;
}

// Scroll down one screen when arrow is clicked
function scrollDown() {
    window.scrollTo({
        top: window.scrollY + window.innerHeight,
        behavior: 'smooth'
    });
}

// Reset scroll progress when route changes
watch(() => route.path, () => {
    // Scroll to top on route change
    window.scrollTo({ top: 0, behavior: 'instant' });
    // Reset progress immediately
    scrollProgress.value = 0;
    showScrollIndicator.value = true;
    // Update after a brief delay to ensure page is rendered
    setTimeout(updateScrollProgress, 100);
});

watch(isSidebarActive, (newVal) => {
    if (newVal) {
        bindOutsideClickListener();
    } else {
        unbindOutsideClickListener();
    }
});

const containerClass = computed(() => {
    return {
        'layout-theme-light': true,
        'layout-overlay': layoutConfig.menuMode === 'overlay',
        'layout-static': layoutConfig.menuMode === 'static',
        'layout-static-inactive': layoutState.staticMenuDesktopInactive && layoutConfig.menuMode === 'static',
        'layout-overlay-active': layoutState.overlayMenuActive,
        'layout-mobile-active': layoutState.staticMenuMobileActive,
        'p-input-filled': layoutConfig.inputStyle === 'filled',
        'p-ripple-disabled': !layoutConfig.ripple
    };
});

const bindOutsideClickListener = () => {
    if (!outsideClickListener.value) {
        outsideClickListener.value = (event) => {
            if (isOutsideClicked(event)) {
                layoutState.overlayMenuActive = false;
                layoutState.staticMenuMobileActive = false;
                layoutState.menuHoverActive = false;
            }
        };
        document.addEventListener('click', outsideClickListener.value);
    }
};

const unbindOutsideClickListener = () => {
    if (outsideClickListener.value) {
        document.removeEventListener('click', outsideClickListener.value);
        outsideClickListener.value = null;
    }
};

const isOutsideClicked = (event) => {
    const sidebarEl = document.querySelector('.layout-sidebar');
    const topbarEl = document.querySelector('.layout-topbar');
    return !(sidebarEl.isSameNode(event.target) || sidebarEl.contains(event.target) || topbarEl.isSameNode(event.target) || topbarEl.contains(event.target));
};

// Lifecycle hooks for scroll progress
onMounted(() => {
    window.addEventListener('scroll', updateScrollProgress);
    updateScrollProgress(); // Initial calculation
});

onUnmounted(() => {
    window.removeEventListener('scroll', updateScrollProgress);
});
</script>

<template>
    <div class="app-layout-root">
        <!-- Scroll Progress Bar - At the VERY top -->
        <div class="fixed top-0 left-0 right-0 h-1 bg-gray-200" style="z-index: 9999">
            <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 transition-all duration-300 ease-out shadow-lg" :style="{ width: scrollProgress + '%' }"></div>
        </div>

        <!-- Scroll Down Indicator (hidden when dialogs are open) -->
        <Transition name="fade">
            <div
                v-if="showScrollIndicator"
                class="fixed bottom-8 left-1/2 transform -translate-x-1/2 z-50 flex flex-col items-center animate-bounce cursor-pointer scroll-indicator"
                style="z-index: 999"
                @click="scrollDown"
            >
                <div class="bg-blue-600 text-white rounded-full p-3 shadow-lg hover:bg-blue-700 transition-colors">
                    <i class="pi pi-angle-down text-xl"></i>
                </div>
                <span class="text-xs text-gray-600 mt-2 font-medium">Scroll Down</span>
            </div>
        </Transition>

        <div class="app-layout-container">
            <div class="layout-wrapper teacher-route" :class="containerClass">
                <AppTopbar />
                <AppSidebar />
                <div class="layout-main-container">
                    <div class="layout-main">
                        <router-view></router-view>
                    </div>
                    <AppFooter />
                </div>
                <div class="layout-mask"></div>
            </div>
        </div>
    </div>
</template>

<style lang="scss">
@import '@/assets/layout/layout.scss';

/* Fade transition for scroll indicator */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Hide scroll indicator when any dialog is open */
body:has(.p-dialog-mask) .scroll-indicator {
    display: none !important;
}

/* Print styles - hide navigation for QR code printing */
@media print {
    .layout-topbar,
    .layout-sidebar,
    .layout-mask,
    .app-footer {
        display: none !important;
        visibility: hidden !important;
    }

    .layout-main-container {
        margin: 0 !important;
        padding: 0 !important;
    }

    .layout-main {
        margin: 0 !important;
        padding: 0 !important;
    }
}
</style>
