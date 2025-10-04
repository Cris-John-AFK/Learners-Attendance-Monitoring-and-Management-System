<script setup>
import { ref } from 'vue';

import AdminMenuItem from './AdminMenuItem.vue';
const model = ref([
    {
        label: 'Home',
        items: [{ label: 'Dashboard', icon: 'pi pi-fw pi-chart-line', to: '/admin' }]
    },
    {
        label: 'Manage',
        items: [
            { label: 'Collected Reports', icon: 'pi pi-fw pi-clipboard', to: '/admin-collected-reports' },
            { label: 'GuardHouse Reports', icon: 'pi pi-fw pi-shield', to: '/admin-guardhouse-reports' },
            { label: 'Subject', icon: 'pi pi-fw pi-book', to: '/admin-subject' },
            { label: 'Curricula', icon: 'pi pi-fw pi-briefcase', to: '/curriculum' },
            { label: 'Teacher', icon: 'pi pi-fw pi-user-edit', to: '/admin-teacher' },
            { label: 'Student', icon: 'pi pi-fw pi-users', to: '/admin-student' },
            { label: 'Enrollment', icon: 'pi pi-fw pi-user-edit', to: '/admin-enrollment' }
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
