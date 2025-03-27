<script setup>
import { useLayout } from '@/layout/composables/layout';
import GuestTopbar from '@/layout/guestlayout/GuestTopbar.vue';
import { ref, watch } from 'vue';
import AppFooter from './AppFooter.vue';
const { layoutState, isSidebarActive } = useLayout();

const outsideClickListener = ref(null);

watch(isSidebarActive, (newVal) => {
    if (newVal) {
        bindOutsideClickListener();
    } else {
        unbindOutsideClickListener();
    }
});

function bindOutsideClickListener() {
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
}

function unbindOutsideClickListener() {
    if (outsideClickListener.value) {
        document.removeEventListener('click', outsideClickListener);
        outsideClickListener.value = null;
    }
}

function isOutsideClicked(event) {
    const topbarEl = document.querySelector('.layout-menu-button');

    return !(topbarEl.isSameNode(event.target) || topbarEl.contains(event.target));
}
</script>

<template>
    <div class="layout-wrapper">
        <guest-topbar></guest-topbar>
        <div class="layout-main-container">
            <div class="layout-content">
                <div class="layout-main">
                    <router-view></router-view>
                </div>
            </div>
        </div>
        <app-footer></app-footer>
        <div class="layout-mask animate-fadein"></div>
    </div>
    <Toast />
</template>

<style lang="scss" scoped>
.layout-wrapper {
    min-height: 100vh;
    background-color: var(--surface-ground);
    display: flex;
    flex-direction: column;
    position: relative;
}

.layout-main-container {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: calc(100vh - 8rem); /* Subtract topbar and footer heights */
    margin-top: 4rem; /* Account for fixed topbar */
    margin-bottom: 4rem; /* Space for footer */
}

.layout-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

.layout-main {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2rem;
}

@media (max-width: 991px) {
    .layout-content {
        padding: 1rem;
    }
}
</style>
