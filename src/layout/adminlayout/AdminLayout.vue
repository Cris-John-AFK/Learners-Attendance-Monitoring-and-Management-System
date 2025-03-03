<script setup>
import AdminTopbar from '@/views/admin/AdminTopbar.vue';
import AdminSidebar from '@/views/admin/AdminSidebar.vue';
import { ref } from 'vue';

const isSidebarActive = ref(true);
</script>

<template>
    <div class="admin-layout">
        <AdminTopbar />
        <div class="layout-content">
            <AdminSidebar v-if="isSidebarActive" />
            <main class="layout-main">
                <router-view></router-view>
            </main>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.admin-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.layout-content {
    display: flex;
    flex: 1;
}

.layout-main {
    flex: 1;
    padding: 2rem;
    background-color: #f8f9fa; /* Light background for admin */
}
</style>
