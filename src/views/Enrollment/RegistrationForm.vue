<template>
    <div class="registration-container">
        <div class="registration-card">
            <div class="form-header">
                <h1>Student Registration</h1>
                <p>Please fill in the information below to enroll a student</p>
            </div>

            <div class="form-progress">
                <div class="progress-step active">
                    <div class="step-number">1</div>
                    <div class="step-label">Basic Info</div>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step">
                    <div class="step-number">2</div>
                    <div class="step-label">Contact</div>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step">
                    <div class="step-number">3</div>
                    <div class="step-label">Review</div>
                </div>
            </div>

            <div class="form-body">
                <div v-if="currentStep === 1" class="form-step">
                    <h2>Basic Information</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <InputText id="firstName" v-model="student.firstName" :class="{ 'p-invalid': submitted && !student.firstName }" />
                            <small v-if="submitted && !student.firstName" class="p-error">First name is required.</small>
                        </div>

                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <InputText id="lastName" v-model="student.lastName" :class="{ 'p-invalid': submitted && !student.lastName }" />
                            <small v-if="submitted && !student.lastName" class="p-error">Last name is required.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="middleName">Middle Name</label>
                            <InputText id="middleName" v-model="student.middleName" />
                        </div>

                        <div class="form-group">
                            <label for="birthdate">Date of Birth</label>
                            <Calendar id="birthdate" v-model="student.birthdate" :showIcon="true" dateFormat="mm/dd/yy" :class="{ 'p-invalid': submitted && !student.birthdate }" />
                            <small v-if="submitted && !student.birthdate" class="p-error">Birth date is required.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <Dropdown id="gender" v-model="student.gender" :options="genderOptions" optionLabel="label" optionValue="value" placeholder="Select Gender" :class="{ 'p-invalid': submitted && !student.gender }" />
                            <small v-if="submitted && !student.gender" class="p-error">Gender is required.</small>
                        </div>

                        <div class="form-group">
                            <label for="gradeLevel">Grade Level</label>
                            <Dropdown id="gradeLevel" v-model="student.gradeLevel" :options="gradeLevelOptions" optionLabel="label" optionValue="value" placeholder="Select Grade Level" :class="{ 'p-invalid': submitted && !student.gradeLevel }" />
                            <small v-if="submitted && !student.gradeLevel" class="p-error">Grade level is required.</small>
                        </div>
                    </div>
                </div>

                <div v-if="currentStep === 2" class="form-step">
                    <h2>Contact Information</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <InputText id="email" v-model="student.email" type="email" :class="{ 'p-invalid': submitted && !student.email }" />
                            <small v-if="submitted && !student.email" class="p-error">Email is required.</small>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <InputText id="phone" v-model="student.phone" :class="{ 'p-invalid': submitted && !student.phone }" />
                            <small v-if="submitted && !student.phone" class="p-error">Phone number is required.</small>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="address">Home Address</label>
                        <Textarea id="address" v-model="student.address" rows="3" :class="{ 'p-invalid': submitted && !student.address }" />
                        <small v-if="submitted && !student.address" class="p-error">Address is required.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <InputText id="city" v-model="student.city" :class="{ 'p-invalid': submitted && !student.city }" />
                            <small v-if="submitted && !student.city" class="p-error">City is required.</small>
                        </div>

                        <div class="form-group">
                            <label for="zipCode">Zip Code</label>
                            <InputText id="zipCode" v-model="student.zipCode" :class="{ 'p-invalid': submitted && !student.zipCode }" />
                            <small v-if="submitted && !student.zipCode" class="p-error">Zip code is required.</small>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="guardianName">Parent/Guardian Name</label>
                        <InputText id="guardianName" v-model="student.guardianName" :class="{ 'p-invalid': submitted && !student.guardianName }" />
                        <small v-if="submitted && !student.guardianName" class="p-error">Guardian name is required.</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="guardianContact">Parent/Guardian Contact Number</label>
                        <InputText id="guardianContact" v-model="student.guardianContact" :class="{ 'p-invalid': submitted && !student.guardianContact }" />
                        <small v-if="submitted && !student.guardianContact" class="p-error">Guardian contact is required.</small>
                    </div>
                </div>

                <div v-if="currentStep === 3" class="form-step">
                    <h2>Review Information</h2>

                    <div class="review-section">
                        <h3>Basic Information</h3>
                        <div class="review-grid">
                            <div class="review-item">
                                <div class="review-label">Full Name:</div>
                                <div class="review-value">{{ student.firstName }} {{ student.middleName }} {{ student.lastName }}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Date of Birth:</div>
                                <div class="review-value">{{ formatDate(student.birthdate) }}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Gender:</div>
                                <div class="review-value">{{ getGenderLabel(student.gender) }}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Grade Level:</div>
                                <div class="review-value">{{ getGradeLevelLabel(student.gradeLevel) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="review-section">
                        <h3>Contact Information</h3>
                        <div class="review-grid">
                            <div class="review-item">
                                <div class="review-label">Email:</div>
                                <div class="review-value">{{ student.email }}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Phone:</div>
                                <div class="review-value">{{ student.phone }}</div>
                            </div>
                            <div class="review-item full-width">
                                <div class="review-label">Address:</div>
                                <div class="review-value">{{ student.address }}, {{ student.city }}, {{ student.zipCode }}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Guardian:</div>
                                <div class="review-value">{{ student.guardianName }}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Guardian Contact:</div>
                                <div class="review-value">{{ student.guardianContact }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="terms-section">
                        <Checkbox v-model="student.termsAccepted" :binary="true" id="terms" />
                        <label for="terms" class="ml-2">I confirm that all information provided is accurate and complete.</label>
                        <small v-if="submitted && !student.termsAccepted" class="p-error block mt-2">You must confirm the information is accurate.</small>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <Button v-if="currentStep > 1" label="Previous" icon="pi pi-arrow-left" class="p-button-outlined" @click="prevStep" />
                <Button v-if="currentStep < 3" label="Next" icon="pi pi-arrow-right" iconPos="right" @click="nextStep" />
                <Button v-if="currentStep === 3" label="Submit Registration" icon="pi pi-check" class="p-button-success" @click="submitForm" />
            </div>
        </div>
    </div>
</template>

<script setup>
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const toast = useToast();
const currentStep = ref(1);
const submitted = ref(false);

const student = ref({
    firstName: '',
    lastName: '',
    middleName: '',
    birthdate: null,
    gender: null,
    gradeLevel: null,
    email: '',
    phone: '',
    address: '',
    city: '',
    zipCode: '',
    guardianName: '',
    guardianContact: '',
    termsAccepted: false
});

const genderOptions = [
    { label: 'Male', value: 'male' },
    { label: 'Female', value: 'female' }
];

const gradeLevelOptions = [
    { label: 'Kindergarten', value: 'K' },
    { label: 'Grade 1', value: '1' },
    { label: 'Grade 2', value: '2' },
    { label: 'Grade 3', value: '3' },
    { label: 'Grade 4', value: '4' },
    { label: 'Grade 5', value: '5' },
    { label: 'Grade 6', value: '6' }
];

const getGenderLabel = (value) => {
    const option = genderOptions.find((opt) => opt.value === value);
    return option ? option.label : '';
};

const getGradeLevelLabel = (value) => {
    const option = gradeLevelOptions.find((opt) => opt.value === value);
    return option ? option.label : '';
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const validateCurrentStep = () => {
    submitted.value = true;

    if (currentStep.value === 1) {
        return student.value.firstName && student.value.lastName && student.value.birthdate && student.value.gender && student.value.gradeLevel;
    } else if (currentStep.value === 2) {
        return student.value.email && student.value.phone && student.value.address && student.value.city && student.value.zipCode && student.value.guardianName && student.value.guardianContact;
    }

    return true;
};

const nextStep = () => {
    if (validateCurrentStep()) {
        currentStep.value++;
        submitted.value = false;

        // Update progress steps
        const steps = document.querySelectorAll('.progress-step');
        steps.forEach((step, index) => {
            if (index < currentStep.value) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
        submitted.value = false;

        // Update progress steps
        const steps = document.querySelectorAll('.progress-step');
        steps.forEach((step, index) => {
            if (index < currentStep.value) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
    }
};

const submitForm = () => {
    submitted.value = true;

    if (!student.value.termsAccepted) {
        return;
    }

    // Here you would typically send the data to your backend
    console.log('Submitting student data:', student.value);

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Registration Successful',
        detail: 'Your registration has been submitted successfully!',
        life: 5000
    });

    // Redirect to confirmation page or dashboard
    setTimeout(() => {
        router.push('/enrollment/confirmation');
    }, 2000);
};
</script>

<style scoped>
.registration-container {
    min-height: calc(100vh - 4rem);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
}

.registration-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 900px;
    overflow: hidden;
    animation: card-fade-in 0.6s ease-out;
}

@keyframes card-fade-in {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-header {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: white;
    padding: 2rem;
    text-align: center;
}

.form-header h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 600;
}

.form-header p {
    margin: 0.5rem 0 0;
    opacity: 0.9;
}

.form-progress {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 2rem;
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
}

.step-number {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: #e2e8f0;
    color: #64748b;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: 600;
    transition: all 0.3s ease;
}

.step-label {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
}

.progress-step.active .step-number {
    background-color: #3b82f6;
    color: white;
}

.progress-step.active .step-label {
    color: #1e40af;
    font-weight: 600;
}

.progress-line {
    flex: 1;
    height: 2px;
    background-color: #e2e8f0;
    margin: 0 0.5rem;
    position: relative;
    top: -18px;
    z-index: 0;
}

.form-body {
    padding: 2rem;
}

.form-step {
    animation: fade-in 0.4s ease-out;
}

@keyframes fade-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.form-step h2 {
    margin-top: 0;
    margin-bottom: 1.5rem;
    color: #1e293b;
    font-weight: 600;
    font-size: 1.5rem;
}

.form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    flex: 1;
    margin-bottom: 1.5rem;
}

.form-group.full-width {
    width: 100%;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #475569;
}

:deep(.p-inputtext),
:deep(.p-dropdown),
:deep(.p-calendar),
:deep(.p-textarea) {
    width: 100%;
    border-radius: 8px;
    transition: all 0.3s ease;
}

:deep(.p-inputtext:hover),
:deep(.p-dropdown:hover),
:deep(.p-calendar:hover),
:deep(.p-textarea:hover) {
    border-color: #3b82f6;
}

:deep(.p-inputtext:focus),
:deep(.p-dropdown:focus),
:deep(.p-calendar:focus),
:deep(.p-textarea:focus) {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
    border-color: #3b82f6;
}

:deep(.p-invalid) {
    border-color: #ef4444 !important;
}

:deep(.p-invalid:focus) {
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25) !important;
}

.p-error {
    color: #ef4444;
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: block;
}

.form-footer {
    padding: 1.5rem 2rem;
    background-color: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
}

/* Review page styling */
.review-section {
    background-color: #f8fafc;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.review-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #3b82f6;
    font-size: 1.25rem;
    font-weight: 600;
}

.review-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.review-item {
    display: flex;
    flex-direction: column;
}

.review-item.full-width {
    grid-column: span 2;
}

.review-label {
    font-weight: 500;
    color: #64748b;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.review-value {
    color: #1e293b;
    font-weight: 500;
}

.terms-section {
    margin-top: 2rem;
    display: flex;
    align-items: flex-start;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }

    .review-grid {
        grid-template-columns: 1fr;
    }

    .review-item.full-width {
        grid-column: span 1;
    }

    .form-footer {
        flex-direction: column;
        gap: 1rem;
    }

    .form-footer button {
        width: 100%;
    }
}
</style>
