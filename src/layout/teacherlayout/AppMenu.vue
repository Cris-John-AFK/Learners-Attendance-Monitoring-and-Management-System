<script setup>
import { ref } from 'vue';

import AppMenuItem from './AppMenuItem.vue';

const model = ref([
    {
        label: 'Home',
        items: [{ label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/teacher' }]
    },
    {
        label: 'Attendance',
        icon: 'pi pi-fw pi-briefcase',
        to: '/pages',
        items:[
            {
                label: 'Attendance Record',
                icon: 'pi pi-fw pi-book',
                to: '/pages/attendance'
            },
            {
                label: 'Submit Report',
                icon: 'pi pi-fw pi-file-export',
                to: '/pages/report'
            }
        ]
    },
    {
        separator: true
    },
    {
        label: 'Manage',
        icon: 'pi pi-fw pi-briefcase',
        to: '/pages',
        items:[
            {
                label: 'Section',
                icon: 'pi pi-fw pi-users',
                to: '/pages/section'
            },
            {
                label: 'Settings',
                icon: 'pi pi-fw pi-cog',
                to: '/pages/settings'
            }
        ]
    },
]);
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
