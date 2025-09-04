<template>
    <div class="student-qr-code">
        <div class="qr-header">
            <h3 class="student-name">{{ studentName }}</h3>
            <p class="student-id">ID: {{ studentId }}</p>
        </div>
        
        <div class="qr-container">
            <div v-if="loading" class="qr-loading">
                <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
                <p>Loading QR Code...</p>
            </div>
            <div v-else-if="qrImageUrl" class="qr-image">
                <img :src="qrImageUrl" alt="Student QR Code" />
            </div>
            <div v-else-if="qrValue" class="qr-generated">
                <qrcode-vue :value="qrValue" :size="size" level="H" />
            </div>
            <div v-else class="qr-error">
                <p>No QR code available</p>
                <Button 
                    label="Generate QR Code" 
                    icon="pi pi-qrcode" 
                    @click="generateQRCode"
                    class="p-button-sm p-button-outlined"
                />
            </div>
        </div>

        <div class="qr-actions">
            <div class="download-buttons">
                <Button 
                    label="PNG" 
                    icon="pi pi-download" 
                    @click="downloadAsPNG"
                    class="p-button-sm"
                    :disabled="!hasQRCode"
                />
                <Button 
                    label="SVG" 
                    icon="pi pi-download" 
                    @click="downloadAsSVG"
                    class="p-button-sm p-button-outlined"
                    :disabled="!hasQRCode"
                />
            </div>
            <Button 
                label="Regenerate" 
                icon="pi pi-refresh" 
                @click="generateQRCode"
                class="p-button-sm p-button-secondary"
            />
        </div>
    </div>
</template>

<script setup>
import { QRCodeAPIService } from '@/router/service/QRCodeAPIService';
import QrcodeVue from 'qrcode.vue';
import { ref, onMounted, computed } from 'vue';

const props = defineProps({
    studentId: {
        type: [String, Number],
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

// State variables
const loading = ref(false);
const qrImageUrl = ref(null);
const qrValue = ref(null);
const qrData = ref(null);

// Computed property to check if QR code exists
const hasQRCode = computed(() => {
    return !!(qrImageUrl.value || qrValue.value || qrData.value);
});

// Load QR code on component mount
onMounted(async () => {
    await loadQRCode();
});

// Load existing QR code for student
const loadQRCode = async () => {
    try {
        loading.value = true;
        const response = await QRCodeAPIService.getStudentQRCode(props.studentId);
        
        if (response.has_qr_code) {
            qrData.value = response.qr_code_data;
            qrImageUrl.value = QRCodeAPIService.getQRCodeImageURL(props.studentId);
        }
    } catch (error) {
        console.error('Error loading QR code:', error);
    } finally {
        loading.value = false;
    }
};

// Generate new QR code
const generateQRCode = async () => {
    try {
        loading.value = true;
        const response = await QRCodeAPIService.generateQRCode(props.studentId);
        
        if (response.success) {
            qrData.value = response.qr_code_data;
            qrImageUrl.value = QRCodeAPIService.getQRCodeImageURL(props.studentId);
        }
    } catch (error) {
        console.error('Error generating QR code:', error);
    } finally {
        loading.value = false;
    }
};

// Download QR code as SVG
const downloadAsSVG = async () => {
    try {
        await QRCodeAPIService.downloadQRCode(props.studentId, props.studentName);
    } catch (error) {
        console.error('Error downloading QR code as SVG:', error);
    }
};

// Download QR code as PNG
const downloadAsPNG = async () => {
    try {
        await QRCodeAPIService.downloadQRCodeAsPNG(props.studentId, props.studentName);
    } catch (error) {
        console.error('Error downloading QR code as PNG:', error);
    }
};
</script>

<style scoped>
.student-qr-code {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.5rem;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    max-width: 280px;
    margin: 0 auto;
    background-color: white;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.student-qr-code:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.qr-header {
    text-align: center;
    margin-bottom: 1rem;
    width: 100%;
}

.student-name {
    font-weight: 600;
    font-size: 1.2rem;
    margin: 0 0 0.25rem 0;
    color: #333;
}

.student-id {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
}

.qr-container {
    width: 220px;
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    border-radius: 8px;
    background-color: #fafafa;
}

.qr-image img {
    width: 200px;
    height: 200px;
    object-fit: contain;
    border-radius: 4px;
}

.qr-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: #666;
}

.qr-error {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    color: #666;
    text-align: center;
    padding: 1rem;
}

.qr-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

.download-buttons {
    display: flex;
    gap: 0.5rem;
}

.download-buttons .p-button {
    flex: 1;
}
</style>
