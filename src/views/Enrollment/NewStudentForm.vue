<template>
    <div>
        <p>New</p>
        <button
            @click="goBack"
            class="fixed bottom-6 left-6 px-6 py-4 bg-blue-600 text-white rounded-full shadow-xl text-lg font-semibold flex items-center gap-2 transition-transform hover:scale-110 hover:bg-blue-700 active:scale-95">
            <i class="pi pi-arrow-left text-2xl"></i>
            <span>Go Back</span>
        </button>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();
const goBack = () => {
  router.back();
};
</script>

<style lang="scss" scoped>

</style>