<template>
    <div class="layout-wrapper">
        <div class="content-wrapper">
            <router-view />
        </div>
    </div>
</template>

<script setup></script>

<style scoped>
.layout-wrapper {
    width: 100%;
    height: 100vh;
    background-color: #000000;
    position: relative;
    overflow: hidden;
}

.content-wrapper {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>
