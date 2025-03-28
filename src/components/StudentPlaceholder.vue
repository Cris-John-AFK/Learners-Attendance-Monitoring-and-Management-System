<template>
    <div class="student-placeholder-card">
        <div class="card-header">
            <h2>Student ID Card</h2>
            <div class="school-info">Westview High School</div>
        </div>

        <div class="card-body">
            <div class="student-info">
                <div class="photo-container">
                    <img :src="studentPhoto" alt="Student Photo" class="student-photo" />
                </div>

                <div class="details">
                    <div class="info-row">
                        <span class="label">ID:</span>
                        <span class="value">{{ student.id }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Name:</span>
                        <span class="value">{{ student.name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Grade:</span>
                        <span class="value">{{ student.gradeLevel }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Section:</span>
                        <span class="value">{{ student.section }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Contact:</span>
                        <span class="value">{{ student.contact }}</span>
                    </div>
                </div>
            </div>

            <div class="qr-container">
                <qrcode-vue :value="student.id.toString()" :size="150" level="H" />
                <div class="qr-label">Scan for Attendance</div>
            </div>
        </div>

        <div class="card-footer">
            <div class="validity">Valid until: {{ student.validUntil }}</div>
            <div class="emergency-contact">Emergency: {{ student.emergencyContact }}</div>
        </div>
    </div>
</template>

<script setup>
import QrcodeVue from 'qrcode.vue';
import { ref } from 'vue';

// Path for student photo - place a photo at this location
const studentPhoto = '/demo/images/student-photo.jpg';

// Student data with all required information
const student = ref({
    id: '12345', // This ID will be encoded in the QR code
    name: 'Jane Smith',
    gradeLevel: '10',
    section: 'A',
    contact: '(555) 123-4567',
    emergencyContact: '(555) 987-6543',
    validUntil: 'June 30, 2024',
    // Additional fields that will be used in the attendance system
    photo: studentPhoto,
    status: 'on-time',
    timestamp: new Date().toLocaleTimeString(),
    date: new Date().toLocaleDateString()
});
</script>

<style scoped>
.student-placeholder-card {
    width: 100%;
    max-width: 500px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    margin: 2rem auto;
    background: white;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.card-header {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    padding: 1.5rem;
    text-align: center;
}

.card-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.school-info {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 0.25rem;
}

.card-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.student-info {
    display: flex;
    gap: 1.5rem;
}

.photo-container {
    width: 120px;
    height: 150px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.student-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-row {
    display: flex;
    border-bottom: 1px solid #f0f4f8;
    padding-bottom: 0.5rem;
}

.label {
    flex: 0 0 70px;
    font-weight: 600;
    color: #4a5568;
}

.value {
    flex: 1;
    color: #2d3748;
}

.qr-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.qr-label {
    margin-top: 0.75rem;
    font-size: 0.9rem;
    color: #4a5568;
    font-weight: 500;
}

.card-footer {
    background: #f1f5f9;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #64748b;
}

@media (max-width: 500px) {
    .student-info {
        flex-direction: column;
        align-items: center;
    }

    .card-footer {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }
}
</style>
