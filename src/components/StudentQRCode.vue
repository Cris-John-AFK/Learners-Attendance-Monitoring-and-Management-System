<template>
    <div class="student-qr-code">
        <div class="qr-image" v-if="qrPath">
            <img :src="qrPath" alt="Student QR Code" />
        </div>
        <div v-else-if="qrValue" class="qr-generated">
            <qrcode-vue :value="qrValue" :size="size" level="H" />
        </div>
        <div v-else class="qr-error">
            <p>No QR code available for this student</p>
        </div>
        <div class="qr-info">
            <p class="student-id">ID: {{ studentId }}</p>
            <p class="student-name">{{ studentName }}</p>
        </div>
    </div>
</template>

<script setup>
import { QRCodeService } from '@/router/service/QRCodeService';
import QrcodeVue from 'qrcode.vue';
import { ref } from 'vue';

const props = defineProps({
    studentId: {
        type: String,
        required: true
    },
    studentName: {
        type: String,
        default: 'Student'
    },
    size: {
        type: Number,
        default: 200
    }
});

// Get the QR code path or generate QR code value
const qrPath = ref(null);
const qrValue = ref(null);

// Find the QR code for this student
qrPath.value = QRCodeService.getQRPathForStudent(props.studentId);

// If no existing QR code image, generate one on the fly
if (!qrPath.value) {
    // Find what QR content maps to this student
    const qrMapping = QRCodeService.getAllQRCodes().find((mapping) => mapping.studentId === props.studentId);

    if (qrMapping) {
        qrValue.value = qrMapping.qrCode;
    } else {
        // If no mapping exists, use the student ID directly
        qrValue.value = props.studentId;

        // Register this mapping for future use
        QRCodeService.registerQRCodeMapping(props.studentId, props.studentId);
    }
}
</script>

<style scoped>
.student-qr-code {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    max-width: 250px;
    margin: 0 auto;
    background-color: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.qr-image img {
    width: 200px;
    height: 200px;
    object-fit: contain;
}

.qr-error {
    width: 200px;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f5f5f5;
    border-radius: 4px;
    color: #666;
    text-align: center;
    padding: 1rem;
}

.qr-info {
    margin-top: 1rem;
    text-align: center;
    width: 100%;
}

.student-id {
    color: #666;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.student-name {
    font-weight: 600;
    font-size: 1.1rem;
    margin: 0;
}
</style>
