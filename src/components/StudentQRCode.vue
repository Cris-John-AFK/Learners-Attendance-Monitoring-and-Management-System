<template>
    <div class="student-qr-code">
        <div class="school-logo print-only">
            <img src="/demo/images/logo.png" alt="NCS Logo" class="school-logo-image" />
            <h2 class="school-name">Naawan Central School</h2>
            <p class="school-subtitle">Student Identification</p>
        </div>

        <div class="qr-header">
            <h3 class="student-name">{{ studentName }}</h3>
            <p class="student-id">ID: {{ studentId }}</p>
            <div class="student-details print-only">
                <p class="detail-item"><strong>Section:</strong> {{ section }}</p>
                <p class="detail-item"><strong>Grade:</strong> {{ grade }}</p>
            </div>
        </div>

        <div class="qr-container">
            <div v-if="loading || (!hasQRCode && !props.batchLoadingComplete)" class="qr-loading">
                <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
                <p v-if="!props.batchLoadingComplete">Loading QR Code...</p>
                <p v-else>Generating QR Code...</p>
            </div>
            <div v-else-if="qrImageUrl" class="qr-image">
                <img :src="qrImageUrl" alt="Student QR Code" />
            </div>
            <div v-else-if="qrValue" class="qr-generated">
                <qrcode-vue :value="qrValue" :size="size" level="H" />
            </div>
            <div v-else class="qr-error">
                <p>No QR code available</p>
                <Button label="Generate QR Code" icon="pi pi-qrcode" @click="generateQRCode" class="p-button-sm p-button-outlined" :disabled="!props.batchLoadingComplete" />
            </div>
        </div>

        <div class="qr-actions">
            <div class="download-buttons">
                <Button label="PNG" icon="pi pi-download" @click="downloadAsPNG" class="p-button-sm" :disabled="!hasQRCode" />
                <Button label="SVG" icon="pi pi-download" @click="downloadAsSVG" class="p-button-sm p-button-outlined" :disabled="!hasQRCode" />
            </div>
            <Button label="Regenerate" icon="pi pi-refresh" @click="generateQRCode" class="p-button-sm p-button-secondary" />
        </div>
    </div>
</template>

<script setup>
import { QRCodeAPIService } from '@/router/service/QRCodeAPIService';
import QrcodeVue from 'qrcode.vue';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    studentId: {
        type: [String, Number],
        required: true
    },
    studentName: {
        type: String,
        default: 'Student'
    },
    section: {
        type: String,
        default: 'N/A'
    },
    grade: {
        type: String,
        default: 'N/A'
    },
    size: {
        type: Number,
        default: 200
    },
    preloadedQRData: {
        type: [String, Object],
        default: null
    },
    batchLoadingComplete: {
        type: Boolean,
        default: false
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
    // 🚀 PERFORMANCE: Use preloaded data if available
    if (props.preloadedQRData) {
        // Handle string SVG data
        const svgData = typeof props.preloadedQRData === 'string' ? props.preloadedQRData : String(props.preloadedQRData);
        
        // Direct SVG data - no need to encode
        qrImageUrl.value = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svgData)}`;
        qrData.value = svgData;
    } else if (props.batchLoadingComplete) {
        // Batch loading is complete but no data - QR doesn't exist
        // Silent - no console log needed
    } else {
        // Wait for batch loading to complete - no individual loading
        // Silent - no console log needed
    }
});

// Watch for batch loading completion
watch(() => props.batchLoadingComplete, (isComplete) => {
    if (isComplete && !props.preloadedQRData && !hasQRCode.value) {
        // Batch loading is complete but no preloaded data - QR doesn't exist
        // Silent - no individual API calls needed
    }
});

// Watch for preloaded data changes
watch(() => props.preloadedQRData, (newData) => {
    if (newData) {
        // Handle string SVG data
        const svgData = typeof newData === 'string' ? newData : String(newData);
        
        // Direct SVG data - no need to encode
        qrImageUrl.value = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svgData)}`;
        qrData.value = svgData;
    }
}, { immediate: true });

