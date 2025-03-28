<script setup>
import { useToast } from 'primevue/usetoast';
import { reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const toast = useToast();
const currentStep = ref(1);
const submitted = ref(false);

// Define student data structure
const student = reactive({
    // Enrollment Info
    schoolYearStart: '',
    schoolYearEnd: '',
    gradeLevel: '',
    hasLRN: false,
    isReturning: false,
    lrn: '',

    // Learner Info
    psaBirthCertNo: '',
    lastName: '',
    firstName: '',
    middleName: '',
    extensionName: '',
    birthdate: null,
    birthplace: '',
    sex: '',
    age: null,
    motherTongue: '',

    // Indigenous People
    isIndigenous: false,
    indigenousCommunity: '',

    // 4Ps
    is4PsBeneficiary: false,
    householdID: '',

    // Disability
    hasDisability: false,
    disabilities: [],
    visualImpairment: false,
    hearingImpairment: false,
    learningDisability: false,
    intellectualDisability: false,
    blind: false,
    autismSpectrum: false,
    emotionalBehavioral: false,
    orthopedicPhysical: false,
    lowVision: false,
    speechLanguage: false,
    cerebralPalsy: false,
    specialHealth: false,
    multipleDisorder: false,
    cancer: false,

    // Current Address
    currentAddress: {
        houseNo: '',
        street: '',
        barangay: '',
        city: '',
        province: '',
        country: '',
        zipCode: ''
    },

    // Permanent Address
    sameAddress: false,
    permanentAddress: {
        houseNo: '',
        street: '',
        barangay: '',
        city: '',
        province: '',
        country: '',
        zipCode: ''
    },

    // Parents Info
    father: {
        lastName: '',
        firstName: '',
        middleName: '',
        contactNumber: ''
    },
    mother: {
        lastName: '',
        firstName: '',
        middleName: '',
        contactNumber: ''
    },

    termsAccepted: false
});

// Format date for display
const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Format address for display
const formatAddress = (address) => {
    if (!address) return 'N/A';
    const parts = [address.houseNo, address.street, address.barangay, address.city, address.province, address.country, address.zipCode].filter((part) => part && part.trim() !== '');

    return parts.length > 0 ? parts.join(', ') : 'N/A';
};

// Get text representation of selected disabilities
const getDisabilitiesText = () => {
    const disabilityMap = {
        visualImpairment: 'Visual Impairment',
        hearingImpairment: 'Hearing Impairment',
        learningDisability: 'Learning Disability',
        intellectualDisability: 'Intellectual Disability',
        blind: 'Blind',
        autismSpectrum: 'Autism Spectrum Disorder',
        emotionalBehavioral: 'Emotional-Behavioral Disorder',
        orthopedicPhysical: 'Orthopedic/Physical Handicap',
        lowVision: 'Low Vision',
        speechLanguage: 'Speech/Language Disorder',
        cerebralPalsy: 'Cerebral Palsy',
        specialHealth: 'Special Health Problem/Chronic Disease',
        multipleDisorder: 'Multiple Disorder',
        cancer: 'Cancer'
    };

    const selectedDisabilities = Object.keys(disabilityMap)
        .filter((key) => student[key])
        .map((key) => disabilityMap[key]);

    return selectedDisabilities.length > 0 ? selectedDisabilities.join(', ') : 'None';
};

// Navigation functions
const nextStep = () => {
    if (validateCurrentStep()) {
        currentStep.value++;
        window.scrollTo(0, 0);
    } else {
        submitted.value = true;
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please fill in all required fields',
            life: 3000
        });
    }
};

const prevStep = () => {
    currentStep.value--;
    window.scrollTo(0, 0);
};

// Validation for each step
const validateCurrentStep = () => {
    submitted.value = true;

    switch (currentStep.value) {
        case 1: // Basic Info
            return !!student.schoolYearStart && !!student.schoolYearEnd && !!student.gradeLevel && (student.hasLRN ? !!student.lrn : true);

        case 2: // Personal Details
            return !!student.lastName && !!student.firstName && !!student.birthdate && !!student.birthplace && !!student.sex && !!student.age;

        case 3: {
            // Address
            const currentAddressValid = !!student.currentAddress.street && !!student.currentAddress.barangay && !!student.currentAddress.city;

            if (student.sameAddress) {
                return currentAddressValid;
            } else {
                return currentAddressValid && !!student.permanentAddress.street && !!student.permanentAddress.barangay && !!student.permanentAddress.city;
            }
        }

        case 4: // Family Info
            return true; // Family info is optional

        case 5: // Review
            return student.termsAccepted;

        default:
            return true;
    }
};

