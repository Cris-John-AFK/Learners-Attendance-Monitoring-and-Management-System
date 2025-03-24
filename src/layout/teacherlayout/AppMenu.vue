<script setup>
import { ref } from 'vue';

import AppMenuItem from './AppMenuItem.vue';

const model = ref([{
        label: 'Home',
        items: [{ label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/teacher' }]
    },
    {
        label: 'Subjects',
        icon: 'pi pi-fw pi-briefcase',
        to: '/pages',
        items: [{
                label: 'Homeroom',
                icon: 'pi pi-fw pi-book',
                to: '/subject/homeroom'
            },
            {
                label: 'Mother Tounge',
                icon: 'pi pi-fw pi-book',
                to: '/subject/mother-tongue'
            },
            {
                label: 'English',
                icon: 'pi pi-fw pi-book',
                to: '/subject/english'
            },
            {
                label: 'Filipino',
                icon: 'pi pi-fw pi-book',
                to: '/subject/filipino'
            },
            {
                label: 'Mathematics',
                icon: 'pi pi-fw pi-book',
                to: '/subject/mathematics'
            }
        ]
    },
    {
        separator: true
    },
    {
        label: 'Other Subjects',
        items: [{
            label: 'MAPEH',
            icon: 'pi pi-fw pi-book',
            to: '/subject/mapeh'
        }]
    },
    {
        separator: true
    },
    {
        label: 'Manage',
        icon: 'pi pi-fw pi-briefcase',
        to: '/pages',
        items: [{
            label: 'Section',
            icon: 'pi pi-fw pi-users',
            to: '/pages/section'
        }]
    }
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

<style lang="scss" scoped>

</style>
