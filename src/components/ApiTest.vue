<template>
    <div class="card">
        <h5>API Test</h5>
        <Button label="Test API Connection" @click="testApi" />
        <div v-if="response" class="mt-3">
            <h6>API Response:</h6>
            <pre>{{ JSON.stringify(response, null, 2) }}</pre>
        </div>
        <div v-if="error" class="mt-3 p-error">
            {{ error }}
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const response = ref(null);
const error = ref(null);

const testApi = async () => {
    try {
        error.value = null;
        const res = await fetch('/api/test');
        if (!res.ok) {
            throw new Error(`API returned status ${res.status}`);
        }
        response.value = await res.json();
    } catch (err) {
        error.value = `Error connecting to API: ${err.message}`;
        console.error(err);
    }
};
</script>
