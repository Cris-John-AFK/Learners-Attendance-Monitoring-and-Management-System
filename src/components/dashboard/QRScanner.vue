<script setup>
import { ref, defineEmits } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';
import { QRCodeAPIService } from '@/router/service/QRCodeAPIService';

const emit = defineEmits(['student-scanned', 'scan-error']);

const result = ref('');
const scanning = ref(true);
const studentInfo = ref(null);

const onDecode = async (decodedText) => {
    try {
        result.value = decodedText;
        scanning.value = false;
        
        // Validate QR code with backend
        const response = await QRCodeAPIService.validateQRCode(decodedText);
        
        if (response.valid) {
            studentInfo.value = response.student;
            emit('student-scanned', response.student);
        } else {
            emit('scan-error', 'Invalid or expired QR code');
        }
    } catch (error) {
        console.error('Error validating QR code:', error);
        emit('scan-error', 'Failed to validate QR code');
    }
};

const resetScanner = () => {
    result.value = '';
    scanning.value = true;
    studentInfo.value = null;
};
</script>

<template>
    <div class="scanner-container">
        <div v-if="scanning" class="scanner-active">
            <qrcode-stream @decode="onDecode" />
            <p class="scanner-instruction">Point camera at QR code to scan</p>
        </div>
        
        <div v-else class="scan-result">
            <div v-if="studentInfo" class="student-found">
                <div class="success-icon">
                    <i class="pi pi-check-circle text-green-500 text-4xl"></i>
                </div>
                <h3>Student Found!</h3>
                <div class="student-details">
                    <p><strong>Name:</strong> {{ studentInfo.name }}</p>
                    <p><strong>ID:</strong> {{ studentInfo.id }}</p>
                    <p><strong>Grade:</strong> {{ studentInfo.gradeLevel }}</p>
                </div>
                <Button 
                    label="Scan Another" 
                    icon="pi pi-refresh" 
                    @click="resetScanner"
                    class="p-button-outlined mt-3"
                />
            </div>
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
    margin: auto;
    padding: 1rem;
}

.scanner-active {
    width: 100%;
    text-align: center;
}

.scanner-instruction {
    margin-top: 1rem;
    color: #666;
    font-size: 0.9rem;
}

.scan-result {
    width: 100%;
    text-align: center;
}

.student-found {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 2px solid #22c55e;
}

.success-icon {
    margin-bottom: 1rem;
}

.student-found h3 {
    color: #22c55e;
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.student-details {
    margin: 1rem 0;
    text-align: left;
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
}

.student-details p {
    margin: 0.5rem 0;
    color: #333;
}
</style>
