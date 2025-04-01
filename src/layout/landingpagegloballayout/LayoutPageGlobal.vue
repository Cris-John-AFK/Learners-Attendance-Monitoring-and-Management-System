<template>
  <div class="layout">
    <TopbarPageGlobal />
    <main class="main-content">
      <router-view></router-view>
    </main>
  </div>
</template>

<script setup>
import TopBarPageGlobal from './TopBarPageGlobal.vue'
</script>

<style scoped>
.layout {
  min-height: 100vh;
  background-color: #81BFDA;
  display: flex;
  flex-direction: column;
}

.main-content {
  padding-top: 80px; /* Height of topbar + some spacing */
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
}
</style>