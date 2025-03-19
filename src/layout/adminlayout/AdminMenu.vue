<script setup>
import { ref } from 'vue';

import AdminMenuItem from './AdminMenuItem.vue';
const model = ref([
    {
        label: 'Home',
        items: [
            { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/admin' },
        ]
    },
    {
        label: 'Manage',
        items: [
            { label: 'Analytical Graph', icon: 'pi pi-fw pi-chart-bar', to: '/admin-graph' },
            { label: 'Teacher', icon: 'pi pi-fw pi-user-edit', to: '/admin-teacher' },
            { label: 'Student', icon: 'pi pi-fw pi-users', to: '/admin-student' },
            { label: 'Section', icon: 'pi pi-fw pi-home', to: '/admin-section' },
        ]
    },
    {
        items: [
            { label: 'Settings', icon: 'pi pi-fw pi-cog', to: '/admin-settings' },
        ]
    },
    {
        items: [
            { label: 'Archive', icon: 'pi pi-fw pi-briefcase', to: '/archive' },
        ]
    }
]);
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="i">
            <admin-menu-item v-if="!item.separator" :item="item" :index="i"></admin-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
