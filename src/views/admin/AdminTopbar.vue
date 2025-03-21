<script setup>
import { useLayout } from '@/layout/composables/layout';

const { toggleMenu } = useLayout();
const isCalendarOpen = ref(false);
</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                <i class="pi pi-bars"></i>
            </button>
            <router-link to="/admin" class="layout-topbar-logo">
                <img src="/demo/images/logo.svg" alt="Logo" />
                <span>NCS - for Admin</span>
            </router-link>
        </div>

        <div class="layout-topbar-actions">
            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
                    <button type="button" class="layout-topbar-action" @click="isCalendarOpen = true">
                        <i class="pi pi-calendar"></i>
                        <span>Calendar</span>
                    </button>
                    <button type="button" class="layout-topbar-action">
                        <i class="pi pi-user"></i>
                        <span>Profile</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Modal -->
    <Dialog v-model:visible="isCalendarOpen" header="School Activities Calendar" :modal="true" :style="{ width: '600px' }">
        <VCalendar :attributes="attributes" />
    </Dialog>
</template>

<style scoped>
img {
    width: 50px;
    height: 50px;
}
</style>
