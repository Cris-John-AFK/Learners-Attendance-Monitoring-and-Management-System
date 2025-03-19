<script setup>
import { ref } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';

const result = ref('');
const onDecode = (decodedText) => {
    result.value = decodedText;
    alert(`QR Code Scanned: ${decodedText}`);
};
</script>

<template>
    <div class="scanner-container">
        <qrcode-stream @decode="onDecode"></qrcode-stream>
        <p v-if="result">Scanned Result: {{ result }}</p>
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
    margin: auto;
}
</style>