// Submit the form
const submitForm = () => {
    if (validateCurrentStep()) {
        // Here you would typically send the data to your backend
        toast.add({
            severity: 'success',
            summary: 'Form Submitted',
            detail: 'Your enrollment form has been submitted successfully',
            life: 3000
        });

        // Redirect to confirmation page or dashboard
        setTimeout(() => {
            router.push('/enrollment/confirmation');
        }, 2000);
    } else {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please accept the terms to continue',
            life: 3000
        });
    }
};

// Handle address copy
const copyCurrentToPermanent = () => {
    if (student.sameAddress) {
        student.permanentAddress = { ...student.currentAddress };
    }
};

// Watch for changes in sameAddress
watch(
    () => student.sameAddress,
    (newValue) => {
        if (newValue) {
            copyCurrentToPermanent();
        }
    }
);

// Create array of floating letters and numbers
const floatingItems = reactive([
    // Foreground (larger, more opaque)
    { content: 'A', top: 15, left: 10, size: 90, color: 'rgba(255,255,255,0.7)', duration: 25, delay: 0, zIndex: -1, direction: 'horizontal' },
    { content: '7', top: 25, left: 85, size: 100, color: 'rgba(255,255,255,0.65)', duration: 30, delay: 1, zIndex: -1, direction: 'diagonal-1' },
    { content: 'B', top: 60, left: 5, size: 95, color: 'rgba(255,255,255,0.68)', duration: 28, delay: 3, zIndex: -1, direction: 'diagonal-2' },

    // Middleground (medium size and opacity)
    { content: '3', top: 75, left: 80, size: 75, color: 'rgba(255,255,255,0.7)', duration: 25, delay: 0, zIndex: -1, direction: 'horizontal' },
    { content: 'C', top: 10, left: 60, size: 70, color: 'rgba(255,255,255,0.65)', duration: 30, delay: 1, zIndex: -1, direction: 'diagonal-1' },
    { content: '9', top: 40, left: 90, size: 80, color: 'rgba(255,255,255,0.68)', duration: 28, delay: 3, zIndex: -1, direction: 'diagonal-2' },

    // Background (smaller, more visible)
    { content: 'D', top: 85, left: 30, size: 55, color: 'rgba(255,255,255,0.7)', duration: 25, delay: 0, zIndex: -1, direction: 'horizontal' },
    { content: '1', top: 30, left: 15, size: 50, color: 'rgba(255,255,255,0.65)', duration: 30, delay: 1, zIndex: -1, direction: 'diagonal-1' },
    { content: 'E', top: 50, left: 75, size: 48, color: 'rgba(255,255,255,0.52)', duration: 31, delay: 2, zIndex: -3, direction: 'horizontal' },
    { content: '5', top: 70, left: 45, size: 52, color: 'rgba(255,255,255,0.7)', duration: 25, delay: 0, zIndex: -1, direction: 'horizontal' }
]);
</script>
<template>
    <div class="registration-container">
        <!-- Gradient background -->
        <div class="gradient-background">
            <div class="animated-gradient"></div>
        </div>

        <!-- Floating letters and numbers -->
        <div
            v-for="(item, index) in floatingItems"
            :key="index"
            class="floating-element"
            :class="['bounce-' + item.direction, 'delay-' + Math.floor(item.delay)]"
            :style="{
                top: item.top + '%',
                left: item.left + '%',
                fontSize: item.size + 'px',
                color: item.color,
                zIndex: item.zIndex
            }"
        >
            {{ item.content }}
        </div>

        <div class="registration-card">
            <div class="form-header">
                <div class="header-logo">
                    <img src="/demo/images/logo.png" alt="School Logo" />
                </div>
                <div class="header-text">
                    <h1>BASIC EDUCATION ENROLLMENT FORM</h1>
                    <p class="form-subtitle">THIS FORM IS NOT FOR SALE</p>
                </div>
            </div>

            <div class="form-instructions">
                <p>
                    INSTRUCTIONS: Please provide accurate and honest information in all fields. This information will be used to create or update your child's learner profile in the school's information system. If you need assistance, please contact
                    the school registrar (+639123456789).
                </p>
            </div>

            <div class="form-progress">
                <div class="progress-step" :class="{ active: currentStep >= 1 }">
                    <div class="step-number">1</div>
                    <div class="step-label">Basic Info</div>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step" :class="{ active: currentStep >= 2 }">
                    <div class="step-number">2</div>
                    <div class="step-label">Personal Details</div>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step" :class="{ active: currentStep >= 3 }">
                    <div class="step-number">3</div>
                    <div class="step-label">Address</div>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step" :class="{ active: currentStep >= 4 }">
                    <div class="step-number">4</div>
                    <div class="step-label">Family Info</div>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step" :class="{ active: currentStep >= 5 }">
                    <div class="step-number">5</div>
                    <div class="step-label">Review</div>
                </div>
            </div>

            <!-- Step 1: Basic Enrollment Information -->
            <div v-if="currentStep === 1" class="form-section">
                <h2 class="section-title">ENROLLMENT INFORMATION</h2>

                <div class="form-group">
                    <label for="schoolYear">School Year</label>
                    <div class="school-year-input">
                        <InputText id="schoolYearStart" v-model="student.schoolYearStart" placeholder="Start" maxlength="4" />
                        <span class="year-separator">-</span>
                        <InputText id="schoolYearEnd" v-model="student.schoolYearEnd" placeholder="End" maxlength="4" />
                    </div>
                    <small v-if="submitted && !student.schoolYearStart" class="p-error">School year is required</small>
                </div>

                <div class="form-group">
                    <label for="gradeLevel">Grade level to Enroll</label>
                    <InputText id="gradeLevel" v-model="student.gradeLevel" maxlength="3" />
                    <small v-if="submitted && !student.gradeLevel" class="p-error">Grade level is required</small>
                </div>

                <div class="form-group checkbox-group">
                    <label>1. With LRN?</label>
                    <div class="checkbox-options">
                        <div class="checkbox-option">
                            <RadioButton id="lrnYes" v-model="student.hasLRN" :value="true" />
                            <label for="lrnYes">Yes</label>
                        </div>
                        <div class="checkbox-option">
                            <RadioButton id="lrnNo" v-model="student.hasLRN" :value="false" />
                            <label for="lrnNo">No</label>
                        </div>
                    </div>
                </div>

                <div class="form-group checkbox-group">
                    <label>2. Returning (Balik-Aral)</label>
                    <div class="checkbox-options">
                        <div class="checkbox-option">
                            <RadioButton id="returningYes" v-model="student.isReturning" :value="true" />
                            <label for="returningYes">Yes</label>
                        </div>
                        <div class="checkbox-option">
                            <RadioButton id="returningNo" v-model="student.isReturning" :value="false" />
                            <label for="returningNo">No</label>
                        </div>
                    </div>
                </div>

                <div class="form-group" v-if="student.hasLRN">
                    <label for="learnerReferenceNo">Learner Reference No.</label>
                    <InputText id="learnerReferenceNo" v-model="student.lrn" />
                    <small v-if="submitted && student.hasLRN && !student.lrn" class="p-error">LRN is required</small>
                </div>

                <div v-if="student.isReturning" class="returning-learner-section">
                    <div class="form-group">
                        <label for="lastGradeLevel">Last Grade Level Completed</label>
                        <InputText id="lastGradeLevel" v-model="student.lastGradeLevel" />
                    </div>

                    <div class="form-group">
                        <label for="lastSchoolYear">Last School Year Completed</label>
                        <InputText id="lastSchoolYear" v-model="student.lastSchoolYear" />
                    </div>

                    <div class="form-group">
                        <label for="lastSchoolAttended">Last School Attended</label>
                        <InputText id="lastSchoolAttended" v-model="student.lastSchoolAttended" />
                    </div>

                    <div class="form-group">
                        <label for="schoolId">School ID</label>
                        <InputText id="schoolId" v-model="student.schoolId" maxlength="6" />
                    </div>
                </div>

                <!-- Fix the disability section in the review step -->
                <div class="review-section" v-if="student.hasDisability">
                    <h3>Disability Information</h3>
                    <div class="review-item full-width">
                        <div class="review-label">Disabilities:</div>
                        <div class="review-value">{{ getDisabilitiesText() }}</div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Learner Personal Information -->
            <div v-if="currentStep === 2" class="form-section">
                <h2 class="section-title">LEARNER INFORMATION</h2>

                <div class="form-group">
                    <label for="psaBirthCertNo">PSA Birth Certificate No. (if available)</label>
                    <InputText id="psaBirthCertNo" v-model="student.psaBirthCertNo" />
                </div>

                <div class="form-group">
                    <label for="lastName">Last Name</label>
                    <InputText id="lastName" v-model="student.lastName" :class="{ 'p-invalid': submitted && !student.lastName }" />
                    <small v-if="submitted && !student.lastName" class="p-error">Last name is required</small>
                </div>

                <div class="form-group">
                    <label for="firstName">First Name</label>
                    <InputText id="firstName" v-model="student.firstName" :class="{ 'p-invalid': submitted && !student.firstName }" />
                    <small v-if="submitted && !student.firstName" class="p-error">First name is required</small>
                </div>

                <div class="form-group">
                    <label for="middleName">Middle Name</label>
                    <InputText id="middleName" v-model="student.middleName" />
                </div>

                <div class="form-group">
                    <label for="extensionName">Extension Name e.g. Jr., III (if applicable)</label>
                    <InputText id="extensionName" v-model="student.extensionName" />
                </div>

                <div class="form-group">
                    <label for="birthdate">Birthdate (mm/dd/yyyy)</label>
                    <Calendar id="birthdate" v-model="student.birthdate" :showIcon="true" dateFormat="mm/dd/yy" :class="{ 'p-invalid': submitted && !student.birthdate }" />
                    <small v-if="submitted && !student.birthdate" class="p-error">Birth date is required</small>
                </div>

                <div class="form-group">
                    <label for="birthplace">Place of Birth (Municipality/City)</label>
                    <InputText id="birthplace" v-model="student.birthplace" />
                    <small v-if="submitted && !student.birthplace" class="p-error">Place of birth is required</small>
                </div>

                <div class="form-group">
                    <label>Sex</label>
                    <div class="checkbox-options">
                        <div class="checkbox-option">
                            <RadioButton id="male" v-model="student.sex" value="male" />
                            <label for="male">Male</label>
                        </div>
                        <div class="checkbox-option">
                            <RadioButton id="female" v-model="student.sex" value="female" />
                            <label for="female">Female</label>
                        </div>
                    </div>
                    <small v-if="submitted && !student.sex" class="p-error">Sex is required</small>
                </div>

                <div class="form-group">
                    <label for="age">Age</label>
                    <InputNumber id="age" v-model="student.age" :min="0" :max="100" />
                    <small v-if="submitted && !student.age" class="p-error">Age is required</small>
                </div>

                <div class="form-group">
                    <label for="motherTongue">Mother Tongue</label>
                    <InputText id="motherTongue" v-model="student.motherTongue" />
                </div>

                <div class="form-group">
                    <label>Belonging to any Indigenous Peoples (IP) Community/Indigenous Cultural Community</label>
                    <div class="checkbox-options">
                        <div class="checkbox-option">
                            <RadioButton id="ipYes" v-model="student.isIndigenous" :value="true" />
                            <label for="ipYes">Yes</label>
                        </div>
                        <div class="checkbox-option">
                            <RadioButton id="ipNo" v-model="student.isIndigenous" :value="false" />
                            <label for="ipNo">No</label>
                        </div>
                    </div>
                </div>

                <div class="form-group" v-if="student.isIndigenous">
                    <label>If Yes, please specify:</label>
                    <InputText v-model="student.indigenousCommunity" />
                    <small v-if="submitted && student.isIndigenous && !student.indigenousCommunity" class="p-error">Please specify the indigenous community</small>
                </div>

                <div class="form-group">
                    <label>Is your family a beneficiary of 4Ps?</label>
                    <div class="checkbox-options">
                        <div class="checkbox-option">
                            <RadioButton id="4psYes" v-model="student.is4PsBeneficiary" :value="true" />
                            <label for="4psYes">Yes</label>
                        </div>
                        <div class="checkbox-option">
                            <RadioButton id="4psNo" v-model="student.is4PsBeneficiary" :value="false" />
                            <label for="4psNo">No</label>
                        </div>
                    </div>
                </div>

                <div class="form-group" v-if="student.is4PsBeneficiary">
                    <label>If Yes, write the 4Ps Household ID Number below:</label>
                    <InputText v-model="student.householdID" />
                    <small v-if="submitted && student.is4PsBeneficiary && !student.householdID" class="p-error">Household ID is required</small>
                </div>

                <div class="form-group">
                    <label>Is the child a Learner with Disability?</label>
                    <div class="checkbox-options">
                        <div class="checkbox-option">
                            <RadioButton id="disabilityYes" v-model="student.hasDisability" :value="true" />
                            <label for="disabilityYes">Yes</label>
                        </div>
                        <div class="checkbox-option">
                            <RadioButton id="disabilityNo" v-model="student.hasDisability" :value="false" />
                            <label for="disabilityNo">No</label>
                        </div>
                    </div>
                </div>

                <div v-if="student.hasDisability" class="disability-section">
                    <label>If Yes, specify the type of disability:</label>
                    <div class="disability-options">
                        <div class="disability-option">
                            <Checkbox id="visualImpairment" v-model="student.disabilities.visualImpairment" :binary="true" />
                            <label for="visualImpairment">Visual Impairment</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="hearingImpairment" v-model="student.disabilities.hearingImpairment" :binary="true" />
                            <label for="hearingImpairment">Hearing Impairment</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="learningDisability" v-model="student.disabilities.learningDisability" :binary="true" />
                            <label for="learningDisability">Learning Disability</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="intellectualDisability" v-model="student.disabilities.intellectualDisability" :binary="true" />
                            <label for="intellectualDisability">Intellectual Disability</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="blind" v-model="student.disabilities.blind" :binary="true" />
                            <label for="blind">Blind</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="autismSpectrum" v-model="student.disabilities.autismSpectrum" :binary="true" />
                            <label for="autismSpectrum">Autism Spectrum Disorder</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="lowVision" v-model="student.disabilities.lowVision" :binary="true" />
                            <label for="lowVision">Low Vision</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="speechLanguage" v-model="student.disabilities.speechLanguage" :binary="true" />
                            <label for="speechLanguage">Speech/Language Disorder</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="emotionalBehavioral" v-model="student.disabilities.emotionalBehavioral" :binary="true" />
                            <label for="emotionalBehavioral">Emotional-Behavioral Disorder</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="cerebralPalsy" v-model="student.disabilities.cerebralPalsy" :binary="true" />
                            <label for="cerebralPalsy">Cerebral Palsy</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="orthopedic" v-model="student.disabilities.orthopedic" :binary="true" />
                            <label for="orthopedic">Orthopedic/Physical Handicap</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="specialHealth" v-model="student.disabilities.specialHealth" :binary="true" />
                            <label for="specialHealth">Special Health Problem/ Chronic Disease</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="multipleDisorder" v-model="student.disabilities.multipleDisorder" :binary="true" />
                            <label for="multipleDisorder">Multiple Disorder</label>
                        </div>
                        <div class="disability-option">
                            <Checkbox id="cancer" v-model="student.disabilities.cancer" :binary="true" />
                            <label for="cancer">Cancer</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Address Information -->
            <div v-if="currentStep === 3" class="form-section">
                <h2 class="section-title">CURRENT ADDRESS</h2>

                <div class="form-group">
                    <label for="currentHouseNo">House No.</label>
                    <InputText id="currentHouseNo" v-model="student.currentAddress.houseNo" />
                </div>

                <div class="form-group">
                    <label for="currentStreet">Street/Sitio/Purok</label>
                    <InputText id="currentStreet" v-model="student.currentAddress.street" />
                </div>

                <div class="form-group">
                    <label for="currentBarangay">Barangay</label>
                    <InputText id="currentBarangay" v-model="student.currentAddress.barangay" :class="{ 'p-invalid': submitted && !student.currentAddress.barangay }" />
                    <small v-if="submitted && !student.currentAddress.barangay" class="p-error">Barangay is required</small>
                </div>

                <div class="form-group">
                    <label for="currentCity">Municipality/City</label>
                    <InputText id="currentCity" v-model="student.currentAddress.city" :class="{ 'p-invalid': submitted && !student.currentAddress.city }" />
                    <small v-if="submitted && !student.currentAddress.city" class="p-error">City is required</small>
                </div>

                <div class="form-group">
                    <label for="currentProvince">Province</label>
                    <InputText id="currentProvince" v-model="student.currentAddress.province" />
                </div>

                <div class="form-group">
                    <label for="currentCountry">Country</label>
                    <InputText id="currentCountry" v-model="student.currentAddress.country" />
                </div>

                <div class="form-group">
                    <label for="currentZipCode">Zip Code</label>
                    <InputText id="currentZipCode" v-model="student.currentAddress.zipCode" />
                </div>

                <h2 class="section-title">PERMANENT ADDRESS</h2>

                <div class="form-group checkbox-group">
                    <label>Same with your Current Address?</label>
                    <div class="checkbox-options">
                        <div class="checkbox-option">
                            <RadioButton id="sameAddressYes" v-model="student.sameAddress" :value="true" />
                            <label for="sameAddressYes">Yes</label>
                        </div>
                        <div class="checkbox-option">
                            <RadioButton id="sameAddressNo" v-model="student.sameAddress" :value="false" />
                            <label for="sameAddressNo">No</label>
                        </div>
                    </div>
                </div>

                <div v-if="!student.sameAddress">
                    <div class="form-group">
                        <label for="permanentHouseNo">House No./Street</label>
                        <InputText id="permanentHouseNo" v-model="student.permanentAddress.houseNo" />
                    </div>

                    <div class="form-group">
                        <label for="permanentStreet">Street Name</label>
                        <InputText id="permanentStreet" v-model="student.permanentAddress.street" />
                    </div>

                    <div class="form-group">
                        <label for="permanentBarangay">Barangay</label>
                        <InputText id="permanentBarangay" v-model="student.permanentAddress.barangay" />
                    </div>

                    <div class="form-group">
                        <label for="permanentCity">Municipality/City</label>
                        <InputText id="permanentCity" v-model="student.permanentAddress.city" />
                    </div>

                    <div class="form-group">
                        <label for="permanentProvince">Province</label>
                        <InputText id="permanentProvince" v-model="student.permanentAddress.province" />
                    </div>

                    <div class="form-group">
                        <label for="permanentCountry">Country</label>
                        <InputText id="permanentCountry" v-model="student.permanentAddress.country" />
                    </div>

                    <div class="form-group">
                        <label for="permanentZipCode">Zip Code</label>
                        <InputText id="permanentZipCode" v-model="student.permanentAddress.zipCode" />
                    </div>
                </div>
            </div>

            <!-- Step 4: Parent/Guardian Information -->
            <div v-if="currentStep === 4" class="form-section">
                <h2 class="section-title">PARENT'S/GUARDIAN'S INFORMATION</h2>

                <h3 class="subsection-title">Father's Name</h3>
                <div class="form-group">
                    <label for="fatherLastName">Last Name</label>
                    <InputText id="fatherLastName" v-model="student.father.lastName" />
                </div>

                <div class="form-group">
                    <label for="fatherFirstName">First Name</label>
                    <InputText id="fatherFirstName" v-model="student.father.firstName" />
                </div>

                <div class="form-group">
                    <label for="fatherMiddleName">Middle Name</label>
                    <InputText id="fatherMiddleName" v-model="student.father.middleName" />
                </div>

                <div class="form-group">
                    <label for="fatherContact">Contact Number</label>
                    <InputText id="fatherContact" v-model="student.father.contactNumber" />
                </div>

                <h3 class="subsection-title">Mother's Maiden Name</h3>
                <div class="form-group">
                    <label for="motherLastName">Last Name</label>
                    <InputText id="motherLastName" v-model="student.mother.lastName" />
                </div>

                <div class="form-group">
                    <label for="motherFirstName">First Name</label>
                    <InputText id="motherFirstName" v-model="student.mother.firstName" />
                </div>

                <div class="form-group">
                    <label for="motherMiddleName">Middle Name</label>
                    <InputText id="motherMiddleName" v-model="student.mother.middleName" />
                </div>

                <div class="form-group">
                    <label for="motherContact">Contact Number</label>
                    <InputText id="motherContact" v-model="student.mother.contactNumber" />
                </div>
            </div>

            <!-- Step 5: Review Information -->
            <div v-if="currentStep === 5" class="form-section">
                <h2 class="section-title">REVIEW INFORMATION</h2>

                <div class="review-section">
                    <h3>Enrollment Information</h3>
                    <div class="review-item">
                        <div class="review-label">School Year:</div>
                        <div class="review-value">{{ student.schoolYearStart }} - {{ student.schoolYearEnd }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Grade Level:</div>
                        <div class="review-value">{{ student.gradeLevel }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">With LRN:</div>
                        <div class="review-value">{{ student.hasLRN ? 'Yes' : 'No' }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Returning (Balik-Aral):</div>
                        <div class="review-value">{{ student.isReturning ? 'Yes' : 'No' }}</div>
                    </div>
                    <div class="review-item" v-if="student.hasLRN">
                        <div class="review-label">LRN:</div>
                        <div class="review-value">{{ student.lrn }}</div>
                    </div>
                </div>

                <div class="review-section">
                    <h3>Learner Information</h3>
                    <div class="review-item">
                        <div class="review-label">Full Name:</div>
                        <div class="review-value">{{ student.lastName }}, {{ student.firstName }} {{ student.middleName }} {{ student.extensionName }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">PSA Birth Certificate No.:</div>
                        <div class="review-value">{{ student.psaBirthCertNo || 'N/A' }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Birthdate:</div>
                        <div class="review-value">{{ formatDate(student.birthdate) }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Place of Birth:</div>
                        <div class="review-value">{{ student.birthplace }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Sex:</div>
                        <div class="review-value">{{ student.sex === 'male' ? 'Male' : 'Female' }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Age:</div>
                        <div class="review-value">{{ student.age }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Mother Tongue:</div>
                        <div class="review-value">{{ student.motherTongue || 'N/A' }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Indigenous People:</div>
                        <div class="review-value">{{ student.isIndigenous ? 'Yes - ' + student.indigenousCommunity : 'No' }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">4Ps Beneficiary:</div>
                        <div class="review-value">{{ student.is4PsBeneficiary ? 'Yes - ' + student.householdID : 'No' }}</div>
                    </div>
                </div>

                <div class="review-section" v-if="student.hasDisability">
                    <h3>Disability Information</h3>
                    <div class="review-item">
                        <div class="review-label">Disabilities:</div>
                        <div class="review-value">{{ getDisabilitiesText() }}</div>
                    </div>
                </div>

                <div class="review-section">
                    <h3>Address Information</h3>
                    <div class="review-item">
                        <div class="review-label">Current Address:</div>
                        <div class="review-value">{{ formatAddress(student.currentAddress) }}</div>
                    </div>
                    <div class="review-item" v-if="!student.sameAddress">
                        <div class="review-label">Permanent Address:</div>
                        <div class="review-value">{{ formatAddress(student.permanentAddress) }}</div>
                    </div>
                </div>

                <div class="review-section">
                    <h3>Parent/Guardian Information</h3>
                    <div class="review-item">
                        <div class="review-label">Father's Name:</div>
                        <div class="review-value">
                            {{ student.father.lastName ? student.father.lastName + ', ' + student.father.firstName + ' ' + student.father.middleName : 'N/A' }}
                        </div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Father's Contact:</div>
                        <div class="review-value">{{ student.father.contactNumber || 'N/A' }}</div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Mother's Name:</div>
                        <div class="review-value">
                            {{ student.mother.lastName ? student.mother.lastName + ', ' + student.mother.firstName + ' ' + student.mother.middleName : 'N/A' }}
                        </div>
                    </div>
                    <div class="review-item">
                        <div class="review-label">Mother's Contact:</div>
                        <div class="review-value">{{ student.mother.contactNumber || 'N/A' }}</div>
                    </div>
                </div>

                <div class="terms-section">
                    <Checkbox id="termsAccepted" v-model="student.termsAccepted" :binary="true" />
                    <label for="termsAccepted">
                        I hereby certify that the above information given are true and correct to the best of my knowledge and I allow the school to use my child's details to create and/or update his/her learner profile in the Learner Information
                        System.
                    </label>
                </div>
                <small v-if="submitted && !student.termsAccepted" class="p-error">You must accept the terms to continue</small>
            </div>

            <div class="form-footer">
                <Button v-if="currentStep > 1" label="Previous" icon="pi pi-arrow-left" class="p-button-outlined" @click="prevStep" />
                <Button v-if="currentStep < 5" label="Next" icon="pi pi-arrow-right" iconPos="right" @click="nextStep" />
                <Button v-if="currentStep === 5" label="Submit" icon="pi pi-check" class="p-button-success" @click="submitForm" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.registration-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 2rem;
    position: relative;
    overflow: hidden;
}

/* Gradient background */
.gradient-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    overflow: hidden;
}

.animated-gradient {
    position: absolute;
    top: -50px;
    left: -50px;
    right: -50px;
    bottom: -50px;
    background: linear-gradient(-45deg, rgba(238, 119, 82, 0.7), /* #ee7752 */ rgba(231, 60, 126, 0.7), /* #e73c7e */ rgba(35, 166, 213, 0.7), /* #23a6d5 */ rgba(35, 213, 171, 0.7) /* #23d5ab */);
    background-size: 400% 400%;
    animation: gradient-animation 15s ease infinite;
    filter: blur(20px);
}

@keyframes gradient-animation {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Floating elements */
.floating-element {
    position: absolute;
    font-weight: bold;
    opacity: 1;
    font-family: 'Arial Rounded MT Bold', 'Helvetica Rounded', Arial, sans-serif;
    pointer-events: none; /* Ensure they don't interfere with clicks */
}

/* Different animations for different directions */
.bounce-horizontal {
    animation: bounce-horizontal 25s linear infinite;
}

.bounce-diagonal-1 {
    animation: bounce-diagonal-1 25s linear infinite;
}

.bounce-diagonal-2 {
    animation: bounce-diagonal-2 25s linear infinite;
}

/* Animation delays */
.delay-0 {
    animation-delay: 0s;
}
.delay-1 {
    animation-delay: 1s;
}
.delay-2 {
    animation-delay: 2s;
}
.delay-3 {
    animation-delay: 3s;
}
.delay-4 {
    animation-delay: 4s;
}

/* Horizontal bouncing (left to right) */
@keyframes bounce-horizontal {
    0%,
    100% {
        transform: translateX(0) rotate(0deg);
        animation-timing-function: ease-in-out;
    }
    25% {
        transform: translateX(calc(70vw - 100%)) rotate(5deg);
        animation-timing-function: ease-in-out;
    }
    50% {
        transform: translateX(0) rotate(0deg);
        animation-timing-function: ease-in-out;
    }
    75% {
        transform: translateX(calc(-70vw + 100%)) rotate(-5deg);
        animation-timing-function: ease-in-out;
    }
}

/* Diagonal bouncing (top-left to bottom-right) */
@keyframes bounce-diagonal-1 {
    0%,
    100% {
        transform: translate(0, 0) rotate(0deg);
        animation-timing-function: ease-in-out;
    }
    25% {
        transform: translate(calc(70vw - 100%), calc(40vh - 100%)) rotate(5deg);
        animation-timing-function: ease-in-out;
    }
    50% {
        transform: translate(0, 0) rotate(0deg);
        animation-timing-function: ease-in-out;
    }
    75% {
        transform: translate(calc(-70vw + 100%), calc(-40vh + 100%)) rotate(-5deg);
        animation-timing-function: ease-in-out;
    }
}

/* Diagonal bouncing (top-right to bottom-left) */
@keyframes bounce-diagonal-2 {
    0%,
    100% {
        transform: translate(0, 0) rotate(0deg);
        animation-timing-function: ease-in-out;
    }
    25% {
        transform: translate(calc(-70vw + 100%), calc(40vh - 100%)) rotate(-5deg);
        animation-timing-function: ease-in-out;
    }
    50% {
        transform: translate(0, 0) rotate(0deg);
        animation-timing-function: ease-in-out;
    }
    75% {
        transform: translate(calc(70vw - 100%), calc(-40vh + 100%)) rotate(5deg);
        animation-timing-function: ease-in-out;
    }
}

/* Add blur effect based on z-index for depth perception */
.floating-element[style*='z-index: -1'] {
    text-shadow:
        0 0 20px rgba(255, 255, 255, 0.7),
        0 0 40px rgba(255, 255, 255, 0.5),
        0 0 60px rgba(255, 255, 255, 0.3);
    filter: blur(0px);
}

.floating-element[style*='z-index: -2'] {
    text-shadow:
        0 0 15px rgba(255, 255, 255, 0.6),
        0 0 30px rgba(255, 255, 255, 0.4);
    filter: blur(0.5px);
}

.floating-element[style*='z-index: -3'] {
    text-shadow:
        0 0 10px rgba(255, 255, 255, 0.5),
        0 0 20px rgba(255, 255, 255, 0.3);
    filter: blur(1px);
}

.registration-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    width: 100%;
    max-width: 900px;
    position: relative;
    z-index: 1;
    border: none;
    overflow: hidden;
}

.form-header {
    display: flex;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(90deg, #1e3a8a, #3b82f6);
    color: white;
    width: 100%;
    margin: 0;
    border-radius: 0;
}

.header-logo {
    margin-right: 1.5rem;
}

.header-logo img {
    height: 60px;
    width: auto;
}

.header-text h1 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: white;
    text-transform: uppercase;
}

.form-subtitle {
    margin: 0.25rem 0 0;
    font-size: 0.875rem;
    opacity: 0.9;
    text-transform: uppercase;
}

.form-instructions {
    background-color: #f8fafc;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e2e8f0;
}

.form-instructions p {
    margin: 0;
    font-size: 0.9rem;
    color: #475569;
    line-height: 1.5;
}

.form-progress {
    display: flex;
    justify-content: space-between;
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

.progress-step .step-number {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: #e2e8f0;
    color: #64748b;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: 600;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.progress-step .step-label {
    font-size: 0.875rem;
    color: #64748b;
    transition: all 0.3s ease;
}

.progress-step.active .step-number {
    background-color: #3b82f6;
    color: white;
}

.progress-step.active .step-label {
    color: #3b82f6;
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

.form-section {
    padding: 2rem;
}

.section-title {
    margin: 0 0 1.5rem;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    text-align: center;
    position: relative;
    padding-bottom: 0.75rem;
}

.section-title:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 3px;
    background: linear-gradient(90deg, #3b82f6, #1e40af);
    border-radius: 3px;
}

.form-group {
    margin-bottom: 1.5rem;
}

label {
    display: block;
    margin-bottom: 0.75rem;
    font-size: 1rem;
    font-weight: 500;
    color: #334155;
}

.checkbox-options {
    display: flex;
    gap: 2rem;
}

.checkbox-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox-option label {
    margin-bottom: 0;
    font-weight: normal;
}

.school-year-input {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.year-separator {
    font-weight: bold;
    color: #64748b;
}

.ip-specify,
.household-id {
    margin-top: 0.75rem;
}

.disability-section {
    margin-top: 1rem;
    padding: 1.5rem;
    background-color: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.disability-options {
    margin-top: 1rem;
}

.disability-row {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1rem;
}

.disability-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.parent-info {
    margin-bottom: 2rem;
}

.parent-info h3 {
    margin: 0 0 1rem;
    font-size: 1.2rem;
    font-weight: 600;
    color: #334155;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.review-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background-color: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.review-section h3 {
    margin: 0 0 1.25rem;
    font-size: 1.2rem;
    color: #334155;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.review-item {
    margin-bottom: 1.25rem;
}

.review-label {
    font-weight: 600;
    color: #64748b;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.review-value {
    color: #1e293b;
    font-weight: 500;
    font-size: 1.1rem;
    padding-left: 1rem;
}

.form-footer {
    padding: 1.5rem 2rem;
    background-color: #f8fafc;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    border-top: 1px solid #e2e8f0;
}

.terms-section {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin: 2rem 0 1rem;
    padding: 1.5rem;
    background-color: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.terms-section label {
    font-size: 1rem;
    line-height: 1.5;
}

:deep(.p-inputtext),
:deep(.p-calendar),
:deep(.p-inputnumber) {
    width: 100%;
    font-size: 1.1rem;
    padding: 0.75rem 1rem;
}

:deep(.p-calendar .p-inputtext) {
    width: 100%;
}

:deep(.p-checkbox),
:deep(.p-radiobutton) {
    width: 22px;
    height: 22px;
}

:deep(.p-button) {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
}

.p-error {
    color: #ef4444;
    font-size: 0.875rem;
    margin-top: 0.5rem;
    display: block;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-progress {
        overflow-x: auto;
        padding: 1rem;
    }

    .progress-step {
        min-width: 80px;
    }

    .form-footer {
        flex-direction: column;
        gap: 1rem;
    }

    .form-footer button {
        width: 100%;
    }

    .checkbox-options {
        flex-direction: column;
        gap: 0.75rem;
    }
}
</style>
