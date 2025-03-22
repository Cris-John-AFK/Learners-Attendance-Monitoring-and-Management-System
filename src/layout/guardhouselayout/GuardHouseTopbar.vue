<script setup>
import Button from 'primevue/button';
</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <router-link to="/guest" class="layout-topbar-logo">
                <img src="/demo/images/logo.svg" alt="Logo" />

                <span>NCS- for Guard House</span>
            </router-link>
            <Button class="back-button" @click="$router.push('/')">
                    <i class="pi pi-arrow-left"></i> Back to Login
            </Button>
        </div>
    </div>
</template>

<style scoped>
img {
    width: 50px;
    height: 50px;
}
.back-button {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  top: 10px; /* Adjust to align properly within the top bar */
  background-color: #1976D2;
  color: white;
  font-weight: bold;
  padding: 8px 16px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: 0.3s ease-in-out;
  box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
}

.back-button:hover {
  background-color: #1565C0;
  transform: translateX(-50%) scale(1.05);
}

.back-button:active {
  transform: translateX(-50%) scale(0.95);
}
</style>
