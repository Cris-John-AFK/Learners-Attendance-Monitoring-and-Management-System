<script setup>
import { ref } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';

const scannedResult = ref(null);
const onDecode = (result) => {
    scannedResult.value = result;
};
</script>

<template>
    <div class="scanner-container">
        <qrcode-stream @decode="onDecode"></qrcode-stream>
        <div v-if="scannedResult" class="result">
            <p>Scanned QR Code:</p>
            <strong>{{ scannedResult }}</strong>
        </div>
    </div>
</template>

<style scoped>
.scanner-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
}

.result {
    margin-top: 20px;
    text-align: center;
    font-size: 1.2rem;
    background: #e0f7fa;
    padding: 10px;
    border-radius: 5px;
}
</style>
