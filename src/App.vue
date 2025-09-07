<script>
import GlobalLoader from '@/components/GlobalLoader.vue'

export default {
    name: 'App',
    components: {
        GlobalLoader
    }
};
</script>

<template>
    <div class="app-container">
        <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
                <component :is="Component" />
            </transition>
        </router-view>
        <GlobalLoader />
    </div>
</template>

<style lang="scss">
@import '@/assets/styles.scss';

/* Global styles for PrimeVue components */
.p-dialog {
    display: flex !important;
    flex-direction: column;
    position: fixed;
    max-height: 90%;
    transform: translate(-50%, -50%);
    top: 50%;
    left: 50%;
    z-index: 9999 !important;
    margin: 0;
    padding: 0;
    border-radius: 6px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    pointer-events: auto;
}

.p-dialog-mask {
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.4);
    z-index: 9000 !important;
    pointer-events: auto;
}

.p-dialog-content {
    overflow: auto;
    background-color: white;
    color: #343a40;
    padding: 1.5rem;
    flex: 1 1 auto;
}
</style>