// Load existing QR code for student (fallback method)
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
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}

.student-qr-code:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.qr-header {
    text-align: center !important;
    margin-bottom: 1rem;
    width: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
}

.student-name {
    font-weight: 600;
    font-size: 1.2rem;
    margin: 0 0 0.25rem 0;
    color: #333;
    text-align: center !important;
    width: 100% !important;
    display: block !important;
}

.student-id {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    text-align: center !important;
    width: 100% !important;
    display: block !important;
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

/* Print-only elements - hidden on screen */
.print-only {
    display: none;
}

/* Print Styles - Beautiful centered layout */
@media print {
    /* Hide screen-only elements */
    .qr-actions,
    button,
    .p-button {
        display: none !important;
    }

    /* Show print-only elements */
    .print-only {
        display: block !important;
    }

    /* Center card on page - FIT ON ONE PAGE */
    .student-qr-code {
        max-width: 700px;
        width: 95%;
        height: 100vh !important;
        margin: 0 auto !important;
        padding: 1.5rem 2rem !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        background: white !important;
        transform: none !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        overflow: hidden !important;
    }

    /* School branding - COMPACT */
    .school-logo {
        text-align: center;
        margin-bottom: 1rem !important;
        padding-bottom: 1rem !important;
        border-bottom: 2px solid #2196f3;
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .school-logo-image {
        width: 70px !important;
        height: 70px !important;
        object-fit: contain;
        margin: 0 auto 0.5rem auto !important;
        display: block;
    }

    .school-name {
        font-size: 1.5rem !important;
        font-weight: 800;
        color: #2196f3;
        margin: 0 0 0.3rem 0 !important;
        letter-spacing: 0.5px;
    }

    .school-subtitle {
        font-size: 0.9rem !important;
        color: #666;
        margin: 0 !important;
        font-weight: 500;
    }

    /* Student header - COMPACT */
    .qr-header {
        text-align: center;
        margin-bottom: 1rem !important;
        width: 100%;
    }

    .student-name {
        font-size: 1.3rem !important;
        font-weight: 700;
        color: #333;
        margin: 0 0 0.5rem 0 !important;
        letter-spacing: 0.3px;
        text-align: center !important;
        width: 100% !important;
        display: block !important;
        line-height: 1.2 !important;
        word-wrap: break-word !important;
        hyphens: auto !important;
        max-width: 400px !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }

    .student-id {
        font-size: 1rem !important;
        color: #666;
        margin: 0 0 1rem 0 !important;
        font-weight: 600;
        background-color: #f0f0f0;
        padding: 0.4rem 1.2rem !important;
        border-radius: 15px;
        display: inline-block;
    }

    /* Student details - COMPACT */
    .student-details {
        margin-top: 1rem !important;
        padding: 0.7rem !important;
        background-color: #f8f9fa;
        border-radius: 8px;
        width: 100%;
    }

    .detail-item {
        font-size: 0.95rem !important;
        color: #555;
        margin: 0.3rem 0 !important;
        padding: 0.3rem !important;
    }

    .detail-item strong {
        color: #2196f3;
        font-weight: 700;
        display: inline-block;
        min-width: 80px;
    }

    /* QR Code container - COMPACT */
    .qr-container {
        width: 280px !important;
        height: 280px !important;
        padding: 1rem !important;
        background: white;
        border: 4px solid #2196f3 !important;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3) !important;
        margin-bottom: 1rem !important;
    }

    .qr-image img {
        width: 240px !important;
        height: 240px !important;
        border-radius: 6px;
    }

    /* Footer instruction - COMPACT */
    .student-qr-code::after {
        content: 'Scan this QR code for attendance tracking';
        display: block;
        text-align: center;
        font-size: 0.85rem !important;
        color: #888;
        margin-top: 1rem !important;
        padding-top: 1rem !important;
        border-top: 1px solid #e0e0e0;
        width: 100%;
        font-style: italic;
    }
}
</style>
