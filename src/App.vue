<script>
export default {
    name: 'App'
};
</script>

<template>
    <div class="app-container">
        <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
                <component :is="Component" />
            </transition>
        </router-view>
    </div>
</template>

<style lang="scss">
@import '@/assets/styles.scss';
</style>
