<template>
    <div class="card">
        <h5>API Test</h5>
        <Button label="Test API Connection" @click="testApi" />
        <div v-if="result" class="mt-3">
            <h6>API Response:</h6>
            <pre>{{ JSON.stringify(result, null, 2) }}</pre>
        </div>
        <div v-if="error" class="mt-3 p-error">
            {{ error }}
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const result = ref(null);
const error = ref(null);

const testApi = async () => {
    try {
        result.value = null;
        error.value = null;

        const response = await axios.get('http://localhost:8000/api/test');
        result.value = response.data;
    } catch (err) {
        error.value = {
            message: err.message,
            response: err.response
                ? {
                      status: err.response.status,
                      data: err.response.data
                  }
                : null
        };
    }
};
</script>
